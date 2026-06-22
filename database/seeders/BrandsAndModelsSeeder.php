<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Seeder;

class BrandsAndModelsSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'name_ar'        => 'بي واي دي',
                'name_en'        => 'BYD',
                'slug'           => 'byd',
                'country'        => 'الصين',
                'description_ar' => 'شركة بي واي دي للسيارات الكهربائية والهجينة، من أكبر شركات السيارات الكهربائية في العالم.',
                'description_en' => 'BYD is a world-leading electric vehicle manufacturer from China.',
                'website_url'    => 'https://www.byd.com',
                'is_active'      => true,
                'is_featured'    => true,
                'sort_order'     => 1,
                'models'         => [
                    ['name_ar' => 'BYD Atto 3', 'name_en' => 'Atto 3', 'slug' => 'byd-atto-3', 'year_from' => 2022, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                    ['name_ar' => 'BYD Han', 'name_en' => 'Han', 'slug' => 'byd-han', 'year_from' => 2020, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                    ['name_ar' => 'BYD Tang', 'name_en' => 'Tang', 'slug' => 'byd-tang', 'year_from' => 2018, 'fuel_type' => 'phev', 'car_type' => 'suv'],
                    ['name_ar' => 'BYD Sea Lion 6', 'name_en' => 'Sea Lion 6', 'slug' => 'byd-sea-lion-6', 'year_from' => 2023, 'fuel_type' => 'phev', 'car_type' => 'suv'],
                    ['name_ar' => 'BYD Seal', 'name_en' => 'Seal', 'slug' => 'byd-seal', 'year_from' => 2022, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                ],
            ],
            [
                'name_ar'        => 'جيتور',
                'name_en'        => 'Jetour',
                'slug'           => 'jetour',
                'country'        => 'الصين',
                'description_ar' => 'جيتور علامة تجارية صينية من مجموعة شيري، متخصصة في سيارات الدفع الرباعي والسيارات الهجينة.',
                'description_en' => 'Jetour is a Chinese SUV brand under Chery, known for hybrid and crossover vehicles.',
                'website_url'    => 'https://www.jetour.com',
                'is_active'      => true,
                'is_featured'    => true,
                'sort_order'     => 2,
                'models'         => [
                    ['name_ar' => 'Jetour Shanhai L7', 'name_en' => 'Shanhai L7', 'slug' => 'jetour-shanhai-l7', 'year_from' => 2024, 'fuel_type' => 'phev', 'car_type' => 'suv'],
                    ['name_ar' => 'Jetour T2', 'name_en' => 'T2', 'slug' => 'jetour-t2', 'year_from' => 2024, 'fuel_type' => 'hybrid', 'car_type' => 'suv'],
                    ['name_ar' => 'Jetour T1', 'name_en' => 'T1', 'slug' => 'jetour-t1', 'year_from' => 2023, 'fuel_type' => 'hybrid', 'car_type' => 'suv'],
                    ['name_ar' => 'Jetour Dashing', 'name_en' => 'Dashing', 'slug' => 'jetour-dashing', 'year_from' => 2022, 'fuel_type' => 'petrol', 'car_type' => 'suv'],
                    ['name_ar' => 'Jetour X70 Plus', 'name_en' => 'X70 Plus', 'slug' => 'jetour-x70-plus', 'year_from' => 2022, 'fuel_type' => 'petrol', 'car_type' => 'suv'],
                ],
            ],
            [
                'name_ar'        => 'زيكر',
                'name_en'        => 'Zeekr',
                'slug'           => 'zeekr',
                'country'        => 'الصين',
                'description_ar' => 'زيكر علامة فاخرة للسيارات الكهربائية من مجموعة جيلي الصينية.',
                'description_en' => 'Zeekr is a premium electric vehicle brand under Geely Group.',
                'website_url'    => 'https://www.zeekr.eu',
                'is_active'      => true,
                'is_featured'    => true,
                'sort_order'     => 3,
                'models'         => [
                    ['name_ar' => 'Zeekr 001', 'name_en' => 'Zeekr 001', 'slug' => 'zeekr-001', 'year_from' => 2021, 'fuel_type' => 'electric', 'car_type' => 'wagon'],
                    ['name_ar' => 'Zeekr 007', 'name_en' => 'Zeekr 007', 'slug' => 'zeekr-007', 'year_from' => 2023, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                    ['name_ar' => 'Zeekr X', 'name_en' => 'Zeekr X', 'slug' => 'zeekr-x', 'year_from' => 2023, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                    ['name_ar' => 'Zeekr 009', 'name_en' => 'Zeekr 009', 'slug' => 'zeekr-009', 'year_from' => 2022, 'fuel_type' => 'electric', 'car_type' => 'van'],
                ],
            ],
            [
                'name_ar'        => 'دينزا',
                'name_en'        => 'Denza',
                'slug'           => 'denza',
                'country'        => 'الصين',
                'description_ar' => 'دينزا علامة فاخرة مشتركة بين BYD وMercedes-Benz.',
                'description_en' => 'Denza is a luxury joint venture brand between BYD and Mercedes-Benz.',
                'is_active'      => true,
                'is_featured'    => false,
                'sort_order'     => 4,
                'models'         => [
                    ['name_ar' => 'Denza D9', 'name_en' => 'D9', 'slug' => 'denza-d9', 'year_from' => 2022, 'fuel_type' => 'phev', 'car_type' => 'van'],
                    ['name_ar' => 'Denza N7', 'name_en' => 'N7', 'slug' => 'denza-n7', 'year_from' => 2023, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                    ['name_ar' => 'Denza Z9 GT', 'name_en' => 'Z9 GT', 'slug' => 'denza-z9-gt', 'year_from' => 2024, 'fuel_type' => 'phev', 'car_type' => 'wagon'],
                ],
            ],
            [
                'name_ar'        => 'شاومي للسيارات',
                'name_en'        => 'Xiaomi Auto',
                'slug'           => 'xiaomi-auto',
                'country'        => 'الصين',
                'description_ar' => 'شاومي تدخل عالم السيارات الكهربائية بسيارتها SU7.',
                'description_en' => 'Xiaomi enters the electric vehicle market with the SU7.',
                'website_url'    => 'https://www.mi.com/global/auto',
                'is_active'      => true,
                'is_featured'    => true,
                'sort_order'     => 5,
                'models'         => [
                    ['name_ar' => 'Xiaomi SU7', 'name_en' => 'SU7', 'slug' => 'xiaomi-su7', 'year_from' => 2024, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                    ['name_ar' => 'Xiaomi SU7 Ultra', 'name_en' => 'SU7 Ultra', 'slug' => 'xiaomi-su7-ultra', 'year_from' => 2024, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                    ['name_ar' => 'Xiaomi YU7', 'name_en' => 'YU7', 'slug' => 'xiaomi-yu7', 'year_from' => 2025, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                ],
            ],
            [
                'name_ar'        => 'أفاتر',
                'name_en'        => 'Avatr',
                'slug'           => 'avatr',
                'country'        => 'الصين',
                'description_ar' => 'أفاتر علامة فاخرة من مجموعة شانجان وهواوي وCATL.',
                'description_en' => 'Avatr is a luxury EV brand backed by Changan, Huawei, and CATL.',
                'is_active'      => true,
                'is_featured'    => false,
                'sort_order'     => 6,
                'models'         => [
                    ['name_ar' => 'Avatr 11', 'name_en' => 'Avatr 11', 'slug' => 'avatr-11', 'year_from' => 2022, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                    ['name_ar' => 'Avatr 12', 'name_en' => 'Avatr 12', 'slug' => 'avatr-12', 'year_from' => 2023, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                ],
            ],
            [
                'name_ar'        => 'إكسبنج',
                'name_en'        => 'Xpeng',
                'slug'           => 'xpeng',
                'country'        => 'الصين',
                'description_ar' => 'إكسبنج أو شياوبنج علامة صينية رائدة في السيارات الكهربائية الذكية.',
                'description_en' => 'Xpeng is a leading Chinese smart electric vehicle brand.',
                'website_url'    => 'https://www.heyxpeng.com',
                'is_active'      => true,
                'is_featured'    => false,
                'sort_order'     => 7,
                'models'         => [
                    ['name_ar' => 'Xpeng G6', 'name_en' => 'G6', 'slug' => 'xpeng-g6', 'year_from' => 2023, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                    ['name_ar' => 'Xpeng P7', 'name_en' => 'P7', 'slug' => 'xpeng-p7', 'year_from' => 2020, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                    ['name_ar' => 'Xpeng X9', 'name_en' => 'X9', 'slug' => 'xpeng-x9', 'year_from' => 2024, 'fuel_type' => 'electric', 'car_type' => 'van'],
                ],
            ],
            [
                'name_ar'        => 'ديبال',
                'name_en'        => 'Deepal',
                'slug'           => 'deepal',
                'country'        => 'الصين',
                'description_ar' => 'ديبال (شياني) علامة من مجموعة شانجان للسيارات الكهربائية.',
                'description_en' => 'Deepal (Qiyuan) is Changan\'s electric vehicle brand.',
                'is_active'      => true,
                'is_featured'    => false,
                'sort_order'     => 8,
                'models'         => [
                    ['name_ar' => 'Deepal SL03', 'name_en' => 'SL03', 'slug' => 'deepal-sl03', 'year_from' => 2022, 'fuel_type' => 'electric', 'car_type' => 'sedan'],
                    ['name_ar' => 'Deepal S07', 'name_en' => 'S07', 'slug' => 'deepal-s07', 'year_from' => 2023, 'fuel_type' => 'phev', 'car_type' => 'suv'],
                    ['name_ar' => 'Deepal L07', 'name_en' => 'L07', 'slug' => 'deepal-l07', 'year_from' => 2023, 'fuel_type' => 'phev', 'car_type' => 'suv'],
                ],
            ],
            [
                'name_ar'        => 'هونشي',
                'name_en'        => 'Hongqi',
                'slug'           => 'hongqi',
                'country'        => 'الصين',
                'description_ar' => 'هونشي (العلم الأحمر) أقدم وأعرق ماركة سيارات صينية، الآن تطرح سيارات كهربائية فاخرة.',
                'description_en' => 'Hongqi (Red Flag) is China\'s most prestigious car brand, now producing luxury EVs.',
                'is_active'      => true,
                'is_featured'    => false,
                'sort_order'     => 9,
                'models'         => [
                    ['name_ar' => 'Hongqi E-HS9', 'name_en' => 'E-HS9', 'slug' => 'hongqi-e-hs9', 'year_from' => 2020, 'fuel_type' => 'electric', 'car_type' => 'suv'],
                    ['name_ar' => 'Hongqi H9', 'name_en' => 'H9', 'slug' => 'hongqi-h9', 'year_from' => 2020, 'fuel_type' => 'hybrid', 'car_type' => 'sedan'],
                ],
            ],
        ];

        foreach ($brands as $brandData) {
            $models = $brandData['models'];
            unset($brandData['models']);

            $brand = Brand::updateOrCreate(['slug' => $brandData['slug']], $brandData);

            foreach ($models as $i => $modelData) {
                CarModel::updateOrCreate(
                    ['slug' => $modelData['slug']],
                    array_merge($modelData, [
                        'brand_id'   => $brand->id,
                        'is_active'  => true,
                        'is_featured' => $i === 0,
                        'sort_order' => $i,
                        'name_en'    => $modelData['name_en'],
                    ])
                );
            }

            $brand->refreshCounts();
        }

        $this->command->info('✅ تم إضافة ' . count($brands) . ' ماركة بنجاح');
    }
}
