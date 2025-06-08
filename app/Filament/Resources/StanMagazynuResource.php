<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StanMagazynuResource\Pages;
use App\Models\StanMagazynu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StanMagazynuResource extends Resource
{
    protected static ?string $model = StanMagazynu::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationLabel = 'Stan magazynu';
    
    protected static ?string $modelLabel = 'Pozycja magazynowa';
    
    protected static ?string $pluralModelLabel = 'Stan magazynu';
    
    protected static ?string $navigationGroup = 'Magazyn';
    
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('typ_towaru')
                    ->label('Typ towaru')
                    ->options([
                        'surowiec' => 'Surowiec',
                        'produkt' => 'Produkt',
                    ])
                    ->required()
                    ->reactive(),
                    
                Forms\Components\Select::make('towar_id')
                    ->label('Towar')
                    ->options(function (callable $get) {
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
                    
                Forms\Components\TextInput::make('numer_partii')
                    ->label('Numer partii')
                    ->helperText('Pozostaw puste dla surowców bez śledzenia partii'),
                    
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('ilosc_dostepna')
                            ->label('Ilość dostępna')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                            
                        Forms\Components\TextInput::make('jednostka')
                            ->label('Jednostka')
                            ->required()
                            ->default('szt'),
                    ]),
                    
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('wartosc')
                            ->label('Wartość')
                            ->numeric()
                            ->prefix('PLN')
                            ->default(0),
                            
                        Forms\Components\DatePicker::make('data_waznosci')
                            ->label('Data ważności'),
                    ]),
                    
                Forms\Components\TextInput::make('lokalizacja')
                    ->label('Lokalizacja')
                    ->helperText('np. Regał A1, Półka 3'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('typ_towaru')
                    ->label('Typ')
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
                    
                Tables\Columns\TextColumn::make('ilosc_dostepna')
                    ->label('Ilość')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->jednostka)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('wartosc')
                    ->label('Wartość')
                    ->money('pln')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('data_waznosci')
                    ->label('Data ważności')
                    ->date()
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->data_waznosci) return null;
                        
                        if ($record->isPrzeterminowany()) {
                            return 'danger';
                        } elseif ($record->isBliskoPrzeterminowania()) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->placeholder('Brak'),
                    
                Tables\Columns\TextColumn::make('lokalizacja')
                    ->label('Lokalizacja')
                    ->searchable()
                    ->placeholder('Nie określono'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('typ_towaru')
                    ->label('Typ towaru')
                    ->options([
                        'surowiec' => 'Surowce',
                        'produkt' => 'Produkty',
                    ]),
                    
                Tables\Filters\Filter::make('mala_ilosc')
                    ->label('Mała ilość (< 10)')
                    ->query(fn ($query) => $query->where('ilosc_dostepna', '<', 10))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('przeterminowane')
                    ->label('Przeterminowane')
                    ->query(fn ($query) => $query->where('data_waznosci', '<', now()))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('blisko_przeterminowania')
                    ->label('Wkrótce przeterminowane (30 dni)')
                    ->query(fn ($query) => $query->whereBetween('data_waznosci', [now(), now()->addDays(30)]))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('ruch_magazynowy')
                    ->label('Dodaj ruch')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('typ_ruchu')
                            ->label('Typ ruchu')
                            ->options([
                                'przyjecie' => '➕ Przyjęcie',
                                'wydanie' => '➖ Wydanie', 
                                'korekta_plus' => '📈 Korekta (+)',
                                'korekta_minus' => '📉 Korekta (-)',
                            ])
                            ->required()
                            ->default('korekta_plus'),
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('cena_jednostkowa')
                            ->label('Cena jednostkowa')
                            ->numeric()
                            ->prefix('PLN')
                            ->default(0),
                        Forms\Components\Textarea::make('uwagi')
                            ->label('Uwagi')
                            ->placeholder('Powód ruchu magazynowego...'),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            // Utwórz ruch magazynowy
                            \App\Models\RuchMagazynowy::createRuch([
                                'typ_ruchu' => $data['typ_ruchu'],
                                'typ_towaru' => $record->typ_towaru,
                                'towar_id' => $record->towar_id,
                                'numer_partii' => $record->numer_partii,
                                'ilosc' => $data['ilosc'],
                                'jednostka' => $record->jednostka,
                                'cena_jednostkowa' => $data['cena_jednostkowa'],
                                'wartosc' => $data['ilosc'] * $data['cena_jednostkowa'],
                                'data_ruchu' => now(),
                                'zrodlo_docelowe' => 'Ruch z poziomu magazynu',
                                'uwagi' => $data['uwagi'] ?? '',
                                'user_id' => auth()->id(),
                            ]);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Ruch magazynowy utworzony')
                                ->body('Stan magazynu został zaktualizowany.')
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Błąd podczas tworzenia ruchu')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Utwórz ruch magazynowy')
                    ->modalSubmitActionLabel('Utwórz ruch')
                    ->modalCancelActionLabel('Anuluj'),
                Tables\Actions\Action::make('zobacz_ruchy')
                    ->label('Zobacz ruchy')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->url(fn () => RuchMagazynowyResource::getUrl('index'))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('nowy_ruch')
                ->label('Nowy ruch magazynowy')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(RuchMagazynowyResource::getUrl('create')),
        ];
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStanMagazynus::route('/'),
            'create' => Pages\CreateStanMagazynu::route('/create'),
            'edit' => Pages\EditStanMagazynu::route('/{record}/edit'),
        ];
    }
}