<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurowiecResource\Pages;
use App\Models\Surowiec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SurowiecResource extends Resource
{
    protected static ?string $model = Surowiec::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    
    protected static ?string $navigationLabel = 'Surowce';
    
    protected static ?string $modelLabel = 'Surowiec';
    
    protected static ?string $pluralModelLabel = 'Surowce';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nazwa')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('kod')
                    ->required()
                    ->unique(ignorable: fn ($record) => $record)
                    ->maxLength(255),
                Forms\Components\Textarea::make('opis')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('cena_jednostkowa')
                    ->required()
                    ->numeric()
                    ->prefix('PLN')
                    ->default(0),
                Forms\Components\Select::make('jednostka_miary')
                    ->options([
                        'g' => 'Gram',
                        'kg' => 'Kilogram',
                        'ml' => 'Mililitr',
                        'l' => 'Litr',
                        'szt' => 'Sztuka',
                    ])
                    ->default('g') // Zmiana domyślnej wartości na gramy
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nazwa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kod')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cena_jednostkowa')
                    ->money('pln')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jednostka_miary')
                    ->searchable(),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nazwa', 'kod'];
    }
    
    public static function getNavigationGroup(): ?string
    {
        return 'Produkcja';
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurowiecs::route('/'),
            'create' => Pages\CreateSurowiec::route('/create'),
            'edit' => Pages\EditSurowiec::route('/{record}/edit'),
        ];
    }
}