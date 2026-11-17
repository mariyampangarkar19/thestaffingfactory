<?php

namespace App\Filament\Crm\Resources\Clients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Contacts');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('Name'))->required()->maxLength(150),
            TextInput::make('job_title')->label(__('Job title'))->maxLength(100),
            TextInput::make('email')->label(__('Email'))->email()->maxLength(190),
            TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(32),
            Toggle::make('is_primary')->label(__('Main contact')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('medium'),
                TextColumn::make('job_title')->label(__('Job title'))->placeholder('—'),
                TextColumn::make('email')->label(__('Email'))->placeholder('—')->copyable(),
                TextColumn::make('phone')->label(__('Phone'))->placeholder('—')->copyable(),
                IconColumn::make('is_primary')->label(__('Main contact'))->boolean(),
            ])
            ->headerActions([CreateAction::make()->label(__('Add contact'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No contacts yet'));
    }
}
