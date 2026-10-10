@props(['group' => 'setup'])

@php
    use App\Support\ActiveRoute;
@endphp

<nav class="s-admin-sidebar">
    {{-- Overview: link back to the admin landing page --}}
    <a href="{{ route('admin.index') }}" wire:navigate.hover
       class="s-admin-nav-item s-admin-nav-overview {{ ActiveRoute::is('admin.index') ? 'active' : '' }}">
        <flux:icon.squares-2x2 class="s-admin-nav-icon" />
        Overview
    </a>

    @if($group === 'setup')
        <div class="s-admin-sidebar-title">Setup</div>
        <a href="{{ route('admin.settings.company') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.company') ? 'active' : '' }}">
            <flux:icon.building-office class="s-admin-nav-icon" />
            Company Details
        </a>
        <a href="{{ route('admin.settings.stores') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.stores') ? 'active' : '' }}">
            <flux:icon.building-storefront class="s-admin-nav-icon" />
            Stores
        </a>
        <a href="{{ route('admin.settings.branding') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.branding') ? 'active' : '' }}">
            <flux:icon.paint-brush class="s-admin-nav-icon" />
            Branding
        </a>
    @endif

    @if($group === 'users')
        <div class="s-admin-sidebar-title">Users & Security</div>
        <a href="{{ route('admin.settings.users') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.users', 'admin.settings.users.*') ? 'active' : '' }}">
            <flux:icon.users class="s-admin-nav-icon" />
            Users
        </a>
        @can('roles.manage')
            <a href="{{ route('admin.settings.roles') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.roles', 'admin.settings.roles.*') ? 'active' : '' }}">
                <flux:icon.shield-check class="s-admin-nav-icon" />
                Roles
            </a>
        @endcan
        <a href="{{ route('admin.settings.permissions') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.permissions') ? 'active' : '' }}">
            <flux:icon.key class="s-admin-nav-icon" />
            Permissions Reference
        </a>
        <a href="{{ route('admin.settings.security') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.security') ? 'active' : '' }}">
            <flux:icon.lock-closed class="s-admin-nav-icon" />
            Security
        </a>
        <a href="{{ route('admin.settings.api') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.api') ? 'active' : '' }}">
            <flux:icon.code-bracket class="s-admin-nav-icon" />
            API Tokens
        </a>
    @endif

    @if($group === 'preferences')
        <div class="s-admin-sidebar-title">Preferences</div>
        <a href="{{ route('admin.settings.preferences') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.preferences') ? 'active' : '' }}">
            <flux:icon.adjustments-horizontal class="s-admin-nav-icon" />
            General
        </a>
        <a href="{{ route('admin.settings.email') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.email') ? 'active' : '' }}">
            <flux:icon.envelope class="s-admin-nav-icon" />
            Email
        </a>
        <a href="{{ route('admin.settings.email-templates') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.email-templates', 'admin.settings.email-templates.*') ? 'active' : '' }}">
            <flux:icon.document-text class="s-admin-nav-icon" />
            Email Templates
        </a>
        <a href="{{ route('admin.settings.notifications') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.notifications') ? 'active' : '' }}">
            <flux:icon.bell class="s-admin-nav-icon" />
            Notifications
        </a>
        <a href="{{ route('admin.settings.scheduling') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.scheduling') ? 'active' : '' }}">
            <flux:icon.calendar-days class="s-admin-nav-icon" />
            Scheduling
        </a>
        <a href="{{ route('admin.settings.integrations') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.integrations') ? 'active' : '' }}">
            <flux:icon.puzzle-piece class="s-admin-nav-icon" />
            Integrations
        </a>
    @endif

    @if($group === 'data')
        <div class="s-admin-sidebar-title">Data</div>
        @can('custom-fields.manage')
            <a href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.custom-field-groups*') ? 'active' : '' }}">
                <flux:icon.rectangle-group class="s-admin-nav-icon" />
                Custom Field Groups
            </a>
            <a href="{{ route('admin.settings.custom-fields') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.custom-fields*') ? 'active' : '' }}">
                <flux:icon.adjustments-vertical class="s-admin-nav-icon" />
                Custom Fields
            </a>
        @endcan
        <a href="{{ route('admin.settings.list-names') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.list-names*', 'admin.settings.lists*', 'admin.settings.list-values*') ? 'active' : '' }}">
            <flux:icon.list-bullet class="s-admin-nav-icon" />
            List Names
        </a>
        <a href="{{ route('admin.settings.countries') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.countries') ? 'active' : '' }}">
            <flux:icon.globe-alt class="s-admin-nav-icon" />
            Countries
        </a>
    @endif

    @if($group === 'tax')
        <div class="s-admin-sidebar-title">Tax</div>
        <a href="{{ route('admin.settings.finance') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.finance') ? 'active' : '' }}">
            <flux:icon.banknotes class="s-admin-nav-icon" />
            Finance
        </a>
        <a href="{{ route('admin.settings.tax.zones') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.zones*') ? 'active' : '' }}">
            <flux:icon.globe-alt class="s-admin-nav-icon" />
            Tax Zones
        </a>
        <a href="{{ route('admin.settings.tax.categories') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.categories*') ? 'active' : '' }}">
            <flux:icon.tag class="s-admin-nav-icon" />
            Tax Categories
        </a>
        <a href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.product-tax-classes*') ? 'active' : '' }}">
            <flux:icon.receipt-percent class="s-admin-nav-icon" />
            Product Tax Classes
        </a>
        <a href="{{ route('admin.settings.tax.organisation-tax-classes') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.organisation-tax-classes*') ? 'active' : '' }}">
            <flux:icon.building-office-2 class="s-admin-nav-icon" />
            Organisation Tax Classes
        </a>
        <a href="{{ route('admin.settings.tax.rates') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.rates*') ? 'active' : '' }}">
            <flux:icon.calculator class="s-admin-nav-icon" />
            Tax Rates
        </a>
        <a href="{{ route('admin.settings.tax.rules') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.tax.rules*') ? 'active' : '' }}">
            <flux:icon.table-cells class="s-admin-nav-icon" />
            Tax Rules
        </a>
    @endif

    @if($group === 'documents')
        <div class="s-admin-sidebar-title">Documents</div>
        @can('numbering.view')
            <a href="{{ route('admin.settings.numbering') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.numbering') ? 'active' : '' }}">
                <flux:icon.hashtag class="s-admin-nav-icon" />
                Numbering
            </a>
        @endcan
        @can('document_templates.view')
            <a href="{{ route('admin.document-templates.index') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.document-templates*') ? 'active' : '' }}">
                <flux:icon.document-text class="s-admin-nav-icon" />
                Document Templates
            </a>
        @endcan
    @endif

    @if($group === 'pricing')
        <div class="s-admin-sidebar-title">Pricing</div>
        @can('rates.view')
            <a href="{{ route('admin.settings.rate-definitions') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.rate-definitions*') ? 'active' : '' }}">
                <flux:icon.calculator class="s-admin-nav-icon" />
                Rate Definitions
            </a>
        @endcan
        @can('pricing.view')
            <a href="{{ route('admin.settings.pricing.discount-categories') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.pricing.discount-categories*') ? 'active' : '' }}">
                <flux:icon.receipt-percent class="s-admin-nav-icon" />
                Discount Categories
            </a>
            <a href="{{ route('admin.settings.pricing.price-categories') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.pricing.price-categories*') ? 'active' : '' }}">
                <flux:icon.currency-pound class="s-admin-nav-icon" />
                Price Categories
            </a>
            <a href="{{ route('admin.settings.pricing.bundle-rules') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.pricing.bundle-rules*') ? 'active' : '' }}">
                <flux:icon.squares-2x2 class="s-admin-nav-icon" />
                Bundle Rules
            </a>
            <a href="{{ route('admin.settings.pricing.surcharge-groups') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.pricing.surcharge-groups*') ? 'active' : '' }}">
                <flux:icon.plus-circle class="s-admin-nav-icon" />
                Surcharge Groups
            </a>
        @endcan
    @endif

    @if($group === 'system')
        <div class="s-admin-sidebar-title">System</div>
        @can('action-log.view')
            <a href="{{ route('admin.settings.action-log') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.action-log') ? 'active' : '' }}">
                <flux:icon.clock class="s-admin-nav-icon" />
                Action Log
            </a>
        @endcan
        <a href="{{ route('admin.settings.system-health') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.system-health') ? 'active' : '' }}">
            <flux:icon.server-stack class="s-admin-nav-icon" />
            System Health
        </a>
        @can('owner')
            <a href="{{ route('admin.settings.infrastructure') }}" wire:navigate.hover
               class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.infrastructure') ? 'active' : '' }}">
                <flux:icon.wrench-screwdriver class="s-admin-nav-icon" />
                Infrastructure
            </a>
        @endcan
        <a href="{{ route('admin.settings.seeders') }}" wire:navigate.hover
           class="s-admin-nav-item {{ ActiveRoute::is('admin.settings.seeders') ? 'active' : '' }}">
            <flux:icon.circle-stack class="s-admin-nav-icon" />
            Database Seeders
        </a>
    @endif
</nav>
