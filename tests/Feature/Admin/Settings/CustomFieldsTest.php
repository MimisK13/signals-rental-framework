<?php

use App\Domain\Administration\Models\User;
use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Presentation\Livewire\Form;
use App\Domain\CustomFields\Presentation\Livewire\Index;
use App\Domain\CustomFields\Services\VisibilityRuleEvaluator;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the custom fields page', function () {
    $this->get(route('admin.settings.custom-fields'))
        ->assertOk()
        ->assertSee('Custom Fields');
});

it('lists custom fields', function () {
    CustomField::factory()->create(['display_name' => 'PO Reference']);
    CustomField::factory()->create(['display_name' => 'Account Number']);

    Livewire::test(Index::class)
        ->assertSee('PO Reference')
        ->assertSee('Account Number');
});

it('can filter by module type', function () {
    CustomField::factory()->forModule('Member')->create(['display_name' => 'Member Field']);
    CustomField::factory()->forModule('Opportunity')->create(['display_name' => 'Opportunity Field']);

    Livewire::test(Index::class)
        ->set('moduleFilter', 'Member')
        ->assertSee('Member Field')
        ->assertDontSee('Opportunity Field');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.custom-fields.create'))
        ->assertOk()
        ->assertSee('Create Custom Field');
});

it('can create a field', function () {
    Livewire::test(Form::class)
        ->set('name', 'po_reference')
        ->set('displayName', 'PO Reference')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('save');

    expect(CustomField::where('name', 'po_reference')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $field = CustomField::factory()->create();

    $this->get(route('admin.settings.custom-fields.edit', $field))
        ->assertOk()
        ->assertSee('Edit Custom Field');
});

it('can edit a field', function () {
    $field = CustomField::factory()->create(['name' => 'test_field', 'display_name' => 'Old Display']);

    Livewire::test(Form::class, ['customField' => $field])
        ->assertSet('displayName', 'Old Display')
        ->set('displayName', 'New Display')
        ->call('save')
        ->assertHasNoErrors();

    expect($field->fresh()->display_name)->toBe('New Display');
});

it('validates required fields', function () {
    Livewire::test(Form::class)
        ->set('name', '')
        ->set('moduleType', '')
        ->call('save')
        ->assertHasErrors(['name', 'module_type']);
});

it('can delete a field', function () {
    $field = CustomField::factory()->create(['name' => 'to_delete']);

    Livewire::test(Index::class)
        ->call('delete', $field->id);

    expect(CustomField::where('name', 'to_delete')->exists())->toBeFalse();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.custom-fields'))
        ->assertForbidden();
});

it('renders the Phase-2 module options in the create form', function () {
    Livewire::test(Form::class)
        // Module-type values (stored in custom_fields.module_type)
        ->assertSeeHtml('value="Activity"')
        ->assertSeeHtml('value="ProductGroup"')
        ->assertSeeHtml('value="StockLevel"')
        ->assertSeeHtml('value="Member"')
        ->assertSeeHtml('value="Product"')
        ->assertSeeHtml('value="Store"')
        // Human-readable labels
        ->assertSee('Product Group')
        ->assertSee('Stock Level');
});

it('does not render phantom module options for non-existent models', function () {
    // Module options are derived from CustomFieldModuleRegistry, whose source of
    // truth is the set of models using the HasCustomFields trait. Models with no
    // backing registry entry must never appear as options.
    Livewire::test(Form::class)
        ->assertDontSeeHtml('value="Invoice"')
        ->assertDontSeeHtml('value="NonExistentModel"');
});

it('renders module options for every custom-field-capable model', function () {
    // Opportunity now uses HasCustomFields and is registered, so it is a
    // legitimate module option (it used to be a phantom example).
    Livewire::test(Form::class)
        ->assertSeeHtml('value="Opportunity"');
});

it('can create a field for a previously-missing module', function () {
    Livewire::test(Form::class)
        ->set('name', 'inspection_due')
        ->set('displayName', 'Inspection Due')
        ->set('moduleType', 'StockLevel')
        ->set('fieldType', CustomFieldType::Date->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::where('name', 'inspection_due')->where('module_type', 'StockLevel')->exists())->toBeTrue();
});

it('persists string validation rules in the shape the validator consumes', function () {
    Livewire::test(Form::class)
        ->set('name', 'po_reference')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->set('validationRules.min_length', '3')
        ->set('validationRules.max_length', '20')
        ->set('validationRules.pattern', '/^PO-\d+$/')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'po_reference')->firstOrFail();

    expect($field->validation_rules)->toBe([
        'min_length' => 3,
        'max_length' => 20,
        'pattern' => '/^PO-\d+$/',
    ]);
});

it('persists numeric validation rules for number fields', function () {
    Livewire::test(Form::class)
        ->set('name', 'weight_kg')
        ->set('moduleType', 'Product')
        ->set('fieldType', CustomFieldType::Number->value)
        ->set('validationRules.min', '0')
        ->set('validationRules.max', '1000')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'weight_kg')->firstOrFail();

    expect($field->validation_rules)->toBe([
        'min' => 0,
        'max' => 1000,
    ]);
});

it('only persists validation keys relevant to the field type', function () {
    // min_length/max_length/pattern apply to String, but min/max do not.
    Livewire::test(Form::class)
        ->set('name', 'short_code')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->set('validationRules.max_length', '5')
        ->set('validationRules.min', '99') // not applicable to String — must be dropped
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'short_code')->firstOrFail();

    expect($field->validation_rules)->toBe(['max_length' => 5]);
});

it('persists null validation rules when none are set', function () {
    Livewire::test(Form::class)
        ->set('name', 'plain_field')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::where('name', 'plain_field')->firstOrFail()->validation_rules)->toBeNull();
});

it('persists visibility rules in the exact shape VisibilityRuleEvaluator consumes', function () {
    Livewire::test(Form::class)
        ->set('name', 'vat_number')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', 'membership_type')
        ->set('visibilityRules.0.operator', 'eq')
        ->set('visibilityRules.0.value', 'Organisation')
        ->call('addVisibilityRule')
        ->set('visibilityRules.1.field', 'is_active')
        ->set('visibilityRules.1.operator', 'present')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'vat_number')->firstOrFail();

    expect($field->visibility_rules)->toBe([
        ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
        ['field' => 'is_active', 'operator' => 'present'],
    ]);

    // The persisted shape must be understood by the evaluator.
    $evaluator = app(VisibilityRuleEvaluator::class);
    expect($evaluator->evaluate($field->visibility_rules, ['membership_type' => 'Organisation', 'is_active' => true]))->toBeTrue();
    expect($evaluator->evaluate($field->visibility_rules, ['membership_type' => 'Contact', 'is_active' => true]))->toBeFalse();
});

it('splits in/not_in visibility rule values into arrays', function () {
    Livewire::test(Form::class)
        ->set('name', 'region_field')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', 'region')
        ->set('visibilityRules.0.operator', 'in')
        ->set('visibilityRules.0.value', 'north, south , east')
        ->call('save')
        ->assertHasNoErrors();

    $field = CustomField::where('name', 'region_field')->firstOrFail();

    expect($field->visibility_rules)->toBe([
        ['field' => 'region', 'operator' => 'in', 'value' => ['north', 'south', 'east']],
    ]);
});

it('drops visibility rule rows with a blank field', function () {
    Livewire::test(Form::class)
        ->set('name', 'blank_rule_field')
        ->set('moduleType', 'Member')
        ->set('fieldType', CustomFieldType::String->value)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', '')
        ->set('visibilityRules.0.operator', 'eq')
        ->set('visibilityRules.0.value', 'ignored')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomField::where('name', 'blank_rule_field')->firstOrFail()->visibility_rules)->toBeNull();
});

it('can remove a visibility rule row', function () {
    $component = Livewire::test(Form::class)
        ->call('addVisibilityRule')
        ->set('visibilityRules.0.field', 'first')
        ->call('addVisibilityRule')
        ->set('visibilityRules.1.field', 'second')
        ->call('removeVisibilityRule', 0);

    $component->assertSet('visibilityRules.0.field', 'second');
    expect($component->get('visibilityRules'))->toHaveCount(1);
});

it('loads existing validation and visibility rules back into the edit form', function () {
    $field = CustomField::factory()->create([
        'name' => 'editable_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
        'validation_rules' => ['min_length' => 2, 'max_length' => 50, 'pattern' => '/^[A-Z]+$/'],
        'visibility_rules' => [
            ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
            ['field' => 'tags', 'operator' => 'in', 'value' => ['a', 'b']],
        ],
    ]);

    Livewire::test(Form::class, ['customField' => $field])
        ->assertSet('validationRules.min_length', '2')
        ->assertSet('validationRules.max_length', '50')
        ->assertSet('validationRules.pattern', '/^[A-Z]+$/')
        ->assertSet('visibilityRules.0.field', 'membership_type')
        ->assertSet('visibilityRules.0.operator', 'eq')
        ->assertSet('visibilityRules.0.value', 'Organisation')
        ->assertSet('visibilityRules.1.field', 'tags')
        ->assertSet('visibilityRules.1.operator', 'in')
        ->assertSet('visibilityRules.1.value', 'a, b');
});

it('preserves rules round-trip when editing without changes', function () {
    $field = CustomField::factory()->create([
        'name' => 'roundtrip_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
        'validation_rules' => ['max_length' => 10],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
    ]);

    Livewire::test(Form::class, ['customField' => $field])
        ->call('save')
        ->assertHasNoErrors();

    $field->refresh();

    expect($field->validation_rules)->toBe(['max_length' => 10]);
    expect($field->visibility_rules)->toBe([['field' => 'status', 'operator' => 'eq', 'value' => 'active']]);
});

it('clears validation and visibility rules from the edit form', function () {
    // #204: emptying the rule inputs in the editor must persist the clear.
    $field = CustomField::factory()->create([
        'name' => 'clearable_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
        'validation_rules' => ['min_length' => 2, 'max_length' => 50],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
    ]);

    Livewire::test(Form::class, ['customField' => $field])
        ->assertSet('validationRules.min_length', '2')
        ->assertSet('validationRules.max_length', '50')
        ->assertSet('visibilityRules.0.field', 'status')
        // Empty every validation input.
        ->set('validationRules.min_length', '')
        ->set('validationRules.max_length', '')
        ->set('validationRules.pattern', '')
        // Drop the only visibility rule row.
        ->call('removeVisibilityRule', 0)
        ->call('save')
        ->assertHasNoErrors();

    $field->refresh();

    expect($field->validation_rules)->toBeNull();
    expect($field->visibility_rules)->toBeNull();
});
