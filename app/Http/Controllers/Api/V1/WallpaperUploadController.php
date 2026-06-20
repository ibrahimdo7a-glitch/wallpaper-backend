<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWallpaperImage;
use App\Models\Wallpaper;
use App\Models\Watermark;
use App\Services\ImageService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WallpaperUploadController extends Controller
{
    public function __construct(
        protected ImageService $imageService,
        protected StorageService $storageService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Permission check
        if (! $user->hasPermissionTo('can_upload_wallpapers')) {
            return response()->json(['message' => 'ليس لديك صلاحية رفع الصور'], 403);
        }

        // Daily limit check
        if (! $user->canUploadToday()) {
            return response()->json([
                'message' => "لقد تجاوزت الحد اليومي للرفع ({$user->daily_upload_limit} صورة)"
            ], 429);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                "max:{$user->max_file_size_mb}024", // KB
                'mimetypes:image/jpeg,image/png,image/webp',
            ],
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string|max:2000',
            'description_en' => 'nullable|string|max:2000',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:tags,id',
            'watermark_id' => 'nullable|exists:watermarks,id',
            'device_type' => 'nullable|in:mobile,desktop,tablet,all',
        ]);

        $file = $request->file('file');

        // Validate file deeply
        $this->imageService->validateFile($file);

        // Check duplicate via hash
        $imageInfo = $this->imageService->getImageInfo($file);

        if (Wallpaper::where('image_hash', $imageInfo['image_hash'])->exists()) {
            return response()->json(['message' => 'هذه الصورة موجودة مسبقاً في المنصة'], 422);
        }

        // Check allowed categories
        if ($request->category_id) {
            $allowedCategories = $user->allowedCategories;
            if ($allowedCategories->isNotEmpty() && ! $allowedCategories->contains('id', $request->category_id)) {
                return response()->json(['message' => 'غير مسموح لك الرفع في هذا القسم'], 403);
            }
        }

        // Handle watermark
        $watermarkId = null;
        if ($request->watermark_id) {
            // Verify user has access to this watermark
            if ($this->canUseWatermark($user, $request->watermark_id)) {
                $watermarkId = $request->watermark_id;
            }
        } elseif (! $user->can_upload_without_watermark) {
            // Auto-assign default watermark
            $defaultWatermark = Watermark::getDefault();
            $watermarkId = $defaultWatermark?->id;
        }

        // Store original file
        $originalPath = $this->storageService->storeOriginal($file);

        // Generate slug
        $slug = Str::slug($request->title_en ?? $request->title_ar ?? Str::random(12));
        if (Wallpaper::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(6);
        }

        // Create wallpaper record
        $wallpaper = Wallpaper::create([
            ...$imageInfo,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'slug' => $slug,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'original_file' => $originalPath,
            'device_type' => $request->device_type ?? 'all',
            'category_id' => $request->category_id,
            'watermark_id' => $watermarkId,
            'uploaded_by' => $user->id,
            'status' => 'pending',
        ]);

        // Attach tags
        if ($request->tags) {
            $wallpaper->tags()->attach($request->tags);
        }

        // Dispatch processing job
        ProcessWallpaperImage::dispatch($wallpaper->id)->onQueue('image-processing');

        return response()->json([
            'message' => 'تم رفع الصورة بنجاح وجاري معالجتها',
            'data' => [
                'id' => $wallpaper->id,
                'slug' => $wallpaper->slug,
                'status' => $wallpaper->status,
            ],
        ], 201);
    }

    protected function canUseWatermark(\App\Models\User $user, int $watermarkId): bool
    {
        $allowed = $user->allowedWatermarks;

        // If no specific watermarks set, check role
        if ($allowed->isEmpty()) {
            $roleWatermarks = Watermark::whereHas('allowedRoles', function ($q) use ($user) {
                $q->whereIn('id', $user->roles->pluck('id'));
            })->pluck('id');

            return $roleWatermarks->isEmpty() || $roleWatermarks->contains($watermarkId);
        }

        return $allowed->contains('id', $watermarkId);
    }
}
