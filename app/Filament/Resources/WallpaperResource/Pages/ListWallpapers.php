<?php

namespace App\Filament\Resources\WallpaperResource\Pages;

use App\Filament\Resources\WallpaperResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWallpapers extends ListRecords
{
    protected static string $resource = WallpaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('رفع خلفية جديدة'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),
            'pending' => Tab::make('قيد الانتظار')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'pending'))
                ->badge(fn() => \App\Models\Wallpaper::where('status', 'pending')->count()),
            'published' => Tab::make('منشور')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'published')),
            'rejected' => Tab::make('مرفوض')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'rejected')),
            'hidden' => Tab::make('مخفي')
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'hidden')),
        ];
    }
}
