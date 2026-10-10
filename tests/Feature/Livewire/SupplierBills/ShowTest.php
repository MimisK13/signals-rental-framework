<?php

use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('supplier-bills.show');

    $component->assertSee('');
});
