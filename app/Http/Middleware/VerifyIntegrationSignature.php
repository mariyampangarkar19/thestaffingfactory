<?php

namespace App\Http\Middleware;

use App\Models\IntegrationKey;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server requests (the WordPress plugin) are signed, not logged in:
 *
 *   X-Key-Id:    integration key id (UUID)
 *   X-Timestamp: unix seconds
 *   X-Signature: hex HMAC-SHA256 of "{timestamp}.{raw body}" with the key's secret
 *
 * Rejects old timestamps and replays of the same signature. Runs after ResolveTenant, so a key
 * from another agency is simply not found (tenant scope + RLS).
 */
final class VerifyIntegrationSignature
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $keyId = (string) $request->header('X-Key-Id');
        $timestamp = (string) $request->header('X-Timestamp');
        $signature = strtolower((string) $request->header('X-Signature'));

        $fail = fn () => response()->json(['message' => 'Invalid signature.'], 401);

        if (! preg_match('/^[0-9a-f-]{36}$/', $keyId) || ! ctype_digit($timestamp) || ! preg_match('/^[0-9a-f]{64}$/', $signature)) {
            return $fail();
        }

        if (abs(time() - (int) $timestamp) > (int) config('platform.intake.max_clock_skew_seconds')) {
            return $fail();
        }

        $key = IntegrationKey::query()->find($keyId);

        if ($key === null || ! $key->allows($scope)) {
            return $fail();
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $key->secret);

        if (! hash_equals($expected, $signature)) {
            return $fail();
        }

        $tenantId = app(TenantContext::class)->id();
        if (! Cache::add("intake-sig:{$tenantId}:{$signature}", 1, (int) config('platform.intake.max_clock_skew_seconds') * 2)) {
            return response()->json(['message' => 'Duplicate request.'], 409);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('integration_key', $key);

        return $next($request);
    }
}
