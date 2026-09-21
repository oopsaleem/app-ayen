# Order pricing is computed server-side; client-sent prices are ignored

The original NestJS system computed `total`, `vat`, `delivery`, and every `OrderDish.unitPrice`/
`totalPrice` on the client and stored whatever the `createOrder` mutation sent, with no
server-side recomputation — a trust-the-client design flagged as a bug to fix, not copy
(`idea/02-features.md`). Considered keeping that shape for speed, and rejected it: it lets a
client submit arbitrary prices for a real financial transaction.

Chosen: the client sends only selections (`dish_id`, `serving_size_id?`, `dish_option_id[]`,
`quantity`, `delivery_mode`, `delivery_address_id?`) per order line. The server looks up each
`Dish`/`ServingSize`/`DishOption`'s *current* price, computes `OrderDish.unit_price`/`total_price`
itself, and computes the order's `subtotal`/`vat`/`delivery_fee`/`total` from
`config('orders.vat_rate')` and `config('orders.delivery_fee')` (flat, global — no per-restaurant
fee overrides yet; revisit if/when restaurant-specific pricing is needed). Any price-shaped field
in the request body is ignored, not validated-against — there is nothing for the client to get
"wrong," because it never supplies a price.
