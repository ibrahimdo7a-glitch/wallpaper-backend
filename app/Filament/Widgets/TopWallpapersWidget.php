<?php

namespace App\Filament\Widgets;

use App\Models\Wallpaper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopWallpapersWidget extends BaseWidget
{
    protected static ?string $heading = 'أكثر الخلفيات تحميلًا';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Wallpaper::published()
                    ->orderByDesc('downloads_count')
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
                    ->limit(30),

                Tables\Columns\TextColumn::make('downloads_count')
                    ->label('تحميلات')
                    ->sortable()
                    ->numeric(),

                Tables\Columns\TextColumn::make('likes_count')
                    ->label('إعجابات')
                    ->numeric(),
            ]);
    }
}
