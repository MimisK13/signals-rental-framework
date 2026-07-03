<?php

use App\Data\Notifications\ChannelConfigFieldData;
use App\Services\Notifications\ChannelConfigMasker;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\SmsSegmentCounter;

test('sms segment counter uses single segment for short messages', function () {
    $stats = app(SmsSegmentCounter::class)->analyse('Hello world');

    expect($stats['characters'])->toBe(11)
        ->and($stats['segments'])->toBe(1)
        ->and($stats['per_segment'])->toBe(160);
});

test('sms segment counter uses concatenated segment size for long messages', function () {
    $stats = app(SmsSegmentCounter::class)->analyse(str_repeat('A', 200));

    expect($stats['characters'])->toBe(200)
        ->and($stats['segments'])->toBe(2)
        ->and($stats['per_segment'])->toBe(153);
});

test('channel config masker masks secret values for ui round trip', function () {
    $schema = [
        new ChannelConfigFieldData('api_key', 'API Key', 'password', secret: true),
        new ChannelConfigFieldData('host', 'Host', 'string'),
    ];

    $masked = ChannelConfigMasker::mask([
        'api_key' => 'my-secret-key',
        'host' => 'smtp.example.com',
    ], $schema);

    expect($masked['api_key'])->not->toBe('my-secret-key')
        ->and($masked['api_key'])->toContain('•')
        ->and($masked['host'])->toBe('smtp.example.com');
});

test('channel provider config service preserves secrets when masked placeholder submitted', function () {
    $driver = app(ChannelProviderRegistry::class)->resolve(driverKey: 'email');
    $service = app(ChannelProviderConfigService::class);

    settings()->set('email.smtp_password', 'original-secret');

    $merged = $service->mergeConfig($driver, [
        'smtp_password' => ChannelConfigMasker::maskValue('original-secret'),
        'smtp_host' => 'updated.example.com',
    ]);

    expect($merged['smtp_password'])->toBe('original-secret')
        ->and($merged['smtp_host'])->toBe('updated.example.com');
});
