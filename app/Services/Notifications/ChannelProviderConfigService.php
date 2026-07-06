<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Models\ChannelProvider;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\SettingsRegistry;

/**
 * Loads, masks, and persists channel driver configuration for the settings UI.
 */
class ChannelProviderConfigService
{
    public function __construct(
        private readonly SettingsRegistry $settingsRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function maskedConfig(ChannelDriver $driver, ?ChannelProvider $provider = null): array
    {
        $schema = $driver->configSchema();
        $config = $this->rawConfig($driver, $provider);

        return ChannelConfigMasker::mask($config, $schema);
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeConfig(ChannelDriver $driver, array $incoming, ?ChannelProvider $provider = null): array
    {
        $schema = $driver->configSchema();
        $existing = $this->rawConfig($driver, $provider);
        $merged = $existing;

        foreach ($schema as $field) {
            if (! array_key_exists($field->key, $incoming)) {
                continue;
            }

            $value = $incoming[$field->key];

            if ($field->secret && is_string($value) && $this->isMaskedPlaceholder($value)) {
                continue;
            }

            $merged[$field->key] = $value;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function persist(ChannelDriver $driver, array $config, ?ChannelProvider $provider = null): void
    {
        if ($driver instanceof EmailDriver) {
            $this->persistEmailSettings($config);

            return;
        }

        if ($provider === null) {
            return;
        }

        $provider->update(['config' => $config]);
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function schema(ChannelDriver $driver): array
    {
        return $driver->configSchema();
    }

    public function isMaskedPlaceholder(string $value): bool
    {
        return str_contains($value, '•');
    }

    /**
     * @return array<string, mixed>
     */
    private function rawConfig(ChannelDriver $driver, ?ChannelProvider $provider): array
    {
        if ($provider !== null && $provider->config !== null) {
            return $provider->config;
        }

        return $driver->resolveConfig();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function persistEmailSettings(array $config): void
    {
        $types = $this->settingsRegistry->types('email');
        $settings = [];

        $map = [
            'mailer' => 'email.mailer',
            'smtp_host' => 'email.smtp_host',
            'smtp_port' => 'email.smtp_port',
            'smtp_username' => 'email.smtp_username',
            'smtp_password' => 'email.smtp_password',
            'smtp_encryption' => 'email.smtp_encryption',
            'ses_key' => 'email.ses_key',
            'ses_secret' => 'email.ses_secret',
            'ses_region' => 'email.ses_region',
            'mailgun_domain' => 'email.mailgun_domain',
            'mailgun_secret' => 'email.mailgun_secret',
            'postmark_token' => 'email.postmark_token',
            'from_address' => 'email.from_address',
            'from_name' => 'email.from_name',
            'reply_to_address' => 'email.reply_to_address',
        ];

        foreach ($map as $key => $settingKey) {
            if (! array_key_exists($key, $config)) {
                continue;
            }

            $value = $config[$key];
            $type = $types[$key] ?? 'string';

            if ($key === 'smtp_port') {
                $settings[$settingKey] = ['value' => (int) $value, 'type' => $type];

                continue;
            }

            if (in_array($key, ['smtp_password', 'ses_secret', 'mailgun_secret', 'postmark_token'], true)) {
                $settings[$settingKey] = ['value' => $value, 'type' => $type];

                continue;
            }

            $settings[$settingKey] = $value;
        }

        if ($settings !== []) {
            settings()->setMany($settings);
        }
    }
}
