<?php

namespace App\Actions\Orders;

use App\Enums\DeliveryMode;
use App\Enums\OrderStatus;
use App\Models\DeliveryAddress;
use App\Models\Dish;
use App\Models\DishOption;
use App\Models\Order;
use App\Models\ServingSize;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A validated order line: the dish being ordered, the selected serving size
 * (if any), the selected options, and the quantity.
 *
 * @phpstan-type OrderLine array{dish: Dish, serving_size: ?ServingSize, options: array<int, DishOption>, quantity: int}
 */
class CreateOrder
{
    /**
     * Place a new order for the given customer.
     *
     * The client sends only selections (ADR-0007); every price is looked up
     * from the *current* Dish/ServingSize/DishOption pricing here. The order's
     * restaurant is derived from its dishes (ADR-0009) and split-restaurant
     * orders are rejected.
     *
     * @param  array<string, mixed>  $data  validated request data
     */
    public function handle(User $user, array $data): Order
    {
        $lines = $data['lines'];
        $deliveryMode = DeliveryMode::from((string) $data['delivery_mode']);

        $dishes = Dish::query()
            ->with('kitchen')
            ->whereIn('id', $this->pluck($lines, 'dish_id'))
            ->get()
            ->keyBy('id');

        $servingSizes = ServingSize::query()
            ->whereIn('id', $this->pluck($lines, 'serving_size_id'))
            ->get()
            ->keyBy('id');

        $options = DishOption::query()
            ->whereIn('id', $this->lineOptionIds($lines))
            ->get()
            ->keyBy('id');

        $orderLines = [];
        $restaurantId = null;

        foreach ($lines as $index => $line) {
            $dish = $dishes[$line['dish_id']] ?? null;
            $lineServingSize = ($line['serving_size_id'] ?? null) !== null
                ? ($servingSizes[$line['serving_size_id']] ?? null)
                : null;

            $lineOptions = [];
            foreach ($line['option_ids'] ?? [] as $optionId) {
                $lineOptions[] = $options[$optionId] ?? null;
            }

            if ($dish === null || ! $dish->is_available) {
                throw $this->invalid("lines.$index.dish_id");
            }

            if (($line['serving_size_id'] ?? null) !== null
                && ($lineServingSize === null || $lineServingSize->dish_id !== $dish->id)) {
                throw $this->invalid("lines.$index.serving_size_id");
            }

            foreach ($lineOptions as $option) {
                if ($option === null || $option->dish_id !== $dish->id || ! $option->is_available) {
                    throw $this->invalid("lines.$index.option_ids");
                }
            }

            if ($restaurantId !== null && $dish->kitchen->restaurant_id !== $restaurantId) {
                throw ValidationException::withMessages([
                    'lines' => __('An order may only contain dishes from one restaurant.'),
                ]);
            }

            $restaurantId = $dish->kitchen->restaurant_id;

            $orderLines[] = [
                'dish' => $dish,
                'serving_size' => $lineServingSize,
                'options' => $lineOptions,
                'quantity' => $line['quantity'],
            ];
        }

        $deliveryAddressId = $this->resolveDeliveryAddress($user, $deliveryMode, $data['delivery_address_id'] ?? null);

        return DB::transaction(function () use ($user, $orderLines, $restaurantId, $deliveryAddressId, $deliveryMode): Order {
            [$subtotal, $vat, $deliveryFee, $total] = $this->totals($orderLines, $deliveryMode);

            $order = Order::create([
                'user_id' => $user->id,
                'restaurant_id' => $restaurantId,
                'delivery_mode' => $deliveryMode,
                'delivery_address_id' => $deliveryAddressId,
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'vat' => $vat,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
            ]);

            foreach ($orderLines as $line) {
                $unitPrice = $this->unitPrice($line);
                $totalPrice = round($unitPrice * $line['quantity'], 2);

                $orderDish = $order->dishes()->create([
                    'dish_id' => $line['dish']->id,
                    'serving_size_id' => $line['serving_size']?->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);

                foreach ($line['options'] as $option) {
                    $orderDish->options()->attach($option->id, ['unit_price' => $option->price]);
                }
            }

            $kitchenIds = [];
            foreach ($orderLines as $line) {
                $kitchenIds[$line['dish']->kitchen_id] = true;
            }

            foreach (array_keys($kitchenIds) as $kitchenId) {
                $order->kitchens()->create(['kitchen_id' => $kitchenId]);
            }

            return $order;
        });
    }

    /**
     * The unit price for an order line: the serving size's price when one is
     * selected, otherwise the dish's price, plus every option's price.
     *
     * @param  OrderLine  $line
     */
    private function unitPrice(array $line): float
    {
        $price = $line['serving_size']->price ?? $line['dish']->price;

        foreach ($line['options'] as $option) {
            $price += (float) $option->price;
        }

        return round((float) $price, 2);
    }

    /**
     * Compute the order totals from the validated lines.
     *
     * @param  array<int, OrderLine>  $lines
     * @return array{0: string, 1: string, 2: string, 3: string} subtotal, vat, delivery fee, total
     */
    private function totals(array $lines, DeliveryMode $deliveryMode): array
    {
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $subtotal = round($subtotal + $this->unitPrice($line) * $line['quantity'], 2);
        }

        $vat = round($subtotal * (float) config('orders.vat_rate'), 2);
        $deliveryFee = $deliveryMode === DeliveryMode::Delivery
            ? round((float) config('orders.delivery_fee'), 2)
            : 0.0;
        $total = round($subtotal + $vat + $deliveryFee, 2);

        return [$this->formatMoney($subtotal), $this->formatMoney($vat), $this->formatMoney($deliveryFee), $this->formatMoney($total)];
    }

    /**
     * Format a monetary amount with exactly two decimal places.
     */
    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Resolve the delivery address for delivery orders; it must belong to the
     * customer. Pickup orders never carry one.
     */
    private function resolveDeliveryAddress(User $user, DeliveryMode $deliveryMode, ?int $deliveryAddressId): ?int
    {
        if ($deliveryMode === DeliveryMode::Pickup) {
            return null;
        }

        $addressId = DeliveryAddress::query()
            ->where('user_id', $user->id)
            ->whereKey($deliveryAddressId)
            ->value('id');

        if ($addressId === null) {
            throw $this->invalid('delivery_address_id');
        }

        return $addressId;
    }

    /**
     * The distinct values of a field across the validated order lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private function pluck(array $lines, string $field): array
    {
        $ids = [];
        foreach ($lines as $line) {
            if (! empty($line[$field])) {
                $ids[] = (int) $line[$field];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The distinct option ids referenced across all order lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private function lineOptionIds(array $lines): array
    {
        $ids = [];
        foreach ($lines as $line) {
            foreach ($line['option_ids'] ?? [] as $optionId) {
                $ids[] = (int) $optionId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A validation error for a field the client got "wrong" by referencing a
     * dish, serving size, or option that doesn't belong together.
     */
    private function invalid(string $field): ValidationException
    {
        return ValidationException::withMessages([
            $field => __('The selected items are invalid.'),
        ]);
    }
}
