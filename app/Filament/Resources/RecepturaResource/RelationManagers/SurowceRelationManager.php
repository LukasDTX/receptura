<?php

namespace App\Filament\Resources\RecepturaResource\RelationManagers;

use App\Models\Surowiec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\JednostkaMiary;

class SurowceRelationManager extends RelationManager
{
    protected static string $relationship = 'surowce';

    protected static ?string $recordTitleAttribute = 'nazwa';
    
    protected static ?string $title = 'Surowce'; 
    
    protected static ?string $modelLabel = 'surowiec';
    
    protected static ?string $pluralModelLabel = 'surowce';
    
    protected function getTableDescription(): ?string
    {
        $record = $this->getOwnerRecord();
        $sumaProcentowa = 0;
        
        if ($record) {
            $meta = is_array($record->meta) ? $record->meta : (json_decode($record->meta, true) ?? []);
            $sumaProcentowa = $meta['suma_procentowa'] ?? 0;
        }
        
        $kolor = 'success';
        $informacja = 'Receptura jest kompletna (suma składników ≈ 100%)';
        
        if ($sumaProcentowa < 99.5) {
            $kolor = 'warning';
            $informacja = 'Uwaga: suma składników wynosi tylko ' . number_format($sumaProcentowa, 2) . '% (poniżej 100%)';
        } elseif ($sumaProcentowa > 100.5) {
            $kolor = 'danger';
            $informacja = 'Uwaga: suma składników wynosi aż ' . number_format($sumaProcentowa, 2) . '% (powyżej 100%)';
        } else {
            $informacja = 'Receptura jest kompletna - suma składników: ' . number_format($sumaProcentowa, 2) . '%';
        }
        
        return "
        <div class='mb-4'>Receptura jest tworzona dla 1kg produktu. Podaj ilości surowców potrzebne do wyprodukowania 1kg.</div>
        <div class='p-2 mb-2 rounded-md bg-{$kolor}-100 text-{$kolor}-700 border border-{$kolor}-200'>
            <div class='flex items-center'>
                <div class='flex-shrink-0'>
                    <svg class='h-5 w-5' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'>
                        <path fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd'></path>
                    </svg>
                </div>
                <div class='ml-3'>
                    <div class='text-sm font-medium'>{$informacja}</div>
                    <div class='mt-1 text-xs'>* Procenty są obliczone na podstawie wagi składników, zakładając że 1kg = 100%. Dla sztuk procent nie jest wyświetlany.</div>
                </div>
            </div>
        </div>
        ";
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('pivot.surowiec_id')
                    ->hiddenOn('create'),
                Forms\Components\Select::make('surowiec_id')
                    ->label('Surowiec')
                    ->options(Surowiec::pluck('nazwa', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('pivot.ilosc')
                    ->label('Ilość')
                    ->required()
                    ->numeric()
                    ->minValue(0.001)
                    ->default(1),
            ]);
    }

    public function table(Table $table): Table
    {
        $record = $this->getOwnerRecord();
        $sumaProcentowa = 0;
        
        if ($record) {
            $meta = $record->meta ?? [];
            $sumaProcentowa = $meta['suma_procentowa'] ?? 0;
        }
        
        $kolor = 'success';
        $informacja = '';
        
        if ($sumaProcentowa < 99.5) {
            $kolor = 'warning';
            $informacja = ' (za mało - składniki stanowią mniej niż 100% produktu)';
        } elseif ($sumaProcentowa > 100.5) {
            $kolor = 'danger';
            $informacja = ' (za dużo - składniki stanowią więcej niż 100% produktu)';
        }
        
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nazwa')
                    ->label('Nazwa surowca')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.ilosc')
                    ->label('Ilość')
                    ->formatStateUsing(function ($state, $record) {
                        // Formatowanie liczby
                        $formatted = is_numeric($state) ? (floor($state) == $state ? (int)$state : $state) : $state;
                        
                        // Użyj ->value aby otrzymać string z enum
                        $jednostka = $record->jednostka_miary instanceof \App\Enums\JednostkaMiary 
                            ? $record->jednostka_miary->value 
                            : $record->jednostka_miary;
                            
                        return $formatted . ' ' . $jednostka;
                    }),
                Tables\Columns\TextColumn::make('procent')
                    ->label('Procent')
                    ->state(function ($record) {
                        // Obliczenie procentu składnika w recepturze (na podstawie ilości w gramach)
                        try {
                            $ilosc = $record->pivot->ilosc ?? 0;
                            
                            // Przeliczenie ilości na gramy, w zależności od jednostki miary
                            $iloscWGramach = 0;
                            if ($record->jednostka_miary === 'g') {
                                $iloscWGramach = $ilosc;
                            } elseif ($record->jednostka_miary === 'kg') {
                                $iloscWGramach = $ilosc * 1000;
                            } elseif ($record->jednostka_miary === 'ml') {
                                // Dla uproszczenia zakładamy, że 1ml = 1g
                                $iloscWGramach = $ilosc;
                            } elseif ($record->jednostka_miary === 'l') {
                                // Dla uproszczenia zakładamy, że 1l = 1kg = 1000g
                                $iloscWGramach = $ilosc * 1000;
                            } else {
                                // Dla sztuk nie możemy obliczyć procentu wagowego
                                return 'n/d';
                            }
                            
                            // Obliczenie procentu (zakładamy, że receptura jest na 1kg = 1000g)
                            $procent = ($iloscWGramach / 1000) * 100;
                            
                            // Formatowanie - do dwóch miejsc po przecinku i dodanie znaku %
                            return number_format($procent, 2) . '%';
                        } catch (\Exception $e) {
                            // Dodajemy obsługę błędów, aby zobaczyć, co może pójść nie tak
                            return 'Błąd: ' . $e->getMessage();
                        }
                    })
                    ->tooltip('Procentowy udział składnika w 1kg produktu'),
                Tables\Columns\TextColumn::make('cena_jednostkowa')
                    ->label('Cena jedn.')
                    ->money('pln')
                    ->label('Cena 1 g')
                    ->formatStateUsing(function ($state, $record) {
                        // Formatowanie liczby - ukrycie części dziesiętnej jeśli są same zera
                        return is_numeric($state) ? number_format($state, 2) : $state;
                    }),
                Tables\Columns\TextColumn::make('koszt_calkowity')
                    ->label('Koszt całkowity')
                    ->money('pln')
                    ->getStateUsing(function ($record) {
                        return $record->cena_jednostkowa * $record->pivot->ilosc;
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Dodaj istniejący surowiec')
                    ->modalHeading('Dodaj surowiec do receptury')
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Surowiec')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) {
                                    $set('jednostka', '');
                                    return;
                                }
                                
                                $surowiec = Surowiec::find($state);
                                if ($surowiec) {
                                    $set('jednostka', $surowiec->jednostka_miary);
                                }
                            })
                            ->required(),
                        Forms\Components\Hidden::make('jednostka'),
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość')
                            ->required()
                            ->numeric()
                            ->minValue(0.001)
                            ->default(1)
                            ->suffix(fn (\Filament\Forms\Get $get) => $get('jednostka'))
                            ->helperText('Podaj ilość potrzebną do produkcji 1kg.'),
                    ])
                    ->after(function (RelationManager $livewire): void {
                        // Odśwież relacje, aby mieć pewność, że mamy najnowsze dane
                        $livewire->getOwnerRecord()->refresh();
                        $livewire->getOwnerRecord()->load('surowce');
                        
                        // Aktualizujemy koszt całkowity receptury po dodaniu surowca
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
                Tables\Actions\Action::make('createSurowiec')
                    ->label('Utwórz nowy surowiec')
                    ->modalHeading('Utwórz nowy surowiec')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('nazwa')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('kod')
                            ->required()
                            ->unique(Surowiec::class)
                            ->maxLength(255),
                        Forms\Components\Textarea::make('opis')
                            ->maxLength(65535),
                        Forms\Components\TextInput::make('cena_jednostkowa')
                            ->required()
                            ->numeric()
                            ->prefix('PLN')
                            ->label('Cena 1 kg')
                            ->default(0),
                        Forms\Components\Select::make('jednostka_miary')
                            ->options(JednostkaMiary::class)
                            ->default(JednostkaMiary::G)
                            ->required(),
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość do dodania')
                            ->required()
                            ->numeric()
                            ->minValue(0.001)
                            ->default(1)
                            ->helperText('Podaj ilość potrzebną do produkcji 1kg.'),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        // Oddzielamy dane surowca od ilości
                        $ilosc = $data['ilosc'];
                        unset($data['ilosc']);
                        
                        // Tworzymy nowy surowiec
                        $surowiec = Surowiec::create($data);
                        
                        // Dodajemy surowiec do receptury z określoną ilością
                        $livewire->getOwnerRecord()->surowce()->attach($surowiec->id, ['ilosc' => $ilosc]);
                        
                        // Odśwież relacje, aby mieć pewność, że mamy najnowsze dane
                        $livewire->getOwnerRecord()->refresh();
                        $livewire->getOwnerRecord()->load('surowce');
                        
                        // Aktualizujemy koszt całkowity receptury
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj ilość')
                    ->modalHeading('Edytuj ilość surowca')
                    ->form(function ($record) {
                        return [
                            Forms\Components\Grid::make()
                                ->schema([
                                    Forms\Components\TextInput::make('ilosc')
                                        ->label('Ilość')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0.001)
                                        // Ukrywamy część dziesiętną jeśli wszystkie cyfry po przecinku to zera
                                        ->formatStateUsing(fn ($state) => is_numeric($state) ? (floor($state) == $state ? (int)$state : $state) : $state)
                                        ->suffix($record->jednostka_miary),
                                    
                                    Forms\Components\Placeholder::make('koszt')
                                        ->label('Koszt')
                                        ->content(function ($get) use ($record) {
                                            $ilosc = $get('ilosc');
                                            if (!is_numeric($ilosc)) return '0 PLN';
                                            
                                            return number_format($record->cena_jednostkowa * $ilosc, 2) . ' PLN';
                                        })
                                        ->extraAttributes(['class' => 'text-right font-medium']),
                                ]),
                            
                            Forms\Components\Placeholder::make('info')
                                ->label('')
                                ->content(function ($get) use ($record) {
                                    return 'Cena jednostkowa: ' . number_format($record->cena_jednostkowa, 2) . ' PLN/' . $record->jednostka_miary;
                                })
                                ->extraAttributes(['class' => 'text-sm text-gray-500']),
                        ];
                    })
                    ->fillForm(function ($record) {
                        // Pobierz aktualną ilość z tabeli pivot
                        return [
                            'ilosc' => $record->pivot->ilosc,
                        ];
                    })
                    ->using(function (array $data, $record) {
                        // Zaktualizuj dane w tabeli pivot bezpośrednio
                        $record->pivot->update([
                            'ilosc' => $data['ilosc'],
                        ]);
                        
                        return $record;
                    })
                    ->after(function (RelationManager $livewire): void {
                        // Aktualizujemy koszt całkowity receptury po zmianie ilości
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
                Tables\Actions\DetachAction::make()
                    ->label('Usuń')
                    ->modalHeading('Usuń surowiec z receptury')
                    ->after(function (RelationManager $livewire): void {
                        // Aktualizujemy koszt całkowity receptury po usunięciu surowca
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Usuń zaznaczone')
                        ->after(function (RelationManager $livewire): void {
                            // Aktualizujemy koszt całkowity receptury po masowym usunięciu surowców
                            $livewire->getOwnerRecord()->obliczKosztCalkowity();
                        }),
                ]),
            ]);
    }
}