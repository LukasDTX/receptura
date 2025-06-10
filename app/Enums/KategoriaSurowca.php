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
    case PROSZEK_OWOCOWY = 'PO';
    case OLEJEK_KOSMETYCZNY = 'OK';
    
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
            self::PROSZEK_OWOCOWY => 'Proszek owocowy',
            self::OLEJEK_KOSMETYCZNY => 'Olejek kosmetyczny',
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
            self::PROSZEK_OWOCOWY,
            self::OLEJEK_KOSMETYCZNY,
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
            self::SUBSTANCJE_BA, => 'red',
            self::WODA => 'yellow',
            self::ZIOLA => 'blue',
            self::PROSZEK_OWOCOWY => 'pink',
            self::OLEJEK_KOSMETYCZNY => 'teal',
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::GRZYB => 'heroicon-o-beaker',
            self::HERBATA => 'heroicon-o-beaker',
            self::KOLAGEN => 'heroicon-o-beaker',
            self::OLEJEK_ETERYCZNY => 'heroicon-o-beaker',
            self::SOK => 'heroicon-o-beaker',
            self::SUBSTANCJE_BA => 'heroicon-o-beaker',
            self::WODA => 'heroicon-o-beaker',
            self::ZIOLA => 'heroicon-o-beaker',
            self::PROSZEK_OWOCOWY => 'heroicon-o-beaker',
            self::OLEJEK_KOSMETYCZNY => 'heroicon-o-beaker',
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
            self::SUBSTANCJE_BA, => 'Substancje bioaktywne',
            self::WODA => 'H2O i wody mineralne',
            self::ZIOLA => 'Zioła i mieszanki ziołowe',
            self::PROSZEK_OWOCOWY => 'Proszki owocowe i warzywne',
            self::OLEJEK_KOSMETYCZNY => 'Olejki kosmetyczne i pielęgnacyjne'
        };
    }
}