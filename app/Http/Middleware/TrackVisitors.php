<?php

namespace App\Http\Middleware;

use App\Models\Visitor;
use Closure;
use Illuminate\Http\Request;

class TrackVisitors
{
    /**
     * Common bot user agent patterns
     */
    protected array $botPatterns = [
        'bot', 'crawl', 'spider', 'slurp', 'search', 'googlebot', 'bingbot',
        'yandexbot', 'baiduspider', 'facebookexternalhit', 'twitterbot',
        'rogerbot', 'linkedinbot', 'embedly', 'quora link preview',
        'showyoubot', 'outbrain', 'pinterest', 'slackbot', 'vkshare',
        'w3c_validator', 'whatsapp', 'curl', 'wget', 'python-requests'
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Don't track admin routes
        if ($request->is('admin/*') || $request->is('login') || $request->is('register')) {
            return $next($request);
        }

        // Don't track AJAX requests
        if ($request->ajax()) {
            return $next($request);
        }

        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Check if it's a bot
        $isBot = $this->isBot($userAgent);

        // Parse user agent
        $deviceInfo = $this->parseUserAgent($userAgent);

        // Create visitor record
        Visitor::create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'page_url' => $request->fullUrl(),
            'referrer' => $request->header('referer'),
            'country' => $this->getCountryFromIP($ipAddress),
            'city' => null, // Would require geolocation service
            'device_type' => $deviceInfo['device_type'],
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'is_bot' => $isBot,
            'visited_at' => now()
        ]);

        return $next($request);
    }

    /**
     * Check if user agent is a bot
     */
    protected function isBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        $userAgentLower = strtolower($userAgent);

        foreach ($this->botPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse user agent to get device info
     */
    protected function parseUserAgent(?string $userAgent): array
    {
        $device_type = 'desktop';
        $browser = 'Unknown';
        $os = 'Unknown';

        if (empty($userAgent)) {
            return compact('device_type', 'browser', 'os');
        }

        $ua = strtolower($userAgent);

        // Detect device type
        if (preg_match('/mobile|android|iphone|ipad|ipod|windows phone/', $ua)) {
            $device_type = 'mobile';
        } elseif (preg_match('/tablet|ipad/', $ua)) {
            $device_type = 'tablet';
        }

        // Detect browser
        if (preg_match('/chrome|crios/', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox|fxios/', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/edge|edg/', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/opera|opr/', $ua)) {
            $browser = 'Opera';
        }

        // Detect OS
        if (preg_match('/windows/', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os/', $ua)) {
            $os = 'MacOS';
        } elseif (preg_match('/linux/', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/android/', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad/', $ua)) {
            $os = 'iOS';
        }

        return compact('device_type', 'browser', 'os');
    }

    /**
     * Get country from IP address (simplified)
     * In production, use a geolocation service like MaxMind GeoIP
     */
    protected function getCountryFromIP(string $ip): ?string
    {
        // For localhost or private IPs, return null
        if ($ip === '127.0.0.1' || $ip === '::1' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_PRIVATE)) {
            return 'SY'; // Default to Syria for local testing
        }

        // In production, integrate with a geolocation service
        // This is a placeholder that returns null
        return null;
    }
}
