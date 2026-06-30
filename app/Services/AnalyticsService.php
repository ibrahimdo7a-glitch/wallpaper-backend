<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsVisitor;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * First-party analytics core. Records pageviews/presence cheaply and answers the
 * dashboard queries. Designed as the single seam for analytics — external
 * providers (Vercel/GA4) complement it for deep web metrics, but everything we
 * own (members, content, our own visitors/sources) flows through here.
 */
class AnalyticsService
{
    public const ONLINE_MINUTES = 3;

    // ─────────────────────────── tracking (write) ───────────────────────────

    public function record(Request $request): void
    {
        $vid = substr((string) $request->input('visitor_id', ''), 0, 64);
        if ($vid === '') {
            return;
        }

        $raw      = (string) $request->input('type');
        $type     = in_array($raw, ['event', 'heartbeat'], true) ? $raw : 'pageview';
        $path     = substr((string) $request->input('path', ''), 0, 512) ?: null;
        $referrer = (string) $request->input('referrer', '');
        $refHost  = $referrer ? (parse_url($referrer, PHP_URL_HOST) ?: null) : null;
        $utm      = substr((string) $request->input('utm_source', ''), 0, 40) ?: null;
        $source   = $this->classifySource($refHost, $utm);
        $country  = $this->country($request);
        [$device, $os, $browser] = $this->parseUserAgent((string) $request->header('User-Agent', ''));

        $memberId = null;
        try {
            $memberId = optional(auth('member')->user())->id;
        } catch (\Throwable) {
        }

        // Append the event (powers trends + sources). Heartbeats are presence-only.
        try {
            if ($type !== 'heartbeat') {
            AnalyticsEvent::create([
                'visitor_id'    => $vid,
                'session_id'    => substr((string) $request->input('session_id', ''), 0, 64) ?: null,
                'member_id'     => $memberId,
                'type'          => $type,
                'name'          => substr((string) $request->input('name', ''), 0, 60) ?: null,
                'path'          => $path,
                'referrer_host' => $refHost ? substr($refHost, 0, 255) : null,
                'source'        => $source,
                'country'       => $country,
                'device'        => $device,
                'created_at'    => now(),
            ]);
            }
        } catch (\Throwable) {
        }

        // Upsert presence/identity (powers online + live + geo).
        try {
            $v = AnalyticsVisitor::find($vid);
            if (! $v) {
                AnalyticsVisitor::create([
                    'visitor_id'    => $vid,
                    'member_id'     => $memberId,
                    'first_seen_at' => now(),
                    'last_seen_at'  => now(),
                    'country'       => $country,
                    'device'        => $device,
                    'os'            => $os,
                    'browser'       => $browser,
                    'ip'            => $request->ip(),
                    'last_path'     => $path,
                    'source'        => $source,
                    'total_views'   => $type === 'pageview' ? 1 : 0,
                    'sessions'      => 1,
                ]);
            } else {
                $v->last_seen_at = now();
                if ($type === 'pageview') {
                    $v->total_views++;
                }
                if ($path) {
                    $v->last_path = $path;
                }
                if ($memberId) {
                    $v->member_id = $memberId;
                }
                if ($country && ! $v->country) {
                    $v->country = $country;
                }
                $v->device  = $device ?: $v->device;
                $v->os      = $os ?: $v->os;
                $v->browser = $browser ?: $v->browser;
                $v->ip      = $request->ip();
                $v->save();
            }
        } catch (\Throwable) {
        }
    }

    private function country(Request $r): ?string
    {
        // Cloudflare sets CF-IPCountry when the API is proxied through it.
        $c = strtoupper(substr((string) $r->header('CF-IPCountry', ''), 0, 2));
        return ($c && ! in_array($c, ['XX', 'T1', ''], true)) ? $c : null;
    }

    /** @return array{0:string,1:string,2:string} [device, os, browser] */
    private function parseUserAgent(string $ua): array
    {
        $s = strtolower($ua);

        $device = 'desktop';
        if (str_contains($s, 'ipad') || (str_contains($s, 'tablet') && ! str_contains($s, 'mobile'))) {
            $device = 'tablet';
        } elseif (str_contains($s, 'mobi') || str_contains($s, 'iphone') || str_contains($s, 'android')) {
            $device = 'mobile';
        }

        $os = 'Other';
        if (str_contains($s, 'windows')) $os = 'Windows';
        elseif (str_contains($s, 'iphone') || str_contains($s, 'ipad') || str_contains($s, 'cpu os')) $os = 'iOS';
        elseif (str_contains($s, 'mac os x') || str_contains($s, 'macintosh')) $os = 'macOS';
        elseif (str_contains($s, 'android')) $os = 'Android';
        elseif (str_contains($s, 'linux')) $os = 'Linux';

        $browser = 'Other';
        if (str_contains($s, 'edg/')) $browser = 'Edge';
        elseif (str_contains($s, 'chrome') || str_contains($s, 'crios')) $browser = 'Chrome';
        elseif (str_contains($s, 'firefox') || str_contains($s, 'fxios')) $browser = 'Firefox';
        elseif (str_contains($s, 'safari')) $browser = 'Safari';

        return [$device, $os, $browser];
    }

    private function bucket(string $s): ?string
    {
        $s = strtolower($s);
        $rules = [
            'google'    => ['google'],
            'telegram'  => ['t.me', 'telegram'],
            'instagram' => ['instagram'],
            'facebook'  => ['facebook', 'fb.'],
            'youtube'   => ['youtube', 'youtu.be'],
            'whatsapp'  => ['whatsapp', 'wa.me'],
            'reddit'    => ['reddit'],
            'x'         => ['twitter', 'x.com', 't.co'],
        ];
        foreach ($rules as $bucket => $needles) {
            foreach ($needles as $n) {
                if (str_contains($s, $n)) {
                    return $bucket;
                }
            }
        }
        return null;
    }

    private function classifySource(?string $host, ?string $utm): string
    {
        if ($utm && ($b = $this->bucket($utm))) {
            return $b;
        }
        if (! $host) {
            return $utm ? 'other' : 'direct';
        }
        if (str_contains(strtolower($host), 'qev.app')) {
            return 'direct'; // internal navigation
        }
        return $this->bucket($host) ?? 'referral';
    }

    // ─────────────────────────── queries (read) ─────────────────────────────

    public function totalMembers(): int
    {
        return Member::count();
    }

    public function newMembersSince(Carbon $from): int
    {
        return Member::where('created_at', '>=', $from)->count();
    }

    public function newMembersBetween(Carbon $from, Carbon $to): int
    {
        return Member::where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
    }

    /** Members seen (last_login_at) within $minutes — a rough "active members" gauge. */
    public function activeMembersSince(Carbon $from): int
    {
        return Member::where('last_login_at', '>=', $from)->count();
    }

    public function lifetimeVisitors(): int
    {
        return AnalyticsVisitor::count();
    }

    public function lifetimePageviews(): int
    {
        return AnalyticsEvent::where('type', 'pageview')->count();
    }

    public function visitorsBetween(Carbon $from, ?Carbon $to = null): int
    {
        $q = AnalyticsEvent::where('created_at', '>=', $from);
        if ($to) {
            $q->where('created_at', '<', $to);
        }
        return (int) $q->distinct('visitor_id')->count('visitor_id');
    }

    public function pageviewsBetween(Carbon $from, ?Carbon $to = null): int
    {
        $q = AnalyticsEvent::where('type', 'pageview')->where('created_at', '>=', $from);
        if ($to) {
            $q->where('created_at', '<', $to);
        }
        return $q->count();
    }

    /** @return array{total:int,members:int,guests:int} */
    public function online(int $minutes = self::ONLINE_MINUTES): array
    {
        $since   = now()->subMinutes($minutes);
        $total   = AnalyticsVisitor::where('last_seen_at', '>=', $since)->count();
        $members = AnalyticsVisitor::where('last_seen_at', '>=', $since)->whereNotNull('member_id')->count();
        return ['total' => $total, 'members' => $members, 'guests' => max(0, $total - $members)];
    }

    /** @return array<string,int> source => pageviews */
    public function trafficSourcesSince(Carbon $from): array
    {
        return AnalyticsEvent::where('type', 'pageview')->where('created_at', '>=', $from)
            ->selectRaw("coalesce(source, 'direct') as s, count(*) as c")
            ->groupBy('s')->orderByDesc('c')
            ->pluck('c', 's')->map(fn ($v) => (int) $v)->toArray();
    }

    /** Aligned last-$days daily series of distinct visitors. @return array<string,int> */
    public function dailyVisitors(int $days = 14): array
    {
        return $this->dailySeries(AnalyticsEvent::query(), $days, 'count(distinct visitor_id)');
    }

    public function dailyPageviews(int $days = 14): array
    {
        return $this->dailySeries(AnalyticsEvent::where('type', 'pageview'), $days, 'count(*)');
    }

    public function dailyRegistrations(int $days = 14): array
    {
        return $this->dailySeries(Member::query(), $days, 'count(*)');
    }

    private function dailySeries($builder, int $days, string $countExpr): array
    {
        $from = now()->startOfDay()->subDays($days - 1);
        $rows = $builder->where('created_at', '>=', $from)
            ->selectRaw("to_char(created_at::date, 'YYYY-MM-DD') as d, {$countExpr} as c")
            ->groupBy('d')->pluck('c', 'd')->toArray();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = now()->startOfDay()->subDays($days - 1 - $i)->format('Y-m-d');
            $out[$d] = (int) ($rows[$d] ?? 0);
        }
        return $out;
    }

    /** Percentage change vs a previous period; null when there's no baseline. */
    public function delta(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round(($current - $previous) / $previous * 100);
    }
}
