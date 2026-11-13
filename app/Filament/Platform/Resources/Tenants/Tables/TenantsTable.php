<?php

namespace App\Filament\Platform\Resources\Tenants\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('country_code')->label('Country'),
                TextColumn::make('plan'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([EditAction::make()]);
        // Deleting an agency deletes all its data, so it is deliberately not a one-click action here.
    }
}
