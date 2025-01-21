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
