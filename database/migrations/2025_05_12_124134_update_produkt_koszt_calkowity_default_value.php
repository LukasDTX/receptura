<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produkt', function (Blueprint $table) {
            // Zmień kolumnę koszt_calkowity, aby miała domyślną wartość 0
            // UWAGA: Jeśli tabela już zawiera rekordy, należy upewnić się,
            // że dla wszystkich null wartości będzie ustawione 0
            DB::statement('UPDATE produkt SET koszt_calkowity = 0 WHERE koszt_calkowity IS NULL');
            
            // Zmień właściwości kolumny
            $table->decimal('koszt_calkowity', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('produkt', function (Blueprint $table) {
            // Przywróć poprzednie właściwości kolumny
            $table->decimal('koszt_calkowity', 10, 2)->default(null)->change();
        });
    }
};