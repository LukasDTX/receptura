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

class SurowceRelationManager extends RelationManager
{
    protected static string $relationship = 'surowce';

    protected static ?string $recordTitleAttribute = 'nazwa';
    
    protected static ?string $title = 'Surowce'; 
    
    protected static ?string $modelLabel = 'surowiec';
    
    protected static ?string $pluralModelLabel = 'surowce';
    
    protected function getTableDescription(): ?string
    {
        return 'Receptura jest tworzona dla 1kg produktu. Podaj ilości surowców potrzebne do wyprodukowania 1kg.';
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nazwa')
                    ->label('Nazwa surowca')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.ilosc')
                    ->label('Ilość')
                    ->formatStateUsing(function ($state, $record) {
                        // Formatowanie liczby - ukrycie części dziesiętnej jeśli są same zera
                        $formatted = is_numeric($state) ? (floor($state) == $state ? (int)$state : $state) : $state;
                        return $formatted . ' ' . $record->jednostka_miary;
                    }),
                Tables\Columns\TextColumn::make('jednostka_miary')
                    ->label('Jednostka'),
                Tables\Columns\TextColumn::make('cena_jednostkowa')
                    ->label('Cena jedn.')
                    ->money('pln'),
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
                            ->suffix(function (Get $get) {
                                return $get('jednostka');
                            })
                            ->helperText('Podaj ilość potrzebną do produkcji 1kg.'),
                    ])
                    ->after(function (RelationManager $livewire): void {
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
                            ->default(0),
                        Forms\Components\Select::make('jednostka_miary')
                            ->options([
                                'g' => 'Gram',
                                'kg' => 'Kilogram',
                                'ml' => 'Mililitr',
                                'l' => 'Litr',
                                'szt' => 'Sztuka',
                            ])
                            ->default('g')
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