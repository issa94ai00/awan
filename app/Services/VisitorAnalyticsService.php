<?php

namespace App\Services;

use App\Models\Visitor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Site-traffic analytics, read from the `visitors` table `TrackVisitors`
 * writes on every public request.
 *
 * Bots are excluded from every aggregate by default: `TrackVisitors` already
 * flags them via `is_bot`, but nothing downstream ever read that column, so a
 * crawler sweep looked identical to a spike in real traffic. The visitor log
 * still lists them (with the flag visible) since an admin auditing traffic
 * needs to see what got excluded and why.
 *
 * API and admin traffic is excluded from every aggregate as well.
 * `TrackVisitors` only skips requests matching `admin/*` (the bare `/admin`
 * route itself does not match that wildcard) and only skips requests carrying
 * the `X-Requested-With` header, which axios — what the whole admin SPA is
 * built on — never sends. In production this table's single most-visited
 * "page" is `/api/v1/notifications/unread-count`, an admin polling endpoint,
 * at several times the hits of the storefront home page. Reporting that as
 * site traffic would make every headline number meaningless to the person
 * reading this screen, so it is filtered here instead of changing what
 * `TrackVisitors` records — that table may have other consumers.
 *
 * The raw log (`log()`/`exportRows()`) does not apply that filter: it is the
 * audit trail, not a report, and hiding rows there would hide the very leak
 * this class works around from the one screen that could reveal it.
 */
class VisitorAnalyticsService
{
    private function baseQuery(string $fromDate, string $toDate, bool $excludeBots = true)
    {
        $query = Visitor::whereBetween('visited_at', [
            $fromDate . ' 00:00:00',
            $toDate . ' 23:59:59',
        ])
            ->where('page_url', 'not like', '%/api/%')
            ->where('page_url', 'not like', '%/admin%');

        if ($excludeBots) {
            $query->where('is_bot', false);
        }

        return $query;
    }

    public function summary(string $fromDate, string $toDate): array
    {
        $totalVisits = $this->baseQuery($fromDate, $toDate)->count();
        $uniqueVisitors = $this->baseQuery($fromDate, $toDate)->distinct('ip_address')->count('ip_address');
        $botVisits = $this->baseQuery($fromDate, $toDate, excludeBots: false)->where('is_bot', true)->count();
        $totalWithBots = $totalVisits + $botVisits;

        $days = (strtotime($toDate) - strtotime($fromDate)) / 86400 + 1;
        $days = max(1, (int) round($days));

        return [
            'total_visits' => $totalVisits,
            'unique_visitors' => $uniqueVisitors,
            'bot_visits' => $botVisits,
            'bot_share' => $totalWithBots > 0 ? round(($botVisits / $totalWithBots) * 100, 1) : 0.0,
            'avg_daily_visits' => round($totalVisits / $days, 1),
        ];
    }

    /** Daily visits and unique visitors for the range, one row per calendar day. */
    public function trend(string $fromDate, string $toDate): array
    {
        $rows = $this->baseQuery($fromDate, $toDate)
            ->selectRaw('DATE(visited_at) as date, COUNT(*) as visits, COUNT(DISTINCT ip_address) as unique_visitors')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $trend = [];
        $cursor = strtotime($fromDate);
        $end = strtotime($toDate);

        while ($cursor <= $end) {
            $date = date('Y-m-d', $cursor);
            $row = $rows->get($date);

            $trend[] = [
                'date' => $date,
                'visits' => (int) ($row->visits ?? 0),
                'unique_visitors' => (int) ($row->unique_visitors ?? 0),
            ];

            $cursor = strtotime('+1 day', $cursor);
        }

        return $trend;
    }

    /** @return array{devices: array, browsers: array, os: array} */
    public function breakdown(string $fromDate, string $toDate): array
    {
        $devices = $this->baseQuery($fromDate, $toDate)
            ->whereNotNull('device_type')
            ->selectRaw('device_type as name, COUNT(*) as count')
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();

        $browsers = $this->baseQuery($fromDate, $toDate)
            ->whereNotNull('browser')
            ->selectRaw('browser as name, COUNT(*) as count')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $os = $this->baseQuery($fromDate, $toDate)
            ->whereNotNull('os')
            ->selectRaw('os as name, COUNT(*) as count')
            ->groupBy('os')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        return [
            'devices' => $devices->toArray(),
            'browsers' => $browsers->toArray(),
            'os' => $os->toArray(),
        ];
    }

    public function topPages(string $fromDate, string $toDate, int $limit = 10): array
    {
        return $this->baseQuery($fromDate, $toDate)
            ->whereNotNull('page_url')
            ->selectRaw('page_url, COUNT(*) as visits')
            ->groupBy('page_url')
            ->orderByDesc('visits')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Referrers grouped by host rather than the raw URL, since two query
     * strings on the same host are the same source for this reading.
     */
    public function topReferrers(string $fromDate, string $toDate, int $limit = 10): array
    {
        $rows = $this->baseQuery($fromDate, $toDate)
            ->whereNotNull('referrer')
            ->pluck('referrer');

        $counts = [];
        foreach ($rows as $referrer) {
            $host = parse_url($referrer, PHP_URL_HOST) ?: $referrer;
            $counts[$host] = ($counts[$host] ?? 0) + 1;
        }

        arsort($counts);

        return collect($counts)
            ->take($limit)
            ->map(fn ($count, $host) => ['referrer' => $host, 'visits' => $count])
            ->values()
            ->toArray();
    }

    /**
     * Paginated visitor log for the detail table.
     *
     * @param array{search?: string, device_type?: string, browser?: string, is_bot?: string} $filters
     */
    public function log(string $fromDate, string $toDate, array $filters, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Visitor::whereBetween('visited_at', [
            $fromDate . ' 00:00:00',
            $toDate . ' 23:59:59',
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('page_url', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('os', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (!empty($filters['browser'])) {
            $query->where('browser', $filters['browser']);
        }

        // Ternary, not boolean: 'all' (or omitted) means no filter, and that is
        // a real, distinct choice from "only bots" or "only humans".
        if (($filters['is_bot'] ?? 'all') !== 'all') {
            $query->where('is_bot', $filters['is_bot'] === '1' || $filters['is_bot'] === 'true');
        }

        return $query->orderByDesc('visited_at')->paginate($perPage, ['*'], 'page', $page);
    }

    /** Flat rows for the CSV export — same filters as the log, no pagination cap beyond `$limit`. */
    public function exportRows(string $fromDate, string $toDate, array $filters, int $limit = 5000): array
    {
        $paginator = $this->log($fromDate, $toDate, $filters, $limit, 1);

        return $paginator->getCollection()
            ->map(fn (Visitor $visitor) => [
                'visited_at' => optional($visitor->visited_at)->format('Y-m-d H:i'),
                'ip_address' => $visitor->ip_address,
                'page_url' => $visitor->page_url,
                'device_type' => $visitor->device_type,
                'browser' => $visitor->browser,
                'os' => $visitor->os,
                'referrer' => $visitor->referrer,
                'country' => $visitor->country,
                'is_bot' => $visitor->is_bot ? 'yes' : 'no',
            ])
            ->toArray();
    }

    public function distinctDeviceTypes(): array
    {
        return Visitor::whereNotNull('device_type')->distinct()->orderBy('device_type')->pluck('device_type')->toArray();
    }

    public function distinctBrowsers(): array
    {
        return Visitor::whereNotNull('browser')->distinct()->orderBy('browser')->pluck('browser')->toArray();
    }
}
