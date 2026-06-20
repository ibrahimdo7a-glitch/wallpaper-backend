<?php

namespace App\Filament\Widgets;

use App\Models\Wallpaper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestWallpapersWidget extends BaseWidget
{
    protected static ?string $heading = 'آخر الخلفيات المرفوعة';

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(Wallpaper::with('uploader')->latest()->limit(8))
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

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'published',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'انتظار',
                        'published' => 'منشور',
                        'rejected' => 'مرفوض',
                        'hidden' => 'مخفي',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('وقت الرفع')
                    ->since(),
            ]);
    }
}
