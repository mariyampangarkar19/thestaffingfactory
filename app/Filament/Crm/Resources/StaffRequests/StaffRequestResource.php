<?php

namespace App\Filament\Crm\Resources\StaffRequests;

use App\Actions\Requests\ChangeRequestStatus;
use App\Enums\RequestChannel;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Filament\Crm\RelationManagers\DocumentsRelationManager;
use App\Filament\Crm\RelationManagers\NotesRelationManager;
use App\Filament\Crm\Resources\BaseResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\ClientContact;
use App\Models\JobRole;
use App\Models\StaffRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Staffing Requests (quote section 1): received from the website or entered by an operator,
 * assigned to an operator, tracked from New to Completed or Cancelled.
 */
class StaffRequestResource extends BaseResource
{
    protected static ?string $model = StaffRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Operations');
    }

    public static function getModelLabel(): string
    {
        return __('Staffing request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Staffing requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = StaffRequest::query()->where('status', RequestStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'operator']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Request'))->columns(2)->schema([
                Select::make('client_id')->label(__('Customer'))->relationship('client', 'company_name')
                    ->searchable()->preload()->required()->live(),
                Select::make('client_contact_id')->label(__('Contact'))
                    ->options(fn (Get $get) => ClientContact::query()->where('client_id', $get('client_id'))->pluck('name', 'id'))
                    ->visible(fn (Get $get) => filled($get('client_id'))),
                TextInput::make('title')->label(__('Title'))->required()->maxLength(150)
                    ->placeholder(__('e.g. Gala dinner, 6 December')),
                Select::make('channel')->label(__('Received by'))->options(RequestChannel::class)
                    ->default(RequestChannel::Phone)->required()->disabledOn('edit'),
                DatePicker::make('starts_on')->label(__('Start date')),
                DatePicker::make('ends_on')->label(__('End date'))->afterOrEqual('starts_on'),
                TextInput::make('location')->label(__('Location'))->maxLength(255)->columnSpanFull(),
                Textarea::make('details')->label(__('What the customer asked for'))->rows(4)->maxLength(5000)->columnSpanFull(),
                Select::make('assigned_to')->label(__('Operator in charge'))
                    ->options(fn () => User::role(Role::staffValues())->orderBy('name')->pluck('name', 'id'))->searchable(),
            ]),
            Section::make(__('Positions needed'))->visibleOn('create')->schema([
                Repeater::make('positions')->hiddenLabel()->columns(3)->defaultItems(1)->minItems(1)
                    ->addActionLabel(__('Add position'))
                    ->schema([
                        Select::make('job_role_id')->label(__('Position'))->required()
                            ->options(fn () => JobRole::query()->where('is_active', true)->get()->mapWithKeys(fn (JobRole $r) => [$r->id => $r->name()])),
                        TextInput::make('quantity')->label(__('How many'))->numeric()->integer()->minValue(1)->maxValue(500)->default(1)->required(),
                        TextInput::make('pay_rate')->label(__('Pay per hour'))->numeric()->minValue(0)->maxValue(9999999)
                            ->helperText(__('Leave empty to use the standard rate.')),
                    ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (StaffRequest $record) => $record->reference.' · '.$record->title)->columns(3)->schema([
                TextEntry::make('status')->label(__('Status'))->badge(),
                TextEntry::make('client.company_name')->label(__('Customer')),
                TextEntry::make('contact.name')->label(__('Contact'))->placeholder('—'),
                TextEntry::make('channel')->label(__('Received by'))->badge(),
                TextEntry::make('starts_on')->label(__('Start date'))->date()->placeholder('—'),
                TextEntry::make('ends_on')->label(__('End date'))->date()->placeholder('—'),
                TextEntry::make('location')->label(__('Location'))->placeholder('—'),
                TextEntry::make('operator.name')->label(__('Operator in charge'))->placeholder(__('Not assigned')),
                TextEntry::make('created_at')->label(__('Received'))->dateTime(),
                TextEntry::make('details')->label(__('What the customer asked for'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('cancel_reason')->label(__('Cancellation reason'))->columnSpanFull()
                    ->visible(fn (StaffRequest $record) => $record->status === RequestStatus::Cancelled),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('Reference'))->searchable()->sortable()->weight('medium'),
                TextColumn::make('client.company_name')->label(__('Customer'))->searchable(),
                TextColumn::make('title')->label(__('Title'))->searchable()->limit(40),
                TextColumn::make('starts_on')->label(__('Start date'))->date()->sortable()->placeholder('—'),
                TextColumn::make('operator.name')->label(__('Operator'))->placeholder(__('Not assigned')),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('channel')->label(__('Received by'))->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(RequestStatus::class)->multiple(),
                SelectFilter::make('assigned_to')->label(__('Operator'))
                    ->options(fn () => User::role(Role::staffValues())->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading(__('No staffing requests yet'))
            ->emptyStateDescription(__('Requests from the website form appear here automatically.'));
    }

    public static function recordHeaderActions(): array
    {
        $move = fn (RequestStatus $to, string $label, string $color, string $icon) => Action::make('to_'.$to->value)
            ->label($label)->color($color)->icon($icon)
            ->visible(fn (StaffRequest $record) => $record->status->canMoveTo($to) && auth()->user()->can('update', $record))
            ->requiresConfirmation()
            ->action(fn (StaffRequest $record) => RunsDomainActions::run(
                fn () => app(ChangeRequestStatus::class)->handle($record, $to),
                __('Request updated'),
            ));

        return [
            $move(RequestStatus::InProgress, __('Start working on it'), 'gray', 'heroicon-o-play'),
            $move(RequestStatus::Confirmed, __('Mark confirmed'), 'primary', 'heroicon-o-check'),
            $move(RequestStatus::Completed, __('Mark completed'), 'success', 'heroicon-o-check-badge'),
            Action::make('cancel')->label(__('Cancel request'))->color('danger')->icon('heroicon-o-x-mark')
                ->visible(fn (StaffRequest $record) => $record->status->canMoveTo(RequestStatus::Cancelled) && auth()->user()->can('update', $record))
                ->schema([Textarea::make('reason')->label(__('Reason'))->required()->maxLength(255)])
                ->modalDescription(__('Freelancers booked on its upcoming shifts will be released.'))
                ->action(fn (StaffRequest $record, array $data) => RunsDomainActions::run(
                    fn () => app(ChangeRequestStatus::class)->handle($record, RequestStatus::Cancelled, $data['reason']),
                    __('Request cancelled'),
                )),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PositionsRelationManager::class,
            RelationManagers\ShiftsRelationManager::class,
            NotesRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffRequests::route('/'),
            'create' => Pages\CreateStaffRequest::route('/create'),
            'view' => Pages\ViewStaffRequest::route('/{record}'),
            'edit' => Pages\EditStaffRequest::route('/{record}/edit'),
        ];
    }
}
