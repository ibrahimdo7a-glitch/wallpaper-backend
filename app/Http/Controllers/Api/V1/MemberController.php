<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MarketListing;
use App\Models\Member;
use App\Models\MemberSave;
use App\Models\MemberSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    // POST /v1/member/saves/toggle  {type, id}
    public function toggleSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:listing,content,news',
            'id'   => 'required|integer',
        ]);

        $member   = $request->user();
        $existing = MemberSave::where('member_id', $member->id)
            ->where('type', $data['type'])->where('item_id', $data['id'])->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['saved' => false]);
        }

        MemberSave::create(['member_id' => $member->id, 'type' => $data['type'], 'item_id' => $data['id']]);
        return response()->json(['saved' => true]);
    }

    // GET /v1/member/saved-ids?type=listing  — ids the member saved (to fill hearts)
    public function savedIds(Request $request): JsonResponse
    {
        $type = $request->query('type', 'listing');
        return response()->json(['ids' => $request->user()->saves()->where('type', $type)->pluck('item_id')]);
    }

    // GET /v1/member/saves?type=listing  — the saved items themselves
    public function mySaves(Request $request): JsonResponse
    {
        $type = $request->query('type', 'listing');
        $ids  = $request->user()->saves()->where('type', $type)->latest()->pluck('item_id');

        if ($type === 'listing') {
            $byId = MarketListing::whereIn('id', $ids)->get()->keyBy('id');
            $items = $ids->map(fn ($id) => $byId->get($id))->filter()->map(fn (MarketListing $l) => [
                'id'        => $l->id,
                'title_ar'  => $l->title_ar,
                'slug'      => $l->slug,
                'price'     => $l->price !== null ? (float) $l->price : null,
                'currency'  => $l->currency,
                'cover_url' => $l->cover_url,
                'status'    => $l->status,
            ])->values();
            return response()->json(['data' => $items]);
        }

        return response()->json(['data' => []]);
    }

    // POST /v1/member/prefs  {news_telegram}
    public function updatePrefs(Request $request): JsonResponse
    {
        $data = $request->validate(['news_telegram' => 'required|boolean']);
        $request->user()->update(['news_telegram' => $data['news_telegram']]);
        return response()->json(['news_telegram' => (bool) $data['news_telegram']]);
    }

    // GET /v1/member/dashboard  — stats + subscriptions + brand options (one call)
    public function dashboard(Request $request): JsonResponse
    {
        /** @var Member $m */
        $m = $request->user();

        $listings = MarketListing::where('member_id', $m->id);
        $stats = [
            'listings_total'     => (clone $listings)->count(),
            'listings_published' => (clone $listings)->where('status', 'published')->count(),
            'listings_pending'   => (clone $listings)->where('status', 'pending')->count(),
            'views_total'        => (int) (clone $listings)->sum('views_count'),
            'saved_count'        => $m->saves()->count(),
            'expiring_soon'      => (clone $listings)->where('status', 'published')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(3)])->count(),
            'member_since'       => $m->created_at?->toIso8601String(),
        ];

        $subs = MemberSubscription::where('member_id', $m->id)->get();
        $shape = function (string $channel) use ($subs): array {
            $row = $subs->firstWhere('channel', $channel);
            return ['enabled' => (bool) $row, 'brand_id' => $row?->brand_id];
        };

        return response()->json([
            'stats' => $stats,
            'subscriptions' => [
                'news'  => ['enabled' => (bool) $m->news_telegram],
                'cars'  => $shape('cars'),
                'parts' => $shape('parts'),
            ],
            'brands' => Brand::active()->orderBy('sort_order')->orderBy('name_ar')->get(['id', 'name_ar']),
            'telegram_linked' => filled($m->telegram_id),
        ]);
    }

    // POST /v1/member/subscriptions  — reconcile all three channels in one call
    public function updateSubscriptions(Request $request): JsonResponse
    {
        $v = $request->validate([
            'news'           => 'required|boolean',
            'cars_enabled'   => 'required|boolean',
            'cars_brand_id'  => 'nullable|integer|exists:brands,id',
            'parts_enabled'  => 'required|boolean',
            'parts_brand_id' => 'nullable|integer|exists:brands,id',
        ]);

        /** @var Member $m */
        $m = $request->user();
        $m->update(['news_telegram' => $v['news']]);
        $this->syncChannel($m, 'cars', (bool) $v['cars_enabled'], $v['cars_brand_id'] ?? null);
        $this->syncChannel($m, 'parts', (bool) $v['parts_enabled'], $v['parts_brand_id'] ?? null);

        return response()->json(['ok' => true]);
    }

    /** Replace a member's rows for one channel with the desired single-brand (or all) state. */
    private function syncChannel(Member $m, string $channel, bool $enabled, ?int $brandId): void
    {
        MemberSubscription::where('member_id', $m->id)->where('channel', $channel)->delete();
        if ($enabled) {
            MemberSubscription::create(['member_id' => $m->id, 'channel' => $channel, 'brand_id' => $brandId]);
        }
    }
}
