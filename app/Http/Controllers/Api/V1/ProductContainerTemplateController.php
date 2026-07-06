<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\UpdateProductContainerConfig;
use App\Data\Products\ProductContainerConfigData;
use App\Data\Products\UpdateProductContainerConfigData;
use App\Http\Controllers\Api\Controller;
use App\Models\Container;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ProductContainerTemplateController extends Controller
{
    /**
     * Show container configuration for a product.
     */
    #[ApiResponse(200, 'Product container configuration', type: 'array{container_template: array<string, mixed>}')]
    public function show(Product $product): JsonResponse
    {
        $this->authorizeRead();

        return $this->respondWith(
            ProductContainerConfigData::fromModel($product)->toArray(),
            'container_template',
        );
    }

    /**
     * Update container configuration for a product.
     */
    #[ApiResponse(200, 'Updated product container configuration', type: 'array{container_template: array<string, mixed>}')]
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorizeWrite();

        $validated = $request->validate(UpdateProductContainerConfigData::rules());
        $dto = UpdateProductContainerConfigData::from($validated);

        $result = (new UpdateProductContainerConfig)($product, $dto);

        return $this->respondWith($result->toArray(), 'container_template');
    }

    private function authorizeRead(): void
    {
        Gate::authorize('products.view');
        $this->ensureTokenAbility('products:read');
    }

    private function authorizeWrite(): void
    {
        Gate::authorize('manageTemplates', Container::class);
        $this->ensureTokenAbility('products:write');
    }

    private function ensureTokenAbility(string $ability): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = request()->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability)) {
            abort(Response::HTTP_FORBIDDEN, "Token does not have the required ability: {$ability}");
        }
    }
}
