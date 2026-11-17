<?php

namespace App\Filament\Portal\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Crm\Resources\Payments\PaymentResource;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Models\Document;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Payments (quote section 2): earnings, payment status and history. Cancelled payments are not shown.
 */
class MyPayments extends Page implements HasTable
{
    use InteractsWithTable;
    use ResolvesFreelancer;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'payments';

    public static function getNavigationLabel(): string
    {
        return __('Payments');
    }

    public function getTitle(): string
    {
        return __('Payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Payment::query()
                ->where('freelancer_profile_id', self::freelancer()->id)
                ->where('status', '!=', PaymentStatus::Cancelled)
                ->withCount('items')
                ->with(['documents' => fn ($q) => $q->where('visible_to_freelancer', true)]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('Reference'))->weight('medium'),
                TextColumn::make('period_start')->label(__('Period'))->date()
                    ->description(fn (Payment $record) => $record->period_end ? __('to :date', ['date' => $record->period_end->translatedFormat('j M Y')]) : null),
                TextColumn::make('items_count')->label(__('Shifts'))
                    ->formatStateUsing(fn (int $state) => trans_choice('{1} :count shift|[2,*] :count shifts', $state, ['count' => $state])),
                TextColumn::make('amount')->label(__('Amount'))->money(fn (Payment $record) => $record->currency)->weight('medium'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('method')->label(__('Method'))->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => PaymentResource::methods()[$state] ?? $state),
                TextColumn::make('paid_at')->label(__('Paid on'))->placeholder('—')
                    ->formatStateUsing(fn (Payment $record) => __('Paid on :date', ['date' => $record->paid_at->timezone(FilamentTimezone::get())->translatedFormat('j M Y')])),
            ])
            ->recordActions([
                Action::make('slip')->label(__('Payment slip'))->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Payment $record) => $record->documents->isNotEmpty())
                    ->url(fn (Payment $record) => ($doc = $record->documents->first()) instanceof Document ? route('documents.download', $doc) : null)
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading(__('No payments yet'))
            ->emptyStateDescription(__('Payments appear here once your hours are approved and the agency prepares them.'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }
}
