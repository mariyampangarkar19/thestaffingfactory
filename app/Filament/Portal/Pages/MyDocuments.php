<?php

namespace App\Filament\Portal\Pages;

use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentType;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Filament\Support\RunsDomainActions;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * My Profile documents (quote section 2): CV, photo, 30-second intro video, certifications.
 * A freelancer sees files they uploaded and files the agency chose to share, nothing else.
 */
class MyDocuments extends Page implements HasTable
{
    use InteractsWithTable;
    use ResolvesFreelancer;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'documents';

    public static function getNavigationLabel(): string
    {
        return __('My documents');
    }

    public function getTitle(): string
    {
        return __('My documents');
    }

    public function getSubheading(): ?string
    {
        return __('Upload your CV, a photo, a 30-second introduction video and your certificates.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')->label(__('Upload document'))->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    Select::make('type')->label(__('Type'))->required()->live()
                        ->options(collect(DocumentType::freelancerUploadable())->mapWithKeys(fn (DocumentType $t) => [$t->value => $t->getLabel()])->all()),
                    TextInput::make('title')->label(__('Title'))->maxLength(150),
                    FileUpload::make('file')->label(__('File'))->required()->storeFiles(false)->maxSize(30 * 1024)
                        ->helperText(__('PDF or image up to 10 MB. Videos up to 30 MB.')),
                    DatePicker::make('expires_on')->label(__('Expiry date'))->minDate(today())
                        ->visible(fn (Get $get) => in_array($get('type'), [DocumentType::Certificate->value, DocumentType::IdDocument->value], true)),
                ])
                ->action(function (array $data): void {
                    $type = DocumentType::from($data['type']);
                    abort_unless(in_array($type, DocumentType::freelancerUploadable(), true), 403);

                    RunsDomainActions::run(fn () => app(StoreDocument::class)->handle(
                        self::freelancer(), $data['file'], $type, $data['title'] ?? null, true, auth()->id(), $data['expires_on'] ?? null,
                    ), __('Document uploaded'));
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $profile = self::freelancer();

        return $table
            ->query(fn () => Document::query()
                ->where('documentable_type', $profile->getMorphClass())
                ->where('documentable_id', $profile->id)
                ->where(fn (Builder $q) => $q->where('uploaded_by', auth()->id())->orWhere('visible_to_freelancer', true)))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->label(__('Type'))->badge(),
                TextColumn::make('original_name')->label(__('File'))->description(fn (Document $record) => $record->title)->limit(40),
                TextColumn::make('size')->label(__('Size'))->formatStateUsing(fn (int $state) => Number::fileSize($state)),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('expires_on')->label(__('Expires'))->date()->placeholder('—'),
                TextColumn::make('created_at')->label(__('Added'))->date(),
            ])
            ->recordActions([
                Action::make('download')->label(__('Download'))->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $record) => route('documents.download', $record))->openUrlInNewTab(),
            ])
            ->emptyStateHeading(__('No documents yet'))
            ->emptyStateDescription(__('Agencies look at your CV and intro video first. Upload them to complete your profile.'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }
}
