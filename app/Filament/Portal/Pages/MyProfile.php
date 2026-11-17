<?php

namespace App\Filament\Portal\Pages;

use App\Actions\Freelancers\UpdateOwnProfile;
use App\Enums\AvailabilityStatus;
use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * My Profile (quote section 2): the freelancer keeps their own contact details and availability up to date.
 */
class MyProfile extends Page
{
    use ResolvesFreelancer;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'profile';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('My profile');
    }

    public function getTitle(): string
    {
        return __('My profile');
    }

    public function mount(): void
    {
        $profile = self::freelancer()->load('jobRoles');

        $this->form->fill([
            ...$profile->only(UpdateOwnProfile::FIELDS),
            'availability_status' => $profile->availability_status->value,
            'full_name' => $profile->fullName(),
            'email' => $profile->email,
            'positions' => $profile->jobRoles->map->name()->implode(', '),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('Managed by the agency'))
                ->description(__('Contact the agency if any of this needs to change.'))
                ->columns(3)
                ->schema([
                    TextInput::make('full_name')->label(__('Name'))->disabled()->dehydrated(false),
                    TextInput::make('email')->label(__('Email'))->disabled()->dehydrated(false),
                    TextInput::make('positions')->label(__('Positions'))->disabled()->dehydrated(false),
                ]),
            Section::make(__('Contact'))->columns(2)->schema([
                TextInput::make('phone')->label(__('Phone'))->tel()->required()->maxLength(32)->regex('/^[0-9+() .-]{6,32}$/'),
                TextInput::make('whatsapp')->label(__('WhatsApp'))->tel()->maxLength(32)->regex('/^[0-9+() .-]{6,32}$/'),
                TextInput::make('city')->label(__('City'))->maxLength(100),
                TextInput::make('address')->label(__('Address'))->maxLength(255),
            ]),
            Section::make(__('Skills and availability'))->columns(2)->schema([
                Select::make('languages')->label(__('Languages'))->multiple()->options(FreelancerProfileResource::languageOptions()),
                TextInput::make('years_experience')->label(__('Years of experience'))->numeric()->integer()->minValue(0)->maxValue(60),
                Select::make('availability_status')->label(__('Availability'))->required()->selectablePlaceholder(false)
                    ->options(collect(AvailabilityStatus::cases())->mapWithKeys(fn (AvailabilityStatus $s) => [$s->value => $s->getLabel()])->all())
                    ->helperText(__('The agency sees this when choosing people for a shift.')),
                Textarea::make('experience_summary')->label(__('Experience'))->maxLength(2000)->rows(4)->columnSpanFull(),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label(__('Save changes'))->submit('save')])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        app(UpdateOwnProfile::class)->handle(self::freelancer(), $this->form->getState());

        Notification::make()->title(__('Profile saved'))->success()->send();
    }
}
