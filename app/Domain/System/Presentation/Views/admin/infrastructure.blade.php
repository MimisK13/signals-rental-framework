<section class="w-full min-w-0">
    <x-admin.layout group="system" title="Infrastructure" description="Configure database, cache, storage, and queue connections.">

        <flux:callout variant="danger" class="mb-6">
            <flux:callout.heading>Danger Zone</flux:callout.heading>
            <flux:callout.text>
                Incorrect settings here can make the application inaccessible. Only modify these values if you are
                certain of the new configuration. Always test connections before saving. Changes are written directly
                to the environment file and take effect immediately.
            </flux:callout.text>
        </flux:callout>

        <div class="space-y-8">

            {{-- PostgreSQL --}}
            <x-signals.form-section title="PostgreSQL Database" description="Primary database connection. Changing these values incorrectly will break the application.">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="dbHost" label="Host" placeholder="127.0.0.1" />
                        </div>
                        <flux:input wire:model="dbPort" label="Port" type="number" placeholder="5432" />
                    </div>
                    <flux:input wire:model="dbDatabase" label="Database Name" placeholder="signals" />
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="dbUsername" label="Username" placeholder="postgres" />
                        <flux:input wire:model="dbPassword" label="Password" type="password" placeholder="Leave blank to keep the current password" />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button size="sm" wire:click="testDatabase">
                            <span wire:loading.remove wire:target="testDatabase">Test Connection</span>
                            <span wire:loading wire:target="testDatabase">Testing...</span>
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="saveDatabase" wire:confirm="You are about to modify the database configuration. Incorrect values can make the application inaccessible. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>

                        @if($dbTestResult)
                            @if($dbTestResult['success'])
                                <span class="text-xs text-green-600 dark:text-green-400">
                                    Connected &mdash; {{ $dbTestResult['version'] }}
                                </span>
                            @else
                                <span class="text-xs text-red-600 dark:text-red-400">
                                    Failed: {{ $dbTestResult['error'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            {{-- Redis --}}
            <x-signals.form-section title="Redis" description="Used for caching, sessions, queues, and real-time broadcasting.">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="redisHost" label="Host" placeholder="127.0.0.1" />
                        </div>
                        <flux:input wire:model="redisPort" label="Port" type="number" placeholder="6379" />
                    </div>
                    <flux:input wire:model="redisPassword" label="Password" type="password" placeholder="Leave blank to keep the current password" />

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button size="sm" wire:click="testRedis">
                            <span wire:loading.remove wire:target="testRedis">Test Connection</span>
                            <span wire:loading wire:target="testRedis">Testing...</span>
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="saveRedis" wire:confirm="You are about to modify the Redis configuration. Incorrect values can make the application inaccessible. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>

                        @if($redisTestResult)
                            @if($redisTestResult['success'])
                                <span class="text-xs text-green-600 dark:text-green-400">
                                    Connected &mdash; {{ $redisTestResult['version'] }}
                                </span>
                            @else
                                <span class="text-xs text-red-600 dark:text-red-400">
                                    Failed: {{ $redisTestResult['error'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            {{-- S3 Storage --}}
            <x-signals.form-section title="S3 Storage" description="Object storage for file uploads, attachments, and documents.">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="s3Key" label="Access Key ID" placeholder="Leave blank to keep the current key" />
                        <flux:input wire:model="s3Secret" label="Secret Access Key" type="password" placeholder="Leave blank to keep the current secret" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="s3Region" label="Region" placeholder="us-east-1" />
                        <flux:input wire:model="s3Bucket" label="Bucket" placeholder="my-bucket" />
                    </div>
                    <flux:input wire:model="s3Endpoint" label="Custom Endpoint" placeholder="Leave blank for AWS S3" />
                    <flux:checkbox wire:model="s3PathStyle" label="Use path-style endpoint (for MinIO / custom S3-compatible)" />

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button size="sm" wire:click="testS3">
                            <span wire:loading.remove wire:target="testS3">Test Connection</span>
                            <span wire:loading wire:target="testS3">Testing...</span>
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="saveS3" wire:confirm="You are about to modify the S3 storage configuration. Incorrect values can make the application inaccessible. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>

                        @if($s3TestResult)
                            @if($s3TestResult['success'])
                                <span class="text-xs text-green-600 dark:text-green-400">Connected &mdash; upload/download verified</span>
                            @else
                                <span class="text-xs text-red-600 dark:text-red-400">
                                    Failed: {{ $s3TestResult['error'] }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            {{-- Queue --}}
            <x-signals.form-section title="Queue Driver" description="Controls how background jobs are processed.">
                <div class="space-y-4">
                    <flux:select wire:model="queueConnection" label="Connection">
                        <flux:select.option value="database">Database</flux:select.option>
                        <flux:select.option value="redis">Redis (recommended)</flux:select.option>
                    </flux:select>

                    <div class="pt-2">
                        <flux:button size="sm" variant="danger" wire:click="saveQueue" wire:confirm="You are about to modify the queue configuration. Changes are written to the .env file and take effect immediately. Continue?">Save Changes</flux:button>
                    </div>
                </div>
            </x-signals.form-section>
        </div>

        <x-action-message class="mt-4" on="infrastructure-saved">
            Configuration saved. You may need to restart queue workers for changes to take effect.
        </x-action-message>

    </x-admin.layout>
</section>
