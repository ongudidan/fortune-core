<?php

namespace Ongudidan\FortuneCore\Modules\Ledger\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ongudidan\FortuneCore\Models\BaseModel;

class JournalEntry extends BaseModel
{
    protected $baseTable = 'journal_entries';

    protected $fillable = [
        'date',
        'reference',
        'description',
        'entry_type',
        'company_id',
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }

    public function getTotalAmountAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum('debit');
        }

        return (float) $this->items()->sum('debit');
    }
}
