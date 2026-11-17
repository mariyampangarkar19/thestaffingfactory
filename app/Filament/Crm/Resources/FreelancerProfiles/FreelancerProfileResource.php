<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles;

use App\Actions\Freelancers\ApproveFreelancer;
use App\Actions\Freelancers\ChangeFreelancerStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\FreelancerStatus;
use App\Filament\Crm\RelationManagers\DocumentsRelationManager;
use App\Filament\Crm\RelationManagers\NotesRelationManager;
use App\Filament\Crm\Resources\BaseResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\FreelancerProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Freelancer Management (quote section 1): registrations, review and approval, profile, documents, history.
 */
class FreelancerProfileResource extends BaseResource
{
    protected static ?string $model = FreelancerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('People');
    }

    public static function getModelLabel(): string
    {
        return __('Freelancer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Freelancers');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof FreelancerProfile ? $record->fullName() : null;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = FreelancerProfile::query()->where('status', FreelancerStatus::New)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('New registrations');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['jobRoles', 'latestQuizResult']);
    }

    /** Languages offered on the profile. Kept here so the CRM form and the portal profile stay in step. */
    public static function languageOptions(): array
    {
        return ['fr' => 'Français', 'en' => 'English'];
    }

    public static function quizLabel(FreelancerProfile $record): ?string
    {
        $quiz = $record->latestQuizResult;

        if ($quiz === null) {
            return null;
        }

        return $quiz->passed
            ? __('PASS :grade · :score', ['grade' => $quiz->grade, 'score' => $quiz->score])
            : __('FAIL · :score', ['score' => $quiz->score]);
    }

    public static function form(Schema $schema): Schema
    {
        // tenant_id and status are never form fields: the agency comes from the address, status changes go through actions.
        return $schema->components([
            Section::make(__('Identity'))->columns(2)->schema([
                TextInput::make('first_name')->label(__('First name'))->required()->maxLength(100),
                TextInput::make('last_name')->label(__('Last name'))->required()->maxLength(100),
                DatePicker::make('date_of_birth')->label(__('Date of birth'))->maxDate(now()->subYears(16)),
                Select::make('gender')->label(__('Gender'))->options(['female' => __('Female'), 'male' => __('Male')]),
            ]),
            Section::make(__('Contact'))->columns(2)->schema([
                TextInput::make('email')->label(__('Email'))->email()->maxLength(190)
                    ->helperText(__('Needed to give the freelancer a portal login.')),
                TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(32),
                TextInput::make('whatsapp')->label(__('WhatsApp'))->tel()->maxLength(32),
                TextInput::make('city')->label(__('City'))->maxLength(100),
                TextInput::make('address')->label(__('Address'))->maxLength(255)->columnSpanFull(),
            ]),
            Section::make(__('Skills and availability'))->columns(2)->schema([
                Select::make('jobRoles')->label(__('Positions'))->multiple()->preload()
                    ->relationship('jobRoles', app()->getLocale() === 'fr' ? 'name_fr' : 'name_en'),
                Select::make('languages')->label(__('Languages'))->multiple()->options(self::languageOptions()),
                TextInput::make('years_experience')->label(__('Years of experience'))->numeric()->integer()->minValue(0)->maxValue(60),
                Select::make('availability_status')->label(__('Availability'))->options(AvailabilityStatus::class)
                    ->default(AvailabilityStatus::Available)->required(),
                Textarea::make('experience_summary')->label(__('Experience'))->maxLength(2000)->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Profile'))->columns(3)->schema([
                TextEntry::make('status')->label(__('Status'))->badge(),
                TextEntry::make('availability_status')->label(__('Availability'))->badge(),
                TextEntry::make('quiz')->label(__('Quiz'))->badge()
                    ->state(fn (FreelancerProfile $record) => self::quizLabel($record))
                    ->color(fn (FreelancerProfile $record) => $record->latestQuizResult?->passed ? 'success' : 'danger')
                    ->placeholder(__('Not taken')),
                TextEntry::make('jobRoles')->label(__('Positions'))->badge()->color('gray')
                    ->state(fn (FreelancerProfile $record) => $record->jobRoles->map->name()->all())->placeholder('—'),
                TextEntry::make('languages')->label(__('Languages'))
                    ->formatStateUsing(fn (string $state) => self::languageOptions()[$state] ?? $state)->placeholder('—'),
                TextEntry::make('years_experience')->label(__('Years of experience'))->placeholder('—'),
                TextEntry::make('experience_summary')->label(__('Experience'))->placeholder('—')->columnSpanFull(),
            ]),
            Section::make(__('Contact'))->columns(3)->schema([
                TextEntry::make('email')->label(__('Email'))->placeholder('—')->copyable(),
                TextEntry::make('phone')->label(__('Phone'))->placeholder('—')->copyable(),
                TextEntry::make('whatsapp')->label(__('WhatsApp'))->placeholder('—')->copyable(),
                TextEntry::make('city')->label(__('City'))->placeholder('—'),
                TextEntry::make('address')->label(__('Address'))->placeholder('—'),
                TextEntry::make('date_of_birth')->label(__('Date of birth'))->date()->placeholder('—'),
            ]),
            Section::make(__('Registration'))->columns(3)->collapsed()->schema([
                TextEntry::make('created_at')->label(__('Signed up'))->dateTime(),
                TextEntry::make('source')->label(__('Source'))->placeholder('—'),
                TextEntry::make('consented_at')->label(__('Consent given'))->dateTime()->placeholder('—'),
                TextEntry::make('approved_at')->label(__('Approved'))->dateTime()->placeholder('—'),
                TextEntry::make('user.email')->label(__('Portal login'))->placeholder(__('No login yet')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('first_name')->label(__('Name'))->weight('medium')
                    ->formatStateUsing(fn (FreelancerProfile $record) => $record->fullName())
                    ->description(fn (FreelancerProfile $record) => $record->phone)
                    ->searchable(['first_name', 'last_name', 'phone', 'email'])->sortable(),
                TextColumn::make('jobRoles')->label(__('Positions'))->badge()->color('gray')
                    ->state(fn (FreelancerProfile $record) => $record->jobRoles->map->name()->all()),
                TextColumn::make('city')->label(__('City'))->searchable()->sortable()->placeholder('—'),
                TextColumn::make('quiz')->label(__('Quiz'))->badge()
                    ->state(fn (FreelancerProfile $record) => self::quizLabel($record))
                    ->color(fn (FreelancerProfile $record) => $record->latestQuizResult?->passed ? 'success' : 'danger')
                    ->placeholder(__('Not taken')),
                TextColumn::make('availability_status')->label(__('Availability'))->badge()->toggleable(),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('source')->label(__('Source'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('Signed up'))->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(FreelancerStatus::class),
                SelectFilter::make('availability_status')->label(__('Availability'))->options(AvailabilityStatus::class),
                SelectFilter::make('jobRoles')->label(__('Position'))->preload()
                    ->relationship('jobRoles', app()->getLocale() === 'fr' ? 'name_fr' : 'name_en'),
                SelectFilter::make('city')->label(__('City'))->searchable()
                    ->options(fn () => FreelancerProfile::query()->whereNotNull('city')->distinct()->orderBy('city')->limit(200)->pluck('city', 'city')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading(__('No freelancers yet'))
            ->emptyStateDescription(__('New website registrations appear here.'));
        // No bulk delete: removing personal data is a deliberate, logged action on one record.
    }

    public static function recordHeaderActions(): array
    {
        $may = fn (FreelancerProfile $record) => auth()->user()->can('update', $record);

        $status = fn (string $name, string $label, FreelancerStatus $to, string $color) => Action::make($name)
            ->label($label)->color($color)->requiresConfirmation()
            ->visible(fn (FreelancerProfile $record) => $may($record) && $record->status !== $to)
            ->action(fn (FreelancerProfile $record) => RunsDomainActions::run(
                fn () => app(ChangeFreelancerStatus::class)->handle($record, $to),
                __('Status updated'),
            ));

        return [
            Action::make('approve')
                ->label(__('Approve'))->icon('heroicon-o-check-badge')->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('The freelancer gets a portal login and an email to choose a password.'))
                ->visible(fn (FreelancerProfile $record) => $may($record) && $record->status !== FreelancerStatus::Approved)
                ->action(fn (FreelancerProfile $record) => RunsDomainActions::run(
                    fn () => app(ApproveFreelancer::class)->handle($record, auth()->user()),
                    __('Freelancer approved'),
                )),
            ActionGroup::make([
                $status('screening', __('Move to screening'), FreelancerStatus::Screening, 'gray'),
                $status('reject', __('Reject'), FreelancerStatus::Rejected, 'danger'),
                $status('deactivate', __('Mark as inactive'), FreelancerStatus::Inactive, 'gray'),
            ])->label(__('Change status'))->icon('heroicon-o-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuizResultsRelationManager::class,
            DocumentsRelationManager::class,
            RelationManagers\AssignmentHistoryRelationManager::class,
            RelationManagers\UnavailabilitiesRelationManager::class,
            RelationManagers\EvaluationsRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFreelancerProfiles::route('/'),
            'create' => Pages\CreateFreelancerProfile::route('/create'),
            'view' => Pages\ViewFreelancerProfile::route('/{record}'),
            'edit' => Pages\EditFreelancerProfile::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
