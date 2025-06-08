<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuchMagazynowyResource\Pages;
use App\Models\RuchMagazynowy;
use App\Enums\TypRuchuMagazynowego;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class RuchMagazynowyResource extends Resource
{
    protected static ?string $model = RuchMagazynowy::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';
    
    protected static ?string $navigationLabel = 'Ruchy magazynowe';
    
    protected static ?string $modelLabel = 'Ruch magazynowy';
    
    protected static ?string $pluralModelLabel = 'Ruchy magazynowe';
    
    protected static ?string $navigationGroup = 'Magazyn';
    
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('numer_dokumentu')
                            ->label('Numer dokumentu')
                            ->helperText('Opcjonalny numer dokumentu źródłowego'),
                            
                        Forms\Components\DatePicker::make('data_ruchu')
                            ->label('Data ruchu')
                            ->required()
                            ->default(now()),
                    ]),
                    
                Forms\Components\Select::make('typ_ruchu')
                    ->label('Typ ruchu')
                    ->options(TypRuchuMagazynowego::class)
                    ->required()
                    ->reactive(),
                    
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('typ_towaru')
                            ->label('Typ towaru')
                            ->options([
                                'surowiec' => 'Surowiec',
                                'produkt' => 'Produkt',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                $set('towar_id', null);
                            }),
                            
                        Forms\Components\Select::make('towar_id')
                            ->label('Towar')
                            ->options(function (Get $get) {
                                $typ = $get('typ_towaru');
                                if ($typ === 'surowiec') {
                                    return \App\Models\Surowiec::pluck('nazwa', 'id');
                                } elseif ($typ === 'produkt') {
                                    return \App\Models\Produkt::pluck('nazwa', 'id');
                                }
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                    
                Forms\Components\TextInput::make('numer_partii')
                    ->label('Numer partii')
                    ->helperText('Dla produktów - obowiązkowy, dla surowców - opcjonalny'),
                    
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość')
                            ->required()
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, Get $get, $state) {
                                $cena = $get('cena_jednostkowa') ?? 0;
                                $set('wartosc', $state * $cena);
                            }),
                            
                        Forms\Components\TextInput::make('jednostka')
                            ->label('Jednostka')
                            ->required()
                            ->default('szt'),
                            
                        Forms\Components\TextInput::make('cena_jednostkowa')
                            ->label('Cena jednostkowa')
                            ->numeric()
                            ->prefix('PLN')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, Get $get, $state) {
                                $ilosc = $get('ilosc') ?? 0;
                                $set('wartosc', $ilosc * $state);
                            }),
                    ]),
                    
                Forms\Components\TextInput::make('wartosc')
                    ->label('Wartość')
                    ->numeric()
                    ->prefix('PLN')
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),
                    
                Forms\Components\TextInput::make('zrodlo_docelowe')
                    ->label('Źródło/Cel')
                    ->helperText('np. Dostawca, Klient, Zlecenie produkcyjne')
                    ->columnSpanFull(),
                    
                Forms\Components\Textarea::make('uwagi')
                    ->label('Uwagi')
                    ->columnSpanFull(),
                    
                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('data_ruchu')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('numer_dokumentu')
                    ->label('Nr dokumentu')
                    ->searchable()
                    ->placeholder('Brak'),
                    
                Tables\Columns\TextColumn::make('typ_ruchu')
                    ->label('Typ ruchu')
                    ->badge()
                    ->color(fn ($state) => $state->color()),
                    
                Tables\Columns\TextColumn::make('typ_towaru')
                    ->label('Typ towaru')
                    ->formatStateUsing(fn ($state) => $state === 'surowiec' ? 'Surowiec' : 'Produkt')
                    ->badge()
                    ->color(fn ($state) => $state === 'surowiec' ? 'info' : 'success'),
                    
                Tables\Columns\TextColumn::make('nazwa_towaru')
                    ->label('Nazwa towaru')
                    ->getStateUsing(fn ($record) => $record->getNazwaTowaru())
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('numer_partii')
                    ->label('Partia')
                    ->searchable()
                    ->placeholder('Brak'),
                    
                Tables\Columns\TextColumn::make('ilosc')
                    ->label('Ilość')
                    ->formatStateUsing(function ($state, $record) {
                        $prefix = '';
                        if (in_array($record->typ_ruchu, [TypRuchuMagazynowego::WYDANIE, TypRuchuMagazynowego::KOREKTA_MINUS])) {
                            $prefix = '-';
                        } elseif (in_array($record->typ_ruchu, [TypRuchuMagazynowego::PRZYJECIE, TypRuchuMagazynowego::KOREKTA_PLUS, TypRuchuMagazynowego::PRODUKCJA])) {
                            $prefix = '+';
                        }
                        return $prefix . number_format(abs($state), 2) . ' ' . $record->jednostka;
                    })
                    ->color(function ($record) {
                        if (in_array($record->typ_ruchu, [TypRuchuMagazynowego::WYDANIE, TypRuchuMagazynowego::KOREKTA_MINUS])) {
                            return 'danger';
                        }
                        return 'success';
                    }),
                    
                Tables\Columns\TextColumn::make('wartosc')
                    ->label('Wartość')
                    ->money('pln')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('zrodlo_docelowe')
                    ->label('Źródło/Cel')
                    ->searchable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Użytkownik')
                    ->placeholder('System'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('typ_ruchu')
                    ->options(TypRuchuMagazynowego::class)
                    ->label('Typ ruchu'),
                    
                Tables\Filters\SelectFilter::make('typ_towaru')
                    ->options([
                        'surowiec' => 'Surowce',
                        'produkt' => 'Produkty',
                    ])
                    ->label('Typ towaru'),
                    
                Tables\Filters\Filter::make('data_ruchu')
                    ->form([
                        Forms\Components\DatePicker::make('data_od')
                            ->label('Od daty'),
                        Forms\Components\DatePicker::make('data_do')
                            ->label('Do daty'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['data_od'],
                                fn ($query, $date) => $query->whereDate('data_ruchu', '>=', $date),
                            )
                            ->when(
                                $data['data_do'],
                                fn ($query, $date) => $query->whereDate('data_ruchu', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->user_id === auth()->id() || auth()->user()->is_admin ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->is_admin ?? false),
                ]),
            ])
            ->defaultSort('data_ruchu', 'desc')
            ->poll('30s'); // Odświeżanie co 30 sekund
    }
    
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereDate('created_at', today())->count();
        return $count > 0 ? $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRuchMagazynowies::route('/'),
            'create' => Pages\CreateRuchMagazynowy::route('/create'),
            'view' => Pages\ViewRuchMagazynowy::route('/{record}'),
            'edit' => Pages\EditRuchMagazynowy::route('/{record}/edit'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return true; // Pozwól na tworzenie ruchów magazynowych
    }
}