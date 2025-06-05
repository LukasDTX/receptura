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
            Forms\Components\TextInput::make('nazwa')->required(),
            Forms\Components\TextInput::make('kod')
                ->required()
                ->unique(ignoreRecord: true)
                ->default(fn () => self::generateKod())
                ->readonly(),
            Forms\Components\Textarea::make('opis'),
            Forms\Components\TextInput::make('cena_jednostkowa')->numeric()->step(0.01),
            Forms\Components\Select::make('jednostka_miary')
                ->options(
                    collect(JednostkaMiary::cases())
                        ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                        ->toArray()
                )
                ->default(JednostkaMiary::KG->value)
                ->required(),
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
