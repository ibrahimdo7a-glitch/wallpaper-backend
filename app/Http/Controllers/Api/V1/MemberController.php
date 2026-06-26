<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MarketListing;
use App\Models\MemberSave;
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
}
