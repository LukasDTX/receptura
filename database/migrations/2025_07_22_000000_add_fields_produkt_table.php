<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produkt', function (Blueprint $table) {
            // Sprawdź czy kolumny już istnieją przed dodaniem
            if (!Schema::hasColumn('produkt', 'baselinker_id')) {
                $table->string('baselinker_id')->nullable()->after('kod');
            }
            if (!Schema::hasColumn('produkt', 'sync_with_baselinker')) {
                $table->boolean('sync_with_baselinker')->default(false)->after('baselinker_id');
            }
            if (!Schema::hasColumn('produkt', 'baselinker_stock')) {
                $table->integer('baselinker_stock')->nullable()->after('sync_with_baselinker');
            }
            if (!Schema::hasColumn('produkt', 'baselinker_price')) {
                $table->decimal('baselinker_price', 10, 2)->nullable()->after('baselinker_stock');
            }
            if (!Schema::hasColumn('produkt', 'last_baselinker_sync')) {
                $table->timestamp('last_baselinker_sync')->nullable()->after('baselinker_price');
            }
            if (!Schema::hasColumn('produkt', 'stan_magazynowy')) {
                $table->integer('stan_magazynowy')->default(0)->after('last_baselinker_sync');
            }
            if (!Schema::hasColumn('produkt', 'ean')) {
                $table->string('ean')->nullable()->after('stan_magazynowy');
            }
            if (!Schema::hasColumn('produkt', 'waga')) {
                $table->decimal('waga', 8, 3)->default(0)->after('ean');
            }
            if (!Schema::hasColumn('produkt', 'zdjecie')) {
                $table->string('zdjecie')->nullable()->after('waga');
            }
            if (!Schema::hasColumn('produkt', 'baselinker_data')) {
                $table->json('baselinker_data')->nullable()->after('zdjecie');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produkt', function (Blueprint $table) {
            $columnsToCheck = [
                'baselinker_id',
                'sync_with_baselinker', 
                'baselinker_stock',
                'baselinker_price',
                'last_baselinker_sync',
                'stan_magazynowy',
                'ean',
                'waga',
                'zdjecie',
                'baselinker_data'
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('produkt', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};