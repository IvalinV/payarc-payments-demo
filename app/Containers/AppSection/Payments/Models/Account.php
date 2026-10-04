<?php

namespace App\Containers\AppSection\Payments\Models;

use App\Containers\AppSection\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active_yn' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_account');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
