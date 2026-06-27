<?php

namespace App\Services;

use App\Models\NewsSource;
use Illuminate\Support\Facades\Http;

/**
 * Pulls the latest headlines from the admin-saved news sources.
 * Each source can be an RSS/Atom feed, or a normal website whose feed we
 * auto-discover; if neither, we fall back to scraping article links.
 *
 * Returns a flat, newest-first list of items:
 *   ['title', 'link', 'source_name', 'source_id', 'published_at', 'image', 'ts']
 */
class NewsFetchService
{
    public function fetchLatest(int $perSource = 5, int $total = 0): array
    {
        $all = [];

        foreach (NewsSource::active()->orderBy('sort_order')->get() as $source) {
            try {
                foreach ($this->fetchSource($source, $perSource) as $item) {
                    if (empty($item['link'])) {
                        continue;
                    }
                    $item['source_name'] = $source->name;
                    $item['source_id']   = $source->id;
                    $all[] = $item;
                }
                $source->forceFill(['last_fetched_at' => now()])->saveQuietly();
            } catch (\Throwable) {
                // A bad source must never break the whole fetch.
            }
        }

        // De-duplicate by link, then sort newest first (items without a date sink to the bottom).
        $seen = [];
        $all  = array_values(array_filter($all, function ($i) use (&$seen) {
            if (isset($seen[$i['link']])) return false;
            $seen[$i['link']] = true;
            return true;
        }));
        usort($all, fn ($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));

        return $total > 0 ? array_slice($all, 0, $total) : $all;
    }

    private function fetchSource(NewsSource $source, int $limit): array
    {
        $body = $this->get($source->url);
        if (! $body) {
            return [];
        }

        if ($this->looksLikeFeed($body)) {
            return $this->parseFeed($body, $limit);
        }

        // A normal web page — try to find its RSS/Atom feed.
        $feedUrl = $this->discoverFeedUrl($body, $source->url);
        if ($feedUrl) {
            $feedBody = $this->get($feedUrl);
            if ($feedBody && $this->looksLikeFeed($feedBody)) {
                return $this->parseFeed($feedBody, $limit);
            }
        }

        // Last resort: scrape plausible article links off the page.
        return $this->scrapeLinks($body, $source->url, $limit);
    }

    private function get(string $url): ?string
    {
        try {
            $resp = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; QEVBot/1.0; +https://qev.app)'])
                ->get($url);
            return $resp->successful() ? $resp->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function looksLikeFeed(string $body): bool
    {
        $head = mb_substr($body, 0, 4000);
        return stripos($head, '<rss') !== false
            || stripos($head, '<feed') !== false
            || stripos($head, '<rdf:RDF') !== false
            || (stripos($head, '<channel') !== false && stripos($head, '<item') !== false);
    }

    private function parseFeed(string $body, int $limit): array
    {
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $xml) {
            return [];
        }

        $items = [];

        if (isset($xml->channel->item)) {            // RSS 2.0
            foreach ($xml->channel->item as $node) {
                $items[] = $this->rssItem($node);
                if (count($items) >= $limit) break;
            }
        } elseif (isset($xml->item)) {               // RDF / RSS 1.0
            foreach ($xml->item as $node) {
                $items[] = $this->rssItem($node);
                if (count($items) >= $limit) break;
            }
        } elseif (isset($xml->entry)) {              // Atom
            foreach ($xml->entry as $node) {
                $items[] = $this->atomEntry($node);
                if (count($items) >= $limit) break;
            }
        }

        return $items;
    }

    private function rssItem(\SimpleXMLElement $node): array
    {
        $date = trim((string) $node->pubDate);
        if (! $date) {
            $dc   = $node->children('http://purl.org/dc/elements/1.1/');
            $date = trim((string) ($dc->date ?? ''));
        }

        return [
            'title'        => trim(html_entity_decode((string) $node->title, ENT_QUOTES, 'UTF-8')),
            'link'         => trim((string) $node->link) ?: trim((string) $node->guid),
            'published_at' => $date,
            'ts'           => $date ? (strtotime($date) ?: 0) : 0,
            'image'        => $this->feedImage($node),
        ];
    }

    private function atomEntry(\SimpleXMLElement $node): array
    {
        $link = '';
        foreach ($node->link as $l) {
            $rel = (string) $l['rel'];
            if ($rel === '' || $rel === 'alternate') {
                $link = (string) $l['href'];
                break;
            }
        }
        if (! $link && isset($node->link[0])) {
            $link = (string) $node->link[0]['href'];
        }

        $date = trim((string) ($node->published ?: $node->updated));

        return [
            'title'        => trim(html_entity_decode((string) $node->title, ENT_QUOTES, 'UTF-8')),
            'link'         => trim($link),
            'published_at' => $date,
            'ts'           => $date ? (strtotime($date) ?: 0) : 0,
            'image'        => $this->feedImage($node),
        ];
    }

    /** Best-effort cover image from a feed item (enclosure / media namespace / <img> in description). */
    private function feedImage(\SimpleXMLElement $node): ?string
    {
        $enc = (string) ($node->enclosure['url'] ?? '');
        $type = (string) ($node->enclosure['type'] ?? '');
        if ($enc && (str_contains($type, 'image') || preg_match('/\.(jpe?g|png|webp|gif)/i', $enc))) {
            return $enc;
        }

        $media = $node->children('http://search.yahoo.com/mrss/');
        if (isset($media->content) && (string) $media->content->attributes()->url) {
            return (string) $media->content->attributes()->url;
        }
        if (isset($media->thumbnail) && (string) $media->thumbnail->attributes()->url) {
            return (string) $media->thumbnail->attributes()->url;
        }

        $desc = (string) $node->description . (string) $node->children('http://purl.org/rss/1.0/modules/content/')->encoded;
        if ($desc && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $desc, $m)) {
            return $m[1];
        }

        return null;
    }

    private function discoverFeedUrl(string $html, string $baseUrl): ?string
    {
        if (preg_match('/<link[^>]+type=["\']application\/(?:rss|atom)\+xml["\'][^>]*>/i', $html, $tag)
            && preg_match('/href=["\']([^"\']+)["\']/i', $tag[0], $href)) {
            return $this->absoluteUrl(html_entity_decode($href[1], ENT_QUOTES, 'UTF-8'), $baseUrl);
        }

        // Some pages put href before type.
        if (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application\/(?:rss|atom)\+xml["\']/i', $html, $m)) {
            return $this->absoluteUrl(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), $baseUrl);
        }

        return null;
    }

    /** Fallback for sites without a feed: collect distinct, meaningful article links. */
    private function scrapeLinks(string $html, string $baseUrl, int $limit): array
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }

        $host  = parse_url($baseUrl, PHP_URL_HOST);
        $items = [];
        $seen  = [];

        foreach ($m as $a) {
            $href  = $this->absoluteUrl(html_entity_decode($a[1], ENT_QUOTES, 'UTF-8'), $baseUrl);
            $title = trim(html_entity_decode(strip_tags($a[2]), ENT_QUOTES, 'UTF-8'));

            if (! $href || mb_strlen($title) < 30 || isset($seen[$href])) {
                continue;
            }
            // Stay on the source's own domain and skip obvious non-article links.
            if ($host && parse_url($href, PHP_URL_HOST) !== $host) {
                continue;
            }
            if (preg_match('#/(tag|category|author|page|about|contact|privacy|login|search)/?#i', $href)) {
                continue;
            }

            $seen[$href] = true;
            $items[] = ['title' => $title, 'link' => $href, 'published_at' => '', 'ts' => 0, 'image' => null];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($base);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = $parts['scheme'];
        $host   = $parts['host'];

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        $path = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';
        return "{$scheme}://{$host}{$path}{$href}";
    }
}
