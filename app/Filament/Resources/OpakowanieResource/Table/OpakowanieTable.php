<?php
namespace App\Filament\Resources\OpakowanieResource\Table;

use Filament\Tables;

class OpakowanieTable
{
    public static function make(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nazwa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pojemnosc')
                    ->label('Pojemność')
                    ->formatStateUsing(fn ($state) => number_format($state, $state == intval($state) ? 0 : 3) . ' g')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cena')
                    ->money('pln')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil'),
                Tables\Actions\DeleteAction::make()
                    ->label('Usuń')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Usuń opakowanie')
                    ->modalDescription('Czy na pewno chcesz usunąć opakowanie? Ta akcja jest nieodwracalna.')
                    ->modalSubmitActionLabel('Usuń')
                    ->modalCancelActionLabel('Anuluj'),
            ]);
    }
}