<?php

namespace App\Enums;

enum TypSurowca: string
{
    case Shoty = 'shoty';
    case Proszki = 'proszki';
    case Olejki = 'olejki';

    public function label(): string
    {
        return match($this) {
            self::Shoty => 'Shoty',
            self::Proszki => 'Proszki',
            self::Olejki => 'Olejki',
        };
    }
}
