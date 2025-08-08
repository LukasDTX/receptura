<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZlecenieResource\Pages;
use App\Models\Zlecenie;
use App\Models\Produkt;
use App\Services\ZlecenieService;
use App\Services\ZlecenieActionsService;
use App\Services\ZlecenieFormService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;
use Filament\Notifications\Notification;

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
                    ->default(fn () => app(ZlecenieService::class)->generateNumerZlecenia())
                    ->required()
                    ->maxLength(255)
                    ->unique(ignorable: fn ($record) => $record),
                
                Forms\Components\Grid::make()
                    ->schema([
                        Select::make('produkt_id')
                            ->label('Produkt')
                            ->options(fn () => Produkt::all()->pluck('nazwa', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('ilosc', 1);
                                $set('ilosc_zmieniona', false);
                                $set('surowce_przeliczone', false);
                                session()->forget('temp_surowce_potrzebne');
                            }),
                            
                        Forms\Components\TextInput::make('ilosc')
                            ->label('Ilość (sztuk)')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state, $old) {
                                if ($old !== null && $old != $state) {
                                    $set('ilosc_zmieniona', true);
                                    $set('surowce_przeliczone', false);
                                }
                            })
                            ->suffixAction(static::createPrzeliczSurowceAction()),
                        
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

                // Status indicator - używa ZlecenieFormService
                Forms\Components\Placeholder::make('status_indicator')
                    ->label('')
                    ->content(function ($record, Get $get) {
                        $formService = app(ZlecenieFormService::class);
                        return $formService->generateStatusIndicator(
                            $record,
                            $get('produkt_id'),
                            $get('ilosc'),
                            $get('surowce_przeliczone')
                        );
                    })
                    ->reactive()
                    ->live()
                    ->columnSpanFull(),

                // Sekcja surowców - używa ZlecenieFormService
                Forms\Components\Section::make('Surowce potrzebne do realizacji zlecenia')
                    ->description('Lista surowców potrzebnych do realizacji zlecenia.')
                    ->schema([
                        Forms\Components\Placeholder::make('surowce_info')
                            ->label('')
                            ->content(function ($record) {
                                $formService = app(ZlecenieFormService::class);
                                return new \Illuminate\Support\HtmlString(
                                    $formService->generateSurowceContent($record)
                                );
                            })
                            ->reactive()
                            ->live(),
                    ])
                    ->collapsed(function ($record, Get $get) {
                        $formService = app(ZlecenieFormService::class);
                        return $formService->isSurowceSectionCollapsed($record, $get('surowce_przeliczone'));
                    })
                    ->collapsible()
                    ->visible(function ($record, Get $get) {
                        $formService = app(ZlecenieFormService::class);
                        return $formService->isSurowceSectionVisible($record, $get('surowce_przeliczone'));
                    })
                    ->columnSpanFull(),

                // Informacje o produkcie - używa ZlecenieFormService
                Forms\Components\Section::make('Informacje o produkcie')
                    ->description('Szczegóły wybranego produktu i obliczenia kosztów')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('info_produkt_podstawowe')
                                    ->label('Podstawowe informacje')
                                    ->content(function (Get $get) {
                                        $formService = app(ZlecenieFormService::class);
                                        return new \Illuminate\Support\HtmlString(
                                            $formService->generateProduktInfo($get('produkt_id'))
                                        );
                                    }),
                                
                                Forms\Components\Placeholder::make('obliczenia_kosztow')
                                    ->label('Obliczenia kosztów')
                                    ->content(function (Get $get) {
                                        $formService = app(ZlecenieFormService::class);
                                        return new \Illuminate\Support\HtmlString(
                                            $formService->generateKosztyInfo($get('produkt_id'), $get('ilosc'))
                                        );
                                    }),
                            ]),
                    ])
                    ->extraAttributes([
                        'style' => 'background-color: #fefce8; border: 1px solid #fde047; border-radius: 0.5rem;'
                    ])
                    ->visible(function ($record, Get $get) {
                        $formService = app(ZlecenieFormService::class);
                        return $formService->isProduktInfoSectionVisible($record, $get('produkt_id'));
                    })
                    ->columnSpanFull(),

                // Ukryte pola
                Forms\Components\Hidden::make('ilosc_zmieniona')
                    ->default(false)
                    ->reactive(),

                Forms\Components\Hidden::make('surowce_przeliczone')
                    ->default(false)
                    ->reactive(),
            ]);
    }

    private static function createPrzeliczSurowceAction(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('przelicz_surowce')
            ->label('')
            ->tooltip(function (Get $get) {
                $iloscZmieniona = $get('ilosc_zmieniona');
                if ($iloscZmieniona === true || $iloscZmieniona === 'true') {
                    return 'Przelicz surowce (ilość została zmieniona)';
                }
                return 'Przelicz surowce';
            })
            ->icon('heroicon-o-calculator')
            ->color(function (Get $get) {
                $iloscZmieniona = $get('ilosc_zmieniona');
                if ($iloscZmieniona === true || $iloscZmieniona === 'true') {
                    return 'danger';
                }
                return 'warning';
            })
            ->visible(fn (Get $get) => $get('produkt_id') !== null)
            ->action(function (Set $set, Get $get, $record) {
                $produktId = $get('produkt_id');
                $ilosc = $get('ilosc');
                
                try {
                    $zlecenieService = app(ZlecenieService::class);
                    
                    if ($record) {
                        // Edycja istniejącego zlecenia
                        $surowcePotrzebne = $zlecenieService->przeliczSurowce($produktId, $ilosc);
                        $zlecenieService->zapiszSurowceDoZlecenia($record, $surowcePotrzebne);
                        
                        Notification::make()
                            ->title('Sukces')
                            ->body('Surowce zostały przeliczone i zapisane.')
                            ->success()
                            ->send();
                        
                        redirect(request()->header('Referer'));
                    } else {
                        // Tworzenie nowego zlecenia
                        $surowcePotrzebne = $zlecenieService->przeliczSurowce($produktId, $ilosc);
                        
                        // Zapisz do sesji
                        session(['temp_surowce_potrzebne' => $surowcePotrzebne]);
                        
                        $set('surowce_przeliczone', true);
                        
                        Notification::make()
                            ->title('Sukces')
                            ->body('Surowce zostały przeliczone. Teraz możesz zapisać zlecenie.')
                            ->success()
                            ->send();
                    }
                    
                    $set('ilosc_zmieniona', false);
                    
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Błąd')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->modalHeading('Przelicz surowce')
            ->modalDescription('Czy na pewno chcesz przeliczyć surowce dla aktualnej ilości produktów?')
            ->modalSubmitActionLabel('Przelicz')
            ->modalCancelActionLabel('Anuluj');
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
                Tables\Columns\TextColumn::make('data_waznosci')
                    ->label('Data ważności')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('numer_partii')
                    ->label('Numer partii')
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
                ...static::getTableActions(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    app(ZlecenieActionsService::class)->createZmienStatusBulkAction(),
                ]),
            ]);
    }

    /**
     * Pobiera akcje dla tabeli
     */
    private static function getTableActions(): array
    {
        $actionsService = app(ZlecenieActionsService::class);
        
        return [
            Tables\Actions\EditAction::make()
                ->label('Edytuj')
                ->icon('heroicon-o-pencil')
                ->visible(fn ($record) => $record->status === 'nowe'),
                
            Tables\Actions\Action::make('drukuj')
                ->label('Drukuj')
                ->icon('heroicon-o-printer')
                ->url(fn (Zlecenie $record): string => route('zlecenie.drukuj', $record))
                ->visible(fn ($record) => $record->status === 'zrealizowane' || $record->status === 'w_realizacji')
                ->openUrlInNewTab(),
                
            $actionsService->createSprawdzDostepnoscAction()->visible(fn ($record) => $record->status === 'nowe'),
            $actionsService->createZobaczPobraneSurowceAction(),
            // $actionsService->createEksportPobranychSurowcowAction(),
            $actionsService->createUruchomProdukcjeAction(),
            $actionsService->createUtworzPartieAction(),
        ];
    }

    // Actions methods implementation would continue here...
    // Due to length constraints, I'm providing the structure for the remaining actions

    private static function createSprawdzDostepnoscAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('sprawdz_dostepnosc')
            ->label('Sprawdź dostępność surowców')
            ->icon('heroicon-o-magnifying-glass')
            ->color('info')
            ->action(function ($record) {
                try {
                    $zlecenieService = app(ZlecenieService::class);
                    $analiza = $zlecenieService->sprawdzDostepnoscSurowcow($record);
                    
                    if ($analiza['mozliwe_do_realizacji']) {
                        $komunikat = "✅ Wszystkie surowce są dostępne w magazynie!";
                        // Add plan details...
                        
                        Notification::make()
                            ->title('Zlecenie można zrealizować')
                            ->body($komunikat)
                            ->success()
                            ->persistent()
                            ->send();
                    } else {
                        $komunikat = "❌ Braki w magazynie:";
                        // Add shortage details...
                        
                        Notification::make()
                            ->title('Nie można zrealizować zlecenia')
                            ->body($komunikat)
                            ->danger()
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Błąd podczas sprawdzania dostępności surowców')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }        
            })
            ->visible(fn ($record) => !empty($record->surowce_potrzebne));
    }

    // Additional action methods would be implemented similarly...
    
    public static function getRelations(): array
    {
        return [];
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