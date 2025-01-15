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
            $table->text('description')->nullable(true);
            $table->tinyInteger('active')->default(1);
            $table->text('logo')->nullable(true);
            $table->string('product_class', 255)->nullable(true);
            $table->string('fulfillment_type', 255)->nullable(true); // synchronous, asynchronous
            $table->tinyInteger('allow_transaction')->default(1);
            $table->tinyInteger('send_order_notif')->default(0);
            $table->tinyInteger('allow_manual_retry')->default(0);
            $table->tinyInteger('max_retry_count')->default(0);
            $table->string('custom_value_1')->nullable(true);
            $table->string('custom_value_2')->nullable(true);
            $table->string('custom_value_3')->nullable(true);
            $table->string('custom_value_4')->nullable(true);
            $table->string('custom_value_5')->nullable(true);
            $table->timestamps();

            $table->index('active');
            $table->index('allow_transaction');
            $table->index('send_order_notif');
            $table->index('allow_manual_retry');
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
