<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\BuildsMarketForm;
use App\Filament\Concerns\HiddenFromCreatives;
use App\Filament\Resources\CarMarketListingResource\Pages;
use App\Models\MarketListing;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class CarMarketListingResource extends Resource
{
    use HiddenFromCreatives;
    use BuildsMarketForm;

    protected static ?string $model = MarketListing::class;
    protected static ?string $slug = 'car-market';
    protected static ?string $navigationIcon  = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'سوق السيارات';
    protected static ?int    $navigationSort   = 1;

    public static function scope(): string { return 'cars'; }

    public static function getNavigationLabel(): string  { return 'الإعلانات'; }
    public static function getModelLabel(): string       { return 'إعلان سيارة'; }
    public static function getPluralModelLabel(): string { return 'إعلانات السيارات'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('listing_type', ['car_sale', 'car_request']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCarMarketListings::route('/'),
            'create' => Pages\CreateCarMarketListing::route('/create'),
            'edit'   => Pages\EditCarMarketListing::route('/{record}/edit'),
        ];
    }
}
