<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Actions\DeleteProduct;
use App\Domain\Products\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('deletes a product via soft delete', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create();

    (new DeleteProduct)($product);

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('records an action_logs row when a product is deleted', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();

    (new DeleteProduct)($product);

    assertActionLogged('product.deleted', Product::class, $product->id, $user->id);
});

it('requires products.delete permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();

    (new DeleteProduct)($product);
})->throws(AuthorizationException::class);
