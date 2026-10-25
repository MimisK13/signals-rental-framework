@props(['title' => null])

@php
    use App\Support\ActiveRoute;

    // Stable, request-independent flags for which sidebar to render. Derived from
    // the page's real route (via Livewire's persisted original URL) so they remain
    // correct during Livewire updates, not just on full page loads.
    $isAdminArea = ActiveRoute::is('admin.*');
    $isSettingsArea = ActiveRoute::is('settings.*');
    $isApiArea = ActiveRoute::is('api.*');
    $hasSidebarContent = $isAdminArea || $isSettingsArea;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="flex h-screen flex-col overflow-hidden bg-[var(--content-bg)] text-[13px] leading-normal text-[var(--text-primary)] antialiased"
          x-data="{ mobileNav: false, sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', sidebarReady: false, notificationsOpen: false }"
          x-init="$nextTick(() => { sidebarReady = true; }); $watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', v))">

        {{-- ============================================================ --}}
        {{--  TOP HEADER (always navy)                                     --}}
        {{-- ============================================================ --}}
        <header class="header">
            {{-- Mobile hamburger --}}
            <button class="mr-3 shrink-0 text-[var(--grey-light)] hover:text-white lg:hidden"
                    x-on:click="mobileNav = true"
                    aria-label="{{ __('Toggle sidebar') }}">
                <flux:icon.bars-2 class="!size-5" />
            </button>

            {{-- Primary module navigation (desktop) --}}
            <nav class="mr-auto hidden h-full items-center gap-0 lg:flex">
                <a href="{{ route('dashboard') }}"
                   class="header-nav-item header-nav-item-icon {{ ActiveRoute::is('dashboard') ? 'active' : '' }}"
                   aria-label="{{ __('Dashboard') }}"
                   wire:navigate.hover>
                    <flux:icon.home class="header-nav-icon-glyph" />
                </a>

                {{-- CRM mega dropdown (members and/or activities) --}}
                @canany(['members.access', 'activities.access'])
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('members.*') ? 'active' : '' }}" type="button">
                        CRM
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            {{-- People & Places --}}
                            @can('members.access')
                            <div>
                                <div class="mega-group-label">People &amp; Places</div>
                                <a href="{{ route('members.index') }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.user-group class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Members</span>
                                        <span class="mega-item-desc">All contacts, companies &amp; venues</span>
                                    </div>
                                </a>
                                <a href="{{ route('members.index', ['type' => 'organisation']) }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.building-office-2 class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Organisations</span>
                                        <span class="mega-item-desc">Companies &amp; businesses</span>
                                    </div>
                                </a>
                                <a href="{{ route('members.index', ['type' => 'venue']) }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.map-pin class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Venues</span>
                                        <span class="mega-item-desc">Venues &amp; locations</span>
                                    </div>
                                </a>
                                <a href="{{ route('members.index', ['type' => 'contact']) }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.user class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Contacts</span>
                                        <span class="mega-item-desc">Individual people</span>
                                    </div>
                                </a>
                            </div>
                            @endcan
                            {{-- Engagement --}}
                            @can('activities.access')
                            <div>
                                <div class="mega-group-label">Engagement</div>
                                <a href="{{ route('activities.index') }}" class="mega-item {{ ActiveRoute::is('activities.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.chat-bubble-left-right class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Activities</span>
                                        <span class="mega-item-desc">Calls, emails, meetings &amp; tasks</span>
                                    </div>
                                </a>
                            </div>
                            @endcan
                        </div>
                    </div>
                </div>
                @endcanany

                {{-- Job Planning mega dropdown. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('opportunities.*', 'availability.*', 'planner', 'projects.*') ? 'active' : '' }}" type="button">
                        Job Planning
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Opportunities</div>
                                @can('opportunities.access')
                                <a href="{{ route('opportunities.index') }}" class="mega-item {{ ActiveRoute::is('opportunities.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.briefcase class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Opportunities</span>
                                        <span class="mega-item-desc">Quotes, orders &amp; hires</span>
                                    </div>
                                </a>
                                @endcan
                                @can('opportunities.access')
                                <a href="{{ route('planner') }}" class="mega-item {{ ActiveRoute::is('planner') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.calendar-days class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Planner</span>
                                        <span class="mega-item-desc">Schedule &amp; resource timeline</span>
                                    </div>
                                </a>
                                @endcan
                                @can('availability.view')
                                <a href="{{ route('availability.index') }}" class="mega-item {{ ActiveRoute::is('availability.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.chart-bar class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Equipment Availability</span>
                                        <span class="mega-item-desc">Stock availability &amp; conflicts</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                            <div>
                                <div class="mega-group-label">Projects</div>
                                @can('projects.view')
                                <a href="{{ route('projects.index') }}" class="mega-item {{ ActiveRoute::is('projects.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.folder class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Projects</span>
                                        <span class="mega-item-desc">Project management</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Experimental workspace. It uses canonical Opportunities and actions. --}}
                @can('opportunities.access')
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('experimental.*') ? 'active' : '' }}" type="button">
                        Experimental
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Workspaces</div>
                                <a href="{{ route('experimental.quotations.index') }}" class="mega-item {{ ActiveRoute::is('experimental.quotations.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.document-text class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Quotations</span>
                                        <span class="mega-item-desc">Alternative quotation workflow</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endcan

                {{-- Resources mega dropdown --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item {{ ActiveRoute::is('products.*', 'product-groups.*', 'stock-levels.*', 'virtual-stock.*') ? 'active' : '' }}" type="button">
                        Resources
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            @canany(['products.access', 'stock.access', 'virtual_stock.access'])
                            <div>
                                <div class="mega-group-label">Catalogue</div>
                                @can('products.access')
                                <a href="{{ route('products.index') }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.cube class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Products</span>
                                        <span class="mega-item-desc">Equipment, services &amp; consumables</span>
                                    </div>
                                </a>
                                <a href="{{ route('product-groups.index') }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.folder class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Product Groups</span>
                                        <span class="mega-item-desc">Categories &amp; hierarchy</span>
                                    </div>
                                </a>
                                @endcan
                                @can('stock.access')
                                <a href="{{ route('stock-levels.index') }}" class="mega-item" wire:navigate.hover>
                                    <flux:icon.archive-box class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Stock Levels</span>
                                        <span class="mega-item-desc">Inventory &amp; asset tracking</span>
                                    </div>
                                </a>
                                @endcan
                                @can('viewAny', App\Models\VirtualStockIntake::class)
                                <a href="{{ route('virtual-stock.index') }}" class="mega-item {{ ActiveRoute::is('virtual-stock.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.arrow-down-circle class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Virtual Stock</span>
                                        <span class="mega-item-desc">Sub-hire supply windows</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                            @endcanany
                            <div>
                                <div class="mega-group-label">Services</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.users class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Crew</span>
                                        <span class="mega-item-desc">People &amp; team scheduling</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.wrench-screwdriver class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Labour</span>
                                        <span class="mega-item-desc">Time &amp; labour charges</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.truck class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Vehicles</span>
                                        <span class="mega-item-desc">Fleet &amp; transport scheduling</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Finance mega dropdown --}}
                @canany(['invoices.access', 'payments.record', 'purchase_orders.access'])
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item" type="button">
                        Finance
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Billing</div>
                                @can('invoices.access')
                                <a href="{{ route('invoices.index') }}" class="mega-item {{ ActiveRoute::is('invoices.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.document-currency-pound class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Invoices</span>
                                        <span class="mega-item-desc">Issue &amp; track invoices</span>
                                    </div>
                                </a>
                                <a href="{{ route('credit-notes.index') }}" class="mega-item {{ ActiveRoute::is('credit-notes.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.receipt-refund class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Credit Notes</span>
                                        <span class="mega-item-desc">Refunds &amp; adjustments</span>
                                    </div>
                                </a>
                                <a href="{{ route('payments.index') }}" class="mega-item {{ ActiveRoute::is('payments.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.banknotes class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Payments</span>
                                        <span class="mega-item-desc">Receipts &amp; reconciliation</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                            <div>
                                <div class="mega-group-label">Purchasing</div>
                                @can('viewAny', App\Models\PurchaseOrder::class)
                                    <a href="{{ route('purchase-orders.index') }}" class="mega-item {{ ActiveRoute::is('purchase-orders.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.shopping-cart class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Purchase Orders</span>
                                        <span class="mega-item-desc">Supplier orders &amp; procurement</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
                @endcanany

                {{-- Operations mega dropdown.
                     Placeholder links are ungated for now; they gain
                     inspections.* gates when that module ships. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-nav-item" type="button">
                        Operations
                        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </button>
                    <div class="mega-dropdown mega-dropdown-cols-2">
                        <div class="mega-grid grid gap-x-8 gap-y-5">
                            <div>
                                <div class="mega-group-label">Warehouse</div>
                                @can('opportunities.view')
                                <a href="{{ route('warehouse.index') }}" class="mega-item {{ ActiveRoute::is('warehouse.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.clipboard-document-list class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Warehouse</span>
                                        <span class="mega-item-desc">Operational work queue</span>
                                    </div>
                                </a>
                                @endcan
                                <a href="#" class="mega-item">
                                    <flux:icon.arrow-path class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Processing</span>
                                        <span class="mega-item-desc">Pick, pack &amp; dispatch</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.squares-2x2 class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Prep Bays</span>
                                        <span class="mega-item-desc">Staging &amp; preparation areas</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.rectangle-stack class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Shelf</span>
                                        <span class="mega-item-desc">Storage locations &amp; bins</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.qr-code class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Global Check-in</span>
                                        <span class="mega-item-desc">Scan &amp; receive returns</span>
                                    </div>
                                </a>
                                @can('viewAny', App\Models\Container::class)
                                <a href="{{ route('containers.index') }}" class="mega-item {{ ActiveRoute::is('containers.*') ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.cube-transparent class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Containers</span>
                                        <span class="mega-item-desc">Cases, flightcases &amp; kits</span>
                                    </div>
                                </a>
                                @endcan
                            </div>
                            <div>
                                <div class="mega-group-label">Inspections</div>
                                <a href="#" class="mega-item">
                                    <flux:icon.clipboard-document-check class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Testing</span>
                                        <span class="mega-item-desc">Inspections &amp; safety checks</span>
                                    </div>
                                </a>
                                <a href="#" class="mega-item">
                                    <flux:icon.shield-check class="mega-item-icon" />
                                    <div class="flex flex-col gap-px">
                                        <span class="mega-item-label">Certificates</span>
                                        <span class="mega-item-desc">Certifications &amp; compliance</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reports — hidden until the Reporting module ships. Restore this
                     link (with a real route + reports.* gate) when it lands; do not
                     surface a dead "#" placeholder in the meantime. --}}
                @if(auth()->user()?->hasAdminAccess())
                    <a href="{{ route('admin.index') }}"
                       class="header-nav-item {{ ActiveRoute::is('admin.*') ? 'active' : '' }}"
                       wire:navigate.hover>
                        <flux:icon.wrench-screwdriver class="!size-4" />
                        Admin
                    </a>
                @endif
            </nav>

            {{-- Header actions (right side) --}}
            <div class="header-actions">
                {{-- Search: compact icon button. Dispatches the same
                     `open-command-palette` event the old search input did;
                     the '/' shortcut is handled globally inside the palette. --}}
                <button class="header-icon-btn" type="button" title="{{ __('Search') }}" aria-label="{{ __('Search') }}" x-on:click="$dispatch('open-command-palette')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>

                @auth
                {{-- Extensions / expandability menu.
                     Placeholder links are ungated for now. --}}
                <div class="nav-dropdown-wrapper">
                    <button class="header-icon-btn" type="button" title="{{ __('Extensions') }}" aria-label="{{ __('Extensions') }}">
                        <flux:icon.puzzle-piece />
                    </button>
                    <div class="mega-dropdown mega-dropdown-end">
                        <div class="grid grid-cols-1 gap-y-1">
                            <a href="#" class="mega-item">
                                <flux:icon.bolt class="mega-item-icon" />
                                <span class="mega-item-label">Workflows</span>
                            </a>
                            <a href="#" class="mega-item">
                                <flux:icon.puzzle-piece class="mega-item-icon" />
                                <span class="mega-item-label">Plugin</span>
                            </a>
                            @can('api.access')
                                <a href="{{ route('api.index') }}" class="mega-item {{ $isApiArea ? 'active' : '' }}" wire:navigate.hover>
                                    <flux:icon.code-bracket class="mega-item-icon" />
                                    <span class="mega-item-label">API</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>

                {{-- Calendar (quick access) --}}
                @can('activities.access')
                <a href="{{ route('calendar.index') }}"
                   class="header-icon-btn {{ ActiveRoute::is('calendar.*') ? 'active' : '' }}"
                   title="{{ __('Calendar') }}" aria-label="{{ __('Calendar') }}"
                   wire:navigate.hover>
                    <flux:icon.calendar-days />
                </a>
                @can('supplier_bills.view')
                    <a href="{{ route('supplier-bills.index') }}" class="mega-item {{ ActiveRoute::is('supplier-bills.*') ? 'active' : '' }}" wire:navigate.hover>
                        <flux:icon.receipt-percent class="mega-item-icon" />
                        <span><span class="mega-item-label">Supplier Bills</span><span class="mega-item-desc">Actual supplier charges</span></span>
                    </a>
                @endcan
                @endcan

                {{-- Notifications bell (pane lives in app-layout) --}}
                <livewire:notifications.bell />

                {{-- User dropdown --}}
                @php
                    $userAvatarSrc = null;
                    $userThumbPath = auth()->user()?->member?->icon_thumb_url;
                    if ($userThumbPath) {
                        try {
                            $userAvatarSrc = app(\App\Services\FileService::class)->signedUrl($userThumbPath);
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                    $userAvatarInitialsClass = $userAvatarSrc ? '' : 'bg-[var(--blue)] text-[var(--brand-on-accent,#ffffff)]';
                @endphp
                <div class="nav-dropdown-wrapper">
                    <button class="header-avatar-btn" type="button" aria-label="{{ __('User menu') }}">
                        <x-dense::avatar :src="$userAvatarSrc" :initials="auth()->user()->initials()" size="sm" :class="'h-7 w-7 '.$userAvatarInitialsClass" />
                    </button>
                    <div class="mega-dropdown mega-dropdown-end">
                        <div class="flex items-center gap-3 pb-4">
                            <x-dense::avatar :src="$userAvatarSrc" :initials="auth()->user()->initials()" size="md" :class="$userAvatarInitialsClass" />
                            <div class="grid flex-1 text-left leading-tight">
                                <span class="truncate text-[12px] font-semibold text-[var(--text-primary)]">{{ auth()->user()->name }}</span>
                                <span class="truncate text-[11px] text-[var(--text-muted)]">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                        <div class="border-t border-[var(--dropdown-border)] pt-3">
                            <a href="{{ route('settings.profile') }}" class="mega-item" wire:navigate.hover>
                                <flux:icon.cog-6-tooth class="mega-item-icon" />
                                <span class="mega-item-label">Settings</span>
                            </a>
                            <a href="{{ route('docs.index') }}" class="mega-item" target="_blank">
                                <flux:icon.book-open class="mega-item-icon" />
                                <span class="mega-item-label">Documentation</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="mega-item w-full">
                                    <flux:icon.arrow-right-start-on-rectangle class="mega-item-icon" />
                                    <span class="mega-item-label">{{ __('Log Out') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endauth
            </div>
        </header>

        {{-- ============================================================ --}}
        {{--  MOBILE SIDEBAR (Alpine-managed, hidden on desktop)            --}}
        {{-- ============================================================ --}}

        {{-- Backdrop --}}
        <div x-show="mobileNav" x-on:click="mobileNav = false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[998] bg-black/50 lg:hidden" style="display:none"></div>

        {{-- Panel --}}
        <aside x-show="mobileNav"
               x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
               x-on:keydown.escape.window="mobileNav = false"
               class="dark fixed inset-y-0 left-0 z-[999] flex w-64 flex-col gap-2 overflow-y-auto border-r border-[var(--navy-light)] bg-[var(--navy)] p-4 text-[#e2e8f0] lg:hidden" style="display:none">

            {{-- Close --}}
            <button x-on:click="mobileNav = false" class="mb-2 self-end text-[var(--grey-light)] hover:text-white">
                <flux:icon.x-mark class="!size-5" />
            </button>

            @if($isAdminArea)
                {{-- Admin mobile sidebar --}}
                <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.arrow-left class="!size-[15px]" /> Back to app
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Admin</div>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.company', 'admin.settings.stores*', 'admin.settings.branding') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.cog-6-tooth class="!size-[15px]" /> Setup
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.users*', 'admin.settings.roles*', 'admin.settings.permissions', 'admin.settings.security', 'admin.settings.api') ? 'active' : '' }}" href="{{ route('admin.settings.users') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.users class="!size-[15px]" /> Users & Security
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.preferences', 'admin.settings.email', 'admin.settings.email-templates*', 'admin.settings.notifications', 'admin.settings.scheduling', 'admin.settings.integrations') ? 'active' : '' }}" href="{{ route('admin.settings.preferences') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.adjustments-horizontal class="!size-[15px]" /> Preferences
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.custom-field-groups*', 'admin.settings.custom-fields*', 'admin.settings.list-names*', 'admin.settings.lists*', 'admin.settings.list-values*', 'admin.settings.countries') ? 'active' : '' }}" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.rectangle-group class="!size-[15px]" /> Data
                </a>
                @can('tax-classes.view')
                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.tax.*') ? 'active' : '' }}" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.receipt-percent class="!size-[15px]" /> Tax
                    </a>
                @endcan
                @can('document_templates.view')
                    <a class="sidebar-item {{ ActiveRoute::is('admin.document-templates*') ? 'active' : '' }}" href="{{ route('admin.document-templates.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.document-text class="!size-[15px]" /> Documents
                    </a>
                @endcan
                @can('rates.view')
                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.rate-definitions*') ? 'active' : '' }}" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.calculator class="!size-[15px]" /> Pricing
                    </a>
                @endcan
                <a class="sidebar-item {{ ActiveRoute::is('admin.settings.action-log', 'admin.settings.system-health', 'admin.settings.infrastructure', 'admin.settings.seeders', 'admin.settings.webhooks') ? 'active' : '' }}" href="{{ route('admin.settings.action-log') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.server-stack class="!size-[15px]" /> System
                </a>
            @elseif($isSettingsArea)
                {{-- Settings mobile sidebar --}}
                <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.arrow-left class="!size-[15px]" /> Back to app
                </a>

                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                <div class="sidebar-group-label">Settings</div>
                <a class="sidebar-item {{ ActiveRoute::is('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.user class="!size-[15px]" /> Profile
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.key class="!size-[15px]" /> Password
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.swatch class="!size-[15px]" /> Appearance
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.calendar') ? 'active' : '' }}" href="{{ route('settings.calendar') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.calendar-days class="!size-[15px]" /> Calendar
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('settings.notifications') ? 'active' : '' }}" href="{{ route('settings.notifications') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.bell class="!size-[15px]" /> Notifications
                </a>
            @else
                {{-- Normal mobile sidebar --}}
                <a class="sidebar-item {{ ActiveRoute::is('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.home class="!size-[15px]" /> Dashboard
                </a>

                @can('members.access')
                    <div class="sidebar-group-label">People &amp; Places</div>
                    <a class="sidebar-item {{ ActiveRoute::is('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.user-group class="!size-[15px]" /> Members
                    </a>
                @endcan
                @can('activities.access')
                    <a class="sidebar-item {{ ActiveRoute::is('activities.*') ? 'active' : '' }}" href="{{ route('activities.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.chat-bubble-left-right class="!size-[15px]" /> Activities
                    </a>
                @endcan

                {{-- Job Planning --}}
                <div class="sidebar-group-label">Job Planning</div>
                @can('opportunities.access')
                    <a class="sidebar-item {{ ActiveRoute::is('opportunities.*') ? 'active' : '' }}" href="{{ route('opportunities.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.briefcase class="!size-[15px]" /> Opportunities
                    </a>
                @endcan
                @can('opportunities.access')
                    <a class="sidebar-item {{ ActiveRoute::is('planner') ? 'active' : '' }}" href="{{ route('planner') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.calendar-days class="!size-[15px]" /> Planner
                    </a>
                @endcan
                @can('availability.view')
                    <a class="sidebar-item {{ ActiveRoute::is('availability.*') ? 'active' : '' }}" href="{{ route('availability.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.chart-bar class="!size-[15px]" /> Equipment Availability
                    </a>
                @endcan
                @can('projects.view')
                    <a class="sidebar-item {{ ActiveRoute::is('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.folder class="!size-[15px]" /> Projects
                    </a>
                @endcan

                @can('opportunities.access')
                    <div class="sidebar-group-label">Experimental</div>
                    <a class="sidebar-item {{ ActiveRoute::is('experimental.quotations.*') ? 'active' : '' }}" href="{{ route('experimental.quotations.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.document-text class="!size-[15px]" /> Quotations
                    </a>
                @endcan

                @canany(['products.access', 'stock.access', 'virtual_stock.access'])
                    <div class="sidebar-group-label">Catalogue</div>
                    @can('products.access')
                        <a class="sidebar-item {{ ActiveRoute::is('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                            <flux:icon.cube class="!size-[15px]" /> Products
                        </a>
                        <a class="sidebar-item {{ ActiveRoute::is('product-groups.*') ? 'active' : '' }}" href="{{ route('product-groups.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                            <flux:icon.folder class="!size-[15px]" /> Product Groups
                        </a>
                    @endcan
                    @can('stock.access')
                        <a class="sidebar-item {{ ActiveRoute::is('stock-levels.*') ? 'active' : '' }}" href="{{ route('stock-levels.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                            <flux:icon.archive-box class="!size-[15px]" /> Stock Levels
                        </a>
                    @endcan
                    @can('viewAny', App\Models\VirtualStockIntake::class)
                        <a class="sidebar-item {{ ActiveRoute::is('virtual-stock.*') ? 'active' : '' }}" href="{{ route('virtual-stock.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                            <flux:icon.arrow-down-circle class="!size-[15px]" /> Virtual Stock
                        </a>
                    @endcan
                @endcanany

                {{-- Services placeholders (ungated until modules ship). --}}
                <div class="sidebar-group-label">Services</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.users class="!size-[15px]" /> Crew
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.wrench-screwdriver class="!size-[15px]" /> Labour
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.truck class="!size-[15px]" /> Vehicles
                </a>

                @can('invoices.access')
                <div class="sidebar-group-label">Finance</div>
                <a class="sidebar-item {{ ActiveRoute::is('invoices.*') ? 'active' : '' }}" href="{{ route('invoices.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.document-currency-pound class="!size-[15px]" /> Invoices
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('credit-notes.*') ? 'active' : '' }}" href="{{ route('credit-notes.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.receipt-refund class="!size-[15px]" /> Credit Notes
                </a>
                <a class="sidebar-item {{ ActiveRoute::is('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.banknotes class="!size-[15px]" /> Payments
                </a>
                @endcan

                @can('viewAny', App\Models\PurchaseOrder::class)
                <div class="sidebar-group-label">Purchasing</div>
                <a class="sidebar-item {{ ActiveRoute::is('purchase-orders.*') ? 'active' : '' }}" href="{{ route('purchase-orders.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.shopping-cart class="!size-[15px]" /> Purchase Orders
                </a>
                @endcan

                <div class="sidebar-group-label">Warehouse</div>
                @can('opportunities.view')
                    <a class="sidebar-item {{ ActiveRoute::is('warehouse.*') ? 'active' : '' }}" href="{{ route('warehouse.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.clipboard-document-list class="!size-[15px]" /> Warehouse
                    </a>
                @endcan
                <a class="sidebar-item" href="#">
                    <flux:icon.arrow-path class="!size-[15px]" /> Processing
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.squares-2x2 class="!size-[15px]" /> Prep Bays
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.rectangle-stack class="!size-[15px]" /> Shelf
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.qr-code class="!size-[15px]" /> Global Check-in
                </a>
                @can('viewAny', App\Models\Container::class)
                    <a class="sidebar-item {{ ActiveRoute::is('containers.*') ? 'active' : '' }}" href="{{ route('containers.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.cube-transparent class="!size-[15px]" /> Containers
                    </a>
                @endcan

                <div class="sidebar-group-label">Operations</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.clipboard-document-check class="!size-[15px]" /> Testing
                </a>
                <a class="sidebar-item" href="#">
                    <flux:icon.shield-check class="!size-[15px]" /> Certificates
                </a>

                <div class="sidebar-group-label">Reports</div>
                <a class="sidebar-item" href="#">
                    <flux:icon.chart-pie class="!size-[15px]" /> Reports
                </a>

                <div class="flex-1"></div>
                <div class="mx-2 my-1 h-px bg-[var(--sidebar-border)]"></div>
                @can('api.access')
                    <a class="sidebar-item {{ $isApiArea ? 'active' : '' }}" href="{{ route('api.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.code-bracket class="!size-[15px]" /> API
                    </a>
                @endcan
                @if(auth()->user()?->hasAdminAccess())
                    <a class="sidebar-item" href="{{ route('admin.index') }}" wire:navigate.hover x-on:click="mobileNav = false">
                        <flux:icon.wrench-screwdriver class="!size-[15px]" /> Admin
                    </a>
                @endif
                <a class="sidebar-item" href="{{ route('settings.profile') }}" wire:navigate.hover x-on:click="mobileNav = false">
                    <flux:icon.cog-6-tooth class="!size-[15px]" /> Settings
                </a>
            @endif
        </aside>

        {{-- ============================================================ --}}
        {{--  LAYOUT: SIDEBAR + MAIN CONTENT                              --}}
        {{-- ============================================================ --}}
        <div class="app-layout">
            {{-- Desktop sidebar for Admin and Settings areas only. --}}
            @if($hasSidebarContent)
            <aside class="app-sidebar hidden lg:flex"
                   :class="{ 'collapsed': !sidebarOpen, 'ready': sidebarReady }">
                @if($isAdminArea)
                    {{-- Admin sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate.hover>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Back to app</span>
                    </a>

                    <div class="sidebar-divider"></div>
                    <div class="sidebar-group-label">Admin</div>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.company', 'admin.settings.stores*', 'admin.settings.branding') ? 'active' : '' }}" href="{{ route('admin.settings.company') }}" wire:navigate.hover>
                        <flux:icon.cog-6-tooth class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Setup</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.users*', 'admin.settings.roles*', 'admin.settings.permissions', 'admin.settings.security', 'admin.settings.api') ? 'active' : '' }}" href="{{ route('admin.settings.users') }}" wire:navigate.hover>
                        <flux:icon.users class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Users & Security</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.preferences', 'admin.settings.email', 'admin.settings.email-templates*', 'admin.settings.notifications', 'admin.settings.scheduling', 'admin.settings.integrations') ? 'active' : '' }}" href="{{ route('admin.settings.preferences') }}" wire:navigate.hover>
                        <flux:icon.adjustments-horizontal class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Preferences</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.custom-field-groups*', 'admin.settings.custom-fields*', 'admin.settings.list-names*', 'admin.settings.lists*', 'admin.settings.list-values*', 'admin.settings.countries') ? 'active' : '' }}" href="{{ route('admin.settings.custom-field-groups') }}" wire:navigate.hover>
                        <flux:icon.rectangle-group class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Data</span>
                    </a>

                    @can('tax-classes.view')
                        <a class="sidebar-item {{ ActiveRoute::is('admin.settings.tax.*') ? 'active' : '' }}" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate.hover>
                            <flux:icon.receipt-percent class="!size-[15px]" />
                            <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Tax</span>
                        </a>
                    @endcan

                    @can('document_templates.view')
                        <a class="sidebar-item {{ ActiveRoute::is('admin.document-templates*') ? 'active' : '' }}" href="{{ route('admin.document-templates.index') }}" wire:navigate.hover>
                            <flux:icon.document-text class="!size-[15px]" />
                            <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Documents</span>
                        </a>
                    @endcan

                    @can('rates.view')
                        <a class="sidebar-item {{ ActiveRoute::is('admin.settings.rate-definitions*') ? 'active' : '' }}" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate.hover>
                            <flux:icon.calculator class="!size-[15px]" />
                            <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Pricing</span>
                        </a>
                    @endcan

                    <a class="sidebar-item {{ ActiveRoute::is('admin.settings.action-log', 'admin.settings.system-health', 'admin.settings.infrastructure', 'admin.settings.seeders', 'admin.settings.webhooks') ? 'active' : '' }}" href="{{ route('admin.settings.action-log') }}" wire:navigate.hover>
                        <flux:icon.server-stack class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>System</span>
                    </a>
                @elseif($isSettingsArea)
                    {{-- Settings sidebar --}}
                    <a class="sidebar-item" href="{{ route('dashboard') }}" wire:navigate.hover>
                        <flux:icon.arrow-left class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Back to app</span>
                    </a>

                    <div class="sidebar-divider"></div>
                    <div class="sidebar-group-label">Settings</div>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.profile') ? 'active' : '' }}" href="{{ route('settings.profile') }}" wire:navigate.hover>
                        <flux:icon.user class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Profile</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.password') ? 'active' : '' }}" href="{{ route('settings.password') }}" wire:navigate.hover>
                        <flux:icon.key class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Password</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.appearance') ? 'active' : '' }}" href="{{ route('settings.appearance') }}" wire:navigate.hover>
                        <flux:icon.swatch class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Appearance</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.calendar') ? 'active' : '' }}" href="{{ route('settings.calendar') }}" wire:navigate.hover>
                        <flux:icon.calendar-days class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Calendar</span>
                    </a>

                    <a class="sidebar-item {{ ActiveRoute::is('settings.notifications') ? 'active' : '' }}" href="{{ route('settings.notifications') }}" wire:navigate.hover>
                        <flux:icon.bell class="!size-[15px]" />
                        <span class="sidebar-label" x-show="sidebarOpen" x-cloak>Notifications</span>
                    </a>
                @else
                    {{-- Normal sidebar (no nav items) --}}
                @endif

                {{-- Bottom section: sidebar toggle. --}}
                <div class="flex-1"></div>
                <button x-on:click="sidebarOpen = !sidebarOpen"
                        class="sidebar-toggle"
                        :aria-label="sidebarOpen ? '{{ __('Collapse sidebar') }}' : '{{ __('Expand sidebar') }}'">
                    <svg class="sidebar-toggle-icon" :class="{ 'rotate-180': !sidebarOpen }" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M10 3L5 8l5 5"/>
                    </svg>
                </button>
            </aside>
            @endif

            {{-- Main content area --}}
            <main class="app-main">
                <div class="flex flex-1 flex-col">
                    {{ $slot }}
                </div>
                @include('components.layouts.app.footer')
            </main>

            {{-- Notifications pane (Livewire + Echo) --}}
            <livewire:notifications.pane />
        </div>

        <x-signals.command-palette x-on:open-command-palette.window="toggle()" />

        <x-dense::confirm-modal />

        {{-- ============================================================ --}}
        {{--  LIVE TOAST STACK                                            --}}
        {{--  Renders auto-dismissing toasts in response to Livewire      --}}
        {{--  `toast` events ($this->dispatch('toast', type, message)).   --}}
        {{--  Success = green accent, error = red; stackable; click to     --}}
        {{--  dismiss; auto-dismiss after ~3.5s.                          --}}
        {{-- ============================================================ --}}
        <div
            id="s-toast-stack"
            class="s-toast-stack"
            x-data="signalsToasts()"
            x-init="init()"
            aria-live="polite"
            aria-atomic="true"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    class="s-toast"
                    :class="{
                        'visible': toast.visible,
                        's-toast-success': toast.type === 'success',
                        's-toast-error': toast.type === 'error',
                    }"
                    x-on:click="dismiss(toast.id)"
                    role="status"
                >
                    <span class="s-toast-icon" x-text="toast.type === 'error' ? '✕' : '✓'"></span>
                    <span x-text="toast.message"></span>
                </div>
            </template>
        </div>

        <script>
            // Bridge the (global) Livewire `toast` event onto a plain DOM CustomEvent
            // exactly ONCE per page load. Livewire.on() registers on the global bus and
            // is never torn down, so registering it inside the Alpine component's init()
            // would stack a new listener every time the component re-mounts (notably on
            // every wire:navigate.hover SPA transition), firing each toast N times. A window-
            // guarded singleton keeps it to a single registration; the Alpine component
            // then listens to the re-dispatched `signals:toast` DOM event, which Alpine
            // auto-cleans on teardown — so exactly one toast shows per dispatch.
            (function registerToastBridge() {
                if (window.__signalsToastBridge) { return; }

                const wire = () => {
                    if (window.__signalsToastBridge || !window.Livewire) { return false; }
                    window.__signalsToastBridge = true;
                    window.Livewire.on('toast', (payload) => {
                        const detail = Array.isArray(payload) ? payload[0] : payload;
                        window.dispatchEvent(new CustomEvent('signals:toast', { detail: detail || {} }));
                    });
                    return true;
                };

                // Livewire may not have booted yet on first parse; retry on its init hook.
                if (!wire()) {
                    document.addEventListener('livewire:init', wire, { once: true });
                }
            })();

            // Alpine factory for the live toast stack. Listens for the bridged
            // `signals:toast` DOM event and renders auto-dismissing, click-to-dismiss
            // toasts. Using a DOM event (auto-cleaned by Alpine) means re-mounting this
            // component never accumulates duplicate listeners.
            function registerSignalsToasts() {
                if (window.__signalsToastsRegistered) { return; }
                window.__signalsToastsRegistered = true;

                Alpine.data('signalsToasts', () => ({
                    toasts: [],
                    _seq: 0,

                    init() {
                        this._onToast = (e) => this.push(e.detail || {});
                        window.addEventListener('signals:toast', this._onToast);
                    },

                    destroy() {
                        if (this._onToast) {
                            window.removeEventListener('signals:toast', this._onToast);
                        }
                    },

                    push({ type = 'success', message = '' } = {}) {
                        if (!message) { return; }

                        const now = Date.now();
                        const last = window.__signalsToastLast || {};

                        if (last.message === message && last.type === type && (now - (last.at || 0)) < 800) {
                            return;
                        }

                        window.__signalsToastLast = { message, type, at: now };

                        const id = ++this._seq;
                        this.toasts.push({ id, type, message, visible: false });

                        // Next frame: flip `visible` so the CSS transition runs.
                        requestAnimationFrame(() => {
                            const toast = this.toasts.find((t) => t.id === id);
                            if (toast) { toast.visible = true; }
                        });

                        setTimeout(() => this.dismiss(id), 3500);
                    },

                    dismiss(id) {
                        const toast = this.toasts.find((t) => t.id === id);
                        if (!toast) { return; }

                        toast.visible = false;
                        // Remove after the fade-out transition completes.
                        setTimeout(() => {
                            this.toasts = this.toasts.filter((t) => t.id !== id);
                        }, 260);
                    },
                }));
            }

            if (window.Alpine) {
                registerSignalsToasts();
            } else {
                document.addEventListener('alpine:init', registerSignalsToasts);
            }
        </script>

        @fluxScripts

        {{-- Per-page script stack (used e.g. by the Editor Lab prototypes to load
             DnD libraries via CDN). Pages opt in with @push('scripts'). --}}
        @stack('scripts')
    </body>
</html>
