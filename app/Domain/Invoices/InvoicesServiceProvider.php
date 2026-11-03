<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Console\ProcessOverdueInvoices;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Policies\CreditNotePolicy;
use App\Domain\Invoices\Policies\InvoicePolicy;
use App\Domain\Invoices\Presentation\Livewire\Communications;
use App\Domain\Invoices\Presentation\Livewire\CreditNotesIndex;
use App\Domain\Invoices\Presentation\Livewire\Form;
use App\Domain\Invoices\Presentation\Livewire\Index;
use App\Domain\Invoices\Presentation\Livewire\OpportunityInvoices;
use App\Domain\Invoices\Presentation\Livewire\PaymentsIndex;
use App\Domain\Invoices\Presentation\Livewire\Show;
use App\Domain\Invoices\Services\InvoiceStatusRegistry;
use App\Domain\Invoices\Settings\InvoiceSettings;
use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class InvoicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InvoiceStatusRegistry::class);
        $this->app->afterResolving(SettingsRegistry::class, function (SettingsRegistry $registry): void {
            $registry->register(new InvoiceSettings);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'invoices');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        $this->commands([ProcessOverdueInvoices::class]);
        Livewire::addComponent(name: 'invoices.index', class: Index::class);
        Livewire::addComponent(name: 'invoices.form', class: Form::class);
        Livewire::addComponent(name: 'invoices.show', class: Show::class);
        Livewire::addComponent(name: 'invoices.communications', class: Communications::class);
        Livewire::addComponent(name: 'opportunities.invoices', class: OpportunityInvoices::class);
        Livewire::addComponent(name: 'credit-notes.index', class: CreditNotesIndex::class);
        Livewire::addComponent(name: 'payments.index', class: PaymentsIndex::class);
    }
}
