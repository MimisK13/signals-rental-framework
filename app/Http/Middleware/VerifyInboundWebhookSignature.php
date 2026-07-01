<?php

namespace App\Http\Middleware;

use App\Models\InboundWebhookSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInboundWebhookSignature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('source');

        if (! is_string($slug) || $slug === '') {
            abort(404);
        }

        $source = InboundWebhookSource::query()
            ->where('slug', $slug)
            ->first();

        if ($source === null || ! $source->is_active) {
            abort(404);
        }

        $providedSignature = $request->header($source->signature_header);
        $expectedSignature = hash_hmac(
            $source->signature_algo,
            $request->getContent(),
            $source->secret,
        );

        $signatureValid = is_string($providedSignature)
            && $providedSignature !== ''
            && hash_equals($expectedSignature, $providedSignature);

        $request->attributes->set('inbound_webhook_source', $source);
        $request->attributes->set('inbound_signature_valid', $signatureValid);

        return $next($request);
    }
}
