<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surowiec', function (Blueprint $table) {
            // Zmień kolumnę jednostka_miary, aby miała domyślną wartość 'g'
            // Najpierw zaktualizuj istniejące rekordy, które mają domyślną wartość 'kg'
            DB::statement("UPDATE surowiec SET jednostka_miary = 'g' WHERE jednostka_miary = 'kg'");
            
            // Zmień właściwości kolumny
            $table->string('jednostka_miary')->default('g')->change();
        });
    }

    public function down(): void
    {
        Schema::table('surowiec', function (Blueprint $table) {
            // Przywróć poprzednie właściwości kolumny
            $table->string('jednostka_miary')->default('kg')->change();
        });
    }
};

