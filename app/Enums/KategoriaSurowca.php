<?php

// 1. ENUM - app/Enums/KategoriaSurowca.php
namespace App\Enums;

enum KategoriaSurowca: string
{
    case GRZYB = 'G';
    case HERBATA = 'H';
    case KOLAGEN = 'K';
    case OLEJEK_ETERYCZNY = 'OE';
    case SOK = 'S';
    case SUBSTANCJE_BA = 'BA';
    case WODA = 'W';
    case ZIOLA = 'Z';
    
    public function label(): string
    {
        return match($this) {
            self::GRZYB => 'Grzyb',
            self::HERBATA => 'Herbata',
            self::KOLAGEN => 'Kolagen',
            self::OLEJEK_ETERYCZNY => 'Olejek eteryczny',
            self::SOK => 'Sok',
            self::SUBSTANCJE_BA => 'Substancje BA',
            self::WODA => 'Woda',
            self::ZIOLA => 'Zioła',
        };
    }
    public static function all(): array
    {
        return [
            self::GRZYB,
            self::HERBATA,
            self::KOLAGEN,
            self::OLEJEK_ETERYCZNY,
            self::SOK,
            self::SUBSTANCJE_BA,
            self::WODA,
            self::ZIOLA,
        ];
    }    
    public function color(): string
    {
        return match($this) {
            self::GRZYB => 'purple',
            self::HERBATA => 'orange',
            self::KOLAGEN => 'green',
            self::OLEJEK_ETERYCZNY => 'yellow',
            self::SOK => 'amber',
            self::WODA => 'yellow',
            self::ZIOLA => 'blue',
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::GRZYB => 'heroicon-o-beaker',
            self::HERBATA => 'heroicon-o-square-3-stack-3d',
            self::KOLAGEN => 'heroicon-o-cube-transparent',
            self::OLEJEK_ETERYCZNY => 'heroicon-o-squares-plus',
            self::SOK => 'heroicon-o-drop',
            self::WODA => 'heroicon-o-sun',
            self::ZIOLA => 'heroicon-o-plus-circle',
        };
    }
    
    public function description(): string
    {
        return match($this) {
            self::GRZYB => 'Grzyby i ich ekstrakty',
            self::HERBATA => 'Herbaty i napary',
            self::KOLAGEN => 'KOlagen i białka',
            self::OLEJEK_ETERYCZNY => 'Olejki eteryczne i aromaty',
            self::SOK => 'Soki',
            self::WODA => 'H2O i wody mineralne',
            self::ZIOLA => 'Zioła i mieszanki ziołowe',
        };
    }
}