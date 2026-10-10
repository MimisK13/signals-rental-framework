<?php

use Illuminate\Support\Facades\Blade;

it('renders dense anonymous index components', function (): void {
    $html = Blade::render(
        <<<'BLADE'
        <x-dense::field-label for="dense-search">Search</x-dense::field-label>
        <x-dense::search-input id="dense-search" model="search" />
        BLADE,
    );

    expect((string) $html)
        ->toContain('for="dense-search"')
        ->toContain('Search')
        ->toContain('id="dense-search"')
        ->toContain('wire:model.live.debounce.300ms="search"');
});

it('exposes the foundation primitives through the dense namespace', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-dense::button>Save</x-dense::button>
        <x-dense::button-group><x-dense::button>One</x-dense::button><x-dense::button>Two</x-dense::button></x-dense::button-group>
        <x-dense::icon-button icon="x-mark" label="Close" />
        <x-dense::input id="name" name="name" placeholder="Full name" aria-describedby="name-help" required />
        <x-dense::textarea name="notes" placeholder="Notes" aria-label="Notes">Notes</x-dense::textarea>
        <x-dense::select name="status" placeholder="Choose a status"><option value="open">Open</option></x-dense::select>
        <x-dense::checkbox name="enabled" />
        <x-dense::radio name="mode" value="one" />
        <x-dense::switch name="active" />
        <x-dense::label for="name">Name</x-dense::label>
        <x-dense::field><x-dense::label>Name</x-dense::label></x-dense::field>
        <x-dense::fieldset><x-dense::label>Details</x-dense::label></x-dense::fieldset>
        <x-dense::badge>New</x-dense::badge>
        <x-dense::status label="Active" />
        <x-dense::icon name="check" />
        BLADE,
    );

    expect((string) $html)
        ->toContain('Save')
        ->toContain('Close')
        ->toContain('name="name"')
        ->toContain('class="input"')
        ->toContain('placeholder="Full name"')
        ->toContain('aria-describedby="name-help"')
        ->toContain('required')
        ->toContain('Choose a status')
        ->toContain('Notes')
        ->toContain('Open')
        ->toContain('New')
        ->toContain('Active')
        ->toContain('data-dense-icon="x-mark"')
        ->toContain('data-dense-icon="check"');

    expect(str_contains((string) $html, 's-input'))->toBeFalse()
        ->and(str_contains((string) $html, 's-btn'))->toBeFalse()
        ->and(str_contains((string) $html, 'class="dense-input"'))->toBeFalse()
        ->and(str_contains((string) $html, 'class="dense-btn'))->toBeFalse();
});

it('exposes idempotent asset directives for the package', function (): void {
    $html = Blade::render('@denseStyles @denseStyles @denseScripts @denseScripts');

    expect((string) $html)
        ->toContain('/vendor/dense/dense.css')
        ->toContain('/vendor/dense/dense.js')
        ->toContain('?v=')
        ->and(substr_count((string) $html, 'dense.css'))->toBe(1)
        ->and(substr_count((string) $html, 'dense.js'))->toBe(1);
});

it('connects the Blade combobox to the reusable Dense behavior', function (): void {
    $html = Blade::render(
        '<x-dense::combobox id="product" :options="[1 => \'Mixer\']" />',
    );

    expect((string) $html)
        ->toContain('x-data="denseCombobox(')
        ->toContain('role="combobox"')
        ->toContain('role="listbox"')
        ->toContain('aria-autocomplete="list"');
});

it('serves versioned package assets with production cache headers', function (): void {
    $css = $this->get('/vendor/dense/dense.css');
    $js = $this->get('/vendor/dense/dense.js');

    $css->assertOk();
    $js->assertOk();

    expect($css->headers->get('cache-control'))->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable')
        ->and($js->headers->get('cache-control'))->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable')
        ->and($css->headers->get('content-type'))->toStartWith('text/css')
        ->and($js->headers->get('content-type'))->toStartWith('application/javascript');
});
