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
        $items = $request->user()->listings()->latest()->get()->map(fn (MarketListing $l) => [
            'id'         => $l->id,
            'title_ar'   => $l->title_ar,
            'slug'       => $l->slug,
            'price'      => $l->price !== null ? (float) $l->price : null,
            'currency'   => $l->currency,
            'cover_url'  => $l->cover_url,
            'status'     => $l->status,
            'created_at' => $l->created_at?->toISOString(),
        ]);

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
            'contact_phone'      => 'nullable|string|max:30',
            'contact_whatsapp'   => 'nullable|string|max:30',
            'contact_telegram'   => 'nullable|string|max:60',
            'specs'              => 'nullable|array',
            'images'             => 'nullable|array|max:10',
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

        // Notify the admin on Telegram (if a chat id is configured)
        if ($adminChat = Setting::get('telegram_admin_chat_id')) {
            $telegram->sendMessage((string) $adminChat,
                "🆕 إعلان جديد بانتظار المراجعة\n<b>" . e($listing->title_ar) . "</b>\n"
                . 'من: ' . e($member->name ?? '') . ($member->telegram_username ? ' (@' . $member->telegram_username . ')' : ''));
        }

        return response()->json([
            'data' => ['slug' => $listing->slug, 'status' => $listing->status],
            'message' => $requireApproval ? 'تم استلام إعلانك — بانتظار مراجعة الإدارة' : 'تم نشر إعلانك',
        ], 201);
    }
}
