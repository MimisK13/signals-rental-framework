<?php

use App\Services\Notifications\ChannelConfigMasker;

it('masks short secret values with a fixed bullet run', function () {
    expect(ChannelConfigMasker::maskValue('ab'))->toBe('••••••••')
        ->and(ChannelConfigMasker::maskValue('1234'))->toBe('••••••••');
});

it('masks longer secret values while preserving leading and trailing characters', function () {
    expect(ChannelConfigMasker::maskValue('hunter2secret'))->toBe('hu••••••••••••et');
});
