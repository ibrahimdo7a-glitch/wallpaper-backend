<?php

namespace App\Services;

use App\Models\Download;
use App\Models\Like;
use App\Models\User;
use App\Models\View;
use App\Models\Wallpaper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public function getGlobalStats(): array
    {
        return Cache::remember('stats.global', 300, function () {
            return [
                'total_wallpapers' => Wallpaper::count(),
                'published_wallpapers' => Wallpaper::where('status', 'published')->count(),
                'pending_wallpapers' => Wallpaper::where('status', 'pending')->count(),
                'rejected_wallpapers' => Wallpaper::where('status', 'rejected')->count(),
                'hidden_wallpapers' => Wallpaper::where('status', 'hidden')->count(),
                'total_moderators' => User::whereHas('roles')->count(),
                'total_downloads' => Download::count(),
                'total_likes' => Like::count(),
                'total_views' => View::count(),
                'downloads_today' => Download::whereDate('created_at', today())->count(),
                'downloads_week' => Download::where('created_at', '>=', now()->subDays(7))->count(),
                'downloads_month' => Download::where('created_at', '>=', now()->subDays(30))->count(),
            ];
        });
    }

    public function getTopWallpapers(string $metric = 'downloads_count', int $limit = 10): array
    {
        return Cache::remember("stats.top_wallpapers.{$metric}", 300, function () use ($metric, $limit) {
            return Wallpaper::published()
                ->orderByDesc($metric)
                ->limit($limit)
                ->with('uploader', 'category')
                ->get()
                ->toArray();
        });
    }

    public function getTopModerators(string $metric = 'downloads_count', int $limit = 10): array
    {
        return Cache::remember("stats.top_moderators.{$metric}", 300, function () use ($metric, $limit) {
            return User::withCount(['wallpapers as published_count' => fn($q) => $q->published()])
                ->withSum(['wallpapers as total_downloads' => fn($q) => $q->published()], 'downloads_count')
                ->withSum(['wallpapers as total_likes' => fn($q) => $q->published()], 'likes_count')
                ->orderByDesc("total_{$metric}")
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    public function getTopCategories(int $limit = 10): array
    {
        return Cache::remember('stats.top_categories', 300, function () use ($limit) {
            return \App\Models\Category::withCount(['wallpapers as published_count' => fn($q) => $q->published()])
                ->orderByDesc('published_count')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    public function getDailyStats(int $days = 30): array
    {
        $cacheKey = "stats.daily.{$days}";

        return Cache::remember($cacheKey, 600, function () use ($days) {
            $downloads = Download::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            $likes = Like::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            $uploads = Wallpaper::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date')
                ->toArray();

            return compact('downloads', 'likes', 'uploads');
        });
    }

    public function getUserStats(int $userId): array
    {
        return Cache::remember("stats.user.{$userId}", 300, function () use ($userId) {
            $user = User::findOrFail($userId);

            $wallpapers = $user->wallpapers();

            return [
                'total_wallpapers' => $wallpapers->count(),
                'published' => $wallpapers->clone()->where('status', 'published')->count(),
                'pending' => $wallpapers->clone()->where('status', 'pending')->count(),
                'rejected' => $wallpapers->clone()->where('status', 'rejected')->count(),
                'hidden' => $wallpapers->clone()->where('status', 'hidden')->count(),
                'total_downloads' => $wallpapers->clone()->sum('downloads_count'),
                'total_likes' => $wallpapers->clone()->sum('likes_count'),
                'total_views' => $wallpapers->clone()->sum('views_count'),
                'best_wallpaper' => $wallpapers->clone()->published()->orderByDesc('downloads_count')->first(),
                'approval_rate' => $this->getApprovalRate($userId),
            ];
        });
    }

    protected function getApprovalRate(int $userId): float
    {
        $total = Wallpaper::where('uploaded_by', $userId)
            ->whereIn('status', ['published', 'rejected'])
            ->count();

        if ($total === 0) {
            return 0;
        }

        $approved = Wallpaper::where('uploaded_by', $userId)
            ->where('status', 'published')
            ->count();

        return round(($approved / $total) * 100, 1);
    }

    public function clearCache(): void
    {
        Cache::forget('stats.global');
        Cache::forget('stats.top_wallpapers.downloads_count');
        Cache::forget('stats.top_wallpapers.likes_count');
        Cache::forget('stats.top_wallpapers.views_count');
        Cache::forget('stats.top_moderators.downloads');
        Cache::forget('stats.top_categories');
    }
}
