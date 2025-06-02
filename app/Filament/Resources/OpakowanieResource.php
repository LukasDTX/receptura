<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpakowanieResource\Pages;
use App\Models\Opakowanie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OpakowanieResource extends Resource
{
    protected static ?string $model = Opakowanie::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Opakowania';
    
    protected static ?string $modelLabel = 'Opakowanie';
    
    protected static ?string $pluralModelLabel = 'Opakowania';
    
    protected static ?string $navigationGroup = 'Produkcja';
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

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
                Forms\Components\TextInput::make('pojemnosc')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->label('Pojemność (g)')
                    ->suffix('g')
                    ->helperText('Pojemność opakowania w gramach. Przykład: 100g, 250g, 1000g')
                    ->default(0),
                Forms\Components\TextInput::make('cena')
                    ->required()
                    ->numeric()
                    ->prefix('PLN')
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpakowania::route('/'),
            'create' => Pages\CreateOpakowanie::route('/create'),
            'edit' => Pages\EditOpakowanie::route('/{record}/edit'),
        ];
    }
}