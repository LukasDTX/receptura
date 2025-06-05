<?php

namespace App\Enums;

enum JednostkaOpakowania: string
{
    case GRAMY = 'g';
    case MILILITRY = 'ml';
    
    public function label(): string
    {
        return match($this) {
            self::GRAMY => 'Gramy (g)',
            self::MILILITRY => 'Mililitry (ml)',
        };
    }
    
    public function opis(): string
    {
        return match($this) {
            self::GRAMY => 'Pojemność w gramach (dla produktów stałych)',
            self::MILILITRY => 'Pojemność w mililitrach (dla produktów płynnych)',
        };
    }
}