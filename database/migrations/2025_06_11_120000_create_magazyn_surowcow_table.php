<?php
// database/migrations/2025_06_11_120000_create_magazyn_surowcow_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela partii surowców
        Schema::create('partie_surowcow', function (Blueprint $table) {
            $table->id();
            $table->string('numer_partii')->unique()->comment('Unikalny numer partii surowca');
            $table->foreignId('surowiec_id')->constrained('surowiec')->onDelete('restrict');
            $table->string('numer_partii_dostawcy')->nullable()->comment('Numer partii od dostawcy');
            $table->decimal('masa_brutto', 10, 3)->comment('Masa brutto opakowania w kg');
            $table->decimal('masa_netto', 10, 3)->comment('Masa netto surowca w kg');
            $table->decimal('masa_pozostala', 10, 3)->comment('Masa pozostała do użycia w kg');
            $table->string('typ_opakowania')->comment('np. worek, pojemnik, beczka');
            $table->decimal('cena_za_kg', 10, 2)->comment('Cena za kg tego surowca w tej partii');
            $table->date('data_przyjecia')->comment('Data przyjęcia do magazynu');
            $table->date('data_waznosci')->nullable()->comment('Data ważności surowca');
            $table->date('data_otwarcia')->nullable()->comment('Data pierwszego użycia/otwarcia');
            $table->enum('status', ['nowa', 'otwarta', 'zuzyta', 'wycofana'])->default('nowa');
            $table->string('lokalizacja_magazyn')->nullable()->comment('Lokalizacja w magazynie');
            $table->text('uwagi')->nullable();
            $table->timestamps();
            
            $table->index(['surowiec_id', 'status', 'data_przyjecia']); // FIFO
            $table->index(['data_waznosci']);
            $table->index(['status']);
        });

        // Tabela magazynu produkcji (częściowo zużyte surowce)
        Schema::create('magazyn_produkcji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partia_surowca_id')->constrained('partie_surowcow')->onDelete('cascade');
            $table->decimal('masa_dostepna', 10, 3)->comment('Masa dostępna w kg');
            $table->string('lokalizacja')->nullable()->comment('Lokalizacja w magazynie produkcji');
            $table->date('data_przeniesienia')->comment('Kiedy przeniesiono z magazynu głównego');
            $table->timestamps();
            
            $table->index(['partia_surowca_id', 'masa_dostepna']);
        });

        // Tabela ruchów surowców (szczegółowa historia)
        Schema::create('ruchy_surowcow', function (Blueprint $table) {
            $table->id();
            $table->string('numer_dokumentu')->nullable();
            $table->enum('typ_ruchu', ['przyjecie', 'wydanie_do_produkcji', 'przeniesienie', 'korekta', 'wycofanie']);
            $table->foreignId('partia_surowca_id')->constrained('partie_surowcow')->onDelete('restrict');
            $table->foreignId('zlecenie_id')->nullable()->constrained('zlecenie')->onDelete('set null');
            $table->decimal('masa', 10, 3)->comment('Masa w kg (+ przyjęcie, - wydanie)');
            $table->decimal('masa_przed', 10, 3)->comment('Stan przed ruchem');
            $table->decimal('masa_po', 10, 3)->comment('Stan po ruchu');
            $table->string('skad')->nullable()->comment('Skąd (magazyn/produkcja)');
            $table->string('dokad')->nullable()->comment('Dokąd (produkcja/zużycie)');
            $table->date('data_ruchu');
            $table->text('uwagi')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['partia_surowca_id', 'data_ruchu']);
            $table->index(['zlecenie_id']);
            $table->index(['data_ruchu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruchy_surowcow');
        Schema::dropIfExists('magazyn_produkcji');
        Schema::dropIfExists('partie_surowcow');
    }
};