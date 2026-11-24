<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class Purchase extends BaseModel
{
    protected $baseTable = 'purchases';

    protected $fillable = [
        'invoice_number',
        'purchase_order_id',
        'location_id',
        'supplier_id',
        'company_id',
        'branch_id',
        'status',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'invoice_date',
        'due_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('supplier'), 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }
}
