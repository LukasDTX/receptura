<?php
namespace App\Enums;

enum JednostkaMiary: string
{
    case G = 'g';
    case KG = 'kg';
    case ML = 'ml';
    case L = 'l';
    case SZT = 'szt';
    
    public function label(): string
    {
        return match($this) {
            self::G => 'Gram',
            self::KG => 'Kilogram',
            self::ML => 'Mililitr',
            self::L => 'Litr',
            self::SZT => 'Sztuka',
        };
    }
}