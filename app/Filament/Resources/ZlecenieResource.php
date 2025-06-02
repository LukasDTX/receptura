<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZlecenieResource\Pages;
use App\Filament\Resources\ZlecenieResource\RelationManagers;
use App\Models\Zlecenie;
use App\Models\Produkt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;

class ZlecenieResource extends Resource
{
    protected static ?string $model = Zlecenie::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    
    protected static ?string $navigationLabel = 'Zlecenia produkcyjne';
    
    protected static ?string $modelLabel = 'Zlecenie';
    
    protected static ?string $pluralModelLabel = 'Zlecenia';
    
    protected static ?string $navigationGroup = 'Produkcja';
    
    protected static ?int $navigationSort = 100;
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'nowe')->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'nowe')->count() > 0 ? 'warning' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('numer')
                    ->label('Numer zlecenia')
                    ->default(function () {
                        $rok = date('Y');
                        $miesiac = date('m');
                        $ostatnieId = \App\Models\Zlecenie::max('id') ?? 0;
                        $noweId = $ostatnieId + 1;
                        
                        return "ZP/{$rok}/{$miesiac}/{$noweId}";
                    })
                    ->required() // Zmieniamy na required
                    ->maxLength(255) // Dodajemy ograniczenie długości
                    ->unique(ignorable: fn ($record) => $record), // Dodajemy sprawdzanie unikalności
                
                Forms\Components\Grid::make()
                    ->schema([
                        Select::make('produkt_id')
                            ->label('Produkt')
                            ->options(function () {
                                return Produkt::all()->pluck('nazwa', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('ilosc', 1);
                            }),
                            
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość (sztuk)')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->reactive(),
                        
                        Forms\Components\DatePicker::make('data_zlecenia')
                            ->label('Data zlecenia')
                            ->required()
                            ->default(now()),
                            
                        Forms\Components\DatePicker::make('planowana_data_realizacji')
                            ->label('Planowana data realizacji')
                            ->minDate(fn (Get $get) => $get('data_zlecenia'))
                            ->default(now()->addDays(7)),
                        
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'nowe' => 'Nowe',
                                'w_realizacji' => 'W realizacji',
                                'zrealizowane' => 'Zrealizowane',
                                'anulowane' => 'Anulowane',
                            ])
                            ->default('nowe')
                            ->required(),
                    ])
                    ->columns(2),
                
                Forms\Components\Textarea::make('uwagi')
                    ->label('Uwagi')
                    ->columnSpanFull(),
                
                Forms\Components\Placeholder::make('info_produkt')
                    ->label('Informacje o produkcie')
                    ->content(function (Get $get) {
                        $produktId = $get('produkt_id');
                        if (!$produktId) {
                            return 'Wybierz produkt, aby zobaczyć szczegóły.';
                        }
                        
                        $produkt = Produkt::with(['receptura', 'opakowanie'])->find($produktId);
                        if (!$produkt) {
                            return 'Nie znaleziono produktu.';
                        }
                        
                        $info = "Nazwa: {$produkt->nazwa}<br>";
                        $info .= "Kod: {$produkt->kod}<br>";
                        
                        if ($produkt->receptura) {
                            $info .= "Receptura: {$produkt->receptura->nazwa}<br>";
                            $info .= "Koszt receptury: " . number_format($produkt->receptura->koszt_calkowity, 2) . " PLN<br>";
                        }
                        
                        if ($produkt->opakowanie) {
                            $info .= "Opakowanie: {$produkt->opakowanie->nazwa}<br>";
                            $info .= "Pojemność: " . number_format($produkt->opakowanie->pojemnosc, 0) . " g<br>";
                        }
                        
                        $info .= "Koszt jednostkowy: " . number_format($produkt->koszt_calkowity, 2) . " PLN<br>";
                        $info .= "Cena sprzedaży: " . number_format($produkt->cena_sprzedazy, 2) . " PLN<br>";
                        
                        $marza = $produkt->cena_sprzedazy - $produkt->koszt_calkowity;
                        $marzaProcent = ($produkt->koszt_calkowity > 0) ? (($marza / $produkt->koszt_calkowity) * 100) : 0;
                        $info .= "Marża: " . number_format($marza, 2) . " PLN (" . number_format($marzaProcent, 2) . "%)";
                        
                        return new \Illuminate\Support\HtmlString($info);
                    })
                    ->columnSpanFull(),
                
                Forms\Components\Section::make('Surowce potrzebne do realizacji zlecenia')
                    ->description('Poniżej jest lista surowców potrzebnych do realizacji zlecenia. Tabela jest wypełniana automatycznie po zapisaniu zlecenia.')
                    ->schema([
                        Forms\Components\Placeholder::make('surowce_info')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->surowce_potrzebne) {
                                    return 'Lista surowców zostanie wygenerowana automatycznie po zapisaniu zlecenia.';
                                }
                                
                                $surowce = $record->surowce_potrzebne;
                                
                                if (empty($surowce)) {
                                    return 'Brak danych o potrzebnych surowcach. Upewnij się, że produkt ma recepturę i opakowanie.';
                                }
                                
                                $html = '<table class="w-full text-left border-collapse">';
                                $html .= '<thead>';
                                $html .= '<tr>';
                                $html .= '<th class="py-2 px-4 bg-gray-100 font-semibold text-gray-700 border-b">Nazwa</th>';
                                $html .= '<th class="py-2 px-4 bg-gray-100 font-semibold text-gray-700 border-b">Kod</th>';
                                $html .= '<th class="py-2 px-4 bg-gray-100 font-semibold text-gray-700 border-b">Ilość</th>';
                                $html .= '<th class="py-2 px-4 bg-gray-100 font-semibold text-gray-700 border-b">Cena jedn.</th>';
                                $html .= '<th class="py-2 px-4 bg-gray-100 font-semibold text-gray-700 border-b">Koszt</th>';
                                $html .= '</tr>';
                                $html .= '</thead>';
                                $html .= '<tbody>';
                                
                                $suma = 0;
                                
                                foreach ($surowce as $surowiec) {
                                    $html .= '<tr>';
                                    $html .= '<td class="py-2 px-4 border-b">' . $surowiec['nazwa'] . '</td>';
                                    $html .= '<td class="py-2 px-4 border-b">' . $surowiec['kod'] . '</td>';
                                    $html .= '<td class="py-2 px-4 border-b">' . number_format($surowiec['ilosc'], 3) . ' ' . $surowiec['jednostka'] . '</td>';
                                    $html .= '<td class="py-2 px-4 border-b">' . number_format($surowiec['cena_jednostkowa'], 2) . ' PLN</td>';
                                    $html .= '<td class="py-2 px-4 border-b">' . number_format($surowiec['koszt'], 2) . ' PLN</td>';
                                    $html .= '</tr>';
                                    
                                    $suma += $surowiec['koszt'];
                                }
                                
                                $html .= '</tbody>';
                                $html .= '<tfoot>';
                                $html .= '<tr>';
                                $html .= '<td colspan="4" class="py-2 px-4 text-right font-bold">Suma:</td>';
                                $html .= '<td class="py-2 px-4 font-bold">' . number_format($suma, 2) . ' PLN</td>';
                                $html .= '</tr>';
                                $html .= '</tfoot>';
                                $html .= '</table>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->collapsed(fn ($record) => $record === null)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numer')
                    ->label('Numer zlecenia')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('produkt.nazwa')
                    ->label('Produkt')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ilosc')
                    ->label('Ilość (szt.)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_zlecenia')
                    ->label('Data zlecenia')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('planowana_data_realizacji')
                    ->label('Planowana realizacja')
                    ->date()
                    ->sortable(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w_realizacji' => 'W realizacji',
                        'zrealizowane' => 'Zrealizowane',
                        'anulowane' => 'Anulowane',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w_realizacji' => 'W realizacji',
                        'zrealizowane' => 'Zrealizowane',
                        'anulowane' => 'Anulowane',
                    ])
                    ->label('Status'),
                Tables\Filters\Filter::make('data_zlecenia')
                    ->form([
                        Forms\Components\DatePicker::make('data_od')
                            ->label('Od daty'),
                        Forms\Components\DatePicker::make('data_do')
                            ->label('Do daty'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_od'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data_zlecenia', '>=', $date),
                            )
                            ->when(
                                $data['data_do'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data_zlecenia', '<=', $date),
                            );
                    })
                    ->label('Data zlecenia'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('drukuj')
                    ->label('Drukuj')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Zlecenie $record): string => route('zlecenie.drukuj', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('zmien_status')
                        ->label('Zmień status')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'nowe' => 'Nowe',
                                    'w_realizacji' => 'W realizacji',
                                    'zrealizowane' => 'Zrealizowane',
                                    'anulowane' => 'Anulowane',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => $data['status'],
                                ]);
                            }
                        }),
                ]),
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
            'index' => Pages\ListZlecenies::route('/'),
            'create' => Pages\CreateZlecenie::route('/create'),
            'edit' => Pages\EditZlecenie::route('/{record}/edit'),
        ];
    }    
}