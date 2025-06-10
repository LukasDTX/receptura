<?php

// 1. ENUM - app/Enums/KategoriaSurowca.php
namespace App\Enums;

enum KategoriaSurowca: string
{
    case OLEJEK_ETERYCZNY = 'OE';
    case SOK = 'S';
    case EKSTRAKT = 'E';
    case PROSZEK = 'P';
    case OLEJ = 'O';
    case MIOD = 'M';
    case DODATEK = 'D';
    case KONSERWANT = 'K';
    case WITAMINA = 'W';
    case MINERAL = 'MIN';
    case AROMAT = 'A';
    
    public function label(): string
    {
        return match($this) {
            self::OLEJEK_ETERYCZNY => 'Olejek eteryczny',
            self::SOK => 'Sok',
            self::EKSTRAKT => 'Ekstrakt',
            self::PROSZEK => 'Proszek',
            self::OLEJ => 'Olej',
            self::MIOD => 'Miód',
            self::DODATEK => 'Dodatek funkcjonalny',
            self::KONSERWANT => 'Konserwant',
            self::WITAMINA => 'Witamina',
            self::MINERAL => 'Minerał',
            self::AROMAT => 'Aromat',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::OLEJEK_ETERYCZNY => 'purple',
            self::SOK => 'orange',
            self::EKSTRAKT => 'green',
            self::PROSZEK => 'yellow',
            self::OLEJ => 'amber',
            self::MIOD => 'yellow',
            self::DODATEK => 'blue',
            self::KONSERWANT => 'red',
            self::WITAMINA => 'emerald',
            self::MINERAL => 'gray',
            self::AROMAT => 'pink',
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::OLEJEK_ETERYCZNY => 'heroicon-o-beaker',
            self::SOK => 'heroicon-o-square-3-stack-3d',
            self::EKSTRAKT => 'heroicon-o-leaf',
            self::PROSZEK => 'heroicon-o-squares-plus',
            self::OLEJ => 'heroicon-o-drop',
            self::MIOD => 'heroicon-o-sun',
            self::DODATEK => 'heroicon-o-plus-circle',
            self::KONSERWANT => 'heroicon-o-shield-check',
            self::WITAMINA => 'heroicon-o-heart',
            self::MINERAL => 'heroicon-o-cube',
            self::AROMAT => 'heroicon-o-sparkles',
        };
    }
    
    public function description(): string
    {
        return match($this) {
            self::OLEJEK_ETERYCZNY => 'Naturalne olejki eteryczne z roślin',
            self::SOK => 'Soki owocowe i warzywne',
            self::EKSTRAKT => 'Ekstrakty roślinne i ziołowe',
            self::PROSZEK => 'Surowce w formie proszku',
            self::OLEJ => 'Oleje roślinne i tłuszcze',
            self::MIOD => 'Miód i produkty pszczele',
            self::DODATEK => 'Dodatki funkcjonalne i aktywne',
            self::KONSERWANT => 'Substancje konserwujące',
            self::WITAMINA => 'Witaminy i ich pochodne',
            self::MINERAL => 'Minerały i sole',
            self::AROMAT => 'Aromaty naturalne i identyczne',
        };
    }
}