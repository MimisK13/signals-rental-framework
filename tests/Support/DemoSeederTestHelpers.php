<?php

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function makeDemoDataSeeder(): DemoDataSeeder
{
    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    return (new DemoDataSeeder)->setContainer(app())->setCommand($command);
}

function invokeDemoSeederMethod(string $method, ?DemoDataSeeder $seeder = null): mixed
{
    $seeder ??= makeDemoDataSeeder();

    return (new ReflectionMethod(DemoDataSeeder::class, $method))->invoke($seeder);
}

/**
 * Invoke DemoDataSeeder::createDemoProducts() in isolation (a private method)
 * without triggering the multi-thousand-row member seed in run(). A silent
 * command is attached so the seeder's $this->command->info() calls succeed.
 */
function seedDemoProducts(): void
{
    invokeDemoSeederMethod('createDemoProducts');
}

/**
 * Invoke DemoDataSeeder::createDemoOpportunities() in isolation (a private
 * method) without triggering the multi-thousand-row member seed in run().
 */
function seedDemoOpportunities(): void
{
    invokeDemoSeederMethod('createDemoOpportunities');
}

function seedDemoMemberEnrichment(): void
{
    invokeDemoSeederMethod('enrichDemoMembers');
}

function seedDemoProductEnrichment(): void
{
    invokeDemoSeederMethod('enrichDemoProducts');
}

function seedDemoStockLevels(): void
{
    invokeDemoSeederMethod('seedDemoStockLevels');
}

function seedDemoInvoices(): void
{
    invokeDemoSeederMethod('createDemoInvoices');
}

function seedDemoPurchaseOrders(): void
{
    invokeDemoSeederMethod('createDemoPurchaseOrders');
}

function seedDemoContainers(): void
{
    invokeDemoSeederMethod('createDemoContainers');
}
