<?php

namespace App\Filament\Widgets;

use App\Models\Download;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DownloadsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'التحميلات - آخر 30 يوم';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $user = auth()->user();
        $canViewGlobal = $user->hasPermissionTo('can_view_global_statistics');

        $query = Download::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date');

        if (! $canViewGlobal) {
            $query->whereHas('wallpaper', fn($q) => $q->where('uploaded_by', $user->id));
        }

        $data = $query->get()->keyBy('date');

        $labels = [];
        $values = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d/m');
            $values[] = $data[$date]->count ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'التحميلات',
                    'data' => $values,
                    'fill' => true,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => '#3B82F6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
