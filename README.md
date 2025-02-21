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

## citronel-commerce
* Add middleware to check if actor token is valid

set order_has_actor to false if order is not attached to an actor
Add policy to check if linked actor is the one doing the action: // authorize - MatchActorToken middleware


## extend aliirfaan\CitronelCommerce\Models\Order\Order
```php
<?php

use aliirfaan\CitronelCommerce\Models\Order\Order;
use aliirfaan\CitronelCore\Models\Actor\CitronelActor;

class MyOrder extends Order
{
    // actor relationship
    public function actor(): BelongsTo
    {
        return $this->belongsTo(CitronelActor::class);
    }

    // create order validation rules
    public function createValidationRules()
    {
        $actorValidationRules = ['actor_id' => ['bail', 'required', 'uuid']];

        return array_merge($actorValidationRules, parent::createValidationRules());
    }
}

```
