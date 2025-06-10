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
Tables\Columns\TextColumn::make('nazwa')
    ->label('Nazwa surowca')
    ->searchable(['nazwa', 'nazwa_naukowa']) // Szukaj w obu polach
    ->sortable()
    ->html()
    ->formatStateUsing(function ($record) {
        $html = '<div class="space-y-1">';
        
        // Nazwa główna
        $html .= '<div class="font-semibold text-gray-900 leading-tight">' 
                . e($record->nazwa) 
                . '</div>';
        
        // Nazwa naukowa (jeśli istnieje)
        if (!empty($record->nazwa_naukowa)) {
            $html .= '<div class="text-xs text-gray-400 italic font-light leading-tight">' 
                    . e($record->nazwa_naukowa) 
                    . '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }),
                Tables\Columns\TextColumn::make('kod')->label('Index')
                    ->toggleable(isToggledHiddenByDefault: false),
                // NOWA KOLUMNA KATEGORII
                Tables\Columns\TextColumn::make('kategoria')
                    ->label('Kategoria')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state?->label() ?? 'Brak')
                    ->color(fn($state) => $state?->color() ?? 'gray')
                    ->icon(fn($state) => $state?->icon() ?? 'heroicon-o-question-mark-circle')
                    ->tooltip(fn($state) => $state?->description() ?? 'Brak kategorii')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
Tables\Columns\TextColumn::make('jednostka_miary')
    ->badge()
    ->formatStateUsing(fn($state) => $state?->label() ?? 'Brak')
    ->color(fn($state) => match($state) {
        \App\Enums\JednostkaMiary::G => 'success',
        \App\Enums\JednostkaMiary::ML => 'info',
        default => 'gray'
    }),
                Tables\Columns\TextColumn::make('cena_jednostkowa')->money('PLN'),
               
                // Tables\Columns\TextColumn::make('typ')
                //     ->formatStateUsing(fn($state) => TypSurowca::from($state)->label()),
            ])
            ->filters([
                // możesz dodać filtr po typie
            ]);
    }
}