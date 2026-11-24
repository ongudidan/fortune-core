<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ongudidan\FortuneCore\Models\BaseModel;

class ProductUnitConversion extends BaseModel
{
    protected $baseTable = 'product_unit_conversions';

    protected $fillable = [
        'product_id',
        'from_unit_id',
        'to_unit_id',
        'unit_id',
        'operator',
        'factor',
        'wholesale_price',
        'barcode',
    ];

    protected $casts = [
        'factor' => 'decimal:4',
        'wholesale_price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
