<?php
namespace App\Filament\Resources\SurowiecResource\Form;

use Filament\Forms;
use App\Enums\TypSurowca;
use App\Enums\JednostkaMiary;

class SurowiecForm
{
    public static function make(): array
    {
        return [
            Forms\Components\TextInput::make('nazwa')->label('Nazwa')->required(),
            Forms\Components\TextInput::make('kod')->label('Kod surowca')
                ->required()
                ->unique(ignoreRecord: true),
                // ->default(fn () => self::generateKod()),
            Forms\Components\TextInput::make('nazwa_naukowa')
                ->label('Nazwa naukowa'),

            Forms\Components\TextInput::make('cena_jednostkowa')->label('Cena jednostkowa')->numeric()->step(0.01)->default(1),
            Forms\Components\Select::make('jednostka_miary')
                ->options(
                    collect(JednostkaMiary::cases())
                        ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                        ->toArray()
                )
                ->default(JednostkaMiary::G->value)
                ->required(),
            Forms\Components\Textarea::make('opis'),
        ];
    }
    public static function generateKod(): string
    {
        // Przykład: prefix + data + losowy ciąg
        $prefix = 'SRW'; // np. Surowiec
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); // 6 losowych znaków

        return "{$prefix}-{$date}-{$random}";
    }
}
