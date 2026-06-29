<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MarketListing;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MemberListingController extends Controller
{
    // GET /v1/member/listings — the member's own listings
    public function mine(Request $request): JsonResponse
    {
        $items = $request->user()->listings()->latest()->get()->map(function (MarketListing $l) {
            // A published listing can be renewed (bumped to the top) once a week, and
            // expires (drops out of the public market) 14 days after publish/renew.
            $canRenew = false;
            $isExpired = false;
            if ($l->status === 'published') {
                $base = $l->published_at ?? $l->created_at;
                $canRenew = now()->gte($base->copy()->addWeek());
                $isExpired = now()->gte($base->copy()->addDays(14));
            }

            return [
                'id'         => $l->id,
                'title_ar'   => $l->title_ar,
                'slug'       => $l->slug,
                'price'      => $l->price !== null ? (float) $l->price : null,
                'currency'   => $l->currency,
                'cover_url'  => $l->cover_url,
                'status'     => $l->status,
                'rejection_reason' => $l->rejection_reason,
                'can_renew'    => $canRenew,
                'is_expired'   => $isExpired,
                'created_at' => $l->created_at?->toISOString(),
            ];
        });

        return response()->json(['data' => $items]);
    }

    // POST /v1/member/listings — submit a new listing (goes to pending review)
    public function store(Request $request, TelegramService $telegram): JsonResponse
    {
        if (! filter_var(Setting::get('member_listings_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['error' => 'نشر الإعلانات متوقّف حاليًا'], 403);
        }

        $member = $request->user();
        if ($member->isBanned()) {
            return response()->json(['error' => 'حسابك محظور'], 403);
        }

        $data = $request->validate([
            'section'            => 'required|in:cars,parts',
            'listing_type'       => 'nullable|in:car_sale,car_request',
            'market_category_id' => 'nullable|exists:market_categories,id',
            'title_ar'           => 'required|string|max:200',
            'description_ar'     => 'nullable|string|max:5000',
            'price'              => 'nullable|numeric|min:0',
            'currency'           => 'nullable|string|max:3',
            'is_negotiable'      => 'nullable|boolean',
            'condition'          => 'nullable|in:new,used,na',
            'country'            => 'nullable|string|max:60',
            'city'               => 'nullable|string|max:80',
            'brand_id'           => 'nullable|exists:brands,id',
            'car_model_id'       => 'nullable|exists:car_models,id',
            'custom_brand'       => 'nullable|string|max:60',
            'custom_model'       => 'nullable|string|max:60',
            'contact_phone'      => 'nullable|string|max:30',
            'contact_whatsapp'   => 'nullable|string|max:30',
            'contact_telegram'   => 'nullable|string|max:60',
            'specs'              => 'nullable|array',
            'images'             => 'nullable|array|max:3',
            'images.*'           => 'image|max:5120',
        ]);

        $paths = [];
        $disk  = config('filesystems.default', 'public');
        $visibility = in_array($disk, ['r2', 's3'], true) ? 'private' : 'public';
        foreach ((array) $request->file('images', []) as $file) {
            $name = Str::random(40) . '.' . ($file->getClientOriginalExtension() ?: 'jpg');
            Storage::disk($disk)->putFileAs('market', $file, $name, $visibility);
            $paths[] = "market/{$name}";
        }

        $requireApproval = filter_var(Setting::get('member_listings_require_approval', '1'), FILTER_VALIDATE_BOOLEAN);

        $listing = MarketListing::create([
            'listing_type'       => $data['section'] === 'cars' ? ($data['listing_type'] ?? 'car_sale') : 'part',
            'market_category_id' => $data['section'] === 'parts' ? ($data['market_category_id'] ?? null) : null,
            'title_ar'           => $data['title_ar'],
            'description_ar'     => $data['description_ar'] ?? null,
            'price'              => $data['price'] ?? null,
            'currency'           => $data['currency'] ?? 'QAR',
            'is_negotiable'      => $data['is_negotiable'] ?? false,
            'condition'          => $data['condition'] ?? null,
            'country'            => $data['country'] ?? null,
            'city'               => $data['city'] ?? null,
            'brand_id'           => $data['brand_id'] ?? null,
            'car_model_id'       => $data['car_model_id'] ?? null,
            'custom_brand'       => $data['custom_brand'] ?? null,
            'custom_model'       => $data['custom_model'] ?? null,
            'specs'              => $data['specs'] ?? null,
            'images'             => $paths,
            'contact_name'       => $member->name,
            'contact_phone'      => $data['contact_phone'] ?? null,
            'contact_whatsapp'   => $data['contact_whatsapp'] ?? null,
            'contact_telegram'   => $data['contact_telegram'] ?? $member->telegram_username,
            'source'             => 'user',
            'member_id'          => $member->id,
            'status'             => $requireApproval ? 'pending' : 'published',
        ]);

        // Notify every opted-in moderator on Telegram.
        if ($listing->status === 'pending') {
            $telegram->notifyModeratorsNewListing($listing->load('member'));
        }

        return response()->json([
            'data' => ['slug' => $listing->slug, 'status' => $listing->status],
            'message' => $requireApproval ? 'تم استلام إعلانك — بانتظار مراجعة الإدارة' : 'تم نشر إعلانك',
        ], 201);
    }

    // GET /v1/member/listings/{id} — fetch the member's own listing for editing
    public function show(Request $request, int $id): JsonResponse
    {
        $l = $request->user()->listings()->findOrFail($id);

        return response()->json(['data' => [
            'id'                 => $l->id,
            'section'            => in_array($l->listing_type, ['car_sale', 'car_request'], true) ? 'cars' : 'parts',
            'listing_type'       => $l->listing_type,
            'market_category_id' => $l->market_category_id,
            'title_ar'           => $l->title_ar,
            'description_ar'     => $l->description_ar,
            'price'              => $l->price !== null ? (float) $l->price : null,
            'currency'           => $l->currency,
            'is_negotiable'      => (bool) $l->is_negotiable,
            'condition'          => $l->condition,
            'country'            => $l->country,
            'city'               => $l->city,
            'brand_id'           => $l->brand_id,
            'car_model_id'       => $l->car_model_id,
            'custom_brand'       => $l->custom_brand,
            'custom_model'       => $l->custom_model,
            'contact_phone'      => $l->contact_phone,
            'contact_whatsapp'   => $l->contact_whatsapp,
            'images'             => $l->imageUrls(),
            'status'             => $l->status,
            'rejection_reason'   => $l->rejection_reason,
        ]]);
    }

    // POST /v1/member/listings/{id} — edit and resubmit for review
    public function update(Request $request, int $id, TelegramService $telegram): JsonResponse
    {
        $member = $request->user();
        if ($member->isBanned()) {
            return response()->json(['error' => 'حسابك محظور'], 403);
        }

        $l = $member->listings()->findOrFail($id);

        $data = $request->validate([
            'title_ar'         => 'required|string|max:200',
            'description_ar'   => 'nullable|string|max:5000',
            'price'            => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|max:3',
            'is_negotiable'    => 'nullable|boolean',
            'condition'        => 'nullable|in:new,used,na',
            'country'          => 'nullable|string|max:60',
            'city'             => 'nullable|string|max:80',
            'brand_id'         => 'nullable|exists:brands,id',
            'car_model_id'     => 'nullable|exists:car_models,id',
            'custom_brand'     => 'nullable|string|max:60',
            'custom_model'     => 'nullable|string|max:60',
            'contact_phone'    => 'nullable|string|max:30',
            'contact_whatsapp' => 'nullable|string|max:30',
            'images'           => 'nullable|array|max:3',
            'images.*'         => 'image|max:5120',
        ]);

        // New images replace the set; no upload keeps the existing images.
        if ($request->hasFile('images')) {
            $disk       = config('filesystems.default', 'public');
            $visibility = in_array($disk, ['r2', 's3'], true) ? 'private' : 'public';
            $paths      = [];
            foreach ((array) $request->file('images', []) as $file) {
                $name = Str::random(40) . '.' . ($file->getClientOriginalExtension() ?: 'jpg');
                Storage::disk($disk)->putFileAs('market', $file, $name, $visibility);
                $paths[] = "market/{$name}";
            }
            $l->images = $paths;
        }

        $requireApproval = filter_var(Setting::get('member_listings_require_approval', '1'), FILTER_VALIDATE_BOOLEAN);

        $l->fill([
            'title_ar'         => $data['title_ar'],
            'description_ar'   => $data['description_ar'] ?? null,
            'price'            => $data['price'] ?? null,
            'currency'         => $data['currency'] ?? 'QAR',
            'is_negotiable'    => $data['is_negotiable'] ?? false,
            'condition'        => $data['condition'] ?? null,
            'country'          => $data['country'] ?? null,
            'city'             => $data['city'] ?? null,
            'brand_id'         => $data['brand_id'] ?? null,
            'car_model_id'     => $data['car_model_id'] ?? null,
            'custom_brand'     => $data['custom_brand'] ?? null,
            'custom_model'     => $data['custom_model'] ?? null,
            'contact_phone'    => $data['contact_phone'] ?? null,
            'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
            'status'           => $requireApproval ? 'pending' : 'published',
            'rejection_reason' => null,
        ]);
        $l->save();

        if ($l->status === 'pending') {
            $telegram->notifyModeratorsNewListing($l->load('member'));
        }

        return response()->json([
            'data'    => ['slug' => $l->slug, 'status' => $l->status],
            'message' => $requireApproval ? 'تم إرسال التعديل للمراجعة' : 'تم تحديث إعلانك',
        ]);
    }

    // POST /v1/member/listings/{id}/action {action: pause|resume|sold|renew}
    // The member manages their own listing: pause (hide from market), resume, mark sold,
    // or renew (bump to the top once a week — no change to views or data).
    public function action(Request $request, int $id): JsonResponse
    {
        $l = $request->user()->listings()->findOrFail($id);
        $action = (string) $request->input('action');

        switch ($action) {
            case 'pause':
                if ($l->status !== 'published') {
                    return response()->json(['error' => 'لا يمكن إيقاف هذا الإعلان'], 422);
                }
                $l->update(['status' => 'hidden']);
                break;

            case 'resume':
                if (! in_array($l->status, ['hidden', 'sold'], true)) {
                    return response()->json(['error' => 'لا يمكن إعادة نشر هذا الإعلان'], 422);
                }
                $l->update(['status' => 'published', 'published_at' => $l->published_at ?? now()]);
                break;

            case 'sold':
                if (! in_array($l->status, ['published', 'hidden'], true)) {
                    return response()->json(['error' => 'لا يمكن تعليم هذا الإعلان كمباع'], 422);
                }
                $l->update(['status' => 'sold']);
                break;

            case 'renew':
                if ($l->status !== 'published') {
                    return response()->json(['error' => 'يمكن تجديد الإعلانات المنشورة فقط'], 422);
                }
                $eligibleAt = ($l->published_at ?? $l->created_at)->copy()->addWeek();
                if (now()->lt($eligibleAt)) {
                    $days = max(1, (int) ceil(now()->floatDiffInDays($eligibleAt)));
                    return response()->json(['error' => "لم يمضِ على نشر الإعلان ٧ أيام — متبقّي {$days} يوم لهذا الخيار."], 422);
                }
                // Bump to the top without touching views or data.
                $l->update(['published_at' => now()]);
                break;

            default:
                return response()->json(['error' => 'إجراء غير معروف'], 422);
        }

        $canRenew = $l->status === 'published'
            && now()->gte(($l->published_at ?? $l->created_at)->copy()->addWeek());

        return response()->json(['ok' => true, 'status' => $l->status, 'can_renew' => $canRenew]);
    }
}
