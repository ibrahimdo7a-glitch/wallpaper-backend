<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Corrects two issues from 000042:
 *  1) section name_ar accidentally included the emoji that also lives in `icon`.
 *  2) lowercase-slug brands duplicated existing brands whose slug was cased
 *     differently (BYD/byd, Jetour/jetour, zeeker/zeekr, avatar/avatr).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $this->fixSectionNames();
        } catch (\Throwable $e) {
            Log::error('fix section names failed: ' . $e->getMessage());
        }

        try {
            $this->dedupeBrands();
        } catch (\Throwable $e) {
            Log::error('dedupe brands failed: ' . $e->getMessage());
        }
    }

    private function fixSectionNames(): void
    {
        $clean = [
            'adapters'   => 'ادبترات',
            'filters'    => 'فلاتر',
            'tires'      => 'دواليب وإطارات',
            'lights'     => 'كشافات وإضاءة',
            'batteries'  => 'بطاريات',
            'chargers'   => 'شواحن وكوابل',
            'interior'   => 'اكسسوارات داخلية',
            'exterior'   => 'اكسسوارات خارجية',
            'multimedia' => 'شاشات وملتيميديا',
            'services'   => 'خدمات',
        ];
        foreach ($clean as $slug => $name) {
            MarketCategory::where('slug', $slug)->update(['name_ar' => $name]);
        }
    }

    private function dedupeBrands(): void
    {
        // The brands 000042 created (inactive). If an older brand shares the same
        // Arabic name, move my seeded models onto it and drop the duplicate.
        $seededSlugs = [
            'byd', 'zeekr', 'geely', 'jetour', 'avatr', 'xpeng', 'nio', 'li-auto', 'aion',
            'hongqi', 'deepal', 'changan', 'chery', 'omoda', 'jaecoo', 'leapmotor', 'voyah',
            'denza', 'mg', 'aito', 'neta', 'tank', 'gac', 'tesla', 'hyundai', 'kia',
        ];

        foreach ($seededSlugs as $slug) {
            $mine = Brand::where('slug', $slug)->where('is_active', false)->first();
            if (! $mine) {
                continue;
            }

            $existing = Brand::where('name_ar', $mine->name_ar)
                ->where('id', '!=', $mine->id)->orderBy('id')->first();

            if (! $existing) {
                continue; // genuinely new brand — keep it
            }

            // Move models to the existing brand, hidden from the public brand page
            // but still selectable in the car-market dropdown.
            CarModel::where('brand_id', $mine->id)->update([
                'brand_id'  => $existing->id,
                'is_active' => false,
            ]);

            $mine->forceDelete();
            $existing->refreshCounts();
        }
    }

    public function down(): void
    {
        // corrective, no rollback
    }
};
