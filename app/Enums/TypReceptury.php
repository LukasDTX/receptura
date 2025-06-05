<?php

namespace App\Enums;

enum TypReceptury: string
{
    case GRAMY = 'gramy';
    case MILILITRY = 'mililitry';
    
    public function label(): string
    {
        return match($this) {
            self::GRAMY => 'Liczony w gramach',
            self::MILILITRY => 'Liczony w mililitrach',
        };
    }
    
    public function jednostka(): string
    {
        return match($this) {
            self::GRAMY => 'g',
            self::MILILITRY => 'ml',
        };
    }
}