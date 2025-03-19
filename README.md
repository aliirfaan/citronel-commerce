# Citronel commerce

Simple order and payment processing for Laravel API project.

## Features
* Product
- Keep products/services in a product table. 
- Each product may have use contracts and have a product class that allows you customize order process per product. 
* Order
- Create order by adding products. 
- An order contains order items.
- An order item has a product and quantity.
- Order status
* Currency
- Use currency service and contract to refresh currency rates.
* Payment
- Add different payment methods. 
- A payment method can be linked to different payment configurations.
- Use payment gateway contract to integrate with payment gateways.
- Manual payment confirmation.
- Payment status
* Fulfillment
- Once payment is completed, create order fulfillment.
- Manual fulfillment retries.
* Refund
- Payment refunds

## Product
* migrate products table
* expects service class in App/Services/Api/v1
* extends AbstractCitronelProduct implements ProductOrderItemInterface, ProductPaymentInterface, ProductOrderFulfillmentRefundInterface 

## Order

## Payment method

## Payment method configuration

## Currency

## Payment

## Fulfillment
Add columns to your order fulfillments
## Manual fulfillment

## Refund

# Use with aliirfaan/citronel-auth

## Create a migration to link order to an actor
```bash
php artisan make:migration alter_actor_id_in_orders_table --table=orders
```

```php
// use actor
use aliirfaan\CitronelAuth\Models\Actor\CitronelActor;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the existing actor_id column
            $table->dropColumn('actor_id');

            // Add the actor_id column with foreign key constraint
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained((new CitronelActor)->getTable());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['actor_id']);

            // Re-add the actor_id column as uuid and nullable
            $table->uuid('actor_id')->nullable(true);
        });
    }
}

```

## extend citornel order
### actor validation rules
```php
<?php

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use aliirfaan\CitronelCommerce\Models\Order\Order;
use aliirfaan\CitronelAuth\Models\Actor\CitronelActor;

class MyOrder extends Order
{
    public function actor(): BelongsTo
    {
        return $this->belongsTo(CitronelActor::class);
    }

    public function createValidationRules()
    {
        $validationRules = parent::createValidationRules();
        $validationRules['actor_id'] = ['bail', 'required', 'uuid'];

        return $validationRules;
    }
}
```

## update order model in config
```php
'order_model' => Models\MyOrder::class,
```

## citronel-commerce
* Add middleware to check if actor token is valid


Add policy to check if linked actor is the one doing the action: // authorize - MatchActorToken middleware

// add middleware
            \aliirfaan\CitronelAuth\Http\Middleware\Actor\EnsureActorIsVerified::class,
            \aliirfaan\CitronelAuth\Http\Middleware\Actor\EnsureActorIsActive::class,
            ActorTokenIsValid,
            MatchActorToken