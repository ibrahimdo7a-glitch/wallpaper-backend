<?php

namespace App\Console\Commands;

use App\Services\HealthCheckService;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check {--no-save : Print only, do not write a report file}';

    protected $description = 'Run a full diagnostics report on the site, services, database, storage and resources.';

    /** status => [icon, console-color] */
    private const STYLE = [
        'ok'    => ['✅', 'green'],
        'warn'  => ['⚠️ ', 'yellow'],
        'error' => ['❌', 'red'],
        'na'    => ['➖', 'gray'],
    ];

    public function handle(HealthCheckService $health): int
    {
        $this->line('');
        $this->line('  <options=bold>QEV — تقرير فحص الموقع والخدمات</>');
        $this->line('  جارٍ الفحص…');

        $report = $health->run();
        $plain = [];

        $emit = function (string $colored, string $plainText = null) use (&$plain) {
            $this->line($colored);
            $plain[] = $plainText ?? preg_replace('/<[^>]+>/', '', $colored);
        };

        $s = $report['summary'];
        $emit('');
        $emit("  ════════════════════════════════════════════════");
        $emit("  📅 {$report['generated_at']}    ⏱️ {$report['duration_ms']}ms");
        $emit("  ✅ {$s['ok']} ناجح    ⚠️ {$s['warn']} تحذير    ❌ {$s['error']} فشل    ➖ {$s['na']} غير مطبّق");
        $emit("  ════════════════════════════════════════════════");

        foreach ($report['sections'] as $section) {
            $emit('');
            $emit("  <options=bold>{$section['title']}</>");
            foreach ($section['checks'] as $c) {
                [$icon, $color] = self::STYLE[$c['status']] ?? ['•', 'white'];
                $label = $c['label'];
                $value = $c['value'];
                $line = sprintf('   %s  %-32s %s', $icon, $this->trim($label, 32), $value);
                $note = $c['note'] ? "  — {$c['note']}" : '';
                $emit("<fg={$color}>{$line}</>{$note}", "   {$icon}  {$label}  {$value}{$note}");
            }
        }

        // Resource bars
        $r = $report['resources'];
        $emit('');
        $emit('  <options=bold>📊 الموارد</>');
        if ($r['ram']) {
            $emit('   RAM   ' . $this->bar($r['ram']['used_pct']) . " {$r['ram']['used_pct']}%");
        }
        if ($r['disk']) {
            $emit('   Disk  ' . $this->bar($r['disk']['used_pct']) . " {$r['disk']['used_pct']}%");
        }

        // Recommendations
        if (! empty($report['recommendations'])) {
            $emit('');
            $emit('  <options=bold;fg=yellow>🛠️ توصيات الإصلاح</>');
            foreach ($report['recommendations'] as $i => $rec) {
                $n = $i + 1;
                $emit("<fg=yellow>   {$n}. {$rec}</>", "   {$n}. {$rec}");
            }
        } else {
            $emit('');
            $emit('  <fg=green>🎉 لا توجد توصيات — كل شيء سليم.</>', '   لا توجد توصيات — كل شيء سليم.');
        }

        $emit('');

        // Save the report
        if (! $this->option('no-save')) {
            $file = storage_path('logs/health-report-' . now()->format('Y-m-d-H-i') . '.txt');
            $header = "QEV Health Report — {$report['generated_at']}\n"
                . str_repeat('=', 50) . "\n";
            @file_put_contents($file, $header . implode("\n", $plain) . "\n");
            $this->line("  💾 حُفظ التقرير: <fg=cyan>{$file}</>");
            $this->line('');
        }

        return $report['summary']['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function trim(string $s, int $len): string
    {
        return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
    }

    private function bar(int $pct): string
    {
        $pct = max(0, min(100, $pct));
        $filled = (int) round($pct / 5);
        return '[' . str_repeat('█', $filled) . str_repeat('░', 20 - $filled) . ']';
    }
}
