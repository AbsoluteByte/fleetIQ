<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClaimsApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.claims_api.token', '');
        if ($expected === '') {
            return response()->json(['message' => 'Claims API is not configured'], 503);
        }

        $header = (string) $request->header('Authorization', '');
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            $token = trim($matches[1]);
        } elseif ($request->header('X-Claims-Api-Token')) {
            $token = trim((string) $request->header('X-Claims-Api-Token'));
        }

        if ($token === '' || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tenantId = $request->header('X-Tenant-Id')
            ?? $request->query('tenant_id')
            ?? config('services.claims_api.tenant_id');

        if ($tenantId === null || $tenantId === '') {
            return response()->json(['message' => 'X-Tenant-Id header or CLAIMS_API_TENANT_ID is required'], 400);
        }

        $request->attributes->set('claims_tenant_id', (int) $tenantId);

        return $next($request);
    }
}
