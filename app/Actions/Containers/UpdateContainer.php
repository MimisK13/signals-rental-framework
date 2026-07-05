<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Data\Containers\UpdateContainerData;
use App\Enums\ContainerScanMode;
use App\Models\Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Update editable metadata on an open container (name, barcode, notes).
 */
class UpdateContainer
{
    public function __invoke(Container $container, UpdateContainerData $data): ContainerData
    {
        Gate::authorize('update', $container);

        if (! $container->status->acceptsPacking()) {
            throw ValidationException::withMessages([
                'container' => __('Only open containers can be edited.'),
            ]);
        }

        $attributes = [];

        if ($data->name !== null) {
            $attributes['name'] = $data->name;
        }

        if ($data->barcode !== null) {
            $barcode = trim($data->barcode);

            if ($barcode === '') {
                throw ValidationException::withMessages([
                    'barcode' => __('The barcode cannot be empty.'),
                ]);
            }

            $duplicate = Container::query()
                ->active()
                ->where('barcode', $barcode)
                ->whereKeyNot($container->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'barcode' => __('This barcode is already in use by an active container.'),
                ]);
            }

            $attributes['barcode'] = $barcode;
        }

        if ($data->notes !== null) {
            $attributes['notes'] = $data->notes;
        }

        if ($data->scan_mode !== null) {
            $mode = ContainerScanMode::tryFrom($data->scan_mode);

            if ($mode === null) {
                throw ValidationException::withMessages([
                    'scan_mode' => __('The selected scan mode is invalid.'),
                ]);
            }

            $attributes['scan_mode'] = $mode;
        }

        if ($attributes === []) {
            throw ValidationException::withMessages([
                'name' => __('At least one field must be provided.'),
            ]);
        }

        $container->update($attributes);

        return ContainerData::fromModel($container->fresh() ?? $container);
    }
}
