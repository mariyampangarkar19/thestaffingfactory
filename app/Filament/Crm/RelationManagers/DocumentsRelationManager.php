<?php

namespace App\Filament\Crm\RelationManagers;

use App\Actions\Documents\StoreDocument;
use App\Audit\AuditLogger;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Filament\Support\RunsDomainActions;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Upload, review and download files attached to a record. Reused on customers, freelancers, requests and payments.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Documents');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('documents.view') ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(self::columns())
            ->headerActions([self::uploadAction(fn () => $this->getOwnerRecord())])
            ->recordActions(self::recordActions())
            ->emptyStateHeading(__('No documents yet'));
    }

    /** @return list<TextColumn|IconColumn> */
    public static function columns(): array
    {
        return [
            TextColumn::make('type')->label(__('Type'))->badge(),
            TextColumn::make('original_name')->label(__('File'))->description(fn (Document $record) => $record->title)->limit(40),
            TextColumn::make('size')->label(__('Size'))->formatStateUsing(fn (int $state) => Number::fileSize($state)),
            TextColumn::make('status')->label(__('Status'))->badge(),
            TextColumn::make('expires_on')->label(__('Expires'))->date()->placeholder('—')
                ->color(fn (Document $record) => $record->expires_on?->isPast() ? 'danger' : null),
            IconColumn::make('visible_to_freelancer')->label(__('Shared with freelancer'))->boolean(),
            TextColumn::make('created_at')->label(__('Added'))->since()->dateTimeTooltip(),
        ];
    }

    public static function uploadAction(\Closure $owner): Action
    {
        return Action::make('upload')
            ->label(__('Upload document'))
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => auth()->user()->can('documents.manage'))
            ->schema([
                Select::make('type')->label(__('Type'))->options(DocumentType::class)->required()->live(),
                TextInput::make('title')->label(__('Title'))->maxLength(150),
                FileUpload::make('file')->label(__('File'))->required()->storeFiles(false)
                    ->maxSize(30 * 1024)
                    ->helperText(__('PDF or image up to 10 MB. Videos up to 30 MB.')),
                DatePicker::make('expires_on')->label(__('Expiry date'))->minDate(today()),
                Toggle::make('visible_to_freelancer')->label(__('Shared with freelancer'))
                    ->helperText(__('The freelancer can see and download this file in their portal.')),
            ])
            ->action(function (array $data) use ($owner): void {
                RunsDomainActions::run(fn () => app(StoreDocument::class)->handle(
                    $owner(),
                    $data['file'],
                    $data['type'] instanceof DocumentType ? $data['type'] : DocumentType::from($data['type']),
                    $data['title'] ?? null,
                    (bool) ($data['visible_to_freelancer'] ?? false),
                    auth()->id(),
                    $data['expires_on'] ?? null,
                ), __('Document uploaded'));
            });
    }

    /** @return list<Action> */
    public static function recordActions(): array
    {
        return [
            Action::make('download')->label(__('Download'))->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Document $record) => route('documents.download', $record))->openUrlInNewTab(),
            Action::make('approve')->label(__('Approve'))->icon('heroicon-o-check')->color('success')
                ->visible(fn (Document $record) => $record->status === DocumentStatus::Pending && auth()->user()->can('review', $record))
                ->action(fn (Document $record) => self::review($record, DocumentStatus::Approved)),
            Action::make('reject')->label(__('Reject'))->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                ->visible(fn (Document $record) => $record->status === DocumentStatus::Pending && auth()->user()->can('review', $record))
                ->action(fn (Document $record) => self::review($record, DocumentStatus::Rejected)),
            DeleteAction::make(),
        ];
    }

    private static function review(Document $document, DocumentStatus $status): void
    {
        $document->forceFill(['status' => $status, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
        app(AuditLogger::class)->log('document.'.$status->value, $document);
    }
}
