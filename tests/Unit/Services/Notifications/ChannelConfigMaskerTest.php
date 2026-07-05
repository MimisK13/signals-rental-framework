<?php

use App\Services\Notifications\ChannelConfigMasker;

it('masks short secret values with a fixed bullet run', function () {
    expect(ChannelConfigMasker::maskValue('ab'))->toBe('••••••••')
        ->and(ChannelConfigMasker::maskValue('1234'))->toBe('••••••••');
});

it('masks longer secret values while preserving leading and trailing characters', function () {
    // 16 chars → middle run capped at 12 bullets, first/last two preserved.
    expect(ChannelConfigMasker::maskValue('hunterverysecret'))->toBe('hu'.str_repeat('•', 12).'et');
});
