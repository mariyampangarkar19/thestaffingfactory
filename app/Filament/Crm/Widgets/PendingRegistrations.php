<?php

namespace App\Filament\Crm\Widgets;

use App\Enums\FreelancerStatus;
use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Models\FreelancerProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Pending approvals" on the dashboard: registrations nobody has decided on yet.
 */
class PendingRegistrations extends TableWidget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('freelancers.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Registrations waiting for review'))
            ->query(fn () => FreelancerProfile::query()
                ->whereIn('status', [FreelancerStatus::New, FreelancerStatus::Screening])
                ->with(['jobRoles', 'latestQuizResult'])
                ->latest())
            ->columns([
                TextColumn::make('first_name')->label(__('Name'))->weight('medium')
                    ->formatStateUsing(fn (FreelancerProfile $record) => $record->fullName()),
                TextColumn::make('jobRoles')->label(__('Positions'))->badge()->color('gray')
                    ->state(fn (FreelancerProfile $record) => $record->jobRoles->map->name()->all()),
                TextColumn::make('city')->label(__('City'))->placeholder('—'),
                TextColumn::make('latestQuizResult.score')->label(__('Quiz'))->placeholder(__('Not taken'))
                    ->badge()->color(fn (FreelancerProfile $record) => $record->latestQuizResult?->passed ? 'success' : 'danger')
                    ->formatStateUsing(fn (FreelancerProfile $record) => FreelancerProfileResource::quizLabel($record)),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('created_at')->label(__('Signed up'))->since()->dateTimeTooltip(),
            ])
            ->recordUrl(fn (FreelancerProfile $record) => FreelancerProfileResource::getUrl('view', ['record' => $record]))
            ->paginated([5])
            ->emptyStateHeading(__('Nothing waiting'))
            ->emptyStateDescription(__('New website registrations appear here.'));
    }
}
