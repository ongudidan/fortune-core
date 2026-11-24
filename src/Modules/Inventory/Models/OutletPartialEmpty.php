<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class OutletPartialEmpty extends BaseModel
{
    protected $baseTable = 'outlet_partial_empties';

    protected $fillable = [
        'location_id',
        'outlet_id',
        'empty_product_id',
        'accumulated_yield',
    ];

    protected $casts = [
        'accumulated_yield' => 'decimal:4',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function emptyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'empty_product_id');
    }
}
