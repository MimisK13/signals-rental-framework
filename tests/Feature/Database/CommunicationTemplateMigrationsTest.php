<?php

use Illuminate\Support\Facades\Schema;

it('creates communication_templates table with expected columns', function () {
    expect(Schema::hasTable('communication_templates'))->toBeTrue();
    expect(Schema::hasColumns('communication_templates', [
        'id',
        'notification_type_id',
        'store_id',
        'name',
        'slug',
        'channel',
        'subject',
        'body',
        'attachments_config',
        'is_default',
        'is_system',
        'is_active',
        'version',
        'last_edited_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();
});

it('creates communication_template_versions table with expected columns', function () {
    expect(Schema::hasTable('communication_template_versions'))->toBeTrue();
    expect(Schema::hasColumns('communication_template_versions', [
        'id',
        'communication_template_id',
        'edited_by_member_id',
        'version',
        'subject',
        'body',
        'change_note',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('keeps auth email_templates table separate from communication_templates', function () {
    expect(Schema::hasTable('email_templates'))->toBeTrue();
    expect(Schema::hasTable('communication_templates'))->toBeTrue();
    expect(Schema::hasColumn('email_templates', 'key'))->toBeTrue();
    expect(Schema::hasColumn('communication_templates', 'notification_type_id'))->toBeTrue();
});
