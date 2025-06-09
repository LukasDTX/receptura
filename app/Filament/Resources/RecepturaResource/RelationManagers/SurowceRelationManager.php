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
        $typReceptury = $record->typ_receptury ?? \App\Enums\TypReceptury::GRAMY;
        $jednostka = $typReceptury === \App\Enums\TypReceptury::GRAMY ? '1kg' : '1l';
        
        if ($record) {
            $meta = is_array($record->meta) ? $record->meta : (json_decode($record->meta, true) ?? []);
            $sumaProcentowa = $meta['suma_procentowa'] ?? 0;
        }
        
        $kolor = 'success';
        $informacja = "Receptura jest kompletna (suma składników ≈ 100% dla {$jednostka})";
        
        if ($sumaProcentowa < 99.5) {
            $kolor = 'warning';
            $informacja = "Uwaga: suma składników wynosi tylko " . number_format($sumaProcentowa, 2) . "% (poniżej 100% dla {$jednostka})";
        } elseif ($sumaProcentowa > 100.5) {
            $kolor = 'danger';
            $informacja = "Uwaga: suma składników wynosi aż " . number_format($sumaProcentowa, 2) . "% (powyżej 100% dla {$jednostka})";
        } else {
            $informacja = "Receptura jest kompletna - suma składników: " . number_format($sumaProcentowa, 2) . "% dla {$jednostka}";
        }
        
        $typOpis = $typReceptury === \App\Enums\TypReceptury::GRAMY 
            ? 'liczonej w gramach' 
            : 'liczonej w mililitrach';
        
        return "
        <div class='mb-4'>Receptura jest tworzona dla {$jednostka} produktu ({$typOpis}). Podaj ilości surowców potrzebne do wyprodukowania {$jednostka}.</div>
        <div class='p-2 mb-2 rounded-md bg-{$kolor}-100 text-{$kolor}-700 border border-{$kolor}-200'>
            <div class='flex items-center'>
                <div class='flex-shrink-0'>
                    <svg class='h-5 w-5' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'>
                        <path fill-rule='evenodd' d='M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z' clip-rule='evenodd'></path>
                    </svg>
                </div>
                <div class='ml-3'>
                    <div class='text-sm font-medium'>{$informacja}</div>
                    <div class='mt-1 text-xs'>* Procenty są obliczone na podstawie {$typOpis}, zakładając że {$jednostka} = 100%. Dla sztuk procent nie jest wyświetlany.</div>
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
                    ->options(function () {
                        $receptura = $this->getOwnerRecord();
                        
                        if (!$receptura || !$receptura->typ_receptury) {
                            // Fallback - pokaż wszystkie surowce
                            return \App\Models\Surowiec::pluck('nazwa', 'id');
                        }
                        
                        // Filtruj surowce według typu receptury
                        if ($receptura->typ_receptury === \App\Enums\TypReceptury::GRAMY) {
                            $surowce = \App\Models\Surowiec::whereIn('jednostka_miary', ['g', 'kg'])->get();
                        } elseif ($receptura->typ_receptury === \App\Enums\TypReceptury::MILILITRY) {
                            $surowce = \App\Models\Surowiec::whereIn('jednostka_miary', ['ml', 'l'])->get();
                        } else {
                            $surowce = \App\Models\Surowiec::all();
                        }
                        
                        return $surowce->pluck('nazwa', 'id');
                    })
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
                        
                        // Jednostka z enum
                        $jednostka = $record->jednostka_miary instanceof \App\Enums\JednostkaMiary 
                            ? $record->jednostka_miary->value 
                            : $record->jednostka_miary;
                            
                        return $formatted . ' ' . $jednostka;
                    }),
                Tables\Columns\TextColumn::make('procent')
                    ->label('Procent')
                    ->state(function ($record) {
                        try {
                            $ilosc = $record->pivot->ilosc ?? 0;
                            
                            // Przeliczenie ilości na jednostki bazowe (zawsze 1000)
                            $iloscWBazowejJednostce = 0;
                            $jednostka = $record->jednostka_miary instanceof \App\Enums\JednostkaMiary 
                                ? $record->jednostka_miary->value 
                                : $record->jednostka_miary;
                                
                            if ($jednostka === 'g' || $jednostka === 'ml') {
                                $iloscWBazowejJednostce = $ilosc;
                            } elseif ($jednostka === 'kg' || $jednostka === 'l') {
                                $iloscWBazowejJednostce = $ilosc * 1000;
                            } else {
                                // Dla sztuk nie możemy obliczyć procentu wagowego/objętościowego
                                return 'n/d';
                            }
                            
                            // Obliczenie procentu (zawsze dla 1000 jednostek bazowych)
                            $procent = ($iloscWBazowejJednostce / 1000) * 100;
                            
                            return number_format($procent, 2) . '%';
                        } catch (\Exception $e) {
                            return 'Błąd: ' . $e->getMessage();
                        }
                    })
                    ->tooltip('Procentowy udział składnika w recepturze'),
                Tables\Columns\TextColumn::make('cena_jednostkowa')
                    ->label('Cena jedn.')
                    ->money('pln')
                    ->formatStateUsing(function ($state, $record) {
                        return number_format($state, 2);
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
                    ->recordSelectOptionsQuery(function () {
                        $receptura = $this->getOwnerRecord();
                        
                        if (!$receptura || !$receptura->typ_receptury) {
                            return \App\Models\Surowiec::query();
                        }
                        
                        // Filtruj surowce według typu receptury
                        if ($receptura->typ_receptury === \App\Enums\TypReceptury::GRAMY) {
                            return \App\Models\Surowiec::whereIn('jednostka_miary', ['g', 'kg']);
                        } elseif ($receptura->typ_receptury === \App\Enums\TypReceptury::MILILITRY) {
                            return \App\Models\Surowiec::whereIn('jednostka_miary', ['ml', 'l']);
                        }
                        
                        return \App\Models\Surowiec::query();
                    })
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Surowiec')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) {
                                    $set('jednostka', '');
                                    return;
                                }
                                
                                $surowiec = \App\Models\Surowiec::find($state);
                                if ($surowiec) {
                                    $jednostka = $surowiec->jednostka_miary instanceof \App\Enums\JednostkaMiary 
                                        ? $surowiec->jednostka_miary->value 
                                        : $surowiec->jednostka_miary;
                                    $set('jednostka', $jednostka);
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
                            ->helperText(function () {
                                $receptura = $this->getOwnerRecord();
                                $jednostka = $receptura->typ_receptury === \App\Enums\TypReceptury::GRAMY ? '1kg' : '1l';
                                return "Podaj ilość potrzebną do produkcji {$jednostka}.";
                            }),
                    ])
                    ->after(function (RelationManager $livewire): void {
                        $livewire->getOwnerRecord()->refresh();
                        $livewire->getOwnerRecord()->load('surowce');
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
                Tables\Actions\Action::make('createSurowiec')
                    ->label('Utwórz nowy surowiec')
                    ->modalHeading('Utwórz nowy surowiec')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form(function () {
                        $receptura = $this->getOwnerRecord();
                        $dozwoloneJednostki = [];
                        
                        if ($receptura->typ_receptury === \App\Enums\TypReceptury::GRAMY) {
                            $dozwoloneJednostki = [
                                \App\Enums\JednostkaMiary::G,
                                // \App\Enums\JednostkaMiary::KG,
                            ];
                        } elseif ($receptura->typ_receptury === \App\Enums\TypReceptury::MILILITRY) {
                            $dozwoloneJednostki = [
                                \App\Enums\JednostkaMiary::ML,
                                // \App\Enums\JednostkaMiary::L,
                            ];
                        } else {
                            $dozwoloneJednostki = \App\Enums\JednostkaMiary::cases();
                        }
                        
                        return [
                            Forms\Components\TextInput::make('nazwa')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('nazwa_naukowa')
                                ->label('Nazwa naukowa')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('kod')
                                ->required()
                                ->unique(\App\Models\Surowiec::class)
                                ->maxLength(255),
                            Forms\Components\Textarea::make('opis')
                                ->maxLength(65535),
                            Forms\Components\TextInput::make('cena_jednostkowa')
                                ->required()
                                ->numeric()
                                ->prefix('PLN')
                                ->label('Cena jednostkowa')
                                ->default(0),
                            Forms\Components\Select::make('jednostka_miary')
                                ->options(collect($dozwoloneJednostki)->mapWithKeys(fn($jednostka) => [
                                    $jednostka->value => $jednostka->label()
                                ])->toArray())
                                ->default($dozwoloneJednostki[0]->value ?? 'g')
                                ->required(),
                            Forms\Components\TextInput::make('ilosc')
                                ->label('Ilość do dodania')
                                ->required()
                                ->numeric()
                                ->minValue(0.001)
                                ->default(1)
                                ->helperText(function () {
                                    $receptura = $this->getOwnerRecord();
                                    $jednostka = $receptura->typ_receptury === \App\Enums\TypReceptury::GRAMY ? '1kg' : '1l';
                                    return "Podaj ilość potrzebną do produkcji {$jednostka}.";
                                }),
                        ];
                    })
                    ->action(function (array $data, RelationManager $livewire): void {
                        $ilosc = $data['ilosc'];
                        unset($data['ilosc']);
                        
                        $surowiec = \App\Models\Surowiec::create($data);
                        $livewire->getOwnerRecord()->surowce()->attach($surowiec->id, ['ilosc' => $ilosc]);
                        
                        $livewire->getOwnerRecord()->refresh();
                        $livewire->getOwnerRecord()->load('surowce');
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj ilość')
                    ->modalHeading('Edytuj ilość surowca')
                    ->form(function ($record) {
                        $jednostka = $record->jednostka_miary instanceof \App\Enums\JednostkaMiary 
                            ? $record->jednostka_miary->value 
                            : $record->jednostka_miary;
                            
                        return [
                            Forms\Components\Grid::make()
                                ->schema([
                                    Forms\Components\TextInput::make('ilosc')
                                        ->label('Ilość')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0.001)
                                        ->formatStateUsing(fn ($state) => is_numeric($state) ? (floor($state) == $state ? (int)$state : $state) : $state)
                                        ->suffix($jednostka),
                                    
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
                                ->content(function () use ($record, $jednostka) {
                                    return 'Cena jednostkowa: ' . number_format($record->cena_jednostkowa, 2) . ' PLN/' . $jednostka;
                                })
                                ->extraAttributes(['class' => 'text-sm text-gray-500']),
                        ];
                    })
                    ->fillForm(function ($record) {
                        return ['ilosc' => $record->pivot->ilosc];
                    })
                    ->using(function (array $data, $record) {
                        $record->pivot->update(['ilosc' => $data['ilosc']]);
                        return $record;
                    })
                    ->after(function (RelationManager $livewire): void {
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
                Tables\Actions\DetachAction::make()
                    ->label('Usuń')
                    ->modalHeading('Usuń surowiec z receptury')
                    ->after(function (RelationManager $livewire): void {
                        $livewire->getOwnerRecord()->obliczKosztCalkowity();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Usuń zaznaczone')
                        ->after(function (RelationManager $livewire): void {
                            $livewire->getOwnerRecord()->obliczKosztCalkowity();
                        }),
                ]),
            ]);
    }
}