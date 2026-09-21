<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Order pricing (ADR-0007)
    |--------------------------------------------------------------------------
    |
    | Pricing is computed server-side from the current Dish/ServingSize/
    | DishOption prices plus the flat, global settings below. The client
    | never supplies price-shaped fields.
    |
    */

    'vat_rate' => env('ORDERS_VAT_RATE', '0.15'),

    'delivery_fee' => env('ORDERS_DELIVERY_FEE', '10.00'),

];
