<?php

use App\Domain\SupplierBills\Presentation\Livewire\Show;
use Livewire\Livewire;

it('can render', function () {
    $component = Livewire::test(Show::class);

    $component->assertSee('');
});
