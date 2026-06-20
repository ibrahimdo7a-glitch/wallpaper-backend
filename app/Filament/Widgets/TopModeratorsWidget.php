<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopModeratorsWidget extends BaseWidget
{
    protected static ?string $heading = 'أفضل المشرفين';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::withCount(['wallpapers as published_count' => fn($q) => $q->where('status', 'published')])
                    ->withSum(['wallpapers as total_downloads' => fn($q) => $q->where('status', 'published')], 'downloads_count')
                    ->whereHas('roles')
                    ->orderByDesc('total_downloads')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('الصورة')
                    ->disk('r2')
                    ->circular()
                    ->width(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم'),

                Tables\Columns\TextColumn::make('published_count')
                    ->label('الخلفيات'),

                Tables\Columns\TextColumn::make('total_downloads')
                    ->label('التحميلات')
                    ->numeric(),
            ]);
    }
}
