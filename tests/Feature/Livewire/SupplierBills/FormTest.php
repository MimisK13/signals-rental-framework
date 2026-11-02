<?php

use App\Domain\SupplierBills\Presentation\Livewire\Form;
use Livewire\Livewire;

it('can render', function () {
    $component = Livewire::test(Form::class);

    $component->assertSee('');
});
