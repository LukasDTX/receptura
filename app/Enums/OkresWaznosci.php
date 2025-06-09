<?php

namespace App\Enums;

enum OkresWaznosci: string
{
    case DWANASCIE_MIESIECY = '12M';
    case DWADZIESCIA_CZTERY_MIESIACE = '24M';
    case TRZYDZIESCI_SZESC_MIESIECY = '36M';
    
    public function label(): string
    {
        return match($this) {
            self::DWANASCIE_MIESIECY => '12 miesięcy',
            self::DWADZIESCIA_CZTERY_MIESIACE => '24 miesiące',
            self::TRZYDZIESCI_SZESC_MIESIECY => '36 miesięcy',
        };
    }
    
    public function getMonths(): int
    {
        return match($this) {
            self::DWANASCIE_MIESIECY => 12,
            self::DWADZIESCIA_CZTERY_MIESIACE => 24,
            self::TRZYDZIESCI_SZESC_MIESIECY => 36,
        };
    }
    
    public function getDescription(): string
    {
        return match($this) {
            self::DWANASCIE_MIESIECY => 'Produkt ważny przez 12 miesięcy od daty produkcji',
            self::DWADZIESCIA_CZTERY_MIESIACE => 'Produkt ważny przez 24 miesiące od daty produkcji',
            self::TRZYDZIESCI_SZESC_MIESIECY => 'Produkt ważny przez 36 miesięcy od daty produkcji',
        };
    }
}