<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * For some orders, we may need to use/restrict different payment method configurations
     */
    public function up(): void
    {
        Schema::create('order_payment_method_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_processing_strategy_name');
            $table->uuid('payment_method_configuration_id');
            $table->timestamps();

            $table->index('order_processing_strategy_name', 'order_processing_strategy_name_index');
            $table->foreign('payment_method_configuration_id', 'order_payment_method_configuration_id_foreign')->references('id')->on('payment_method_configurations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_payment_method_configurations');
    }
};
