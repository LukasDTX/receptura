<?php
// app/Filament/Resources/PartiaSurowcaResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\PartiaSurowcaResource\Pages;
use App\Models\PartiaSurowca;
use App\Models\Surowiec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class PartiaSurowcaResource extends Resource
{
    protected static ?string $model = PartiaSurowca::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    
    protected static ?string $navigationLabel = 'Partie surowców';
    
    protected static ?string $modelLabel = 'Partia surowca';
    
    protected static ?string $pluralModelLabel = 'Partie surowców';
    
    protected static ?string $navigationGroup = 'Magazyn Surowców';
    
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\TextInput::make('numer_partii')
                            ->label('Numer partii')
                            ->default(fn () => PartiaSurowca::generateNumerPartii())
                            ->required()
                            ->unique(ignorable: fn ($record) => $record)
                            ->readonly()
                            ->helperText('Automatycznie generowany'),
                            
                        Forms\Components\Select::make('surowiec_id')
                            ->label('Surowiec')
                            ->options(Surowiec::pluck('nazwa', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('numer_partii_dostawcy')
                            ->label('Numer partii dostawcy')
                            ->helperText('Numer partii od dostawcy (opcjonalny)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dane o masie i opakowaniu')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('masa_brutto')
                                    ->label('Masa brutto (kg)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->suffix('kg')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        $masaNetto = $get('masa_netto');
                                        if ($masaNetto && $state && $masaNetto > $state) {
                                            $set('masa_netto', $state);
                                        }
                                    }),
                                    
                                Forms\Components\TextInput::make('masa_netto')
                                    ->label('Masa netto (kg)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->suffix('kg')
                                    ->reactive()
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $masaBrutto = $get('masa_brutto');
                                            if ($masaBrutto && $value > $masaBrutto) {
                                                $fail('Masa netto nie może być większa niż masa brutto.');
                                            }
                                        },
                                    ]),
                                    
                                Forms\Components\Placeholder::make('masa_opakowania')
                                    ->label('Masa opakowania')
                                    ->content(function (Get $get) {
                                        $brutto = $get('masa_brutto');
                                        $netto = $get('masa_netto');
                                        if ($brutto && $netto) {
                                            return number_format($brutto - $netto, 3) . ' kg';
                                        }
                                        return '0 kg';
                                    })
                                    ->extraAttributes(['class' => 'text-sm text-gray-600']),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('typ_opakowania')
                                    ->label('Typ opakowania')
                                    ->options([
                                        'worek_5kg' => 'Worek 5kg',
                                        'worek_10kg' => 'Worek 10kg', 
                                        'worek_20kg' => 'Worek 20kg',
                                        'worek_25kg' => 'Worek 25kg',
                                        'worek_50kg' => 'Worek 50kg',
                                        'pojemnik_plastikowy' => 'Pojemnik plastikowy',
                                        'beczka_plastikowa' => 'Beczka plastikowa',
                                        'beczka_metalowa' => 'Beczka metalowa',
                                        'karton' => 'Karton',
                                        'big_bag' => 'Big Bag',
                                        'inne' => 'Inne',
                                    ])
                                    ->required()
                                    ->searchable(),
                                    
                                Forms\Components\TextInput::make('cena_za_kg')
                                    ->label('Cena za kg (PLN)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('PLN')
                                    ->reactive(),
                            ]),
                            
                        Forms\Components\Placeholder::make('wartosc_calkowita')
                            ->label('Wartość całkowita partii')
                            ->content(function (Get $get) {
                                $masaNetto = $get('masa_netto');
                                $cenaZaKg = $get('cena_za_kg');
                                if ($masaNetto && $cenaZaKg) {
                                    $wartosc = $masaNetto * $cenaZaKg;
                                    return number_format($wartosc, 2) . ' PLN';
                                }
                                return '0.00 PLN';
                            })
                            ->extraAttributes(['class' => 'text-lg font-semibold text-green-600']),
                    ]),

                Forms\Components\Section::make('Daty i lokalizacja')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('data_przyjecia')
                                    ->label('Data przyjęcia')
                                    ->required()
                                    ->default(now()),
                                    
                                Forms\Components\DatePicker::make('data_waznosci')
                                    ->label('Data ważności')
                                    ->minDate(fn (Get $get) => $get('data_przyjecia'))
                                    ->helperText('Pozostaw puste jeśli surowiec nie ma terminu ważności'),
                            ]),
                            
                        Forms\Components\TextInput::make('lokalizacja_magazyn')
                            ->label('Lokalizacja w magazynie')
                            ->helperText('np. Regał A1, Sektor B, Strefa chłodnicza')
                            ->placeholder('A1-P2-L3'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Status i uwagi')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'nowa' => 'Nowa (nieużywana)',
                                'otwarta' => 'Otwarta (częściowo użyta)',
                                'zuzyta' => 'Zużyta',
                                'wycofana' => 'Wycofana',
                            ])
                            ->default('nowa')
                            ->required()
                            ->disabled(fn ($context) => $context === 'create'),
                            
                        Forms\Components\Textarea::make('uwagi')
                            ->label('Uwagi')
                            ->placeholder('Dodatkowe informacje o partii, stanie opakowania, itp.')
                            ->rows(3),
                    ])
                    ->columns(1),

                // Sekcja dla trybu edycji - informacje o wykorzystaniu
                Forms\Components\Section::make('Informacje o wykorzystaniu')
                    ->schema([
                        Forms\Components\Placeholder::make('masa_pozostala_info')
                            ->label('Masa pozostała')
                            ->content(function ($record) {
                                if (!$record) return 'Będzie równa masie netto po zapisaniu';
                                
                                $pozostala = $record->masa_pozostala;
                                $procent = $record->masa_netto > 0 ? ($pozostala / $record->masa_netto) * 100 : 0;
                                
                                return number_format($pozostala, 3) . ' kg (' . number_format($procent, 1) . '%)';
                            })
                            ->extraAttributes(function ($record) {
                                if (!$record) return [];
                                
                                $procent = $record->masa_netto > 0 ? ($record->masa_pozostala / $record->masa_netto) * 100 : 0;
                                $class = 'text-lg font-semibold ';
                                
                                if ($procent > 75) $class .= 'text-green-600';
                                elseif ($procent > 25) $class .= 'text-yellow-600';
                                else $class .= 'text-red-600';
                                
                                return ['class' => $class];
                            }),
                            
                        Forms\Components\Placeholder::make('historia_ruchow')
                            ->label('Historia ruchów')
                            ->content(function ($record) {
                                if (!$record) return 'Historia będzie dostępna po zapisaniu partii';
                                
                                $ruchy = $record->ruchy()->latest()->take(5)->get();
                                if ($ruchy->isEmpty()) return 'Brak ruchów';
                                
                                $html = '<div class="space-y-2">';
                                foreach ($ruchy as $ruch) {
                                    $typ = match($ruch->typ_ruchu) {
                                        'przyjecie' => '📥 Przyjęcie',
                                        'wydanie_do_produkcji' => '📤 Wydanie do produkcji',
                                        'przeniesienie' => '🔄 Przeniesienie',
                                        'korekta' => '✏️ Korekta',
                                        'wycofanie' => '🗑️ Wycofanie',
                                        default => '❓ ' . $ruch->typ_ruchu,
                                    };
                                    
                                    $masa = $ruch->masa >= 0 ? '+' . $ruch->masa : $ruch->masa;
                                    $html .= '<div class="text-sm">';
                                    $html .= '<span class="font-medium">' . $typ . '</span> ';
                                    $html .= '<span class="text-gray-600">' . $masa . 'kg</span> ';
                                    $html .= '<span class="text-xs text-gray-400">(' . $ruch->data_ruchu->format('d.m.Y') . ')</span>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->columns(2)
                    ->visibleOn('edit')
                    ->collapsed()
                    ->collapsible(),
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
                    
                Tables\Columns\TextColumn::make('surowiec.nazwa')
                    ->label('Surowiec')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('masa_netto')
                    ->label('Masa netto')
                    ->suffix(' kg')
                    ->sortable()
                    ->alignEnd(),
                    
                Tables\Columns\TextColumn::make('masa_pozostala')
                    ->label('Pozostało')
                    ->suffix(' kg')
                    ->sortable()
                    ->alignEnd()
                    ->color(function ($record) {
                        if ($record->masa_netto <= 0) return 'gray';
                        $procent = ($record->masa_pozostala / $record->masa_netto) * 100;
                        
                        if ($procent > 75) return 'success';
                        if ($procent > 25) return 'warning';
                        return 'danger';
                    })
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('procent_pozostaly')
                    ->label('% pozostały')
                    ->getStateUsing(function ($record) {
                        if ($record->masa_netto <= 0) return '0%';
                        return number_format(($record->masa_pozostala / $record->masa_netto) * 100, 1) . '%';
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->masa_netto <= 0) return 'gray';
                        $procent = ($record->masa_pozostala / $record->masa_netto) * 100;
                        
                        if ($procent > 75) return 'success';
                        if ($procent > 25) return 'warning';
                        return 'danger';
                    }),
                    
                Tables\Columns\TextColumn::make('typ_opakowania')
                    ->label('Opakowanie')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'worek_5kg' => 'Worek 5kg',
                            'worek_10kg' => 'Worek 10kg',
                            'worek_20kg' => 'Worek 20kg',
                            'worek_25kg' => 'Worek 25kg',
                            'worek_50kg' => 'Worek 50kg',
                            'pojemnik_plastikowy' => 'Pojemnik',
                            'beczka_plastikowa' => 'Beczka (P)',
                            'beczka_metalowa' => 'Beczka (M)',
                            'big_bag' => 'Big Bag',
                            default => ucfirst($state),
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('data_przyjecia')
                    ->label('Data przyjęcia')
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
                    })
                    ->placeholder('Brak'),
                    
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'nowa' => 'Nowa',
                        'otwarta' => 'Otwarta',
                        'zuzyta' => 'Zużyta',
                        'wycofana' => 'Wycofana',
                    ])
                    ->disabled(fn ($record) => $record->status === 'zuzyta'),
                    
                Tables\Columns\TextColumn::make('lokalizacja_magazyn')
                    ->label('Lokalizacja')
                    ->searchable()
                    ->placeholder('Nie określono')
                    ->limit(15),
                    
                Tables\Columns\TextColumn::make('wartosc')
                    ->label('Wartość')
                    ->getStateUsing(fn ($record) => $record->masa_pozostala * $record->cena_za_kg)
                    ->money('pln')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('surowiec_id')
                    ->relationship('surowiec', 'nazwa')
                    ->label('Surowiec')
                    ->preload()
                    ->searchable(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'nowa' => 'Nowe',
                        'otwarta' => 'Otwarte',
                        'zuzyta' => 'Zużyte',
                        'wycofana' => 'Wycofane',
                    ])
                    ->label('Status'),
                    
                Tables\Filters\SelectFilter::make('typ_opakowania')
                    ->options([
                        'worek_5kg' => 'Worek 5kg',
                        'worek_10kg' => 'Worek 10kg',
                        'worek_20kg' => 'Worek 20kg',
                        'worek_25kg' => 'Worek 25kg',
                        'worek_50kg' => 'Worek 50kg',
                        'pojemnik_plastikowy' => 'Pojemnik plastikowy',
                        'beczka_plastikowa' => 'Beczka plastikowa',
                        'beczka_metalowa' => 'Beczka metalowa',
                        'big_bag' => 'Big Bag',
                    ])
                    ->label('Typ opakowania'),
                    
                Tables\Filters\Filter::make('dostepne')
                    ->label('Tylko dostępne')
                    ->query(fn ($query) => $query->where('masa_pozostala', '>', 0)
                                                  ->whereIn('status', ['nowa', 'otwarta']))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('przeterminowane')
                    ->label('Przeterminowane')
                    ->query(fn ($query) => $query->where('data_waznosci', '<', now()))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('wkrotce_przeterminowane')
                    ->label('Wkrótce przeterminowane (30 dni)')
                    ->query(fn ($query) => $query->whereBetween('data_waznosci', [now(), now()->addDays(30)]))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj'),
                    
                Tables\Actions\Action::make('historia_ruchow')
                    ->label('Historia ruchów')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->modalHeading(fn ($record) => 'Historia ruchów - ' . $record->numer_partii)
                    ->modalContent(function ($record) {
                        $ruchy = $record->ruchy()->with('user', 'zlecenie')->latest()->get();
                        
                        if ($ruchy->isEmpty()) {
                            return view('filament.modals.empty-content', ['message' => 'Brak ruchów dla tej partii']);
                        }
                        
                        return view('filament.modals.historia-ruchow', compact('ruchy'));
                    })
                    ->modalCancelActionLabel('Zamknij')
                    ->modalSubmitAction(false),
                    
                Tables\Actions\Action::make('korekta_masy')
                    ->label('Korekta masy')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->status, ['nowa', 'otwarta']))
                    ->form([
                        Forms\Components\TextInput::make('nowa_masa')
                            ->label('Nowa masa pozostała (kg)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('kg'),
                        Forms\Components\Textarea::make('uwagi')
                            ->label('Uzasadnienie korekty')
                            ->required()
                            ->placeholder('Powód korekty masy...'),
                    ])
                    ->action(function (array $data, $record) {
                        $staraMasa = $record->masa_pozostala;
                        $nowaMasa = $data['nowa_masa'];
                        $roznica = $nowaMasa - $staraMasa;
                        
                        // Utwórz ruch korekty
                        \App\Models\RuchSurowca::create([
                            'typ_ruchu' => 'korekta',
                            'partia_surowca_id' => $record->id,
                            'masa' => $roznica,
                            'masa_przed' => $staraMasa,
                            'masa_po' => $nowaMasa,
                            'skad' => 'korekta',
                            'dokad' => 'korekta',
                            'data_ruchu' => now(),
                            'uwagi' => $data['uwagi'],
                            'user_id' => auth()->id(),
                        ]);
                        
                        // Aktualizuj masę w partii
                        $record->update(['masa_pozostala' => $nowaMasa]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Korekta wykonana')
                            ->body("Masa została zmieniona z {$staraMasa}kg na {$nowaMasa}kg")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Korekta masy partii')
                    ->modalDescription('Wykonaj korektę masy pozostałej w partii.')
                    ->modalSubmitActionLabel('Wykonaj korektę'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('wycofaj')
                        ->label('Wycofaj partie')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (in_array($record->status, ['nowa', 'otwarta'])) {
                                    $record->update(['status' => 'wycofana']);
                                    
                                    \App\Models\RuchSurowca::create([
                                        'typ_ruchu' => 'wycofanie',
                                        'partia_surowca_id' => $record->id,
                                        'masa' => -$record->masa_pozostala,
                                        'masa_przed' => $record->masa_pozostala,
                                        'masa_po' => 0,
                                        'skad' => 'magazyn',
                                        'dokad' => 'wycofanie',
                                        'data_ruchu' => now(),
                                        'uwagi' => 'Masowe wycofanie partii',
                                        'user_id' => auth()->id(),
                                    ]);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('data_przyjecia', 'desc')
            ->poll('30s');
    }
    
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['nowa', 'otwarta'])
                                   ->where('masa_pozostala', '>', 0)
                                   ->count();
        return $count > 0 ? $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartieSurowcow::route('/'),
            'create' => Pages\CreatePartiaSurowca::route('/create'),
            'edit' => Pages\EditPartiaSurowca::route('/{record}/edit'),
        ];
    }
}