<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'المشرفون';
    }

    public static function getModelLabel(): string
    {
        return 'مشرف';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المشرفون';
    }

    public static function canAccess(): bool
    {
        // Moderators list is visible to the super admin only.
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('المعلومات الأساسية')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('الاسم الكامل')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('username')
                        ->label('اسم المستخدم')
                        ->required()
                        ->unique(User::class, 'username', ignoreRecord: true)
                        ->maxLength(50)
                        ->alphaNum(),

                    Forms\Components\TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email', ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->label('كلمة المرور')
                        ->password()
                        ->dehydrateStateUsing(fn(?string $state) => $state ? Hash::make($state) : null)
                        ->dehydrated(fn(?string $state) => filled($state))
                        ->required(fn(string $context) => $context === 'create'),

                    Forms\Components\FileUpload::make('avatar')
                        ->label('الصورة الشخصية')
                        ->image()
                        ->disk('r2')
                        ->directory('avatars')
                        ->visibility('private'),
                ])->columns(2),

            Forms\Components\Section::make('الأدوار والصلاحيات')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('الدور')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),

                    Forms\Components\Select::make('permissions')
                        ->label('صلاحيات إضافية')
                        ->relationship('permissions', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ])->columns(2),

            Forms\Components\Section::make('إشعارات تلجرام (مراجعة الإعلانات)')
                ->description('فعّل الخيار ثم اربط تلجرام المشرف ليصله إشعار + زر مراجعة بكل إعلان عضو جديد بانتظار المراجعة.')
                ->schema([
                    Forms\Components\Toggle::make('notify_new_listings')
                        ->label('يستقبل إشعارات الإعلانات الجديدة على تلجرام'),
                    Forms\Components\Placeholder::make('tg_link_status')
                        ->label('حالة ربط تلجرام')
                        ->content(fn (?User $record) => $record?->telegram_chat_id
                            ? '✅ مربوط — جاهز لاستقبال الإشعارات'
                            : '❌ غير مربوط — اضغط «رابط ربط تلجرام» بالأعلى وأرسل الرابط للمشرف ليفتحه من تلجرامه.'),
                ]),

            Forms\Components\Section::make('إعدادات الرفع')
                ->schema([
                    Forms\Components\TextInput::make('daily_upload_limit')
                        ->label('الحد اليومي للرفع')
                        ->numeric()
                        ->default(50)
                        ->minValue(0)
                        ->maxValue(1000),

                    Forms\Components\TextInput::make('max_file_size_mb')
                        ->label('الحد الأقصى لحجم الملف (MB)')
                        ->numeric()
                        ->default(20)
                        ->minValue(1)
                        ->maxValue(100),

                    Forms\Components\Toggle::make('auto_publish')
                        ->label('نشر تلقائي'),

                    Forms\Components\Toggle::make('can_upload_without_watermark')
                        ->label('رفع بدون توقيع'),
                ])->columns(2),

            Forms\Components\Section::make('الأقسام المسموحة')
                ->schema([
                    Forms\Components\Select::make('allowedCategories')
                        ->label('الأقسام المسموحة للرفع')
                        ->relationship('allowedCategories', 'name_ar')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->placeholder('جميع الأقسام'),

                    Forms\Components\Select::make('allowedWatermarks')
                        ->label('التواقيع المسموحة')
                        ->relationship('allowedWatermarks', 'name')
                        ->multiple()
                        ->preload()
                        ->placeholder('جميع التواقيع'),
                ])->columns(2),

            Forms\Components\Section::make('الحالة')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعل')
                        ->default(true),

                    Forms\Components\Toggle::make('force_password_change')
                        ->label('إجبار تغيير كلمة المرور عند الدخول',),

                    Forms\Components\Toggle::make('show_public_profile')
                        ->label('إظهار الملف الشخصي للعموم')
                        ->default(true),
                ])->columns(3),

            Forms\Components\Section::make('معلومات إضافية')
                ->schema([
                    Forms\Components\Textarea::make('bio_ar')->label('نبذة (عربي)')->rows(3),
                    Forms\Components\Textarea::make('bio_en')->label('نبذة (إنجليزي)')->rows(3),
                    Forms\Components\TextInput::make('website')->label('الموقع')->url(),
                    Forms\Components\TextInput::make('twitter')->label('تويتر'),
                ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('الصورة')
                    ->disk('r2')
                    ->circular()
                    ->width(40)
                    ->height(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),

                Tables\Columns\TextColumn::make('username')
                    ->label('اسم المستخدم')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('البريد')
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('الدور')
                    ->badge(),

                Tables\Columns\TextColumn::make('wallpapers_count')
                    ->label('الخلفيات')
                    ->counts('wallpapers'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('مفعل'),

                Tables\Filters\SelectFilter::make('roles')
                    ->label('الدور')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('deactivate')
                    ->label('تعطيل')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(User $u) => $u->is_active)
                    ->action(fn(User $u) => $u->update(['is_active' => false])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
