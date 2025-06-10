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
                Tables\Columns\TextColumn::make('pojemnosc_formatted')
                    ->label('Pojemność')
                    ->getStateUsing(function ($record) {
                        $jednostka = $record->jednostka instanceof \App\Enums\JednostkaOpakowania 
                            ? $record->jednostka->value 
                            : $record->jednostka;
                        return number_format($record->pojemnosc, $record->pojemnosc == intval($record->pojemnosc) ? 0 : 2) . ' ' . $jednostka;
                    })
                    ->sortable('pojemnosc')
                    ->badge()
                    ->color(function ($record) {
                        $jednostka = $record->jednostka instanceof \App\Enums\JednostkaOpakowania 
                            ? $record->jednostka->value 
                            : $record->jednostka;
                        return $jednostka === 'ml' ? 'info' : 'success';
                    }),
                Tables\Columns\TextColumn::make('jednostka')
                    ->label('Typ')
                    ->formatStateUsing(function ($state) {
                        $jednostka = $state instanceof \App\Enums\JednostkaOpakowania 
                            ? $state->value 
                            : $state;
                        return $jednostka === 'ml' ? 'Płynny' : 'Stały';
                    })
                    ->badge()
                    ->color(function ($state) {
                        $jednostka = $state instanceof \App\Enums\JednostkaOpakowania 
                            ? $state->value 
                            : $state;
                        return $jednostka === 'ml' ? 'info' : 'success';
                    }),
                Tables\Columns\TextColumn::make('cena')
                    ->money('pln')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('jednostka')
                    ->label('Typ opakowania')
                    ->options([
                        'g' => 'Stałe (gramy)',
                        'ml' => 'Płynne (mililitry)',
                    ]),
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