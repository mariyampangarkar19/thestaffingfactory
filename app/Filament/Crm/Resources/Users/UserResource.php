<?php

namespace App\Filament\Crm\Resources\Users;

use App\Actions\Team\CreateStaffMember;
use App\Enums\Role;
use App\Filament\Crm\Resources\BaseResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Team & Operator Management (quote section 1): agency staff logins and their role. Freelancer logins are not listed here.
 */
class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'team';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public static function getModelLabel(): string
    {
        return __('Team member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Team');
    }

    public static function canCreate(): bool
    {
        return false; // Staff are invited by email with the "Invite team member" button.
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role(Role::staffValues())->with('roles');
    }

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return collect(Role::cases())->filter->isStaff()
            ->mapWithKeys(fn (Role $role) => [$role->value => self::roleLabel($role)])->all();
    }

    public static function roleLabel(Role $role): string
    {
        return match ($role) {
            Role::Owner => __('Owner'),
            Role::Manager => __('Manager'),
            Role::Recruiter => __('Recruiter'),
            Role::Sales => __('Sales'),
            Role::Finance => __('Finance'),
            Role::Viewer => __('Read only'),
            Role::Freelancer => __('Freelancer'),
        };
    }

    /** One line per role so an owner knows what they are granting. */
    public static function roleHelp(): string
    {
        return __('Owner: everything, including team and settings. Manager: daily operations and timesheet approval. Recruiter: freelancers and assignments. Sales: customers and requests. Finance: timesheets, payments and reports. Read only: can look, cannot change.');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('medium')->searchable()
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('role')->label(__('Role'))->badge()->color('gray')
                    ->state(fn (User $record) => ($role = Role::tryFrom((string) $record->roles->first()?->name)) ? self::roleLabel($role) : null),
                TextColumn::make('phone')->label(__('Phone'))->placeholder('—')->toggleable(),
                IconColumn::make('two_step')->label(__('Two-step sign-in'))->boolean()
                    ->state(fn (User $record) => filled($record->app_authentication_secret)),
                TextColumn::make('last_login_at')->label(__('Last sign-in'))->since()->placeholder(__('Never'))->dateTimeTooltip(),
            ])
            ->recordActions([
                Action::make('changeRole')->label(__('Change role'))->icon('heroicon-o-key')
                    ->visible(fn (User $record) => auth()->user()->can('update', $record))
                    ->fillForm(fn (User $record) => ['role' => $record->roles->first()?->name])
                    ->schema([Select::make('role')->label(__('Role'))->options(self::roleOptions())->required()->helperText(self::roleHelp())])
                    ->action(fn (User $record, array $data) => RunsDomainActions::run(
                        fn () => app(CreateStaffMember::class)->changeRole($record, Role::from($data['role']), auth()->user()),
                        __('Role updated'),
                    )),
                Action::make('removeAccess')->label(__('Remove access'))->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (User $record) => auth()->user()->can('update', $record) && ! $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->modalDescription(__('They are signed out at once and can no longer sign in. Their past work stays in the records. You can invite them again later.'))
                    ->action(fn (User $record) => RunsDomainActions::run(
                        fn () => app(CreateStaffMember::class)->removeAccess($record, auth()->user()),
                        __('Access removed'),
                    )),
            ])
            ->emptyStateHeading(__('No team members yet'));
    }

    public static function listHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('Invite team member'))->icon('heroicon-o-envelope')
                ->visible(fn () => auth()->user()->can('create', User::class))
                ->modalDescription(__('They receive an email with a link to choose their password.'))
                ->schema([
                    TextInput::make('name')->label(__('Name'))->required()->maxLength(120),
                    TextInput::make('email')->label(__('Email'))->email()->required()->maxLength(190),
                    TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(32),
                    Select::make('role')->label(__('Role'))->options(self::roleOptions())->required()->helperText(self::roleHelp()),
                ])
                ->action(fn (array $data) => RunsDomainActions::run(
                    fn () => app(CreateStaffMember::class)->handle($data['name'], $data['email'], Role::from($data['role']), $data['phone'] ?? null),
                    __('Invitation sent'),
                )),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUsers::route('/')];
    }
}
