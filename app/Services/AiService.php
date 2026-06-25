<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Anthropic (Claude) Messages API.
 * Key, model and the editable prompts all live in Settings (admin-managed).
 */
class AiService
{
    public ?string $lastError = null;

    public function isConfigured(): bool
    {
        return filled(Setting::get('ai_api_key'))
            && filter_var(Setting::get('ai_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function model(): string
    {
        return Setting::get('ai_model') ?: 'claude-haiku-4-5-20251001';
    }

    /** Low-level Claude call. Returns the text, or null (with $lastError set) on failure. */
    public function complete(string $system, string $user, int $maxTokens = 3000): ?string
    {
        $this->lastError = null;
        $key = Setting::get('ai_api_key');
        if (! $key) {
            $this->lastError = 'لم يُضبط مفتاح API';
            return null;
        }

        try {
            $res = Http::timeout(90)->withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model(),
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => [['role' => 'user', 'content' => $user]],
            ]);

            if (! $res->successful()) {
                $this->lastError = $res->json('error.message') ?? ('HTTP ' . $res->status());
                return null;
            }

            return $res->json('content.0.text');
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    /** Pull the first JSON object out of a model reply. */
    private function parseJson(?string $text): ?array
    {
        if (! $text) return null;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
            return is_array($data) ? $data : null;
        }
        $this->lastError = 'تعذّر قراءة استجابة الذكاء الاصطناعي';
        return null;
    }

    /**
     * Translate Arabic fields → English.
     * $fields example: ['title' => '...', 'summary' => '...', 'content' => '...'].
     * Returns the same keys with English values.
     */
    public function translate(array $fields): ?array
    {
        $fields = array_filter($fields, fn ($v) => filled($v));
        if (empty($fields)) {
            $this->lastError = 'لا توجد حقول عربية لترجمتها';
            return null;
        }

        $prompt = Setting::get('ai_translation_prompt') ?: $this->defaultTranslationPrompt();
        $keys   = implode(', ', array_keys($fields));

        $system = $prompt . "\n\nأخرج JSON فقط بالمفاتيح: {$keys}. القيم بالإنجليزية. لا تكتب أي شيء خارج الـ JSON.";
        $user   = "ترجم الحقول التالية إلى الإنجليزية:\n\n"
                . json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $this->parseJson($this->complete($system, $user, 4000));
    }

    /**
     * Fetch a source URL, extract the useful parts, summarise + translate.
     * Returns: title_ar, summary_ar, content_ar, title_en, summary_en, content_en.
     */
    public function articleFromUrl(string $url): ?array
    {
        $html = null;
        try {
            $resp = Http::timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; QEVBot/1.0)'])->get($url);
            if ($resp->successful()) {
                $html = $resp->body();
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        if (! $html) {
            $this->lastError = $this->lastError ?: 'تعذّر فتح الرابط';
            return null;
        }

        // Reduce the page to readable text to keep token cost low.
        $text = preg_replace('#<(script|style|nav|footer|header|aside|form|noscript)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $text = mb_substr($text, 0, 14000);

        if (mb_strlen($text) < 120) {
            $this->lastError = 'لم أجد نصًا كافيًا في الصفحة';
            return null;
        }

        $prompt = Setting::get('ai_summarize_prompt') ?: $this->defaultSummarizePrompt();
        $system = $prompt . "\n\nأخرج JSON فقط بهذي المفاتيح: title_ar, summary_ar, content_ar, title_en, summary_en, content_en. "
                . "حقول content بصيغة HTML بسيط (<p> و <h3> فقط). لا تكتب أي شيء خارج الـ JSON.";
        $user   = "نص المقال المصدر:\n\n" . $text;

        return $this->parseJson($this->complete($system, $user, 4000));
    }

    public function defaultTranslationPrompt(): string
    {
        return 'أنت محرّر محترف ثنائي اللغة لموقع أخبار وتطبيقات سيارات كهربائية وصينية. '
            . 'ترجم المحتوى العربي إلى إنجليزي طبيعي وسلس كأنه مكتوب من كاتب إنجليزي أصلي — وليس ترجمة حرفية. '
            . 'اجعله واضحًا وعصريًا ومريحًا للقراءة، اختصر الحشو والتكرار، واحفظ كل الأرقام وأسماء السيارات والحقائق بدقة تامة.';
    }

    public function defaultSummarizePrompt(): string
    {
        return 'أنت محرّر أخبار محترف لموقع سيارات كهربائية وصينية. من نص المقال المصدر: '
            . 'استخرج الأجزاء المهمة والمفيدة فقط وأعد صياغتها في خبر عربي مركّز وواضح وجذّاب — لا تنسخ حرفيًا. '
            . 'اكتب عنوانًا قويًا، وملخّصًا قصيرًا (سطرين)، ومحتوى منظّمًا بفقرات قصيرة. '
            . 'احفظ الأرقام والحقائق وأسماء السيارات، وتجاهل الإعلانات والقوائم والروابط غير المتعلقة. '
            . 'ثم قدّم النسخة الإنجليزية بأسلوب طبيعي.';
    }
}
