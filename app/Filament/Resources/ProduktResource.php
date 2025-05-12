<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProduktResource\Pages;
use App\Models\Produkt;
use App\Models\Receptura;
use App\Models\Opakowanie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;

class ProduktResource extends Resource
{
    protected static ?string $model = Produkt::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    
    protected static ?string $navigationLabel = 'Produkty';
    
    protected static ?string $modelLabel = 'Produkt';
    
    protected static ?string $pluralModelLabel = 'Produkty';

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
                
                Forms\Components\Section::make('Komponenty produktu')
                    ->description('Wybierz recepturę (półprodukt) i opakowanie')
                    ->schema([
                        Select::make('receptura_id')
                            ->label('Receptura (półprodukt)')
                            ->options(function () {
                                return Receptura::all()->pluck('nazwa', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('koszt_calkowity', null)),
                            
                        Select::make('opakowanie_id')
                            ->label('Opakowanie')
                            ->options(function () {
                                return Opakowanie::all()->pluck('nazwa', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('koszt_calkowity', null)),
                            
                        Forms\Components\Placeholder::make('receptura_koszt')
                            ->label('Koszt receptury')
                            ->content(function (Get $get) {
                                $recepturaId = $get('receptura_id');
                                if (!$recepturaId) return 'Wybierz recepturę';
                                
                                $receptura = Receptura::find($recepturaId);
                                if (!$receptura) return 'Receptura nie znaleziona';
                                
                                return number_format($receptura->koszt_calkowity, 2) . ' PLN';
                            }),
                            
                        Forms\Components\Placeholder::make('opakowanie_koszt')
                            ->label('Koszt opakowania')
                            ->content(function (Get $get) {
                                $opakownieId = $get('opakowanie_id');
                                if (!$opakownieId) return 'Wybierz opakowanie';
                                
                                $opakowanie = Opakowanie::find($opakownieId);
                                if (!$opakowanie) return 'Opakowanie nie znalezione';
                                
                                return number_format($opakowanie->cena, 2) . ' PLN';
                            }),
                    ])->columns(2),
                
                Forms\Components\Section::make('Koszty i ceny')
                    ->schema([
                        Forms\Components\TextInput::make('koszt_calkowity')
                            ->disabled()
                            ->dehydrated(true) // Zmiana na true aby wartość była przekazywana do bazy
                            ->default(0) // Dodanie domyślnej wartości 0
                            ->label('Koszt całkowity produktu')
                            ->helperText('Automatycznie obliczony na podstawie receptury i opakowania')
                            ->prefix('PLN'),
                            
                        Forms\Components\TextInput::make('cena_sprzedazy')
                            ->required()
                            ->numeric()
                            ->label('Cena sprzedaży')
                            ->prefix('PLN')
                            ->default(0),
                            
                        Forms\Components\Placeholder::make('marza')
                            ->label('Marża')
                            ->content(function (Get $get) {
                                $kosztCalkowity = $get('koszt_calkowity');
                                $cenaSprzedazy = $get('cena_sprzedazy');
                                
                                if (!$kosztCalkowity || !$cenaSprzedazy || $kosztCalkowity == 0) {
                                    return '0.00 PLN (0%)';
                                }
                                
                                $marza = $cenaSprzedazy - $kosztCalkowity;
                                $marzaProcentowa = ($marza / $kosztCalkowity) * 100;
                                
                                return number_format($marza, 2) . ' PLN (' . number_format($marzaProcentowa, 2) . '%)';
                            }),
                    ])->columns(3),
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
                Tables\Columns\TextColumn::make('receptura.nazwa')
                    ->label('Receptura')
                    ->searchable(),
                Tables\Columns\TextColumn::make('opakowanie.nazwa')
                    ->label('Opakowanie')
                    ->searchable(),
                Tables\Columns\TextColumn::make('koszt_calkowity')
                    ->money('pln')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cena_sprzedazy')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static ?string $navigationGroup = 'Produkcja';
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdukts::route('/'),
            'create' => Pages\CreateProdukt::route('/create'),
            'edit' => Pages\EditProdukt::route('/{record}/edit'),
        ];
    }
}