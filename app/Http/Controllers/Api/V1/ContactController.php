<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    // POST /v1/contact — a member sends a short message that reaches the super admin on Telegram.
    public function send(Request $request, TelegramService $telegram): JsonResponse
    {
        if (! filter_var(Setting::get('contact_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['error' => 'ميزة التواصل متوقّفة حاليًا'], 403);
        }

        $member = $request->user();
        $data = $request->validate(['message' => 'required|string|max:200']);

        $msg = ContactMessage::create([
            'member_id'   => $member->id,
            'member_name' => $member->name ?: ($member->telegram_username ?: 'عضو'),
            'message'     => $data['message'],
        ]);

        // Keep only the latest 50 messages — the rest are pruned automatically.
        $keep = ContactMessage::orderByDesc('id')->limit(50)->pluck('id');
        ContactMessage::whereNotIn('id', $keep)->delete();

        // DM the super admin(s) who linked their Telegram.
        $admins = User::role('super_admin')
            ->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')
            ->pluck('telegram_chat_id');

        $text = "📩 <b>رسالة تواصل جديدة</b>\nمن: " . e($msg->member_name)
              . ($member->telegram_username ? ' (@' . $member->telegram_username . ')' : '')
              . "\n\n" . e($msg->message);

        foreach ($admins as $chatId) {
            $telegram->sendMessage((string) $chatId, $text);
        }

        return response()->json(['ok' => true, 'message' => 'تم إرسال رسالتك ✓']);
    }
}
