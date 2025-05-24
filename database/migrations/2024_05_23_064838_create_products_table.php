<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('product_category_id')->nullable(true);
            $table->text('description')->nullable(true);
            $table->tinyInteger('active')->default(1);
            $table->text('logo')->nullable(true);
            $table->decimal('price', 13, 2)->nullable(true);
            $table->char('price_currency_code', 3)->nullable(true);
            $table->string('product_class', 255)->nullable(true);
            $table->enum('fulfillment_type', ['sync', 'async', 'none'])->default('sync');
            $table->tinyInteger('allow_transaction')->default(1);
            $table->tinyInteger('send_order_notif')->default(0);
            $table->tinyInteger('allow_auto_retry')->default(0);
            $table->tinyInteger('max_auto_retry')->default(0);
            $table->tinyInteger('allow_manual_retry')->default(0);
            $table->tinyInteger('max_manual_retry')->default(0);
            $table->text('fulfillment_conditions')->nullable(true);
            $table->string('custom_value_1')->nullable(true);
            $table->string('custom_value_2')->nullable(true);
            $table->string('custom_value_3')->nullable(true);
            $table->string('custom_value_4')->nullable(true);
            $table->string('custom_value_5')->nullable(true);
            $table->timestamps();

            $table->foreign('product_category_id')->references('id')->on('product_categories');
            $table->index('active');
            $table->index('allow_transaction');
            $table->index('send_order_notif');
            $table->index('allow_auto_retry');
            $table->index('allow_manual_retry');
            $table->index('price_currency_code');
            $table->index('custom_value_1');
            $table->index('custom_value_2');
            $table->index('custom_value_3');
            $table->index('custom_value_4');
            $table->index('custom_value_5');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
