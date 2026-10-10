<?php

use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('supplier-bills.index');

    $component->assertSee('');
});
