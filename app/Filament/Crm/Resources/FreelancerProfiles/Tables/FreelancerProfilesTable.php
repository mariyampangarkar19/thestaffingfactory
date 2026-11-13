<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\Tables;

use App\Filament\Crm\Resources\FreelancerProfiles\Schemas\FreelancerProfileForm;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FreelancerProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('first_name')->label(__('First name'))->searchable(),
                TextColumn::make('last_name')->label(__('Last name'))->searchable(),
                TextColumn::make('phone')->label(__('Phone'))->searchable(),
                TextColumn::make('city')->label(__('City'))->searchable()->sortable(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state) => FreelancerProfileForm::statuses()[$state] ?? $state),
                TextColumn::make('source')->label(__('Source'))->toggleable(),
                TextColumn::make('created_at')->label(__('Signed up'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(FreelancerProfileForm::statuses()),
                TrashedFilter::make(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
        // No bulk delete: personal data removal goes through a logged data-request flow (milestone 3).
    }
}
