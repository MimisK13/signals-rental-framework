<?php

declare(strict_types=1);

namespace App\Domain\Attachments;

use App\Domain\Attachments\Models\Attachment;
use App\Domain\Attachments\Policies\AttachmentPolicy;
use App\Domain\Attachments\Presentation\Livewire\FileUploadModal;
use App\Domain\Attachments\Presentation\Livewire\IconUpload;
use App\Domain\Attachments\Presentation\Livewire\MemberFiles;
use App\Domain\Attachments\Presentation\Livewire\OpportunityFiles;
use App\Domain\Attachments\Presentation\Livewire\ProductFiles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AttachmentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Gate::policy(Attachment::class, AttachmentPolicy::class);

        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'attachments');
        Livewire::addComponent(name: 'components.file-upload-modal', class: FileUploadModal::class);
        Livewire::addComponent(name: 'components.icon-upload', class: IconUpload::class);
        Livewire::addComponent(name: 'members.files', class: MemberFiles::class);
        Livewire::addComponent(name: 'products.files', class: ProductFiles::class);
        Livewire::addComponent(name: 'opportunities.files', class: OpportunityFiles::class);

        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');
    }
}
