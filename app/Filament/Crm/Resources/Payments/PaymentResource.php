<?php

namespace App\Filament\Crm\Resources\Payments;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\MarkPaymentPaid;
use App\Enums\PaymentStatus;
use App\Filament\Crm\RelationManagers\DocumentsRelationManager;
use App\Filament\Crm\Resources\BaseResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\FreelancerProfile;
use App\Models\Payment;
use App\Models\Timesheet;
use App\Support\Duration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Payment Management (quote section 1): earnings from approved hours and payment status tracking.
 * The platform records payments made outside it; it does not move money.
 */
class PaymentResource extends BaseResource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Finance');
    }

    public static function getModelLabel(): string
    {
        return __('Payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payments');
    }

    public static function canCreate(): bool
    {
        return false; // Payments are built from approved timesheets with the "Create payment" button.
    }

    /** @return array<string, string> */
    public static function methods(): array
    {
        return [
            'mobile_money' => __('Mobile money'),
            'bank_transfer' => __('Bank transfer'),
            'cash' => __('Cash'),
            'cheque' => __('Cheque'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('freelancerProfile');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('Reference'))->searchable()->weight('medium'),
                TextColumn::make('freelancerProfile.first_name')->label(__('Freelancer'))
                    ->formatStateUsing(fn (Payment $record) => $record->freelancerProfile->fullName())
                    ->searchable(['freelancer_profiles.first_name', 'freelancer_profiles.last_name']),
                TextColumn::make('period_start')->label(__('Period'))->date()
                    ->description(fn (Payment $record) => $record->period_end ? __('to :date', ['date' => $record->period_end->translatedFormat('j M Y')]) : null),
                TextColumn::make('amount')->label(__('Amount'))->money(fn (Payment $record) => $record->currency)->alignEnd(),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('method')->label(__('Method'))->formatStateUsing(fn (?string $state) => self::methods()[$state] ?? $state)->placeholder('—')->toggleable(),
                TextColumn::make('paid_at')->label(__('Paid on'))->date()->placeholder('—')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label(__('Status'))->options(PaymentStatus::class)])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading(__('No payments yet'))
            ->emptyStateDescription(__('Create a payment once timesheets are approved.'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (Payment $record) => $record->reference)->columns(3)->columnSpanFull()->schema([
                TextEntry::make('freelancer')->label(__('Freelancer'))->state(fn (Payment $record) => $record->freelancerProfile->fullName()),
                TextEntry::make('amount')->label(__('Amount'))->money(fn (Payment $record) => $record->currency)->weight('bold'),
                TextEntry::make('status')->label(__('Status'))->badge(),
                TextEntry::make('period_start')->label(__('Period from'))->date()->placeholder('—'),
                TextEntry::make('period_end')->label(__('Period to'))->date()->placeholder('—'),
                TextEntry::make('paid_at')->label(__('Paid on'))->dateTime()->placeholder('—'),
                TextEntry::make('method')->label(__('Method'))->formatStateUsing(fn (?string $state) => self::methods()[$state] ?? $state)->placeholder('—'),
                TextEntry::make('transaction_ref')->label(__('Transaction reference'))->placeholder('—')->copyable(),
                TextEntry::make('notes')->label(__('Notes'))->placeholder('—'),
            ]),
        ]);
    }

    public static function listHeaderActions(): array
    {
        return [
            Action::make('createPayment')
                ->label(__('Create payment'))->icon('heroicon-o-plus')
                ->visible(fn () => auth()->user()->can('create', Payment::class))
                ->modalDescription(__('Choose a freelancer, then the approved hours this payment covers.'))
                ->schema([
                    Select::make('freelancer_profile_id')->label(__('Freelancer'))->required()->searchable()->live()
                        ->options(fn () => FreelancerProfile::query()
                            ->whereIn('id', CreatePayment::payable()->select('freelancer_profile_id'))
                            ->orderBy('first_name')->get()
                            ->mapWithKeys(fn (FreelancerProfile $f) => [$f->id => $f->fullName()])->all())
                        ->helperText(__('Only freelancers with approved, unpaid hours are listed.'))
                        ->afterStateUpdated(fn (callable $set) => $set('timesheet_ids', [])),
                    CheckboxList::make('timesheet_ids')->label(__('Approved hours'))->required()
                        ->visible(fn (Get $get) => filled($get('freelancer_profile_id')))
                        ->options(fn (Get $get) => self::payableOptions($get('freelancer_profile_id')))
                        ->bulkToggleable(),
                ])
                ->action(function (array $data): void {
                    $freelancer = FreelancerProfile::query()->findOrFail($data['freelancer_profile_id']);
                    $payment = null;

                    $ok = RunsDomainActions::run(function () use ($freelancer, $data, &$payment): void {
                        $payment = app(CreatePayment::class)->handle($freelancer, auth()->user(), array_values($data['timesheet_ids']));
                    }, __('Payment created'));

                    if ($ok && $payment !== null) {
                        redirect(self::getUrl('view', ['record' => $payment]));
                    }
                }),
        ];
    }

    /** @return array<string, string> */
    private static function payableOptions(?string $freelancerId): array
    {
        if (blank($freelancerId)) {
            return [];
        }

        return CreatePayment::payable($freelancerId)
            ->with(['assignment.shift.staffRequest.client'])
            ->orderBy('started_at')
            ->get()
            ->mapWithKeys(fn (Timesheet $t) => [$t->id => sprintf(
                '%s · %s · %s · %s %s',
                $t->started_at->timezone(FilamentTimezone::get())->translatedFormat('j M Y'),
                $t->assignment->shift->staffRequest->client->company_name,
                Duration::format($t->payableMinutes()),
                $t->assignment->pay_rate === null ? __('no rate') : CreatePayment::earnings($t->payableMinutes(), (string) $t->assignment->pay_rate),
                $t->assignment->pay_rate === null ? '' : $t->assignment->currency,
            )])
            ->all();
    }

    public static function recordHeaderActions(): array
    {
        $pending = fn (Payment $record) => $record->status === PaymentStatus::Pending && auth()->user()->can('update', $record);

        return [
            Action::make('markPaid')
                ->label(__('Mark as paid'))->icon('heroicon-o-check-circle')->color('success')
                ->visible($pending)
                ->schema([
                    Select::make('method')->label(__('Method'))->options(self::methods())->required(),
                    TextInput::make('transaction_ref')->label(__('Transaction reference'))->maxLength(190),
                    DateTimePicker::make('paid_at')->label(__('Paid on'))->seconds(false)->default(now())->required(),
                ])
                ->action(fn (Payment $record, array $data) => RunsDomainActions::run(
                    fn () => app(MarkPaymentPaid::class)->handle(
                        $record,
                        $data['method'],
                        $data['transaction_ref'] ?? null,
                        Carbon::parse($data['paid_at']),
                    ),
                    __('Payment marked as paid'),
                )),
            Action::make('cancel')
                ->label(__('Cancel payment'))->color('danger')->requiresConfirmation()
                ->modalDescription(__('The hours on this payment become payable again.'))
                ->visible($pending)
                ->action(fn (Payment $record) => RunsDomainActions::run(
                    fn () => app(MarkPaymentPaid::class)->cancel($record),
                    __('Payment cancelled'),
                )),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ItemsRelationManager::class, DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
