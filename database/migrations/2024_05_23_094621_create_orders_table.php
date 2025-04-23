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
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); // use this to generate order number
            $table->uuid('order_guid')->nullable(true); // use this for update via api so that we do not expose sequential id
            $table->uuid('actor_id')->nullable(true);
            $table->string('order_number')->nullable(true); // this is order numebr communicated to the the world
            $table->string('order_status')->nullable(true);
            $table->uuid('order_payment_method_configuration_id')->nullable(true); // latest payment method configuration used for this order, useful before any payment is made and for review
            $table->unsignedBigInteger('currency_rate_id')->nullable(true);
            $table->char('order_currency_code', 3)->nullable(true);
            $table->decimal('order_base_currency_subtotal', 13, 2)->nullable(true);
            $table->decimal('order_base_currency_tax_amount', 13, 2)->nullable(true);
            $table->decimal('order_base_currency_discount_amount', 13, 2)->nullable(true);
            $table->decimal('order_base_currency_grand_total', 13, 2)->nullable(true);
            $table->decimal('order_subtotal', 13, 2)->nullable(true); // Order subtotal before taxes and discounts
            $table->decimal('order_tax_amount', 13, 2)->nullable(true);
            $table->decimal('order_discount_amount', 13, 2)->nullable(true);
            $table->decimal('order_grand_total', 13, 2)->nullable(true); // Order total including taxes and discounts
            $table->string('correlation_token')->nullable(true);
            $table->dateTime('expires_at')->nullable(true); // Session expiration time
            $table->tinyInteger('fulfillment_fail_notif_sent')->nullable(true);
            $table->tinyInteger('lock_currency')->nullable(true);
            $table->timestamps();

            $table->foreign('currency_rate_id')->references('id')->on('currency_rates');

            $table->unique('order_guid');
            $table->unique('order_number');
            $table->index('order_status');
            $table->index('order_payment_method_configuration_id');
            $table->index('order_currency_code');
            $table->index('expires_at');
            $table->index('fulfillment_fail_notif_sent');
            $table->index('lock_currency');
            $table->index('correlation_token');
            $table->index('created_at');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
