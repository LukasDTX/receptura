<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartiaResource\Pages;
use App\Models\Partia;
use App\Models\Zlecenie;
use App\Enums\StatusPartii;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class PartiaResource extends Resource
{
    protected static ?string $model = Partia::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';
    
    protected static ?string $navigationLabel = 'Partie produktów';
    
    protected static ?string $modelLabel = 'Partia';
    
    protected static ?string $pluralModelLabel = 'Partie';
    
    protected static ?string $navigationGroup = 'Magazyn';
    
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('numer_partii')
                    ->label('Numer partii')
                    ->default(fn () => Partia::generateNumerPartii())
                    ->required()
                    ->unique(ignorable: fn ($record) => $record)
                    ->readonly()
                    ->helperText('Automatycznie generowany'),
                    
                Forms\Components\Select::make('zlecenie_id')
                    ->label('Zlecenie produkcyjne')
                    ->options(function () {
                        return Zlecenie::with('produkt')
                            ->where('status', 'zrealizowane')
                            ->get()
                            ->mapWithKeys(function ($zlecenie) {
                                return [$zlecenie->id => $zlecenie->numer . ' - ' . ($zlecenie->produkt->nazwa ?? 'Nieznany produkt')];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $zlecenie = Zlecenie::with('produkt')->find($state);
                            if ($zlecenie) {
                                $set('produkt_id', $zlecenie->produkt_id);
                                $set('ilosc_wyprodukowana', $zlecenie->ilosc);
                                $set('surowce_uzyte', $zlecenie->surowce_potrzebne);
                            }
                        }
                    }),
                    
                Forms\Components\TextInput::make('produkt_id')
                    ->label('Produkt')
                    ->disabled()
                    ->dehydrated(),
                    
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('ilosc_wyprodukowana')
                            ->label('Ilość wyprodukowana')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->suffix('szt'),
                            
                        Forms\Components\DatePicker::make('data_produkcji')
                            ->label('Data produkcji')
                            ->required()
                            ->default(now()),
                    ]),
                    
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('data_waznosci')
                            ->label('Data ważności')
                            ->minDate(fn (Get $get) => $get('data_produkcji'))
                            ->default(now()->addMonths(12)),
                            
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(StatusPartii::class)
                            ->default(StatusPartii::WYPRODUKOWANA)
                            ->required(),
                    ]),
                    
                Forms\Components\TextInput::make('koszt_produkcji')
                    ->label('Koszt produkcji')
                    ->numeric()
                    ->prefix('PLN')
                    ->default(0)
                    ->helperText('Automatycznie obliczany na podstawie użytych surowców'),
                    
                Forms\Components\Textarea::make('uwagi')
                    ->label('Uwagi')
                    ->columnSpanFull(),
                    
                // Sekcja surowców użytych
                Forms\Components\Section::make('Surowce użyte w produkcji')
                    ->description('Lista surowców rzeczywiście użytych do produkcji tej partii')
                    ->schema([
                        Forms\Components\Placeholder::make('surowce_info')
                            ->label('')
                            ->content(function ($record, Get $get) {
                                $surowce = null;
                                if (!$record) {
                                    $surowce = $get('surowce_uzyte');
                                } else {
                                    $surowce = $record->surowce_uzyte;
                                }
                                
                                if (!$surowce || empty($surowce)) {
                                    return '<div style="padding: 20px; text-align: center; color: #6b7280;">
                                            <p>Brak danych o użytych surowcach.</p>
                                            <p><small>Wybierz zlecenie, aby załadować listę surowców.</small></p>
                                            </div>';
                                }
                                
                                $html = '<div style="overflow-x: auto;">';
                                $html .= '<table class="w-full text-left border-collapse border border-gray-300">';
                                $html .= '<thead>';
                                $html .= '<tr style="background-color: #f9fafb;">';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Nazwa</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Kod</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Ilość</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Cena jedn.</th>';
                                $html .= '<th class="py-3 px-4 font-semibold text-gray-700 border-b border-gray-300">Koszt</th>';
                                $html .= '</tr>';
                                $html .= '</thead>';
                                $html .= '<tbody>';
                                
                                $suma = 0;
                                
                                foreach ($surowce as $surowiec) {
                                    $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
                                    $html .= '<td class="py-2 px-4">' . htmlspecialchars($surowiec['nazwa']) . '</td>';
                                    $html .= '<td class="py-2 px-4">' . htmlspecialchars($surowiec['kod']) . '</td>';
                                    
                                    $iloscFormatowana = $surowiec['ilosc'] < 1 
                                        ? number_format($surowiec['ilosc'], 2) 
                                        : number_format($surowiec['ilosc'], $surowiec['ilosc'] == intval($surowiec['ilosc']) ? 0 : 2);
                                    
                                    $html .= '<td class="py-2 px-4">' . $iloscFormatowana . ' ' . htmlspecialchars($surowiec['jednostka']) . '</td>';
                                    $html .= '<td class="py-2 px-4">' . number_format($surowiec['cena_jednostkowa'], 2) . ' PLN</td>';
                                    $html .= '<td class="py-2 px-4 font-semibold">' . number_format($surowiec['koszt'], 2) . ' PLN</td>';
                                    $html .= '</tr>';
                                    
                                    $suma += $surowiec['koszt'];
                                }
                                
                                $html .= '</tbody>';
                                $html .= '<tfoot>';
                                $html .= '<tr style="background-color: #f3f4f6; font-weight: bold;">';
                                $html .= '<td colspan="4" class="py-3 px-4 text-right">SUMA KOSZTÓW:</td>';
                                $html .= '<td class="py-3 px-4 text-lg">' . number_format($suma, 2) . ' PLN</td>';
                                $html .= '</tr>';
                                $html .= '</tfoot>';
                                $html .= '</table>';
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->reactive()
                            ->live(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->columnSpanFull(),
                    
                Forms\Components\Hidden::make('surowce_uzyte')
                    ->dehydrated(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numer_partii')
                    ->label('Numer partii')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('produkt.nazwa')
                    ->label('Produkt')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('zlecenie.numer')
                    ->label('Zlecenie')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('ilosc_wyprodukowana')
                    ->label('Ilość')
                    ->suffix(' szt')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('data_produkcji')
                    ->label('Data produkcji')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('data_waznosci')
                    ->label('Data ważności')
                    ->date()
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->data_waznosci) return null;
                        
                        if ($record->data_waznosci < now()) {
                            return 'danger'; // Przeterminowane
                        } elseif ($record->data_waznosci <= now()->addDays(30)) {
                            return 'warning'; // Wkrótce się przeterminuje
                        }
                        return 'success';
                    }),
                    
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(StatusPartii::class)
                    ->disabled(fn ($record) => $record->status === StatusPartii::WYCOFANA),
                    
                Tables\Columns\TextColumn::make('koszt_produkcji')
                    ->label('Koszt')
                    ->money('pln')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(StatusPartii::class)
                    ->label('Status'),
                    
                Tables\Filters\SelectFilter::make('produkt_id')
                    ->relationship('produkt', 'nazwa')
                    ->label('Produkt'),
                    
                Tables\Filters\Filter::make('data_waznosci')
                    ->label('Data ważności')
                    ->form([
                        Forms\Components\DatePicker::make('waznosc_od')
                            ->label('Od daty'),
                        Forms\Components\DatePicker::make('waznosc_do')
                            ->label('Do daty'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['waznosc_od'],
                                fn ($query, $date) => $query->whereDate('data_waznosci', '>=', $date),
                            )
                            ->when(
                                $data['waznosc_do'],
                                fn ($query, $date) => $query->whereDate('data_waznosci', '<=', $date),
                            );
                    }),
                    
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
                Tables\Actions\ViewAction::make()
                    ->label('Podgląd'),
                Tables\Actions\EditAction::make()
                    ->label('Edytuj'),
                Tables\Actions\Action::make('wycofaj')
                    ->label('Wycofaj partię')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn ($record) => !in_array($record->status, [StatusPartii::WYDANA, StatusPartii::WYCOFANA]))
                    ->requiresConfirmation()
                    ->modalHeading('Wycofaj partię')
                    ->modalDescription('Czy na pewno chcesz wycofać tę partię? Ta akcja spowoduje usunięcie jej z magazynu.')
                    ->action(function ($record) {
                        $record->update(['status' => StatusPartii::WYCOFANA]);
                        
                        // Utwórz ruch magazynowy wycofania
                        \App\Models\RuchMagazynowy::create([
                            'typ_ruchu' => \App\Enums\TypRuchuMagazynowego::KOREKTA_MINUS,
                            'typ_towaru' => 'produkt',
                            'towar_id' => $record->produkt_id,
                            'numer_partii' => $record->numer_partii,
                            'ilosc' => $record->ilosc_wyprodukowana,
                            'jednostka' => 'szt',
                            'cena_jednostkowa' => 0,
                            'wartosc' => 0,
                            'data_ruchu' => now(),
                            'zrodlo_docelowe' => 'Wycofanie partii',
                            'uwagi' => 'Partia wycofana przez użytkownika',
                            'user_id' => auth()->id(),
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Partia wycofana')
                            ->body('Partia została wycofana z magazynu.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_produkcji', 'desc');
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', StatusPartii::W_MAGAZYNIE)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartias::route('/'),
            'create' => Pages\CreatePartia::route('/create'),
            'view' => Pages\ViewPartia::route('/{record}'),
            'edit' => Pages\EditPartia::route('/{record}/edit'),
        ];
    }
}