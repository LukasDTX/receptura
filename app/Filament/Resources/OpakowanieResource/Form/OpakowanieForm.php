<?php
namespace App\Filament\Resources\OpakowanieResource\Form;

use Filament\Forms;
use App\Enums\JednostkaOpakowania;

class OpakowanieForm
{
    public static function make(): array
    {
        return [
            Forms\Components\TextInput::make('nazwa')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('kod')
                ->required()
                ->unique(ignoreRecord: true)
                ->default(fn () => self::generateKod())
                ->readonly(),
            Forms\Components\Textarea::make('opis')
                ->maxLength(65535)
                ->columnSpanFull(),
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('pojemnosc')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->label('Pojemność')
                        ->placeholder('np. 250')
                        ->reactive()
                        ->suffix(function (callable $get) {
                            $jednostka = $get('jednostka') ?? 'g';
                            return $jednostka;
                        }),
                    Forms\Components\Select::make('jednostka')
                        ->label('Jednostka')
                        ->options([
                            'g' => 'Gramy (g) - produkty stałe',
                            'ml' => 'Mililitry (ml) - produkty płynne',
                        ])
                        ->default('g')
                        ->required()
                        ->reactive()
                        ->disabled(fn ($context) => $context === 'edit') // Zablokowane podczas edycji
                        ->dehydrated() // Zapewnij, że wartość jest nadal przekazywana
                        ->helperText(function ($context) {
                            if ($context === 'edit') {
                                return 'Jednostka może być zmieniona tylko podczas tworzenia nowego opakowania.';
                            }
                            return 'Wybierz jednostkę zgodną z typem produktu';
                        }),
                ])
                ->columnSpanFull(),
            Forms\Components\Placeholder::make('jednostka_info')
                ->label('Typ opakowania')
                ->content(function ($record) {
                    if (!$record) return '';
                    
                    $jednostka = $record->jednostka instanceof \App\Enums\JednostkaOpakowania 
                        ? $record->jednostka->value 
                        : $record->jednostka;
                        
                    if ($jednostka === 'ml') {
                        return '🥤 Opakowanie dla produktów płynnych (mililitry)';
                    } else {
                        return '📦 Opakowanie dla produktów stałych (gramy)';
                    }
                })
                ->visibleOn('edit')
                ->extraAttributes(['class' => 'text-blue-600 font-medium'])
                ->columnSpanFull(),
            Forms\Components\Section::make('Przykłady pojemności')
                ->description('Typowe pojemności dla różnych typów opakowań')
                ->schema([
                    Forms\Components\Placeholder::make('przyklad_gramy')
                        ->label('Produkty stałe (gramy)')
                        ->content('Tabletki: 50g, 100g, 250g | Proszki: 100g, 250g, 500g, 1000g | Kapsułki: 30g, 60g, 120g')
                        ->visible(fn (callable $get) => ($get('jednostka') ?? 'g') === 'g'),
                    Forms\Components\Placeholder::make('przyklad_ml')
                        ->label('Produkty płynne (mililitry)')
                        ->content('Buteleczki: 30ml, 50ml, 100ml | Butelki: 250ml, 500ml, 1000ml | Ampułki: 10ml, 25ml')
                        ->visible(fn (callable $get) => ($get('jednostka') ?? 'g') === 'ml'),
                ])
                ->collapsed()
                ->collapsible()
                ->extraAttributes(['class' => 'bg-blue-50'])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('cena')
                ->required()
                ->numeric()
                ->prefix('PLN')
                ->default(0),
        ];
    }
    
    public static function generateKod(): string
    {
        $prefix = 'OPAK';
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 2));

        return "{$prefix}-{$date}-{$random}";
    }
}