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
                    ->label('Kod receptury')
                    ->default(function () {
                        // Kompatybilna wersja dla PostgreSQL i MySQL
                        try {
                            // Pobierz wszystkie kody zaczynające się od 'RCP-'
                            $kody = \App\Models\Receptura::where('kod', 'LIKE', 'RCP-%')
                                ->pluck('kod')
                                ->toArray();
                            
                            $najwyzszyNumer = 0;
                            
                            foreach ($kody as $kod) {
                                // Wyciągnij numer z kodu (po 'RCP-')
                                if (preg_match('/^RCP-(\d+)$/', $kod, $matches)) {
                                    $numer = (int) $matches[1];
                                    if ($numer > $najwyzszyNumer) {
                                        $najwyzszyNumer = $numer;
                                    }
                                }
                            }
                            
                            $nowyNumer = $najwyzszyNumer + 1;
                            return 'RCP-' . $nowyNumer;
                            
                        } catch (\Exception $e) {
                            // Fallback - użyj count + 1
                            $count = \App\Models\Receptura::count();
                            return 'RCP-' . ($count + 1);
                        }
                    })
                    ->required()
                    ->unique(ignorable: fn ($record) => $record)
                    ->readonly()
                    ->helperText('Kod generowany automatycznie w formacie RCP-numer'),
                Forms\Components\Select::make('typ_receptury')
                    ->label('Typ receptury')
                    ->options([
                        'gramy' => 'Liczony w gramach (1kg = 1000g)',
                        'mililitry' => 'Liczony w mililitrach (1l = 1000ml)',
                    ])
                    ->default('gramy')
                    ->required()
                    ->disabled(fn ($context) => $context === 'edit')
                    ->dehydrated()
                    ->helperText(function ($context) {
                        if ($context === 'edit') {
                            return 'Typ receptury można zmienić tylko podczas tworzenia nowej receptury.';
                        }
                        return 'Określa czy receptura jest tworzona na podstawie wagi (gramy) czy objętości (mililitry). Nie można zmienić po utworzeniu.';
                    }),
                Forms\Components\Placeholder::make('typ_receptury_info')
                    ->label('Typ receptury')
                    ->content(function ($record) {
                        if (!$record) return '';
                        
                        $typ = $record->typ_receptury;
                        if ($typ === \App\Enums\TypReceptury::GRAMY) {
                            return '📏 Receptura liczona w gramach (1kg = 1000g)';
                        } else {
                            return '🥤 Receptura liczona w mililitrach (1l = 1000ml)';
                        }
                    })
                    ->visibleOn('edit')
                    ->extraAttributes(['class' => 'text-blue-600 font-medium']),
                Forms\Components\Textarea::make('opis')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('koszt_calkowity')
                    ->disabled()
                    ->dehydrated(false)
                    ->prefix('PLN')
                    ->numeric()
                    ->label('Koszt całkowity')
                    ->helperText(function ($record) {
                        if (!$record) return 'Koszt wytworzenia według tej receptury.';
                        
                        $jednostka = $record->typ_receptury === \App\Enums\TypReceptury::GRAMY ? '1kg' : '1l';
                        return "Koszt wytworzenia {$jednostka} produktu według tej receptury.";
                    }),
                    
                Forms\Components\Placeholder::make('suma_procentowa')
                    ->label('Suma procentowa składników')
                    ->content(function ($record) {
                        if (!$record) return 'Obliczana po zapisaniu receptury';
                        
                        try {
                            // Pobierz świeżą instancję rekordu z relacjami
                            $freshRecord = \App\Models\Receptura::with('surowce')->find($record->id);
                            
                            if (!$freshRecord) {
                                return 'Nie można pobrać danych receptury';
                            }
                            
                            // Sprawdź meta dane
                            $meta = [];
                            if (is_array($freshRecord->meta)) {
                                $meta = $freshRecord->meta;
                            } elseif (is_string($freshRecord->meta)) {
                                $meta = json_decode($freshRecord->meta, true) ?: [];
                            }
                            
                            $sumaProcentowa = $meta['suma_procentowa'] ?? 0;
                            
                            // Jeśli suma jest 0, spróbuj przeliczyć na nowo
                            if ($sumaProcentowa == 0 && $freshRecord->surowce->count() > 0) {
                                $freshRecord->obliczKosztCalkowity();
                                $freshRecord->refresh();
                                
                                $meta = is_array($freshRecord->meta) ? $freshRecord->meta : (json_decode($freshRecord->meta, true) ?: []);
                                $sumaProcentowa = $meta['suma_procentowa'] ?? 0;
                            }
                            
                            $jednostka = $freshRecord->typ_receptury === \App\Enums\TypReceptury::GRAMY ? '1kg' : '1l';
                            
                            // Określ kolor i informację na podstawie wartości
                            $kolorHex = '#10B981'; // zielony
                            $informacja = '';
                            
                            if ($sumaProcentowa < 99.5) {
                                $kolorHex = '#FBBF24'; // żółty
                                $informacja = " (za mało - składniki stanowią mniej niż 100% {$jednostka})";
                            } elseif ($sumaProcentowa > 100.5) {
                                $kolorHex = '#EF4444'; // czerwony
                                $informacja = " (za dużo - składniki stanowią więcej niż 100% {$jednostka})";
                            }
                            
                            return new \Illuminate\Support\HtmlString(
                                '<div style="color: ' . $kolorHex . '; font-weight: 500; font-size: 16px;">' . 
                                number_format($sumaProcentowa, 2) . '%' . 
                                $informacja . 
                                '</div>' .
                                '<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Dla ' . $jednostka . ' produktu</div>'
                            );
                            
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Błąd podczas wyświetlania sumy procentowej: ' . $e->getMessage(), [
                                'receptura_id' => $record->id ?? null,
                                'exception' => $e,
                            ]);
                            
                            return 'Błąd podczas obliczania: ' . $e->getMessage();
                        }
                    })
                    ->extraAttributes(['class' => 'font-bold'])
                    ->columnSpanFull(),
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
                Tables\Columns\SelectColumn::make('typ_receptury')
                    ->label('Typ')
                    ->options([
                        'gramy' => 'Gramy',
                        'mililitry' => 'Mililitry',
                    ])
                    ->disabled()
                    ->tooltip(function ($record) {
                        return $record->typ_receptury === \App\Enums\TypReceptury::GRAMY 
                            ? 'Receptura liczona w gramach (1kg = 1000g)' 
                            : 'Receptura liczona w mililitrach (1l = 1000ml)';
                    }),
                Tables\Columns\TextColumn::make('koszt_calkowity')
                    ->label('Koszt całkowity')
                    ->money('pln')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('Całkowity koszt produkcji produktu'),
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
                Tables\Filters\SelectFilter::make('typ_receptury')
                    ->label('Typ receptury')
                    ->options([
                        'gramy' => 'Gramy',
                        'mililitry' => 'Mililitry',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil'),
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