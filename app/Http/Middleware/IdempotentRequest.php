<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a write safe to retry.
 *
 * The mobile app queues writes made offline and replays them when the
 * connection returns. A request that timed out on the phone may still have
 * been committed here, so the replay would create a second quote or order.
 * The app sends one `Idempotency-Key` per logical write; the first response
 * for that key is stored and returned as-is to every repeat.
 *
 * Scoped by the caller's bearer token (or IP when anonymous), method and path,
 * so a key can never surface another user's response. 5xx and auth/throttle
 * responses are not stored: those are the cases a retry should re-run.
 */
class IdempotentRequest
{
    private const TTL_SECONDS = 86400;

    private const LOCK_SECONDS = 60;

    private const NOT_STORED = [401, 408, 409, 419, 429];

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '' || strlen($key) > 100 || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $scope = $request->bearerToken() ?: 'ip:'.$request->ip();
        $cacheKey = 'idem:'.sha1($scope.'|'.$request->method().'|'.$request->path().'|'.$key);

        if ($stored = Cache::get($cacheKey)) {
            return $this->replay($stored);
        }

        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب نفسه قيد المعالجة، أعد المحاولة بعد قليل',
            ], 409);
        }

        try {
            // Another attempt may have finished while this one waited.
            if ($stored = Cache::get($cacheKey)) {
                return $this->replay($stored);
            }

            $response = $next($request);
            $status = $response->getStatusCode();

            if ($status < 500 && ! in_array($status, self::NOT_STORED, true)) {
                Cache::put($cacheKey, [
                    'status' => $status,
                    'content' => $response->getContent(),
                    'type' => $response->headers->get('Content-Type', 'application/json'),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{status:int, content:string|false, type:string}  $stored
     */
    private function replay(array $stored): Response
    {
        return response((string) $stored['content'], $stored['status'])
            ->header('Content-Type', $stored['type'])
            ->header('Idempotent-Replayed', 'true');
    }
}
