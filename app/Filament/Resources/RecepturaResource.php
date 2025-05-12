<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecepturaResource\Pages;
use App\Filament\Resources\RecepturaResource\RelationManagers;
use App\Models\Receptura;
use App\Models\Surowiec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class RecepturaResource extends Resource
{
    protected static ?string $model = Receptura::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Receptury';
    
    protected static ?string $modelLabel = 'Receptura';
    
    protected static ?string $pluralModelLabel = 'Receptury';
    
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
                Forms\Components\TextInput::make('koszt_calkowity')
                    ->disabled()
                    ->dehydrated(false)
                    ->prefix('PLN')
                    ->numeric()
                    ->label('Koszt całkowity (za 1kg)')
                    ->helperText('Koszt wytworzenia 1kg produktu według tej receptury.'),
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
                Tables\Columns\TextColumn::make('koszt_calkowity')
                    ->money('pln')
                    ->sortable(),
                Tables\Columns\TextColumn::make('surowce_count')
                    ->label('Ilość surowców')
                    ->counts('surowce')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SurowceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecepturas::route('/'),
            'create' => Pages\CreateReceptura::route('/create'),
            'edit' => Pages\EditReceptura::route('/{record}/edit'),
        ];
    }
}