<?php

namespace App\Services\Containers;

use App\Enums\ContainerAvailabilityMode;
use App\Enums\KitComponentBinding;
use App\Enums\StockMethod;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Validate-on-save rules for product container templates
 * (serialised-containers.md §"Product Fields" / template structure).
 */
class ContainerTemplateValidator
{
    /**
     * @param  array<string, mixed>|null  $template
     *
     * @throws ValidationException
     */
    public function validate(Product $product, ?array $template, ContainerAvailabilityMode $mode): void
    {
        if ($template === null) {
            return;
        }

        $slots = $template['slots'] ?? null;

        if (! is_array($slots) || $slots === []) {
            if (array_key_exists('max_items', $template) && $template['max_items'] !== null) {
                throw ValidationException::withMessages([
                    'container_template.max_items' => $this->message('Max items cannot be set without template slots.'),
                ]);
            }

            return;
        }

        /** @var array<string, string> $errors */
        $errors = [];
        $totalQuantity = 0;

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)) {
                $errors["container_template.slots.{$index}"] = $this->message('Each template slot must be an object.');

                continue;
            }

            $productId = $slot['product_id'] ?? null;

            if ($productId === null || $productId === '') {
                $errors["container_template.slots.{$index}.product_id"] = $this->message('A product is required for each template slot.');

                continue;
            }

            if (! is_int($productId) && ! (is_string($productId) && ctype_digit($productId))) {
                $errors["container_template.slots.{$index}.product_id"] = $this->message('The product reference must be a valid product ID.');

                continue;
            }

            $productId = (int) $productId;

            if ($productId === $product->id) {
                $errors["container_template.slots.{$index}.product_id"] = $this->message('A container template cannot reference its own product.');

                continue;
            }

            $referenced = Product::withTrashed()->find($productId);

            if ($referenced === null) {
                $errors["container_template.slots.{$index}.product_id"] = $this->message('The referenced product does not exist.');

                continue;
            }

            if ($referenced->trashed()) {
                $errors["container_template.slots.{$index}.product_id"] = $this->message('The referenced product has been deleted.');

                continue;
            }

            if ($referenced->stock_method !== StockMethod::Serialised) {
                $errors["container_template.slots.{$index}.product_id"] = $this->message('Only serialised products can appear in a container template.');
            }

            $quantity = $slot['quantity'] ?? null;

            if ($quantity === null || $quantity === '') {
                $errors["container_template.slots.{$index}.quantity"] = $this->message('A positive integer quantity is required for each template slot.');
            } elseif (! is_int($quantity) && ! (is_string($quantity) && ctype_digit($quantity))) {
                $errors["container_template.slots.{$index}.quantity"] = $this->message('Quantity must be a positive whole number.');
            } else {
                $quantity = (int) $quantity;

                if ($quantity < 1) {
                    $errors["container_template.slots.{$index}.quantity"] = $this->message('Quantity must be at least 1.');
                } else {
                    $totalQuantity += $quantity;
                }
            }

            $bindingRaw = $slot['binding'] ?? null;
            $binding = is_string($bindingRaw) ? KitComponentBinding::tryFrom($bindingRaw) : null;

            match ($mode) {
                ContainerAvailabilityMode::Kit => $this->assertKitBinding($binding, $bindingRaw, $index, $errors),
                ContainerAvailabilityMode::Transport => $this->assertTransportBinding($binding, $bindingRaw, $index, $errors),
                ContainerAvailabilityMode::Hybrid => $this->assertHybridBinding($binding, $bindingRaw, $index, $errors),
            };
        }

        if (array_key_exists('max_items', $template) && $template['max_items'] !== null) {
            $maxItems = $template['max_items'];

            if (! is_int($maxItems) && ! (is_string($maxItems) && ctype_digit($maxItems))) {
                $errors['container_template.max_items'] = $this->message('Max items must be a positive whole number.');
            } else {
                $maxItems = (int) $maxItems;

                if ($maxItems < 1) {
                    $errors['container_template.max_items'] = $this->message('Max items must be at least 1.');
                } elseif ($maxItems !== $totalQuantity) {
                    $errors['container_template.max_items'] = $this->message(
                        'Max items must equal the sum of slot quantities (:sum).',
                        ['sum' => $totalQuantity],
                    );
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function assertKitBinding(?KitComponentBinding $binding, mixed $bindingRaw, int $index, array &$errors): void
    {
        if ($bindingRaw === null || $bindingRaw === '') {
            return;
        }

        if ($binding === null) {
            $errors["container_template.slots.{$index}.binding"] = $this->message('Binding must be fixed or pool.');

            return;
        }

        if ($binding !== KitComponentBinding::Fixed) {
            $errors["container_template.slots.{$index}.binding"] = $this->message('Kit mode templates require fixed binding on every slot.');
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function assertTransportBinding(?KitComponentBinding $binding, mixed $bindingRaw, int $index, array &$errors): void
    {
        if ($bindingRaw === null || $bindingRaw === '') {
            return;
        }

        if ($binding === null) {
            $errors["container_template.slots.{$index}.binding"] = $this->message('Binding must be fixed or pool.');

            return;
        }

        if ($binding !== KitComponentBinding::Pool) {
            $errors["container_template.slots.{$index}.binding"] = $this->message('Transport mode templates require pool binding on every slot.');
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function assertHybridBinding(?KitComponentBinding $binding, mixed $bindingRaw, int $index, array &$errors): void
    {
        if ($bindingRaw === null || $bindingRaw === '') {
            $errors["container_template.slots.{$index}.binding"] = $this->message('Hybrid mode requires an explicit binding on every slot.');

            return;
        }

        if ($binding === null) {
            $errors["container_template.slots.{$index}.binding"] = $this->message('Binding must be fixed or pool.');
        }
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private function message(string $text, array $replace = []): string
    {
        return (string) __($text, $replace);
    }
}
