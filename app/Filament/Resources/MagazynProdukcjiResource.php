<?php
// app/Filament/Resources/MagazynProdukcjiResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\MagazynProdukcjiResource\Pages;
use App\Models\MagazynProdukcji;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Carbon\Carbon;

class MagazynProdukcjiResource extends Resource
{
    protected static ?string $model = MagazynProdukcji::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    
    protected static ?string $navigationLabel = 'Magazyn produkcji';
    
    protected static ?string $modelLabel = 'Pozycja magazynu produkcji';
    
    protected static ?string $pluralModelLabel = 'Magazyn produkcji';
    
    protected static ?string $navigationGroup = 'Magazyn Surowców';
    
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('partia_surowca_id')
                    ->label('Partia surowca')
                    ->relationship('partiaSurowca', 'numer_partii', function ($query) {
                        return $query->with('surowiec')
                                     ->whereIn('status', ['nowa', 'otwarta'])
                                     ->where('masa_pozostala', '>', 0);
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        $record->numer_partii . ' - ' . $record->surowiec->nazwa . ' (' . $record->masa_pozostala . 'kg)'
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                    
                Forms\Components\TextInput::make('masa_dostepna')
                    ->label('Masa dostępna (kg)')
                    ->required()
                    ->numeric()
                    ->minValue(0.001)
                    ->suffix('kg'),
                    
                Forms\Components\TextInput::make('lokalizacja')
                    ->label('Lokalizacja w magazynie produkcji')
                    ->required()
                    ->placeholder('PROD-A1-P2'),
                    
                Forms\Components\DatePicker::make('data_przeniesienia')
                    ->label('Data przeniesienia')
                    ->required()
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partiaSurowca.numer_partii')
                    ->label('Numer partii')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('partiaSurowca.surowiec.nazwa')
                    ->label('Surowiec')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('masa_dostepna')
                    ->label('Masa dostępna')
                    ->suffix(' kg')
                    ->sortable()
                    ->alignEnd()
                    ->color('success')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('lokalizacja')
                    ->label('Lokalizacja')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('data_przeniesienia')
                    ->label('Data przeniesienia')
                    ->date()
                    ->sortable(),
                    
Tables\Columns\TextColumn::make('dni_w_produkcji')
    ->label('Dni w produkcji')
->getStateUsing(function ($record) {
    $data = Carbon::parse($record->data_przeniesienia);

    $dni = (int) now()->diffInDays($data);
    $godziny = (int) now()->diffInHours($data) - ($dni * 24);

    return "{$dni} dni ({$godziny} godz.)";
})
    ->badge()
    ->color(function ($record) {
        $dni = now()->diffInDays(Carbon::parse($record->data_przeniesienia));
        if ($dni <= 7) return 'success';
        if ($dni <= 30) return 'warning';
        return 'danger';
    }),
                    
                Tables\Columns\TextColumn::make('partiaSurowca.data_waznosci')
                    ->label('Data ważności')
                    ->date()
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->partiaSurowca->data_waznosci) return null;
                        
                        if ($record->partiaSurowca->data_waznosci < now()) {
                            return 'danger';
                        } elseif ($record->partiaSurowca->data_waznosci <= now()->addDays(7)) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->placeholder('Brak'),
                    
                Tables\Columns\TextColumn::make('wartosc')
                    ->label('Wartość')
                    ->getStateUsing(fn ($record) => 
                        $record->masa_dostepna * $record->partiaSurowca->cena_za_kg
                    )
                    ->money('pln')
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('surowiec')
                    ->relationship('partiaSurowca.surowiec', 'nazwa')
                    ->label('Surowiec')
                    ->preload()
                    ->searchable(),
                    
                Tables\Filters\Filter::make('stare_pozycje')
                    ->label('Pozycje starsze niż 30 dni')
                    ->query(fn ($query) => $query->where('data_przeniesienia', '<', now()->subDays(30)))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('przeterminowane')
                    ->label('Przeterminowane')
                    ->query(fn ($query) => $query->whereHas('partiaSurowca', function ($q) {
                        $q->where('data_waznosci', '<', now());
                    }))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('wydaj_do_zlecenia')
                    ->label('Wydaj do zlecenia')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('zlecenie_id')
                            ->label('Zlecenie')
                            ->relationship('zlecenie', 'numer', function ($query) {
                                return $query->whereIn('status', ['nowe', 'w_realizacji']);
                            })
                            ->searchable()
                            ->required(),
                            
                        Forms\Components\TextInput::make('masa_do_wydania')
                            ->label('Masa do wydania (kg)')
                            ->required()
                            ->numeric()
                            ->minValue(0.001)
                            ->suffix('kg'),
                            
                        Forms\Components\Textarea::make('uwagi')
                            ->label('Uwagi')
                            ->placeholder('Uwagi dotyczące wydania...'),
                    ])
                    ->action(function (array $data, $record) {
                        $zlecenie = \App\Models\Zlecenie::find($data['zlecenie_id']);
                        $masaDoWydania = $data['masa_do_wydania'];
                        
                        if ($masaDoWydania > $record->masa_dostepna) {
                            \Filament\Notifications\Notification::make()
                                ->title('Błąd')
                                ->body('Niewystarczająca ilość w magazynie produkcji')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Utwórz ruch wydania
                        \App\Models\RuchSurowca::create([
                            'typ_ruchu' => 'wydanie_do_produkcji',
                            'partia_surowca_id' => $record->partia_surowca_id,
                            'zlecenie_id' => $zlecenie->id,
                            'masa' => -$masaDoWydania,
                            'masa_przed' => $record->masa_dostepna,
                            'masa_po' => $record->masa_dostepna - $masaDoWydania,
                            'skad' => 'magazyn_produkcji',
                            'dokad' => 'zlecenie_' . $zlecenie->numer,
                            'data_ruchu' => now(),
                            'uwagi' => $data['uwagi'] ?? "Wydanie do zlecenia {$zlecenie->numer}",
                            'user_id' => auth()->id(),
                        ]);
                        
                        // Aktualizuj pozycję w magazynie produkcji
                        $record->masa_dostepna -= $masaDoWydania;
                        
                        if ($record->masa_dostepna <= 0) {
                            $record->delete();
                        } else {
                            $record->save();
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Wydanie wykonane')
                            ->body("Wydano {$masaDoWydania}kg do zlecenia {$zlecenie->numer}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Wydaj surowiec do zlecenia')
                    ->modalSubmitActionLabel('Wydaj'),
                    
                Tables\Actions\Action::make('przenies_z_powrotem')
                    ->label('Przenieś z powrotem')
                    ->icon('heroicon-o-arrow-left-circle')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('masa_do_przeniesienia')
                            ->label('Masa do przeniesienia (kg)')
                            ->required()
                            ->numeric()
                            ->minValue(0.001)
                            ->suffix('kg'),
                            
                        Forms\Components\Textarea::make('uwagi')
                            ->label('Powód przeniesienia z powrotem')
                            ->required()
                            ->placeholder('Dlaczego przenosisz z powrotem do magazynu głównego...'),
                    ])
                    ->action(function (array $data, $record) {
                        $masaDoPrzeniesienia = $data['masa_do_przeniesienia'];
                        
                        if ($masaDoPrzeniesienia > $record->masa_dostepna) {
                            \Filament\Notifications\Notification::make()
                                ->title('Błąd')
                                ->body('Niewystarczająca ilość do przeniesienia')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Utwórz ruch przeniesienia z powrotem
                        \App\Models\RuchSurowca::create([
                            'typ_ruchu' => 'przeniesienie',
                            'partia_surowca_id' => $record->partia_surowca_id,
                            'masa' => $masaDoPrzeniesienia,
                            'masa_przed' => $record->partiaSurowca->masa_pozostala,
                            'masa_po' => $record->partiaSurowca->masa_pozostala + $masaDoPrzeniesienia,
                            'skad' => 'magazyn_produkcji',
                            'dokad' => 'magazyn_glowny',
                            'data_ruchu' => now(),
                            'uwagi' => $data['uwagi'],
                            'user_id' => auth()->id(),
                        ]);
                        
                        // Aktualizuj partię główną
                        $record->partiaSurowca->increment('masa_pozostala', $masaDoPrzeniesienia);
                        
                        // Aktualizuj pozycję w magazynie produkcji
                        $record->masa_dostepna -= $masaDoPrzeniesienia;
                        
                        if ($record->masa_dostepna <= 0) {
                            $record->delete();
                        } else {
                            $record->save();
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Przeniesienie wykonane')
                            ->body("Przeniesiono {$masaDoPrzeniesienia}kg z powrotem do magazynu głównego")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Przenieś z powrotem do magazynu głównego')
                    ->modalSubmitActionLabel('Przenieś'),
                    
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_przeniesienia', 'desc')
            ->poll('30s');
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::dostepne()->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMagazynProdukcji::route('/'),
            'create' => Pages\CreateMagazynProdukcji::route('/create'),
            'edit' => Pages\EditMagazynProdukcji::route('/{record}/edit'),
        ];
    }
}

// app/Filament/Resources/MagazynProdukcjiResource/Pages/ListMagazynProdukcji.php

namespace App\Filament\Resources\MagazynProdukcjiResource\Pages;

use App\Filament\Resources\MagazynProdukcjiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMagazynProdukcji extends ListRecords
{
    protected static string $resource = MagazynProdukcjiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj pozycję')
                ->icon('heroicon-o-plus'),
                
            Actions\Action::make('czysc_stare')
                ->label('Wyczyść stare pozycje')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    $stare = \App\Models\MagazynProdukcji::where('data_przeniesienia', '<', now()->subDays(60))
                                                        ->where('masa_dostepna', '<', 0.1)
                                                        ->delete();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Wyczyszczono')
                        ->body("Usunięto {$stare} starych pozycji z małymi ilościami")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Wyczyść stare pozycje')
                ->modalDescription('Usuń pozycje starsze niż 60 dni z masą mniejszą niż 0.1kg')
                ->modalSubmitActionLabel('Wyczyść'),
        ];
    }
}

// app/Filament/Resources/MagazynProdukcjiResource/Pages/CreateMagazynProdukcji.php

namespace App\Filament\Resources\MagazynProdukcjiResource\Pages;

use App\Filament\Resources\MagazynProdukcjiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMagazynProdukcji extends CreateRecord
{
    protected static string $resource = MagazynProdukcjiResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

// app/Filament/Resources/MagazynProdukcjiResource/Pages/EditMagazynProdukcji.php

namespace App\Filament\Resources\MagazynProdukcjiResource\Pages;

use App\Filament\Resources\MagazynProdukcjiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMagazynProdukcji extends EditRecord
{
    protected static string $resource = MagazynProdukcjiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}