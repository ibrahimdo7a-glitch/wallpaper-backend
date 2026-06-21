<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WallpaperResource\Pages;
use App\Jobs\ApplyWatermark;
use App\Models\Wallpaper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class WallpaperResource extends Resource
{
    protected static ?string $model = Wallpaper::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'الخلفيات';
    }

    public static function getModelLabel(): string
    {
        return 'خلفية';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الخلفيات';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = Auth::user();

        // Moderators with limited scope only see their own wallpapers
        if ($user->hasPermissionTo('can_edit_all_wallpapers')) {
            return $query;
        }

        return $query->where('uploaded_by', $user->id);
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();

        return $form->schema([
            Forms\Components\Section::make('معلومات الخلفية')
                ->schema([
                    Forms\Components\TextInput::make('title_ar')
                        ->label('العنوان (عربي)')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('title_en')
                        ->label('العنوان (إنجليزي)')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description_ar')
                        ->label('الوصف (عربي)')
                        ->rows(3),

                    Forms\Components\Textarea::make('description_en')
                        ->label('الوصف (إنجليزي)')
                        ->rows(3),

                    Forms\Components\Select::make('category_id')
                        ->label('القسم')
                        ->relationship('category', 'name_ar')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('tags')
                        ->label('الوسوم')
                        ->relationship('tags', 'name_ar')
                        ->multiple()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('device_type')
                        ->label('نوع الجهاز')
                        ->options([
                            'mobile' => 'جوال',
                            'desktop' => 'كمبيوتر',
                            'tablet' => 'تابلت',
                            'all' => 'الكل',
                        ])
                        ->default('all'),
                ])->columns(2),

            Forms\Components\Section::make('الملف')
                ->schema([
                    Forms\Components\FileUpload::make('original_file')
                        ->label('صورة الخلفية')
                        ->image()
                        ->disk(config('filesystems.default', 'public'))
                        ->directory('wallpapers/original/' . date('Y/m'))
                        ->visibility(config('filesystems.default', 'public') === 'r2' ? 'private' : 'public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(20480)
                        ->required(fn($context) => $context === 'create'),
                ])->visibleOn('create'),

            Forms\Components\Section::make('التوقيع / العلامة المائية')
                ->schema([
                    Forms\Components\Select::make('watermark_id')
                        ->label('التوقيع')
                        ->options(fn() => \App\Models\Watermark::where('is_active', true)->pluck('name', 'id')->toArray())
                        ->nullable()
                        ->placeholder('بدون توقيع')
                        ->visible(fn() => $user->can_upload_without_watermark || true),

                    Forms\Components\Toggle::make('watermark_applied')
                        ->label('تم تطبيق التوقيع')
                        ->disabled()
                        ->visibleOn('edit'),
                ])->columns(2),

            Forms\Components\Section::make('الحالة')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('الحالة')
                        ->options([
                            'pending' => 'قيد الانتظار',
                            'published' => 'منشور',
                            'rejected' => 'مرفوض',
                            'hidden' => 'مخفي',
                        ])
                        ->default('published')
                        ->visible(fn() => Auth::user()->hasPermissionTo('can_publish_wallpapers')),

                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('سبب الرفض')
                        ->visible(fn(Forms\Get $get) => $get('status') === 'rejected'),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('مميزة')
                        ->visible(fn() => Auth::user()->hasPermissionTo('can_edit_all_wallpapers')),
                ])->columns(2),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title_ar')->label('Meta Title (عربي)'),
                    Forms\Components\TextInput::make('meta_title_en')->label('Meta Title (إنجليزي)'),
                    Forms\Components\Textarea::make('meta_description_ar')->label('Meta Description (عربي)')->rows(2),
                    Forms\Components\Textarea::make('meta_description_en')->label('Meta Description (إنجليزي)')->rows(2),
                ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_file')
                    ->label('معاينة')
                    ->state(fn($record) => $record->thumbnail_file ?? $record->original_file)
                    ->disk(config('filesystems.default', 'public'))
                    ->width(80)
                    ->height(50),

                Tables\Columns\TextColumn::make('title_ar')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('الرافع')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name_ar')
                    ->label('القسم'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'published',
                        'danger' => 'rejected',
                        'secondary' => 'hidden',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'published' => 'منشور',
                        'rejected' => 'مرفوض',
                        'hidden' => 'مخفي',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('resolution_label')
                    ->label('الدقة'),

                Tables\Columns\TextColumn::make('downloads_count')
                    ->label('التحميلات')
                    ->sortable()
                    ->numeric(),

                Tables\Columns\TextColumn::make('likes_count')
                    ->label('الإعجابات')
                    ->sortable()
                    ->numeric(),

                Tables\Columns\IconColumn::make('watermark_applied')
                    ->label('توقيع')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'published' => 'منشور',
                        'rejected' => 'مرفوض',
                        'hidden' => 'مخفي',
                    ]),

                Tables\Filters\SelectFilter::make('device_type')
                    ->label('نوع الجهاز')
                    ->options([
                        'mobile' => 'جوال',
                        'desktop' => 'كمبيوتر',
                        'tablet' => 'تابلت',
                        'all' => 'الكل',
                    ]),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('القسم')
                    ->relationship('category', 'name_ar'),

                Tables\Filters\SelectFilter::make('watermark_id')
                    ->label('التوقيع')
                    ->relationship('watermark', 'name'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label('نشر')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(Wallpaper $w) => Auth::user()->hasPermissionTo('can_publish_wallpapers') && $w->status !== 'published')
                    ->action(function (Wallpaper $wallpaper) {
                        $wallpaper->publish(Auth::user());
                        Notification::make()->title('تم نشر الخلفية')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('سبب الرفض')
                            ->required(),
                    ])
                    ->visible(fn(Wallpaper $w) => Auth::user()->hasPermissionTo('can_reject_wallpapers') && $w->status !== 'rejected')
                    ->action(function (Wallpaper $wallpaper, array $data) {
                        $wallpaper->reject($data['rejection_reason']);
                        Notification::make()->title('تم رفض الخلفية')->warning()->send();
                    }),

                Tables\Actions\Action::make('reapply_watermark')
                    ->label('إعادة تطبيق التوقيع')
                    ->icon('heroicon-o-paint-brush')
                    ->color('info')
                    ->visible(fn(Wallpaper $w) => Auth::user()->hasPermissionTo('can_apply_watermarks') && $w->watermark_id)
                    ->action(function (Wallpaper $wallpaper) {
                        ApplyWatermark::dispatch($wallpaper->id)->onQueue('watermark');
                        Notification::make()->title('جاري إعادة تطبيق التوقيع')->info()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn(Wallpaper $w) => Auth::user()->hasPermissionTo('can_edit_all_wallpapers')
                        || ($w->uploaded_by === Auth::id() && Auth::user()->hasPermissionTo('can_edit_own_wallpapers'))),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(Wallpaper $w) => Auth::user()->hasPermissionTo('can_delete_all_wallpapers')
                        || ($w->uploaded_by === Auth::id() && Auth::user()->hasPermissionTo('can_delete_own_wallpapers'))),

                Tables\Actions\RestoreAction::make()
                    ->visible(fn() => Auth::user()->hasPermissionTo('can_restore_deleted_wallpapers')),

                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn() => Auth::user()->hasPermissionTo('can_force_delete_wallpapers')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish_selected')
                        ->label('نشر المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn() => Auth::user()->hasPermissionTo('can_publish_wallpapers'))
                        ->action(function ($records) {
                            $records->each(fn($w) => $w->publish(Auth::user()));
                            Notification::make()->title('تم نشر الخلفيات المحددة')->success()->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => Auth::user()->hasPermissionTo('can_delete_all_wallpapers')),

                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn() => Auth::user()->hasPermissionTo('can_restore_deleted_wallpapers')),

                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn() => Auth::user()->hasPermissionTo('can_force_delete_wallpapers')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWallpapers::route('/'),
            'create' => Pages\CreateWallpaper::route('/create'),
            'edit' => Pages\EditWallpaper::route('/{record}/edit'),
        ];
    }
}
