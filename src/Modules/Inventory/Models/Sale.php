<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class Sale extends BaseModel
{
    protected $baseTable = 'sales';

    protected $fillable = [
        'invoice_number',
        'location_id',
        'customer_id',
        'user_id',
        'company_id',
        'branch_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'cash_paid',
        'mpesa_paid',
        'wallet_paid',
        'change_amount',
        'payment_status',
        'status',
        'payment_method',
        'sale_type',
        'mpesa_reference',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cash_paid' => 'decimal:2',
        'mpesa_paid' => 'decimal:2',
        'wallet_paid' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'user_id');
    }
}
