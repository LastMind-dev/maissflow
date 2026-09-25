<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasPublicId;

    protected $fillable = [
        'workspace_id', 'public_id', 'wa_id', 'phone_number', 'name', 'email',
        'status', 'opt_in', 'opted_in_at', 'opted_out_at', 'attributes', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'opt_in' => 'boolean',
            'attributes' => 'array',
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
