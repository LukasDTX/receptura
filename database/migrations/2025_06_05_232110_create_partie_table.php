<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partie', function (Blueprint $table) {
            $table->id();
            $table->string('numer_partii')->unique()->comment('Unikalny numer partii');
            $table->foreignId('zlecenie_id')->constrained('zlecenie')->onDelete('restrict');
            $table->foreignId('produkt_id')->constrained('produkt')->onDelete('restrict');
            $table->integer('ilosc_wyprodukowana')->comment('Ilość rzeczywiście wyprodukowana');
            $table->date('data_produkcji');
            $table->date('data_waznosci')->nullable();
            $table->enum('status', ['wyprodukowana', 'w_magazynie', 'wydana', 'wycofana'])->default('wyprodukowana');
            $table->json('surowce_uzyte')->nullable()->comment('Rzeczywiście użyte surowce z numerami partii');
            $table->decimal('koszt_produkcji', 10, 2)->default(0);
            $table->text('uwagi')->nullable();
            $table->timestamps();
            
            $table->index(['produkt_id', 'data_produkcji']);
            $table->index(['status', 'data_waznosci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partie');
    }
};