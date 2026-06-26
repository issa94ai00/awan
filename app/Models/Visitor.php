<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'user_agent',
        'page_url',
        'referrer',
        'country',
        'city',
        'device_type',
        'browser',
        'os',
        'is_bot',
        'visited_at'
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'is_bot' => 'boolean',
    ];

    /**
     * Get today's visitors count
     */
    public static function todayCount(): int
    {
        return self::whereDate('visited_at', today())->count();
    }

    /**
     * Get this week's visitors count
     */
    public static function weekCount(): int
    {
        return self::whereBetween('visited_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
    }

    /**
     * Get this month's visitors count
     */
    public static function monthCount(): int
    {
        return self::whereMonth('visited_at', now()->month)
            ->whereYear('visited_at', now()->year)
            ->count();
    }

    /**
     * Get total visitors count
     */
    public static function totalCount(): int
    {
        return self::count();
    }

    /**
     * Get unique visitors count for today
     */
    public static function uniqueTodayCount(): int
    {
        return self::whereDate('visited_at', today())
            ->distinct('ip_address')
            ->count('ip_address');
    }

    /**
     * Get visitors by device type
     */
    public static function byDeviceType(): array
    {
        return self::selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();
    }

    /**
     * Get visitors by browser
     */
    public static function byBrowser(): array
    {
        return self::selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->pluck('count', 'browser')
            ->toArray();
    }

    /**
     * Get daily stats for chart (last 30 days)
     */
    public static function dailyStats(int $days = 30): array
    {
        $stats = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = self::whereDate('visited_at', $date)->count();
            $stats[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('d/m'),
                'count' => $count
            ];
        }
        return $stats;
    }

    /**
     * Get top visited pages
     */
    public static function topPages(int $limit = 10): array
    {
        return self::selectRaw('page_url, COUNT(*) as visits')
            ->groupBy('page_url')
            ->orderByDesc('visits')
            ->limit($limit)
            ->pluck('visits', 'page_url')
            ->toArray();
    }

    /**
     * Get top countries
     */
    public static function topCountries(int $limit = 10): array
    {
        return self::selectRaw('country, COUNT(*) as visits')
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('visits')
            ->limit($limit)
            ->pluck('visits', 'country')
            ->toArray();
    }
}
