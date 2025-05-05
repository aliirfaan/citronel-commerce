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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('payment_guid')->nullable(true); // use this for update via api so that we do not expose sequential id
            $table->unsignedBigInteger('order_id');
            $table->uuid('payment_method_configuration_id');
            $table->string('payment_status');
            $table->string('gateway_merchant_transaction_no')->nullable(true); // use this to track the transaction in the gateway
            $table->char('currency_code', 3)->nullable(true);
            $table->decimal('subtotal', 13, 2)->nullable(true); // Order subtotal before taxes and discounts
            $table->decimal('tax_amount', 13, 2)->nullable(true);
            $table->decimal('discount_amount', 13, 2)->nullable(true);
            $table->decimal('grand_total', 13, 2)->nullable(true); // Order total including taxes and discounts
            $table->string('gateway_transaction_no')->nullable(true);
            $table->string('gateway_response_code')->nullable(true);
            $table->string('gateway_response_status')->nullable(true);
            $table->string('gateway_response_message')->nullable(true);
            $table->string('payment_channel')->nullable(true);
            $table->text('payment_remarks')->nullable(true);
            $table->string('gateway_first_leg_response_code')->nullable(true);
            $table->text('gateway_first_leg_response_message')->nullable(true);
            $table->string('gateway_first_leg_transaction_no_1')->nullable(true);
            $table->string('gateway_first_leg_transaction_no_2')->nullable(true);
            $table->dateTime('paid_at')->nullable(true);
            $table->dateTime('cancelled_at')->nullable(true);
            $table->dateTime('expired_at')->nullable(true);
            $table->string('card_holder')->nullable(true);
            $table->string('card_number')->nullable(true);
            $table->string('card_expiry')->nullable(true);
            $table->timestamps();

            $table->index('payment_status');
            $table->index('currency_code');
            $table->unique('payment_guid');
            $table->unique(['order_id','gateway_merchant_transaction_no']);
            $table->index('gateway_transaction_no');
            $table->index('gateway_response_code');
            $table->index('gateway_response_status');
            $table->index('gateway_response_message');
            $table->index('payment_channel');
            $table->index('gateway_first_leg_response_code');
            $table->index('gateway_first_leg_transaction_no_1');
            $table->index('gateway_first_leg_transaction_no_2');
            $table->index('paid_at');
            $table->index('cancelled_at');
            $table->index('expired_at');
            $table->index('card_holder');
            $table->index('card_number');
            $table->index('card_expiry');

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('payment_method_configuration_id')->references('id')->on('payment_method_configurations');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
