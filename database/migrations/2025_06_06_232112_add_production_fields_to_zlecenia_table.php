<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zlecenie', function (Blueprint $table) {
            $table->date('data_produkcji')
                  ->nullable()
                  ->after('planowana_data_realizacji')
                  ->comment('Data rzeczywistej produkcji');
                  
            $table->date('data_waznosci')
                  ->nullable()
                  ->after('data_produkcji')
                  ->comment('Data ważności produktu (data_produkcji + okres_waznosci)');
                  
            $table->string('numer_partii')
                  ->nullable()
                  ->after('data_waznosci')
                  ->comment('Automatycznie generowany numer partii w formacie PA/RRRRMMDD/XXX');
        });
    }

    public function down(): void
    {
        Schema::table('zlecenie', function (Blueprint $table) {
            $table->dropColumn(['data_produkcji', 'data_waznosci', 'numer_partii']);
        });
    }
};