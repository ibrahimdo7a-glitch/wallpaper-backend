<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\Like;
use App\Models\Report;
use App\Models\View;
use App\Models\Wallpaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WallpaperController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Wallpaper::published()
            ->with(['uploader:id,name,username,avatar', 'category:id,name_ar,name_en,slug', 'tags:id,name_ar,name_en,slug'])
            ->select([
                'id', 'title_ar', 'title_en', 'slug', 'thumbnail_file', 'webp_file',
                'watermarked_webp_file', 'watermark_applied', 'width', 'height',
                'resolution_label', 'device_type', 'is_free', 'is_paid', 'price', 'currency',
                'views_count', 'downloads_count', 'likes_count', 'is_featured',
                'uploaded_by', 'category_id', 'published_at',
            ]);

        // Filters
        if ($request->category) {
            $cat = $request->category;
            if (is_numeric($cat)) {
                $query->where('category_id', (int) $cat);
            } else {
                $catId = \App\Models\Category::where('slug', $cat)->value('id');
                if ($catId) $query->where('category_id', $catId);
            }
        }

        if ($request->device_type) {
            $query->forDevice($request->device_type);
        }

        if ($request->resolution) {
            $query->where('resolution_label', $request->resolution);
        }

        if ($request->is_free !== null) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        if ($request->tag) {
            $query->whereHas('tags', fn($q) => $q->where('slug', $request->tag));
        }

        if ($request->uploader) {
            $query->whereHas('uploader', fn($q) => $q->where('username', $request->uploader));
        }

        // Sorting
        $sort = match ($request->sort) {
            'most_downloaded' => ['downloads_count', 'desc'],
            'most_liked' => ['likes_count', 'desc'],
            'most_viewed' => ['views_count', 'desc'],
            'oldest' => ['published_at', 'asc'],
            default => ['published_at', 'desc'],
        };
        $query->orderBy(...$sort);

        // Cap per_page so nobody can dump the whole catalog in one request.
        $wallpapers = $query->paginate(min(48, max(1, $request->integer('per_page', 24))));

        return response()->json([
            'data' => $wallpapers->map(fn($w) => $this->formatWallpaper($w)),
            'meta' => [
                'current_page' => $wallpapers->currentPage(),
                'last_page' => $wallpapers->lastPage(),
                'per_page' => $wallpapers->perPage(),
                'total' => $wallpapers->total(),
            ],
        ]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $wallpaper = Cache::remember("wallpaper.{$slug}", 300, function () use ($slug) {
            return Wallpaper::published()
                ->with(['uploader:id,name,username,avatar,bio_ar,bio_en', 'category', 'tags', 'watermark:id,name'])
                ->where('slug', $slug)
                ->firstOrFail();
        });

        // Track view (async)
        $this->trackView($wallpaper->id, $request);

        return response()->json([
            'data' => $this->formatWallpaperFull($wallpaper, $request),
        ]);
    }

    public function like(int $id, Request $request): JsonResponse
    {
        $key = "like:{$id}:" . $this->getVisitorHash($request);

        $executed = RateLimiter::attempt($key, 1, function () use ($id, $request) {
            $ipHash = $this->hashIp($request->ip());
            $uaHash = $this->hashUserAgent($request->userAgent());

            $existing = Like::where('wallpaper_id', $id)
                ->where('ip_hash', $ipHash)
                ->where('user_agent_hash', $uaHash)
                ->first();

            if ($existing) {
                return ['liked' => true, 'already_liked' => true];
            }

            Like::create([
                'wallpaper_id' => $id,
                'ip_hash' => $ipHash,
                'user_agent_hash' => $uaHash,
                'cookie_id' => $request->cookie('visitor_id'),
            ]);

            Wallpaper::where('id', $id)->increment('likes_count');

            return ['liked' => true, 'already_liked' => false];
        }, 60);

        if (! $executed) {
            return response()->json(['message' => 'Too many requests'], 429);
        }

        return response()->json($executed);
    }

    public function download(int $id, Request $request): JsonResponse
    {
        $key = "download:{$id}:" . $this->hashIp($request->ip());

        $allowed = RateLimiter::attempt($key, (int) config('app.download_rate_limit', 10), function () {}, 3600);

        if (! $allowed) {
            return response()->json(['message' => 'تجاوزت حد التحميل المسموح به لهذه الساعة'], 429);
        }

        $wallpaper = Wallpaper::published()->findOrFail($id);

        Download::create([
            'wallpaper_id' => $id,
            'ip_hash' => $this->hashIp($request->ip()),
            'user_agent_hash' => $this->hashUserAgent($request->userAgent()),
            'cookie_id' => $request->cookie('visitor_id'),
        ]);

        $wallpaper->increment('downloads_count');

        $url = $wallpaper->getDownloadUrlAttribute();

        return response()->json(['download_url' => $url]);
    }

    public function report(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|in:copyright,inappropriate,spam,offensive,other',
            'message' => 'nullable|string|max:1000',
        ]);

        $wallpaper = Wallpaper::published()->findOrFail($id);

        Report::create([
            'wallpaper_id' => $id,
            'reason' => $request->reason,
            'message' => $request->message,
            'ip_hash' => $this->hashIp($request->ip()),
        ]);

        return response()->json(['message' => 'تم إرسال البلاغ بنجاح']);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['data' => [], 'meta' => []]);
        }

        $wallpapers = Wallpaper::published()
            ->where(function ($q) use ($query) {
                $q->where('title_ar', 'ILIKE', "%{$query}%")
                    ->orWhere('title_en', 'ILIKE', "%{$query}%")
                    ->orWhere('description_ar', 'ILIKE', "%{$query}%")
                    ->orWhere('description_en', 'ILIKE', "%{$query}%")
                    ->orWhereHas('tags', fn($tq) => $tq->where('name_ar', 'ILIKE', "%{$query}%")->orWhere('name_en', 'ILIKE', "%{$query}%"));
            })
            ->with(['uploader:id,name,username', 'category:id,name_ar,name_en,slug'])
            ->orderByDesc('downloads_count')
            ->paginate(24);

        return response()->json([
            'data' => $wallpapers->map(fn($w) => $this->formatWallpaper($w)),
            'meta' => [
                'current_page' => $wallpapers->currentPage(),
                'last_page' => $wallpapers->lastPage(),
                'total' => $wallpapers->total(),
            ],
        ]);
    }

    protected function trackView(int $wallpaperId, Request $request): void
    {
        $key = "viewed:{$wallpaperId}:" . $this->getVisitorHash($request);

        if (! Cache::has($key)) {
            Cache::put($key, true, 3600);

            View::create([
                'wallpaper_id' => $wallpaperId,
                'ip_hash' => $this->hashIp($request->ip()),
                'user_agent_hash' => $this->hashUserAgent($request->userAgent()),
                'created_at' => now(),
            ]);

            Wallpaper::where('id', $wallpaperId)->increment('views_count');
        }
    }

    protected function hashIp(string $ip): string
    {
        return hash('sha256', $ip . config('app.key'));
    }

    protected function hashUserAgent(string $ua): string
    {
        return hash('sha256', $ua . config('app.key'));
    }

    protected function getVisitorHash(Request $request): string
    {
        return hash('sha256', $request->ip() . $request->userAgent() . config('app.key'));
    }

    protected function formatWallpaper(Wallpaper $wallpaper): array
    {
        $disk = config('filesystems.default', 'public');
        $imageFile = $wallpaper->watermark_applied && $wallpaper->watermarked_webp_file
            ? $wallpaper->watermarked_webp_file
            : ($wallpaper->webp_file ?? $wallpaper->original_file);

        return [
            'id' => $wallpaper->id,
            'title_ar' => $wallpaper->title_ar,
            'title_en' => $wallpaper->title_en,
            'slug' => $wallpaper->slug,
            'thumbnail_url' => $wallpaper->thumbnail_file ? \Storage::disk($disk)->url($wallpaper->thumbnail_file) : null,
            'image_url' => \Storage::disk($disk)->url($imageFile),
            'width' => $wallpaper->width,
            'height' => $wallpaper->height,
            'resolution_label' => $wallpaper->resolution_label,
            'device_type' => $wallpaper->device_type,
            'is_free' => $wallpaper->is_free,
            'is_paid' => $wallpaper->is_paid,
            'price' => $wallpaper->price,
            'currency' => $wallpaper->currency,
            'views_count' => $wallpaper->views_count,
            'downloads_count' => $wallpaper->downloads_count,
            'likes_count' => $wallpaper->likes_count,
            'is_featured' => $wallpaper->is_featured,
            'published_at' => $wallpaper->published_at?->toISOString(),
            'uploader' => $wallpaper->uploader ? [
                'id' => $wallpaper->uploader->id,
                'name' => $wallpaper->uploader->name,
                'username' => $wallpaper->uploader->username,
                'avatar_url' => $wallpaper->uploader->avatar
                    ? \Storage::disk($disk)->url($wallpaper->uploader->avatar)
                    : null,
            ] : null,
            'category' => $wallpaper->category ? [
                'id' => $wallpaper->category->id,
                'name_ar' => $wallpaper->category->name_ar,
                'name_en' => $wallpaper->category->name_en,
                'slug' => $wallpaper->category->slug,
            ] : null,
            'tags' => $wallpaper->tags?->map(fn($t) => [
                'id' => $t->id,
                'name_ar' => $t->name_ar,
                'name_en' => $t->name_en,
                'slug' => $t->slug,
            ]),
        ];
    }

    protected function formatWallpaperFull(Wallpaper $wallpaper, Request $request): array
    {
        $base = $this->formatWallpaper($wallpaper);

        $disk = config('filesystems.default', 'public');

        return array_merge($base, [
            'description_ar' => $wallpaper->description_ar,
            'description_en' => $wallpaper->description_en,
            'file_size' => $wallpaper->file_size,
            'mime_type' => $wallpaper->mime_type,
            'uploader' => $wallpaper->uploader ? [
                'id' => $wallpaper->uploader->id,
                'name' => $wallpaper->uploader->name,
                'username' => $wallpaper->uploader->username,
                'bio_ar' => $wallpaper->uploader->bio_ar,
                'bio_en' => $wallpaper->uploader->bio_en,
                'avatar_url' => $wallpaper->uploader->avatar
                    ? \Storage::disk($disk)->url($wallpaper->uploader->avatar)
                    : null,
                'stats' => $wallpaper->uploader->getPublicStats(),
            ] : null,
            'related' => $this->getRelated($wallpaper),
        ]);
    }

    protected function getRelated(Wallpaper $wallpaper): array
    {
        return Wallpaper::published()
            ->where('id', '!=', $wallpaper->id)
            ->where('category_id', $wallpaper->category_id)
            ->orderByDesc('downloads_count')
            ->limit(12)
            ->get()
            ->map(fn($w) => $this->formatWallpaper($w))
            ->toArray();
    }
}
