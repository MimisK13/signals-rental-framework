<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Tax\Actions\CreateTaxRate;
use App\Domain\Tax\Actions\CreateTaxRule;
use App\Domain\Tax\Actions\DeleteTaxRate;
use App\Domain\Tax\Actions\DeleteTaxRule;
use App\Domain\Tax\Actions\UpdateTaxRate;
use App\Domain\Tax\Actions\UpdateTaxRule;
use App\Domain\Tax\Data\CreateTaxRateData;
use App\Domain\Tax\Data\CreateTaxRuleData;
use App\Domain\Tax\Data\UpdateTaxRateData;
use App\Domain\Tax\Data\UpdateTaxRuleData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);

    Webhook::factory()->create([
        'user_id' => $this->owner->id,
        'events' => [
            'tax_rate.created', 'tax_rate.updated', 'tax_rate.deleted',
            'tax_rule.created', 'tax_rule.updated', 'tax_rule.deleted',
        ],
        'is_active' => true,
    ]);
});

it('dispatches tax_rate.created webhook when creating a tax rate', function () {
    Queue::fake();

    $rate = (new CreateTaxRate)(CreateTaxRateData::from([
        'name' => 'New Rate',
        'rate' => '20.0000',
    ]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rate.created'
        && $job->payload['tax_rate']['id'] === $rate->id
        && $job->payload['tax_rate']['name'] === 'New Rate');
});

it('dispatches tax_rate.updated webhook when updating a tax rate', function () {
    Queue::fake();
    $rate = TaxRate::factory()->create();

    (new UpdateTaxRate)($rate, UpdateTaxRateData::from(['name' => 'Updated Rate']));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rate.updated'
        && $job->payload['tax_rate']['id'] === $rate->id);
});

it('dispatches tax_rate.deleted webhook when deleting a tax rate', function () {
    Queue::fake();
    $rate = TaxRate::factory()->create();
    $id = $rate->id;

    (new DeleteTaxRate)($rate);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rate.deleted'
        && $job->payload['id'] === $id);
});

it('dispatches tax_rule.created webhook when creating a tax rule', function () {
    Queue::fake();
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $rate = TaxRate::factory()->create();

    $rule = (new CreateTaxRule)(CreateTaxRuleData::from([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $rate->id,
    ]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rule.created'
        && $job->payload['tax_rule']['id'] === $rule->id);
});

it('dispatches tax_rule.updated webhook when updating a tax rule', function () {
    Queue::fake();
    $rule = TaxRule::factory()->create();

    (new UpdateTaxRule)($rule, UpdateTaxRuleData::from(['priority' => 5]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rule.updated'
        && $job->payload['tax_rule']['id'] === $rule->id);
});

it('dispatches tax_rule.deleted webhook when deleting a tax rule', function () {
    Queue::fake();
    $rule = TaxRule::factory()->create();
    $id = $rule->id;

    (new DeleteTaxRule)($rule);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rule.deleted'
        && $job->payload['id'] === $id);
});

it('does not dispatch tax webhooks when no active webhook exists', function () {
    Queue::fake();
    Webhook::query()->delete();

    (new CreateTaxRate)(CreateTaxRateData::from(['name' => 'Silent', 'rate' => '20.0000']));

    Queue::assertNotPushed(DeliverWebhook::class);
});
