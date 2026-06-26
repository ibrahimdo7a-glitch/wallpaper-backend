<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\BuildsMarketForm;
use App\Filament\Concerns\HiddenFromCreatives;
use App\Filament\Resources\PartMarketListingResource\Pages;
use App\Models\MarketListing;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class PartMarketListingResource extends Resource
{
    use HiddenFromCreatives;
    use BuildsMarketForm;

    protected static ?string $model = MarketListing::class;
    protected static ?string $slug = 'parts-market';
    protected static ?string $navigationIcon  = 'heroicon-o-wrench';
    protected static ?string $navigationGroup = 'سوق القطع والاكسسوارات';
    protected static ?int    $navigationSort   = 1;

    public static function scope(): string { return 'parts'; }

    public static function getNavigationLabel(): string  { return 'الإعلانات'; }
    public static function getModelLabel(): string       { return 'إعلان قطعة'; }
    public static function getPluralModelLabel(): string { return 'إعلانات القطع والاكسسوارات'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('listing_type', ['part', 'accessory', 'service']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPartMarketListings::route('/'),
            'create' => Pages\CreatePartMarketListing::route('/create'),
            'edit'   => Pages\EditPartMarketListing::route('/{record}/edit'),
        ];
    }
}
