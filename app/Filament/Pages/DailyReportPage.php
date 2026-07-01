<?php

namespace App\Filament\Pages;

use App\Console\Commands\SendDailyReportCommand;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;

class DailyReportPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'التحليلات';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function getTitle(): string|Htmlable { return 'التقرير اليومي'; }
    public static function getNavigationLabel(): string { return 'التقرير اليومي'; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'daily_report_enabled' => filter_var(Setting::get('daily_report_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function form(Form $form): Form
    {
        $linked = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')->count();

        return $form->schema([
            Forms\Components\Section::make('تقرير تلجرام اليومي')
                ->description('ملخّص يومي بأرقام الموقع يصلك على تلجرام الساعة ٩ مساءً (توقيت الرياض).')
                ->schema([
                    Forms\Components\Toggle::make('daily_report_enabled')
                        ->label('تفعيل التقرير اليومي')->inline(false)
                        ->helperText('عند التفعيل يُرسَل التقرير تلقائيًا كل ليلة الساعة ٩ لكل سوبر أدمن مربوط بتلجرام.'),

                    Forms\Components\Placeholder::make('recipients')
                        ->label('المستلمون')
                        ->content($linked > 0
                            ? "✅ {$linked} سوبر أدمن مربوط بتلجرام وسيصله التقرير."
                            : '⚠️ لا يوجد سوبر أدمن مربوط بتلجرام — اربط تلجرامك من صفحة «المشرفون» أولًا، وإلا لن يصل التقرير.'),

                    Forms\Components\Placeholder::make('preview')
                        ->label('محتوى التقرير الآن (معاينة)')
                        ->content(fn (): Htmlable => new HtmlString(
                            '<div style="white-space:pre-line;line-height:2;font-size:13px;background:rgba(0,0,0,.04);border-radius:12px;padding:12px 16px">'
                            . app(SendDailyReportCommand::class)->buildReport(app(AnalyticsService::class))
                            . '</div>'
                        )),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        Setting::set('daily_report_enabled', ($state['daily_report_enabled'] ?? false) ? '1' : '0');
        Notification::make()->title('تم حفظ إعدادات التقرير ✓')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')->label('حفظ')->icon('heroicon-o-check')->action('save'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sendTest')
                ->label('أرسل تقريرًا تجريبيًا الآن')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('سيُرسَل التقرير فورًا لكل سوبر أدمن مربوط بتلجرام (بغضّ النظر عن حالة التفعيل).')
                ->action(function () {
                    Artisan::call('report:daily', ['--force' => true]);
                    $out = trim(Artisan::output());
                    Notification::make()->title('تم تنفيذ الإرسال')->body($out ?: 'تم.')->success()->send();
                }),
        ];
    }
}
