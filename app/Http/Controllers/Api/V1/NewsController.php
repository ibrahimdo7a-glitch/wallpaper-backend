<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $articles = NewsArticle::published()
            ->with(['category', 'brands', 'carModels'])
            ->when($request->get('category'), fn($q, $v) => $q->whereHas('category', fn($q2) => $q2->where('slug', $v)))
            ->when($request->get('brand'), fn($q, $v) => $q->whereHas('brands', fn($q2) => $q2->where('slug', $v)))
            ->when($request->get('model'), fn($q, $v) => $q->whereHas('carModels', fn($q2) => $q2->where('slug', $v)))
            ->when($request->boolean('featured'), fn($q) => $q->featured())
            ->when($request->get('search'), fn($q, $v) => $q->where(fn($q2) =>
                $q2->where('title_ar', 'like', "%$v%")->orWhere('title_en', 'like', "%$v%")
            ))
            ->when(
                $request->get('sort') === 'likes',
                fn($q) => $q->orderByDesc('likes_count')->orderByDesc('published_at'),
                fn($q) => $q->orderByDesc('published_at')
            )
            ->paginate($request->get('per_page', 12));

        return response()->json([
            'data' => $articles->map(fn($a) => $this->articleList($a)),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
                'total'        => $articles->total(),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $article = NewsArticle::published()->where('slug', $slug)
            ->with(['category', 'brands', 'carModels'])
            ->firstOrFail();

        $article->increment('views_count');

        return response()->json([
            'data' => [
                'id'              => $article->id,
                'title_ar'        => $article->title_ar,
                'title_en'        => $article->title_en,
                'slug'            => $article->slug,
                'summary_ar'      => $article->summary_ar,
                'summary_en'      => $article->summary_en,
                'content_ar'      => $article->content_ar,
                'content_en'      => $article->content_en,
                'cover_image_url' => $article->cover_image_url,
                'source_url'      => $article->source_url,
                'source_name'     => $article->source_name,
                'author_name'     => $article->author_name,
                'is_breaking'     => $article->is_breaking,
                'views_count'     => $article->views_count,
                'likes_count'     => $article->likes_count,
                'published_at'    => $article->published_at,
                'meta_title'      => $article->meta_title,
                'meta_description' => $article->meta_description,
                'category'        => $article->category ? ['name_ar' => $article->category->name_ar, 'slug' => $article->category->slug, 'color' => $article->category->color] : null,
                'brands'          => $article->brands->map(fn($b) => ['name_ar' => $b->name_ar, 'slug' => $b->slug, 'logo_url' => $b->logo_url]),
                'car_models'      => $article->carModels->map(fn($m) => ['name_ar' => $m->name_ar, 'slug' => $m->slug]),
            ],
        ]);
    }

    public function like(int $id)
    {
        $article = NewsArticle::published()->findOrFail($id);
        $article->increment('likes_count');

        return response()->json(['likes_count' => $article->likes_count]);
    }

    public function categories()
    {
        $categories = NewsCategory::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name_ar'  => $c->name_ar,
                'name_en'  => $c->name_en,
                'slug'     => $c->slug,
                'color'    => $c->color,
                'icon'     => $c->icon,
                'articles_count' => $c->articles_count,
            ]);

        return response()->json(['data' => $categories]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email'          => 'required|email|max:255',
            'name'           => 'nullable|string|max:100',
            'subscribe_all'  => 'boolean',
            'brand_ids'      => 'array',
            'brand_ids.*'    => 'integer|exists:brands,id',
            'model_ids'      => 'array',
            'model_ids.*'    => 'integer|exists:car_models,id',
            'category_ids'   => 'array',
            'category_ids.*' => 'integer|exists:news_categories,id',
        ]);

        $subscription = NewsSubscription::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name'          => $validated['name'] ?? null,
                'subscribe_all' => $validated['subscribe_all'] ?? false,
                'ip_address'    => $request->ip(),
            ]
        );

        if ($subscription->status === 'unsubscribed') {
            $subscription->update(['status' => 'active', 'unsubscribed_at' => null]);
        }

        if (!empty($validated['brand_ids'])) {
            $subscription->brands()->syncWithoutDetaching($validated['brand_ids']);
        }
        if (!empty($validated['model_ids'])) {
            $subscription->carModels()->syncWithoutDetaching($validated['model_ids']);
        }
        if (!empty($validated['category_ids'])) {
            $subscription->newsCategories()->syncWithoutDetaching($validated['category_ids']);
        }

        return response()->json(['message' => 'تم الاشتراك بنجاح، يرجى التحقق من بريدك الإلكتروني.'], 201);
    }

    public function unsubscribe(string $token)
    {
        $subscription = NewsSubscription::where('token', $token)->firstOrFail();
        $subscription->unsubscribe();

        return response()->json(['message' => 'تم إلغاء الاشتراك بنجاح.']);
    }

    private function articleList(NewsArticle $a): array
    {
        return [
            'id'              => $a->id,
            'title_ar'        => $a->title_ar,
            'title_en'        => $a->title_en,
            'slug'            => $a->slug,
            'summary_ar'      => $a->summary_ar,
            'summary_en'      => $a->summary_en,
            'cover_image_url' => $a->cover_image_url,
            'is_breaking'     => $a->is_breaking,
            'is_featured'     => $a->is_featured,
            'views_count'     => $a->views_count,
            'likes_count'     => $a->likes_count,
            'published_at'    => $a->published_at,
            'category'        => $a->category ? ['name_ar' => $a->category->name_ar, 'slug' => $a->category->slug, 'color' => $a->category->color] : null,
            'brands'          => $a->brands->map(fn($b) => ['name_ar' => $b->name_ar, 'slug' => $b->slug]),
        ];
    }
}
