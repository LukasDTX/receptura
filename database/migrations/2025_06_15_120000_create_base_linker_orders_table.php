<?php
// database/migrations/xxxx_xx_xx_create_base_linker_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_linker_orders', function (Blueprint $table) {
            $table->id();
            
            // Podstawowe dane zamówienia
            $table->bigInteger('baselinker_order_id')->unique();
            $table->bigInteger('shop_order_id')->default(0);
            $table->string('external_order_id')->nullable();
            $table->string('order_source')->nullable();
            $table->integer('order_source_id')->default(0);
            $table->string('order_source_info')->nullable();
            $table->integer('order_status_id')->default(0);
            
            // Statusy
            $table->boolean('confirmed')->default(false);
            $table->timestamp('date_confirmed')->nullable();
            $table->timestamp('date_add');
            $table->timestamp('date_in_status')->nullable();
            
            // Dane użytkownika
            $table->string('user_login')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('user_comments')->nullable();
            $table->text('admin_comments')->nullable();
            
            // Płatność
            $table->string('currency', 3)->default('PLN');
            $table->string('payment_method')->nullable();
            $table->string('payment_method_cod')->nullable();
            $table->boolean('payment_done')->default(false);
            
            // Dostawa
            $table->string('delivery_method')->nullable();
            $table->decimal('delivery_price', 10, 2)->default(0);
            $table->string('delivery_package_module')->nullable();
            $table->string('delivery_package_nr')->nullable();
            
            // Adres dostawy
            $table->string('delivery_fullname')->nullable();
            $table->string('delivery_company')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_city')->nullable();
            $table->string('delivery_state')->nullable();
            $table->string('delivery_postcode')->nullable();
            $table->string('delivery_country_code', 2)->nullable();
            
            // Punkt odbioru
            $table->string('delivery_point_id')->nullable();
            $table->string('delivery_point_name')->nullable();
            $table->string('delivery_point_address')->nullable();
            $table->string('delivery_point_postcode')->nullable();
            $table->string('delivery_point_city')->nullable();
            
            // Dane do faktury
            $table->string('invoice_fullname')->nullable();
            $table->string('invoice_company')->nullable();
            $table->string('invoice_nip')->nullable();
            $table->string('invoice_address')->nullable();
            $table->string('invoice_city')->nullable();
            $table->string('invoice_state')->nullable();
            $table->string('invoice_postcode')->nullable();
            $table->string('invoice_country_code', 2)->nullable();
            $table->boolean('want_invoice')->default(false);
            
            // Dodatkowe pola
            $table->string('extra_field_1')->nullable();
            $table->string('extra_field_2')->nullable();
            $table->string('order_page')->nullable();
            $table->integer('pick_state')->default(0);
            $table->integer('pack_state')->default(0);
            $table->string('delivery_country')->nullable();
            $table->string('invoice_country')->nullable();
            
            // Produkty (JSON)
            $table->json('products');
            
            // Synchronizacja
            $table->timestamp('synced_at');
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['baselinker_order_id']);
            $table->index(['order_source', 'date_add']);
            $table->index(['confirmed', 'date_add']);
            $table->index(['payment_done']);
            $table->index(['email']);
            $table->index(['delivery_package_nr']);
            $table->index(['date_add']);
            $table->index(['synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_linker_orders');
    }
};