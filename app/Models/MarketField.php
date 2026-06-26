<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketField extends Model
{
    protected $fillable = [
        'scope', 'market_category_id', 'key', 'column_name', 'label_ar', 'label_en',
        'type', 'options', 'unit', 'placeholder', 'help_text',
        'is_system', 'is_enabled', 'is_required', 'is_filterable', 'sort_order',
    ];

    protected $casts = [
        'options'       => 'array',
        'is_system'     => 'boolean',
        'is_enabled'    => 'boolean',
        'is_required'   => 'boolean',
        'is_filterable' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(MarketCategory::class, 'market_category_id');
    }

    public function scopeEnabled($q)
    {
        return $q->where('is_enabled', true);
    }

    /** Whether the value lives in a real listings column (vs the specs json). */
    public function isColumn(): bool
    {
        return ! empty($this->column_name);
    }

    /** Filament form field name that binds this field to the listing. */
    public function formPath(): string
    {
        return $this->isColumn() ? $this->column_name : "specs.{$this->key}";
    }

    /** Options as a simple [value => label_ar] map for selects. */
    public function optionsMap(): array
    {
        $out = [];
        foreach ((array) $this->options as $o) {
            if (isset($o['value'])) {
                $out[$o['value']] = $o['label_ar'] ?? $o['value'];
            }
        }
        return $out;
    }

    /**
     * Fields that apply to a given scope and (for parts) section.
     * Cars: all scope=cars rows. Parts: rows for the section + section-agnostic rows.
     */
    public static function forContext(string $scope, ?int $categoryId = null)
    {
        return static::query()
            ->where('scope', $scope)
            ->when($scope === 'parts', fn ($q) => $q->where(function ($w) use ($categoryId) {
                $w->whereNull('market_category_id');
                if ($categoryId) {
                    $w->orWhere('market_category_id', $categoryId);
                }
            }))
            ->orderBy('sort_order');
    }
}
