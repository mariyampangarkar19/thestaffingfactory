<?php

namespace App\Filament\Crm\Resources\JobRoles;

use App\Filament\Crm\Resources\BaseResource;
use App\Models\JobRole;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * The positions an agency staffs and what each pays per hour. Every agency keeps its own list.
 */
class JobRoleResource extends BaseResource
{
    protected static ?string $model = JobRole::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public static function getModelLabel(): string
    {
        return __('Position');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Positions and pay rates');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof JobRole ? $record->name() : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Position'))->columns(2)->schema([
                TextInput::make('name_en')->label(__('Name (English)'))->required()->maxLength(100)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('name_fr')->label(__('Name (French)'))->required()->maxLength(100),
                TextInput::make('slug')->label(__('Short code'))->required()->maxLength(60)->alphaDash()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', app(TenantContext::class)->id()))
                    ->helperText(__('Used to match the position chosen on the website form. Lower case, no spaces.'))
                    ->disabledOn('edit'),
                Toggle::make('is_active')->label(__('In use'))->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name_en')
            ->columns([
                TextColumn::make('name_en')->label(__('Position'))->weight('medium')
                    ->formatStateUsing(fn (JobRole $record) => $record->name())->searchable(['name_en', 'name_fr']),
                TextColumn::make('slug')->label(__('Short code'))->color('gray'),
                TextColumn::make('pay_rates_count')->counts('payRates')->label(__('Pay rates'))->alignEnd(),
                IconColumn::make('is_active')->label(__('In use'))->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading(__('No positions yet'));
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PayRatesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobRoles::route('/'),
            'create' => Pages\CreateJobRole::route('/create'),
            'edit' => Pages\EditJobRole::route('/{record}/edit'),
        ];
    }
}
