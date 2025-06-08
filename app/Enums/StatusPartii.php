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


