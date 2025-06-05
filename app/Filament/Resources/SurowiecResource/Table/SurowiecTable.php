<?php
namespace App\Filament\Resources\SurowiecResource\Table;

use Filament\Tables;

class SurowiecTable
{
    public static function make(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nazwa')->searchable(),
                Tables\Columns\TextColumn::make('kod'),
                 Tables\Columns\TextColumn::make('jednostka_miary'),
                Tables\Columns\TextColumn::make('cena_jednostkowa')->money('PLN'),
               
                // Tables\Columns\TextColumn::make('typ')
                //     ->formatStateUsing(fn($state) => TypSurowca::from($state)->label()),
            ])
            ->filters([
                // możesz dodać filtr po typie
            ]);
    }
}