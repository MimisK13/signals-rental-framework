<?php

use App\Contracts\Documents\DocumentRenderer;
use App\Enums\DocumentOutputFormat;
use App\Exceptions\Documents\DocumentRenderException;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\SandboxedBladeRenderer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    Log::spy();
});

function assertSandboxBlocked(string $template, string $cacheKey): void
{
    expect(fn () => app(SandboxedBladeRenderer::class)->render($template, [], $cacheKey))
        ->toThrow(DocumentRenderException::class);

    Log::shouldHaveReceived('warning');
}

describe('SandboxedBladeRenderer allowlist enforcement', function () {
    it('allows benign property access and approved helpers', function () {
        $html = app(SandboxedBladeRenderer::class)->render(
            '<p>{{ $opportunity->subject }} ({{ count($items) }})</p>',
            [
                'opportunity' => (object) ['subject' => 'Allowed Subject'],
                'items' => [(object) ['name' => 'Line']],
            ],
            'sandbox-allowed-test',
        );

        expect($html)->toContain('Allowed Subject');
    });

    it('rejects raw shell execution helpers without running them', function () {
        assertSandboxBlocked("{{ exec('id') }}", 'sandbox-exec-test');
        assertSandboxBlocked("{{ system('id') }}", 'sandbox-system-test');
        assertSandboxBlocked("{{ file_get_contents('/etc/passwd') }}", 'sandbox-file-get-contents-test');
    });

    it('rejects facades whether short or fully-qualified without database side effects', function () {
        $usersBefore = DB::table('users')->count();

        assertSandboxBlocked('{{ DB::table("users")->count() }}', 'sandbox-db-short-test');
        assertSandboxBlocked(
            '{{ \\Illuminate\\Support\\Facades\\DB::table("users")->get() }}',
            'sandbox-db-fqcn-test',
        );

        expect(DB::table('users')->count())->toBe($usersBefore);
    });

    it('rejects config and env secret exfiltration attempts', function () {
        assertSandboxBlocked("{{ config('app.key') }}", 'sandbox-config-test');
        assertSandboxBlocked("{{ env('DB_PASSWORD') }}", 'sandbox-env-test');
    });

    it('rejects variable functions and method calls', function () {
        assertSandboxBlocked('{{ $fn() }}', 'sandbox-variable-function-test');
        assertSandboxBlocked('{{ $opportunity->delete() }}', 'sandbox-method-call-test');
    });

    it('rejects container, request, and auth helpers', function () {
        assertSandboxBlocked('{{ app("config")->get("app.name") }}', 'sandbox-app-test');
        assertSandboxBlocked('{{ request()->path() }}', 'sandbox-request-test');
        assertSandboxBlocked('{{ auth()->id() }}', 'sandbox-auth-test');
    });

    it('rejects raw @php blocks via compiler directive blocks', function () {
        assertSandboxBlocked('@php echo "nope"; @endphp', 'sandbox-php-test');
    });

    it('rejects dynamic invocation disguised as a grouped callable', function () {
        assertSandboxBlocked("{{ ('exec')('id') }}", 'sandbox-dynamic-invocation-test');
    });
});

describe('sandboxed pipeline failures', function () {
    it('returns a friendly render failure through the pipeline without a 500 response', function () {
        $store = Store::factory()->create();
        $opportunity = Opportunity::factory()->for($store)->create();
        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '@php dump("secret"); @endphp',
        ]);

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            'opportunity',
            $opportunity->id,
            DocumentOutputFormat::Preview,
        );

        expect($result->success)->toBeFalse()
            ->and($result->error)->toContain('Raw PHP blocks are not allowed');

        Log::shouldHaveReceived('warning');
    });

    it('does not leak config values into preview output when blocked', function () {
        $store = Store::factory()->create();
        $opportunity = Opportunity::factory()->for($store)->create();
        $secret = (string) config('app.key');
        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => "{{ config('app.key') }}",
        ]);

        $result = app(DocumentRenderer::class)->render(
            $template->fresh('store'),
            'opportunity',
            $opportunity->id,
            DocumentOutputFormat::Preview,
        );

        expect($result->success)->toBeFalse()
            ->and($result->html)->toBeNull()
            ->and($result->error)->toContain('Function [config]');

        if ($secret !== '') {
            expect($result->error)->not->toContain($secret);
        }
    });
});
