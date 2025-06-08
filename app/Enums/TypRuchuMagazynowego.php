<?php

namespace App\Enums;
enum TypRuchuMagazynowego: string
{
    case PRZYJECIE = 'przyjecie';
    case WYDANIE = 'wydanie';
    case KOREKTA_PLUS = 'korekta_plus';
    case KOREKTA_MINUS = 'korekta_minus';
    case TRANSFER = 'transfer';
    case PRODUKCJA = 'produkcja';
    
    public function label(): string
    {
        return match($this) {
            self::PRZYJECIE => 'Przyjęcie',
            self::WYDANIE => 'Wydanie',
            self::KOREKTA_PLUS => 'Korekta (+)',
            self::KOREKTA_MINUS => 'Korekta (-)',
            self::TRANSFER => 'Transfer',
            self::PRODUKCJA => 'Produkcja',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::PRZYJECIE => 'success',
            self::WYDANIE => 'danger',
            self::KOREKTA_PLUS => 'info',
            self::KOREKTA_MINUS => 'warning',
            self::TRANSFER => 'gray',
            self::PRODUKCJA => 'primary',
        };
    }
}