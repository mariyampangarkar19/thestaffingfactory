<?php

namespace App\Filament\Crm\Resources\Announcements;

use App\Filament\Crm\Resources\BaseResource;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Agency announcements shown on the freelancer portal home page (quote section 2, "Notifications").
 */
class AnnouncementResource extends BaseResource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('People');
    }

    public static function getModelLabel(): string
    {
        return __('Announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Announcements for freelancers');
    }

    public static function getNavigationLabel(): string
    {
        return __('Announcements');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof Announcement ? ($record->title_fr ?: $record->title_en) : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Message'))
                ->description(__('Fill in at least one language. Freelancers see their own language when it is available.'))
                ->columns(2)
                ->schema([
                    TextInput::make('title_fr')->label(__('Title (French)'))->maxLength(150)->requiredWithout('title_en'),
                    TextInput::make('title_en')->label(__('Title (English)'))->maxLength(150)->requiredWithout('title_fr'),
                    Textarea::make('body_fr')->label(__('Message (French)'))->maxLength(2000)->rows(5)->requiredWithout('body_en'),
                    Textarea::make('body_en')->label(__('Message (English)'))->maxLength(2000)->rows(5)->requiredWithout('body_fr'),
                ]),
            Section::make(__('When'))->columns(2)->schema([
                DateTimePicker::make('published_at')->label(__('Publish on'))->seconds(false)->default(now())
                    ->helperText(__('Leave empty to keep it as a draft.')),
                DateTimePicker::make('expires_at')->label(__('Hide after'))->seconds(false)->after('published_at')
                    ->helperText(__('Leave empty to show it until you remove it.')),
            ]),
        ]);
    }

    public static function state(Announcement $record): string
    {
        return match (true) {
            $record->published_at === null => __('Draft'),
            $record->published_at->isFuture() => __('Scheduled'),
            $record->expires_at?->isPast() ?? false => __('Expired'),
            default => __('Published'),
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('Title'))->weight('medium')
                    ->state(fn (Announcement $record) => self::getRecordTitle($record))
                    ->description(fn (Announcement $record) => str((string) ($record->body_fr ?: $record->body_en))->limit(90)),
                TextColumn::make('state')->label(__('State'))->badge()
                    ->state(fn (Announcement $record) => self::state($record))
                    ->color(fn (Announcement $record) => $record->published_at !== null && $record->published_at->isPast() && ! ($record->expires_at?->isPast() ?? false) ? 'success' : 'gray'),
                TextColumn::make('published_at')->label(__('Publish on'))->dateTime()->placeholder('—'),
                TextColumn::make('expires_at')->label(__('Hide after'))->dateTime()->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No announcements yet'))
            ->emptyStateDescription(__('Announcements appear on the home page of the freelancer portal.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
