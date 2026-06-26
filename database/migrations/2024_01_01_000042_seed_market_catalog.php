<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MarketCategory;
use App\Models\MarketField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Data-only seed: parts sections (+ starter fields) and the EV brand/model catalog
 * used by the cars marketplace. Wrapped in try/catch so a data hiccup can never
 * abort the deploy's migrate step (schema already exists from 000041).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $this->seedPartsSections();
        } catch (\Throwable $e) {
            Log::error('seed parts sections failed: ' . $e->getMessage());
        }

        try {
            $this->seedCarCatalog();
        } catch (\Throwable $e) {
            Log::error('seed car catalog failed: ' . $e->getMessage());
        }
    }

    private function seedPartsSections(): void
    {
        // section => [icon, [ [key,label_ar,type,unit,options(ar list)] ... ]]
        $sections = [
            'adapters'    => ['🔌 ادبترات', '🔌', [
                ['connector', 'نوع الموصل', 'text', null, null],
                ['power', 'القدرة', 'number', 'واط', null],
            ]],
            'filters'     => ['🧴 فلاتر', '🧴', [
                ['filter_type', 'نوع الفلتر', 'select', null, ['زيت', 'هواء', 'مكيف', 'بنزين']],
                ['part_no', 'رقم القطعة', 'text', null, null],
            ]],
            'tires'       => ['🛞 دواليب وإطارات', '🛞', [
                ['size', 'المقاس', 'text', null, null],
                ['season', 'النوع', 'select', null, ['صيفي', 'شتوي', 'لكل الفصول']],
            ]],
            'lights'      => ['💡 كشافات وإضاءة', '💡', [
                ['light_type', 'نوع الإضاءة', 'select', null, ['LED', 'زينون', 'هالوجين']],
                ['position', 'الموضع', 'select', null, ['أمامي', 'خلفي', 'داخلي']],
            ]],
            'batteries'   => ['🔋 بطاريات', '🔋', [
                ['voltage', 'الجهد', 'number', 'فولت', null],
                ['capacity', 'السعة', 'number', 'أمبير', null],
            ]],
            'chargers'    => ['⚡ شواحن وكوابل', '⚡', [
                ['plug', 'نوع القابس', 'select', null, ['Type 2', 'CCS', 'GB/T', 'منزلي']],
                ['kw', 'القدرة', 'number', 'kW', null],
            ]],
            'interior'    => ['🪑 اكسسوارات داخلية', '🪑', [
                ['material', 'الخامة', 'text', null, null],
            ]],
            'exterior'    => ['✨ اكسسوارات خارجية', '✨', [
                ['color', 'اللون', 'text', null, null],
            ]],
            'multimedia'  => ['📺 شاشات وملتيميديا', '📺', [
                ['screen_size', 'مقاس الشاشة', 'text', 'إنش', null],
            ]],
            'services'    => ['🛠️ خدمات', '🛠️', [
                ['service_type', 'نوع الخدمة', 'select', null, ['تركيب شواحن', 'برمجة', 'صيانة', 'فحص']],
                ['includes_install', 'يشمل التركيب', 'boolean', null, null],
            ]],
        ];

        $order = 0;
        foreach ($sections as $slug => [$nameAr, $icon, $fields]) {
            $order += 10;
            $cat = MarketCategory::firstOrCreate(
                ['slug' => $slug],
                ['listing_type' => 'part', 'name_ar' => $nameAr, 'icon' => $icon, 'sort_order' => $order, 'is_active' => true]
            );

            if ($cat->fields()->exists()) {
                continue; // already seeded
            }

            $fOrder = 0;
            foreach ($fields as [$key, $labelAr, $type, $unit, $opts]) {
                $fOrder += 10;
                MarketField::create([
                    'scope'              => 'parts',
                    'market_category_id' => $cat->id,
                    'key'                => $key,
                    'label_ar'           => $labelAr,
                    'type'               => $type,
                    'unit'               => $unit,
                    'options'            => $opts ? array_map(fn ($o) => ['value' => Str::slug($o, '_') ?: $o, 'label_ar' => $o], $opts) : null,
                    'is_enabled'         => true,
                    'is_filterable'      => in_array($type, ['select'], true),
                    'sort_order'         => $fOrder,
                ]);
            }
        }
    }

    private function seedCarCatalog(): void
    {
        // slug => [name_ar, name_en, country, [ [model_ar, model_en], ... ]]
        $brands = [
            'byd'        => ['بي واي دي', 'BYD', 'الصين', [['آتو 3', 'Atto 3'], ['سيل', 'Seal'], ['دولفين', 'Dolphin'], ['هان', 'Han'], ['تانغ', 'Tang'], ['سونغ بلس', 'Song Plus'], ['سيجل', 'Seagull'], ['سيلايون 7', 'Sealion 7']]],
            'zeekr'      => ['زيكر', 'Zeekr', 'الصين', [['001', '001'], ['007', '007'], ['X', 'X'], ['009', '009']]],
            'geely'      => ['جيلي', 'Geely', 'الصين', [['جالكسي E5', 'Galaxy E5'], ['جالكسي E8', 'Galaxy E8'], ['جالكسي L7', 'Galaxy L7'], ['جيومتري C', 'Geometry C']]],
            'jetour'     => ['جيتور', 'Jetour', 'الصين', [['داشينغ', 'Dashing'], ['T2', 'T2'], ['X70 بلس', 'X70 Plus'], ['ترافيلر', 'Traveller']]],
            'avatr'      => ['افاتار', 'Avatr', 'الصين', [['11', '11'], ['12', '12'], ['07', '07']]],
            'xpeng'      => ['إكس بينغ', 'Xpeng', 'الصين', [['G6', 'G6'], ['G9', 'G9'], ['P7', 'P7'], ['X9', 'X9']]],
            'nio'        => ['نيو', 'NIO', 'الصين', [['ET5', 'ET5'], ['ET7', 'ET7'], ['ES6', 'ES6'], ['ES8', 'ES8'], ['EL7', 'EL7']]],
            'li-auto'    => ['لي شيانغ', 'Li Auto', 'الصين', [['L6', 'L6'], ['L7', 'L7'], ['L8', 'L8'], ['L9', 'L9'], ['ميجا', 'Mega']]],
            'aion'       => ['أيون', 'Aion', 'الصين', [['Y بلس', 'Y Plus'], ['S', 'S'], ['V', 'V'], ['هايبر GT', 'Hyper GT']]],
            'hongqi'     => ['هونشي', 'Hongqi', 'الصين', [['E-HS9', 'E-HS9'], ['EH7', 'EH7'], ['H5', 'H5'], ['HS5', 'HS5']]],
            'deepal'     => ['ديبال', 'Deepal', 'الصين', [['S07', 'S07'], ['L07', 'L07'], ['S05', 'S05']]],
            'changan'    => ['تشانجان', 'Changan', 'الصين', [['إيدو', 'Eado'], ['UNI-V', 'UNI-V'], ['UNI-K', 'UNI-K'], ['CS75 بلس', 'CS75 Plus']]],
            'chery'      => ['شيري', 'Chery', 'الصين', [['تيجو 8 برو', 'Tiggo 8 Pro'], ['تيجو 7 برو', 'Tiggo 7 Pro'], ['أريزو 8', 'Arrizo 8']]],
            'omoda'      => ['أومودا', 'Omoda', 'الصين', [['C5', 'C5'], ['E5', 'E5'], ['C7', 'C7']]],
            'jaecoo'     => ['جايكو', 'Jaecoo', 'الصين', [['J7', 'J7'], ['J8', 'J8']]],
            'leapmotor'  => ['ليب موتور', 'Leapmotor', 'الصين', [['C10', 'C10'], ['C11', 'C11'], ['T03', 'T03']]],
            'voyah'      => ['فويا', 'Voyah', 'الصين', [['فري', 'Free'], ['دريم', 'Dream'], ['باشن', 'Passion'], ['كوراج', 'Courage']]],
            'denza'      => ['دينزا', 'Denza', 'الصين', [['D9', 'D9'], ['N7', 'N7'], ['N8', 'N8']]],
            'mg'         => ['إم جي', 'MG', 'الصين', [['MG4 EV', 'MG4 EV'], ['MG5', 'MG5'], ['ZS EV', 'ZS EV'], ['مارفل R', 'Marvel R'], ['سايبرستر', 'Cyberster']]],
            'aito'       => ['أيتو', 'AITO', 'الصين', [['M5', 'M5'], ['M7', 'M7'], ['M9', 'M9']]],
            'neta'       => ['نيتا', 'Neta', 'الصين', [['V', 'V'], ['U', 'U'], ['S', 'S'], ['GT', 'GT']]],
            'tank'       => ['تانك', 'Tank', 'الصين', [['300', '300'], ['500', '500'], ['700', '700']]],
            'gac'        => ['جي إيه سي', 'GAC', 'الصين', [['أيون Y', 'Aion Y'], ['إمكو', 'Emkoo'], ['GS3', 'GS3']]],
            'tesla'      => ['تسلا', 'Tesla', 'أمريكا', [['موديل 3', 'Model 3'], ['موديل Y', 'Model Y'], ['موديل S', 'Model S'], ['موديل X', 'Model X']]],
            'hyundai'    => ['هيونداي', 'Hyundai', 'كوريا', [['أيونيك 5', 'Ioniq 5'], ['أيونيك 6', 'Ioniq 6'], ['كونا', 'Kona Electric'], ['توسان', 'Tucson']]],
            'kia'        => ['كيا', 'Kia', 'كوريا', [['EV6', 'EV6'], ['EV9', 'EV9'], ['نيرو', 'Niro EV'], ['سبورتاج', 'Sportage']]],
        ];

        $bOrder = 0;
        foreach ($brands as $slug => [$nameAr, $nameEn, $country, $models]) {
            $bOrder += 10;
            $brand = Brand::withTrashed()->where('slug', $slug)->first();
            if (! $brand) {
                $brand = Brand::create([
                    'slug' => $slug, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'country' => $country,
                    'is_active' => false, 'is_featured' => false, 'sort_order' => $bOrder,
                ]);
            }

            $mOrder = 0;
            foreach ($models as [$mAr, $mEn]) {
                $mOrder += 10;
                $exists = CarModel::where('brand_id', $brand->id)->where('name_en', $mEn)->exists();
                if ($exists) {
                    continue;
                }
                CarModel::create([
                    'brand_id' => $brand->id, 'name_ar' => $mAr, 'name_en' => $mEn,
                    'fuel_type' => 'electric', 'is_active' => true, 'sort_order' => $mOrder,
                ]);
            }
        }
    }

    public function down(): void
    {
        // data-only; no structural rollback
    }
};
