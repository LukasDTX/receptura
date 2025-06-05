<?php
namespace App\Filament\Resources\OpakowanieResource\Form;

use Filament\Forms;
use App\Enums\TypSurowca;
use App\Enums\JednostkaMiary;

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
                Forms\Components\TextInput::make('pojemnosc')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->label('Pojemność (g)')
                    ->suffix('g')
                    ->placeholder('np. 250')
                    ->helperText('Pojemność opakowania w gramach. Przykład: 100g, 250g, 1000g'),
                Forms\Components\TextInput::make('cena')
                    ->required()
                    ->numeric()
                    ->prefix('PLN')
                    ->default(0),
        ];
    }
    public static function generateKod(): string
    {
        // Przykład: prefix + data + losowy ciąg
        $prefix = 'OPAK'; // np. Surowiec
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 2)); // 6 losowych znaków

        return "{$prefix}-{$date}-{$random}";
    }
}