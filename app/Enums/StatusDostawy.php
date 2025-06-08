<?php

namespace App\Enums;
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