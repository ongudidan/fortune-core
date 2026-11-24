<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class Location extends BaseModel
{
    protected $baseTable = 'locations';

    protected $fillable = [
        'name',
        'code',
        'type', // warehouse, retail, van
        'branch_id',
        'company_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('company'), 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('branch'), 'branch_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(ProductInventory::class, 'location_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'location_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'location_id');
    }
}
