<?php

namespace App\Http\Controllers;

use App\Models\AndroidApp;
use App\Models\Brand;
use App\Models\BrandSection;
use App\Models\CarModel;
use App\Models\ContentItem;
use App\Models\MarketListing;
use App\Models\NewsArticle;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Serves /robots.txt and /sitemap.xml. The Next.js frontend rewrites
 * qev.app/{robots.txt,sitemap.xml} to these backend routes, so crawlers see
 * them on the content domain while we build them straight from the DB.
 */
class SitemapController extends Controller
{
    /** Both site locales — every URL is listed once per locale with hreflang alternates. */
    private const LOCALES = ['ar', 'en'];

    public function robots(): Response
    {
        $front = rtrim(config('app.frontend_url', 'https://qev.app'), '/');

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api',
            '',
            "Sitemap: {$front}/sitemap.xml",
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        // Each entry: [path, lastmod(ISO8601|null), changefreq, priority]. Path is locale-less.
        $entries = [];
        $add = function (string $path, $lastmod = null, string $freq = 'weekly', string $priority = '0.6') use (&$entries) {
            $entries[] = [$path, $lastmod ? $lastmod->toAtomString() : null, $freq, $priority];
        };

        // ── Static pages ──
        $add('', null, 'daily', '1.0');
        foreach (['/cars', '/parts', '/brands', '/apps', '/news'] as $p) {
            $add($p, null, 'daily', '0.9');
        }
        foreach (['/about', '/terms', '/privacy'] as $p) {
            $add($p, null, 'monthly', '0.3');
        }

        // ── Geo landing pages (slugs mirror frontend lib/countries.ts) ──
        foreach (['qatar', 'saudi-arabia', 'uae', 'kuwait', 'bahrain', 'oman', 'jordan', 'iraq'] as $cs) {
            $add("/electric-cars/{$cs}", null, 'weekly', '0.8');
        }

        // ── Brands ──
        Brand::active()->get(['id', 'slug', 'updated_at'])->each(
            fn (Brand $b) => $add("/brands/{$b->slug}", $b->updated_at, 'weekly', '0.8')
        );

        // ── Car models ──
        CarModel::where('is_active', true)->with('brand:id,slug')->get()->each(function (CarModel $m) use ($add) {
            if ($m->brand) {
                $add("/brands/{$m->brand->slug}/models/{$m->slug}", $m->updated_at, 'weekly', '0.7');
            }
        });

        // ── Brand sections ──
        BrandSection::where('is_enabled', true)->with('brand:id,slug')->get()->each(function (BrandSection $s) use ($add) {
            if ($s->brand) {
                $add("/brands/{$s->brand->slug}/{$s->slug}", $s->updated_at, 'weekly', '0.6');
            }
        });

        // ── Market listings ──
        MarketListing::published()->get(['slug', 'updated_at'])->each(
            fn (MarketListing $l) => $add("/market/{$l->slug}", $l->updated_at, 'daily', '0.7')
        );

        // ── News ──
        NewsArticle::published()->get(['slug', 'updated_at'])->each(
            fn (NewsArticle $n) => $add("/news/{$n->slug}", $n->updated_at, 'weekly', '0.7')
        );

        // ── Apps ──
        AndroidApp::published()->get(['slug', 'updated_at'])->each(
            fn (AndroidApp $a) => $add("/apps/{$a->slug}", $a->updated_at, 'weekly', '0.6')
        );

        // ── Wallpapers / content items (URL uses the item id under its brand + section) ──
        ContentItem::published()->with(['brand:id,slug', 'brandSection:id,slug'])->get()->each(function (ContentItem $i) use ($add) {
            if ($i->brand && $i->brandSection) {
                $add("/brands/{$i->brand->slug}/{$i->brandSection->slug}/{$i->id}", $i->updated_at, 'monthly', '0.5');
            }
        });

        return $this->render($entries);
    }

    /** Render the <urlset> with one <url> per locale and full hreflang alternates. */
    private function render(array $entries): string
    {
        $front = rtrim(config('app.frontend_url', 'https://qev.app'), '/');
        $esc = fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($entries as [$path, $lastmod, $freq, $priority]) {
            foreach (self::LOCALES as $loc) {
                $out .= "  <url>\n";
                $out .= '    <loc>' . $esc("{$front}/{$loc}{$path}") . "</loc>\n";
                foreach (self::LOCALES as $alt) {
                    $out .= '    <xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . $esc("{$front}/{$alt}{$path}") . "\"/>\n";
                }
                $out .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $esc("{$front}/ar{$path}") . "\"/>\n";
                if ($lastmod) {
                    $out .= "    <lastmod>{$lastmod}</lastmod>\n";
                }
                $out .= "    <changefreq>{$freq}</changefreq>\n";
                $out .= "    <priority>{$priority}</priority>\n";
                $out .= "  </url>\n";
            }
        }

        return $out . '</urlset>';
    }
}
