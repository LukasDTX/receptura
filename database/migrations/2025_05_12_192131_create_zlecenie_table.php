<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zlecenie', function (Blueprint $table) {
            $table->id();
            $table->string('numer')->unique()->comment('Unikalny numer zlecenia produkcyjnego');
            $table->foreignId('produkt_id')->constrained('produkt')->onDelete('restrict');
            $table->integer('ilosc')->comment('Ilość produktu do wyprodukowania (w sztukach)');
            $table->date('data_zlecenia')->default(DB::raw('CURRENT_DATE'));
            $table->date('planowana_data_realizacji')->nullable();
            $table->enum('status', ['nowe', 'w_realizacji', 'zrealizowane', 'anulowane'])->default('nowe');
            $table->json('surowce_potrzebne')->nullable()->comment('Kalkulacja potrzebnych surowców');
            $table->text('uwagi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zlecenie');
    }
};
