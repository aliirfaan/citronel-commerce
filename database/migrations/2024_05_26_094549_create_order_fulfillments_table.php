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
        Schema::create('order_fulfillments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_item_id');
            $table->uuid('actor_id')->nullable(true);
            $table->unsignedBigInteger('order_id'); // redundant, but for easy access
            $table->string('product_id');
            $table->text('order_item_meta')->nullable(true);
            $table->string('order_item_fulfillment_status')->nullable(true);
            $table->string('reseller_order_reference');
            $table->string('previous_reseller_order_reference')->nullable(true);
            $table->string('supplier_order_id')->nullable(true); //updated by supplier
            $table->string('correlation_token')->nullable(true);
            $table->dateTime('fulfilled_at')->nullable(true);
            $table->integer('retry_count')->nullable(true);
            $table->string('result_code')->nullable(true);
            $table->text('result_message')->nullable(true);
            $table->string('product_code')->nullable(true); // external code to identify product like a SKU
            $table->string('custom_value_1')->nullable(true);
            $table->string('custom_value_2')->nullable(true);
            $table->string('custom_value_3')->nullable(true);
            $table->string('custom_value_4')->nullable(true);
            $table->string('custom_value_5')->nullable(true);
            $table->timestamps();
            

            $table->foreign('order_item_id')->references('id')->on('order_items');

            $table->index('actor_id');
            $table->index('order_id');
            $table->index('product_id');
            $table->index('order_item_fulfillment_status');
            $table->index('reseller_order_reference');
            $table->index('previous_reseller_order_reference');
            $table->index('supplier_order_id');
            $table->index('correlation_token');
            $table->index('fulfilled_at');
            $table->index('retry_count');
            $table->index('result_code');
            $table->index('product_code');
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
        Schema::dropIfExists('order_fulfillments');
    }
};
