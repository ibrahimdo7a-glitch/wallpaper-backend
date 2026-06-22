<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppCompatibility extends Model
{
    protected $table = 'app_compatibilities';

    protected $fillable = [
        'app_id', 'brand_id', 'car_model_id',
        'android_version', 'compatibility_status', 'notes_ar', 'notes_en',
    ];

    public function app(): BelongsTo      { return $this->belongsTo(AndroidApp::class, 'app_id'); }
    public function brand(): BelongsTo    { return $this->belongsTo(Brand::class); }
    public function carModel(): BelongsTo { return $this->belongsTo(CarModel::class, 'car_model_id'); }

    public function getStatusBadgeAttribute(): array
    {
        return match($this->compatibility_status) {
            'compatible'   => ['label_ar' => 'متوافق',        'label_en' => 'Compatible',   'color' => 'green'],
            'partial'      => ['label_ar' => 'متوافق جزئياً', 'label_en' => 'Partial',       'color' => 'yellow'],
            'incompatible' => ['label_ar' => 'غير متوافق',    'label_en' => 'Incompatible',  'color' => 'red'],
            default        => ['label_ar' => 'غير معروف',      'label_en' => 'Unknown',       'color' => 'gray'],
        };
    }
}
