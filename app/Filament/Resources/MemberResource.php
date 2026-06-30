<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static ?string $slug = 'members';
    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'الأعضاء';
    protected static ?int    $navigationSort   = 1;

    // Members list is visible to the super admin only.
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationLabel(): string  { return 'الأعضاء'; }
    public static function getModelLabel(): string       { return 'عضو'; }
    public static function getPluralModelLabel(): string { return 'الأعضاء'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('name')->label('الاسم')->maxLength(120),
                    Forms\Components\TextInput::make('telegram_username')->label('يوزر تلجرام')->disabled(),
                ]),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('status')->label('الحالة')
                        ->options(['active' => 'مفعّل', 'banned' => 'محظور'])->required(),
                    Forms\Components\Select::make('tier')->label('الباقة')
                        ->options(['none' => 'عادي', 'premium' => 'مميّز', 'merchant' => 'تاجر'])->default('none'),
                    Forms\Components\Toggle::make('is_premium')->label('مميّز ⭐')->inline(false),
                ]),
                Forms\Components\TextInput::make('phone')->label('الهاتف')->disabled(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('telegram_username')->label('يوزر تلجرام')->prefix('@')->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('listings_count')->label('إعلاناته')->counts('listings'),
                Tables\Columns\TextColumn::make('tier')->label('الباقة')
                    ->formatStateUsing(fn ($state) => match ($state) { 'premium' => '⭐ مميّز', 'merchant' => '🏪 تاجر', default => 'عادي' }),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn ($state) => $state === 'banned' ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $state === 'banned' ? 'محظور' : 'مفعّل'),
                Tables\Columns\TextColumn::make('last_login_at')->label('آخر دخول')->dateTime('d/m/Y')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('انضمّ')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['active' => 'مفعّل', 'banned' => 'محظور']),
            ])
            ->actions([
                Tables\Actions\Action::make('telegram_dm')
                    ->label('مراسلة تلجرام')->icon('heroicon-o-paper-airplane')->color('info')
                    ->modalHeading('إرسال رسالة عبر تلجرام')
                    ->form([
                        Forms\Components\Textarea::make('message')->label('الرسالة')->required()->rows(4)->maxLength(2000),
                    ])
                    ->action(function (Member $record, array $data) {
                        try {
                            $res = app(\App\Services\TelegramService::class)->sendMessage((string) $record->telegram_id, $data['message']);
                            ($res['ok'] ?? false)
                                ? \Filament\Notifications\Notification::make()->title('تم الإرسال عبر تلجرام ✓')->success()->send()
                                : \Filament\Notifications\Notification::make()->title('تعذّر الإرسال — قد لا يكون العضو بدأ محادثة البوت')->warning()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()->title('فشل الإرسال')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('site_message')
                    ->label('رسالة بالموقع')->icon('heroicon-o-chat-bubble-left-right')->color('warning')
                    ->modalHeading('رسالة تظهر للعضو داخل الموقع')
                    ->modalDescription('تظهر للعضو في نافذة عند فتحه الموقع، مرة واحدة فقط ثم تختفي.')
                    ->fillForm(fn (Member $record) => ['site_message' => $record->site_message])
                    ->form([
                        Forms\Components\Textarea::make('site_message')->label('نص الرسالة')->required()->rows(4)->maxLength(1000),
                    ])
                    ->action(function (Member $record, array $data) {
                        $record->update(['site_message' => $data['site_message']]);
                        \Filament\Notifications\Notification::make()->title('سيراها العضو عند زيارته القادمة ✓')->success()->send();
                    }),

                Tables\Actions\Action::make('toggle_ban')
                    ->label(fn (Member $record) => $record->status === 'banned' ? 'تفعيل' : 'طرد / حظر')
                    ->icon('heroicon-o-no-symbol')
                    ->color(fn (Member $record) => $record->status === 'banned' ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(function (Member $record) {
                        $banning = $record->status !== 'banned';
                        $record->update(['status' => $banning ? 'banned' : 'active']);
                        if ($banning) {
                            // Force-logout: revoke all the member's API tokens.
                            try { $record->tokens()->delete(); } catch (\Throwable) {}
                        }
                        \Filament\Notifications\Notification::make()
                            ->title($banning ? 'تم طرد العضو وإنهاء جلساته' : 'تمت إعادة تفعيل العضو')
                            ->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'edit'  => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
