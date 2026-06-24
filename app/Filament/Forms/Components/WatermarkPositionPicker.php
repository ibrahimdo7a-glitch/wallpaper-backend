<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Visual 3×3 picker: the admin taps the zone of the image where the signature
 * should sit. Optionally renders the actual wallpaper behind the grid.
 */
class WatermarkPositionPicker extends Field
{
    protected string $view = 'filament.forms.components.watermark-position-picker';

    protected string|Closure|null $imageUrl = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->default('bottom-left');
        $this->dehydrateStateUsing(fn ($state) => $state ?: 'bottom-left');
    }

    public function image(string|Closure|null $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->evaluate($this->imageUrl);
    }

    /** Position key => Arabic label, in visual reading order. */
    public function getPositions(): array
    {
        return [
            'top-left'      => 'أعلى يسار',
            'top-center'    => 'أعلى وسط',
            'top-right'     => 'أعلى يمين',
            'middle-left'   => 'وسط يسار',
            'center'        => 'المنتصف',
            'middle-right'  => 'وسط يمين',
            'bottom-left'   => 'أسفل يسار',
            'bottom-center' => 'أسفل وسط',
            'bottom-right'  => 'أسفل يمين',
        ];
    }
}
