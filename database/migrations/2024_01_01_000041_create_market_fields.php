<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_fields', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->index();                 // cars | parts
            $table->foreignId('market_category_id')->nullable()
                  ->constrained('market_categories')->cascadeOnDelete(); // parts: which section
            $table->string('key');                            // slug used in specs json
            $table->string('column_name')->nullable();        // real column on market_listings, else null = stored in specs
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->string('type')->default('text');          // text|number|select|boolean|textarea
            $table->json('options')->nullable();              // for select: [{value,label_ar,label_en}]
            $table->string('unit')->nullable();               // كم، كيلوواط...
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->boolean('is_system')->default(false);     // curated (toggle but don't delete)
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['scope', 'market_category_id', 'sort_order']);
        });

        $this->seedCarFields();
    }

    private function seedCarFields(): void
    {
        $now = now();
        $sel = fn (array $pairs) => json_encode(array_map(
            fn ($v, $l) => ['value' => $v, 'label_ar' => $l],
            array_keys($pairs), array_values($pairs)
        ), JSON_UNESCAPED_UNICODE);

        $rows = [
            // key, column, label_ar, label_en, type, options, unit, filterable, order
            ['year',             'year',    'سنة الصنع',      'Year',        'number', null, null, true,  10],
            ['mileage',          'mileage', 'الممشى',         'Mileage',     'number', null, 'كم', true,  20],
            ['condition',        'condition','الحالة',        'Condition',   'select', $sel(['new' => 'جديد', 'used' => 'مستعمل']), null, true, 30],
            ['vin',              null,      'رقم الشاصي (VIN)','VIN',         'text',   null, null, false, 40],
            ['transmission',     null,      'ناقل الحركة',    'Transmission','select', $sel(['automatic' => 'أوتوماتيك', 'manual' => 'عادي']), null, true, 50],
            ['drivetrain',       null,      'نظام الدفع',     'Drivetrain',  'select', $sel(['fwd' => 'أمامي', 'rwd' => 'خلفي', 'awd' => 'رباعي']), null, true, 60],
            ['body_type',        null,      'نوع الهيكل',     'Body type',   'select', $sel(['suv' => 'SUV', 'sedan' => 'سيدان', 'hatch' => 'هاتشباك', 'coupe' => 'كوبيه', 'pickup' => 'بيك أب', 'mpv' => 'عائلية']), null, true, 70],
            ['battery_capacity', null,      'سعة البطارية',   'Battery',     'number', null, 'kWh', false, 80],
            ['range_km',         null,      'المدى',          'Range',       'number', null, 'كم', false, 90],
            ['color',            null,      'اللون',          'Color',       'text',   null, null, false, 100],
            ['seats',            null,      'عدد المقاعد',    'Seats',       'number', null, null, false, 110],
            ['warranty',         null,      'الضمان',         'Warranty',    'text',   null, null, false, 120],
        ];

        $insert = [];
        foreach ($rows as $r) {
            $insert[] = [
                'scope'        => 'cars',
                'market_category_id' => null,
                'key'          => $r[0],
                'column_name'  => $r[1],
                'label_ar'     => $r[2],
                'label_en'     => $r[3],
                'type'         => $r[4],
                'options'      => $r[5],
                'unit'         => $r[6],
                'placeholder'  => null,
                'help_text'    => null,
                'is_system'    => true,
                'is_enabled'   => true,
                'is_required'  => false,
                'is_filterable'=> $r[7],
                'sort_order'   => $r[8],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        DB::table('market_fields')->insert($insert);
    }

    public function down(): void
    {
        Schema::dropIfExists('market_fields');
    }
};
