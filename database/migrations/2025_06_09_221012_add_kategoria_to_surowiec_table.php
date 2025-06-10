<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surowiec', function (Blueprint $table) {
            $table->string('kategoria', 10)
                  ->nullable()
                  ->after('jednostka_miary')
                  ->comment('Kategoria surowca z enum KategoriaSurowca');
                  
            // Alternatywnie jako enum SQL:
            // $table->enum('kategoria', ['OE', 'S', 'E', 'P', 'O', 'M', 'D', 'K', 'W', 'MIN', 'A'])
            //       ->nullable()
            //       ->after('jednostka_miary');
        });
    }

    public function down(): void
    {
        Schema::table('surowiec', function (Blueprint $table) {
            $table->dropColumn('kategoria');
        });
    }
};