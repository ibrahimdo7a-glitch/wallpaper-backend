<?php

namespace App\Filament\Widgets;

use App\Models\Wallpaper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingReviewWidget extends BaseWidget
{
    protected static ?string $heading = 'الخلفيات المعلقة - تحتاج مراجعة';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Wallpaper::with(['uploader', 'category'])
                    ->where('status', 'pending')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_file')
                    ->label('معاينة')
                    ->disk('r2')
                    ->width(60)
                    ->height(40),

                Tables\Columns\TextColumn::make('title_ar')
                    ->label('العنوان')
                    ->limit(25),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('الرافع'),

                Tables\Columns\TextColumn::make('category.name_ar')
                    ->label('القسم'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الرفع')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label('نشر')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn() => auth()->user()->hasPermissionTo('can_publish_wallpapers'))
                    ->action(fn(Wallpaper $w) => $w->publish(auth()->user())),

                Tables\Actions\Action::make('view')
                    ->label('عرض')
                    ->url(fn(Wallpaper $w) => route('filament.admin.resources.wallpapers.edit', $w))
                    ->icon('heroicon-o-eye'),
            ]);
    }
}
