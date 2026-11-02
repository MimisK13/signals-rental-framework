<?php

declare(strict_types=1);

namespace App\Domain\Communications;

use App\Domain\Communications\Console\PruneCommunicationLog;
use App\Domain\Communications\Listeners\RecordCustomerCommunicationDelivery;
use App\Domain\Communications\Models\CommunicationLog;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Models\EmailTemplate;
use App\Domain\Communications\Policies\CommunicationLogPolicy;
use App\Domain\Communications\Policies\CommunicationTemplatePolicy;
use App\Domain\Communications\Policies\EmailTemplatePolicy;
use App\Domain\Communications\Presentation\Livewire\CommunicationLog as CommunicationLogPage;
use App\Domain\Communications\Presentation\Livewire\CommunicationTemplateEditor;
use App\Domain\Communications\Presentation\Livewire\EmailTemplateForm;
use App\Domain\Communications\Presentation\Livewire\EmailTemplates;
use App\Domain\Communications\Presentation\Livewire\MemberCommunications;
use App\Domain\Communications\Presentation\Livewire\OpportunityCommunications;
use App\Domain\Communications\Services\ChannelProviderRegistry;
use App\Domain\Communications\Services\CommunicationLogStatusRegistry;
use App\Domain\Communications\Services\CommunicationTemplateRenderer;
use App\Domain\Communications\Services\CoreMergeFieldFilters;
use App\Domain\Communications\Services\Drivers\BroadcastDriver;
use App\Domain\Communications\Services\Drivers\DatabaseDriver;
use App\Domain\Communications\Services\Drivers\EmailDriver;
use App\Domain\Communications\Services\MergeFieldFilterRegistry;
use App\Domain\Communications\Services\MergeFieldRenderer;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class CommunicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelProviderRegistry::class, function ($app): ChannelProviderRegistry {
            $registry = new ChannelProviderRegistry($app);
            $registry->register('email', EmailDriver::class);
            $registry->register('database', DatabaseDriver::class);
            $registry->register('broadcast', BroadcastDriver::class);

            return $registry;
        });

        $this->app->singleton(CommunicationLogStatusRegistry::class);
        $this->app->singleton(MergeFieldFilterRegistry::class, function (): MergeFieldFilterRegistry {
            $registry = new MergeFieldFilterRegistry;
            CoreMergeFieldFilters::register($registry);

            return $registry;
        });
        $this->app->singleton(MergeFieldRenderer::class);
        $this->app->singleton(CommunicationTemplateRenderer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'communications');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(CommunicationLog::class, CommunicationLogPolicy::class);
        Gate::policy(CommunicationTemplate::class, CommunicationTemplatePolicy::class);
        Gate::policy(EmailTemplate::class, EmailTemplatePolicy::class);

        $this->commands([PruneCommunicationLog::class]);

        Livewire::addComponent(name: 'admin.settings.email-templates', class: EmailTemplates::class);
        Livewire::addComponent(name: 'admin.settings.email-template-form', class: EmailTemplateForm::class);
        Livewire::addComponent(name: 'admin.settings.communication-template-editor', class: CommunicationTemplateEditor::class);
        Livewire::addComponent(name: 'admin.settings.communication-log', class: CommunicationLogPage::class);
        Livewire::addComponent(name: 'members.communications', class: MemberCommunications::class);
        Livewire::addComponent(name: 'opportunities.communications', class: OpportunityCommunications::class);

        Event::listen(NotificationSent::class, [RecordCustomerCommunicationDelivery::class, 'handle']);
    }
}
