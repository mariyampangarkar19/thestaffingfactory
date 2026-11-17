<?php

namespace App\Filament\Crm\Resources\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Filament\Crm\RelationManagers\DocumentsRelationManager;
use App\Filament\Crm\Resources\BaseResource;
use App\Models\Client;
use App\Models\Document;
use App\Models\FreelancerProfile;
use App\Models\Payment;
use App\Models\StaffRequest;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Contracts & Documents (quote section 1): every stored file in one list, with review and expiry tracking.
 * Files are uploaded from the record they belong to (freelancer, customer, request or payment).
 */
class DocumentResource extends BaseResource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Records');
    }

    public static function getModelLabel(): string
    {
        return __('Document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Documents');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('documentable');

        // Payment slips are finance records: hidden from people without finance access.
        return auth()->user()?->can('finance.view')
            ? $query
            : $query->where('documentable_type', '!=', (new Payment)->getMorphClass());
    }

    public static function ownerLabel(Document $document): string
    {
        $owner = $document->documentable;

        return match (true) {
            $owner instanceof FreelancerProfile => $owner->fullName(),
            $owner instanceof Client => $owner->company_name,
            $owner instanceof StaffRequest, $owner instanceof Payment => $owner->reference,
            default => '—',
        };
    }

    public static function ownerKind(Document $document): string
    {
        return match (true) {
            $document->documentable instanceof FreelancerProfile => __('Freelancer'),
            $document->documentable instanceof Client => __('Customer'),
            $document->documentable instanceof StaffRequest => __('Staffing request'),
            $document->documentable instanceof Payment => __('Payment'),
            default => '',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('owner')->label(__('Belongs to'))->weight('medium')
                    ->state(fn (Document $record) => self::ownerLabel($record))
                    ->description(fn (Document $record) => self::ownerKind($record)),
                ...DocumentsRelationManager::columns(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('Type'))->options(DocumentType::class),
                SelectFilter::make('status')->label(__('Status'))->options(DocumentStatus::class),
                Filter::make('expiring')->label(__('Expires within 30 days'))
                    ->query(fn (Builder $query) => $query->whereNotNull('expires_on')->whereDate('expires_on', '<=', today()->addDays(30))),
            ])
            ->recordActions(DocumentsRelationManager::recordActions())
            ->emptyStateHeading(__('No documents yet'))
            ->emptyStateDescription(__('Upload files from a freelancer, customer, request or payment.'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocuments::route('/')];
    }
}
