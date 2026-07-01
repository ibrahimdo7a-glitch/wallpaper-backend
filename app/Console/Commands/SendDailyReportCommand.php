<?php

namespace App\Console\Commands;

use App\Models\AndroidApp;
use App\Models\ContentItem;
use App\Models\MarketListing;
use App\Models\Member;
use App\Models\NewsArticle;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily stats digest DM'd to every super admin linked on Telegram.
 * Scheduled 21:00 Riyadh (routes/console.php). Gated by the daily_report_enabled
 * setting and idempotent per local day. Use --force to send now (test button).
 */
class SendDailyReportCommand extends Command
{
    protected $signature = 'report:daily {--force : Send now, ignoring the enabled toggle and the once-per-day guard}';

    protected $description = 'DM the daily stats report to super admins on Telegram';

    /** All "today" boundaries + the 21:00 send time use this timezone. */
    public const TZ = 'Asia/Riyadh';

    public function handle(AnalyticsService $analytics, TelegramService $tg): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! filter_var(Setting::get('daily_report_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Daily report disabled — skipping.');
            return self::SUCCESS;
        }

        // Idempotent per local day (survives restarts / a duplicate scheduler tick).
        $today = Carbon::now(self::TZ)->toDateString();
        if (! $force && Setting::get('daily_report_sent_date') === $today) {
            $this->info('Already sent today — skipping.');
            return self::SUCCESS;
        }

        $recipients = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')
            ->pluck('telegram_chat_id');

        if ($recipients->isEmpty()) {
            $this->warn('No super admin is linked on Telegram — nothing to send.');
            return self::SUCCESS;
        }

        $text = $this->buildReport($analytics);
        $sent = 0;
        foreach ($recipients as $chatId) {
            $res = $tg->sendMessage((string) $chatId, $text);
            if ($res['ok'] ?? false) {
                $sent++;
            }
            usleep(40000); // ~25/sec, under Telegram limits
        }

        if (! $force) {
            Setting::set('daily_report_sent_date', $today);
        }

        $this->info("Daily report sent to {$sent}/{$recipients->count()} admin(s).");
        return self::SUCCESS;
    }

    /** Build the Telegram (HTML) report body. Public so the settings page can preview / test it. */
    public function buildReport(AnalyticsService $analytics): string
    {
        $start = Carbon::now(self::TZ)->startOfDay()->utc();

        $visitors = 0;
        try {
            $visitors = $analytics->visitorsBetween($start);
        } catch (\Throwable) {
            // analytics is best-effort — never let it block the report
        }

        $rows = [
            ['👁️', 'زوّار الموقع اليوم', $visitors],
            ['🆕', 'أعضاء سجّلوا اليوم', Member::where('created_at', '>=', $start)->count()],
            ['👥', 'إجمالي الأعضاء', Member::count()],
            ['🖼️', 'خلفيات أُضيفت اليوم', ContentItem::where('content_type', 'wallpapers')->where('created_at', '>=', $start)->count()],
            ['📰', 'أخبار جديدة اليوم', NewsArticle::where('created_at', '>=', $start)->count()],
            ['📱', 'برامج أُضيفت اليوم', AndroidApp::where('created_at', '>=', $start)->count()],
            ['🛒', 'إعلانات أُضيفت اليوم', MarketListing::where('created_at', '>=', $start)->count()],
        ];

        $lines = [
            '📊 <b>تقرير qev اليومي</b>',
            '🗓️ ' . Carbon::now(self::TZ)->format('Y-m-d'),
            '',
        ];
        foreach ($rows as [$icon, $label, $value]) {
            $lines[] = "{$icon} {$label}: <b>" . number_format((int) $value) . '</b>';
        }
        $lines[] = '';
        $lines[] = '⚙️ <a href="https://api.qev.app/admin">لوحة التحكم</a>';

        return implode("\n", $lines);
    }
}
