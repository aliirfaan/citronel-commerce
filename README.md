# Citronel commerce

Simple order and payment processing for Laravel API project.

## Dependencies
- [aliirfaan/citronel-core](https://github.com/aliirfaan/citronel-core)
- [aliirfaan/citronel-job](https://github.com/aliirfaan/citronel-job)

## Features
* Product
- Keep products/services in a product table with product configurations. 
- Each product may use contracts and have a product class that allows you customize order process per product. 
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

### Features
* product_class
Each product has a product class that extends traits.
* fulfillment_type
Each product may be fulfillemt type as sync or async(queue).
* allow_transaction
Transactions/payments can be disabled per product.
* allow_manual_retry
Alow manual fulfillment in case of failure.

### Contracts
* AbstractCitronelProduct  
Each product class must extend AbstractCitronelProduct.  
A product class may also implement contracts found in ```src/Contracts/Product```

## Order

### Config
* citronel-order.php

### Features
* fulfillment failure notification

## Payment method

## Payment method configuration

## Payment

## Currency

## Fulfillment

## Manual fulfillment

## Refund

## Create a migration to link order to an actor
```bash
$ php artisan make:migration alter_actor_id_in_orders_table --table=orders
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

## Routes
* Publish routes if you want to override them
``` bash
$ php artisan vendor:publish --tag=citronel-commerce-routes
```
* Make sure to review routes and remove endpoints you do not want to expose

* Review route prefix

## Middleware
* Review route middleware  
Add policy to check if linked actor is the one doing the action: // authorize - MatchActorToken middleware  
```php
\aliirfaan\CitronelAuth\Http\Middleware\Actor\EnsureActorIsVerified::class,
\aliirfaan\CitronelAuth\Http\Middleware\Actor\EnsureActorIsActive::class,
ActorTokenIsValid,
MatchActorToken
```

## Extend citronel order
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