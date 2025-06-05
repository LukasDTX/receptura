<?php

namespace App\Enums;

enum StatusPartii: string
{
    case WYPRODUKOWANA = 'wyprodukowana';
    case W_MAGAZYNIE = 'w_magazynie';
    case WYDANA = 'wydana';
    case WYCOFANA = 'wycofana';
    
    public function label(): string
    {
        return match($this) {
            self::WYPRODUKOWANA => 'Wyprodukowana',
            self::W_MAGAZYNIE => 'W magazynie',
            self::WYDANA => 'Wydana',
            self::WYCOFANA => 'Wycofana',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::WYPRODUKOWANA => 'info',
            self::W_MAGAZYNIE => 'success',
            self::WYDANA => 'warning',
            self::WYCOFANA => 'danger',
        };
    }
}

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

enum StatusDostawy: string
{
    case ZAMOWIONA = 'zamowiona';
    case W_TRANSPORCIE = 'w_transporcie';
    case DOSTARCZONA = 'dostarczona';
    case ANULOWANA = 'anulowana';
    
    public function label(): string
    {
        return match($this) {
            self::ZAMOWIONA => 'Zamówiona',
            self::W_TRANSPORCIE => 'W transporcie',
            self::DOSTARCZONA => 'Dostarczona',
            self::ANULOWANA => 'Anulowana',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::ZAMOWIONA => 'warning',
            self::W_TRANSPORCIE => 'info',
            self::DOSTARCZONA => 'success',
            self::ANULOWANA => 'danger',
        };
    }
}