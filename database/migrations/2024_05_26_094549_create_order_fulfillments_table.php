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
            $table->uuid('customer_id'); // guest order
            $table->unsignedBigInteger('order_id'); // redundant, but for easy access
            $table->string('product_id');
            $table->text('order_item_meta')->nullable(true);
            $table->string('order_item_fulfillment_status')->nullable(true);
            $table->string('bundle_code')->nullable(true); // redundant, but for easy access
            $table->string('reseller_order_reference');
            $table->string('previous_reseller_order_reference')->nullable(true);
            $table->string('supplier_order_id')->nullable(true); //updated by supplier
            $table->string('iccid')->nullable(true);
            $table->string('correlation_token')->nullable(true);
            $table->dateTime('fulfilled_at')->nullable(true);
            $table->integer('retry_count')->nullable(true);
            $table->string('result_code')->nullable(true);
            $table->string('result_message', 512)->nullable(true);
            $table->timestamps();
            

            $table->foreign('order_item_id')->references('id')->on('order_items');
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->index('order_id');
            $table->index('product_id');
            $table->index('order_item_fulfillment_status');
            $table->index('bundle_code');
            $table->index('reseller_order_reference');
            $table->index('previous_reseller_order_reference');
            $table->index('supplier_order_id');
            $table->index('iccid');
            $table->index('correlation_token');
            $table->index('fulfilled_at');
            $table->index('retry_count');
            $table->index('result_code');
            $table->index('result_message');
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
