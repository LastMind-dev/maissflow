<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppChannel extends Model
{
    use HasPublicId;

    protected $table = 'whatsapp_channels';

    protected $fillable = [
        'workspace_id',
        'public_id',
        'name',
        'phone_number_id',
        'waba_id',
        'display_phone_number',
        'access_token',
        'app_secret',
        'verify_token',
        'graph_version',
        'status',
        'last_verified_at',
    ];

    protected $hidden = ['access_token', 'app_secret', 'verify_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'verify_token' => 'encrypted',
            'last_verified_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'whatsapp_channel_id');
    }

    public function isConfigured(): bool
    {
        return filled($this->phone_number_id)
            && filled($this->waba_id)
            && filled($this->access_token)
            && filled($this->app_secret)
            && filled($this->verify_token);
    }
}
