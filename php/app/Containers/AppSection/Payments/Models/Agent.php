<?php

namespace App\Containers\AppSection\Payments\Models;

use App\Containers\AppSection\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Agent extends Model
{
    use HasFactory;

    protected $table = 'agents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_split' => 'decimal:2',
        ];
    }

    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_agent_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'agent_account');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_user');
    }
}
