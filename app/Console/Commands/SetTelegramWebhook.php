<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\TelegramAuthController;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Register the Telegram bot webhook used for member login';

    public function handle(TelegramService $telegram): int
    {
        $url = TelegramAuthController::webhookUrl();
        $res = $telegram->setWebhook($url);

        if ($res['ok']) {
            $this->info("Webhook set: {$url}");
            return self::SUCCESS;
        }

        $this->error('Failed: ' . ($res['error'] ?? 'unknown'));
        return self::FAILURE;
    }
}
