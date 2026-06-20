<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallpaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UploaderController extends Controller
{
    public function show(string $username): JsonResponse
    {
        $user = Cache::remember("uploader.{$username}", 600, function () use ($username) {
            return User::active()
                ->where('username', $username)
                ->where('show_public_profile', true)
                ->firstOrFail();
        });

        $stats = $user->getPublicStats();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'bio_ar' => $user->bio_ar,
                'bio_en' => $user->bio_en,
                'website' => $user->website,
                'twitter' => $user->twitter,
                'avatar_url' => $user->avatar
                    ? \Storage::disk('r2')->url($user->avatar)
                    : null,
                'stats' => $stats,
                'member_since' => $user->created_at->toISOString(),
            ],
        ]);
    }

    public function wallpapers(string $username, Request $request): JsonResponse
    {
        $user = User::active()
            ->where('username', $username)
            ->where('show_public_profile', true)
            ->firstOrFail();

        $query = Wallpaper::published()
            ->where('uploaded_by', $user->id)
            ->with(['category:id,name_ar,name_en,slug', 'tags:id,name_ar,name_en,slug']);

        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        $sort = match ($request->sort) {
            'most_downloaded' => ['downloads_count', 'desc'],
            'most_liked' => ['likes_count', 'desc'],
            default => ['published_at', 'desc'],
        };
        $query->orderBy(...$sort);

        $wallpapers = $query->paginate(24);

        return response()->json([
            'data' => $wallpapers->items(),
            'meta' => [
                'current_page' => $wallpapers->currentPage(),
                'last_page' => $wallpapers->lastPage(),
                'total' => $wallpapers->total(),
            ],
        ]);
    }
}
