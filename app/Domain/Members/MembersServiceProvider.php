<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Models\Member;
use App\Domain\Members\Policies\MemberPolicy;
use App\Domain\Members\Presentation\Columns\MemberColumnRegistry;
use App\Domain\Members\Presentation\Livewire\Addresses;
use App\Domain\Members\Presentation\Livewire\AddressForm;
use App\Domain\Members\Presentation\Livewire\CustomFields;
use App\Domain\Members\Presentation\Livewire\EmailForm;
use App\Domain\Members\Presentation\Livewire\Emails;
use App\Domain\Members\Presentation\Livewire\Form;
use App\Domain\Members\Presentation\Livewire\Index;
use App\Domain\Members\Presentation\Livewire\Information;
use App\Domain\Members\Presentation\Livewire\Invoices;
use App\Domain\Members\Presentation\Livewire\LinkForm;
use App\Domain\Members\Presentation\Livewire\Links;
use App\Domain\Members\Presentation\Livewire\MemberContacts;
use App\Domain\Members\Presentation\Livewire\MergeModal;
use App\Domain\Members\Presentation\Livewire\Opportunities;
use App\Domain\Members\Presentation\Livewire\PhoneForm;
use App\Domain\Members\Presentation\Livewire\Phones;
use App\Domain\Members\Presentation\Livewire\Quotes;
use App\Domain\Members\Presentation\Livewire\RelationshipForm;
use App\Domain\Members\Presentation\Livewire\Relationships;
use App\Domain\Members\Presentation\Livewire\Show;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class MembersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(ColumnRegistryResolver::class, function (ColumnRegistryResolver $resolver): void {
            $resolver->register('members', MemberColumnRegistry::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'members');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/Presentation/Routes/api.php');

        Gate::policy(Member::class, MemberPolicy::class);

        foreach ([
            'members.index' => Index::class,
            'members.form' => Form::class,
            'members.show' => Show::class,
            'members.information' => Information::class,
            'members.custom-fields' => CustomFields::class,
            'members.relationships' => Relationships::class,
            'members.relationship-form' => RelationshipForm::class,
            'members.member-contacts' => MemberContacts::class,
            'members.opportunities' => Opportunities::class,
            'members.invoices' => Invoices::class,
            'members.quotes' => Quotes::class,
            'members.addresses' => Addresses::class,
            'members.address-form' => AddressForm::class,
            'members.emails' => Emails::class,
            'members.email-form' => EmailForm::class,
            'members.phones' => Phones::class,
            'members.phone-form' => PhoneForm::class,
            'members.links' => Links::class,
            'members.link-form' => LinkForm::class,
            'members.merge-modal' => MergeModal::class,
        ] as $name => $class) {
            Livewire::addComponent(name: $name, class: $class);
        }
    }
}
