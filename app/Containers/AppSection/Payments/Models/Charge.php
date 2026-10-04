<?php

namespace App\Containers\AppSection\Payments\Models;

use App\Containers\AppSection\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Charge extends Model
{
    use HasFactory;

    protected $table = 'charges';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_captured' => 'integer',
            'amount_refunded' => 'integer',
            'amount_voided' => 'integer',
            'refunded' => 'boolean',
            'disputed' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
