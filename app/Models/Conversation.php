<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasPublicId;

    protected $fillable = [
        'workspace_id', 'whatsapp_channel_id', 'contact_id', 'public_id', 'status',
        'assigned_to', 'automation_version_id', 'current_node_id', 'context',
        'unread_count', 'customer_service_window_expires_at', 'last_message_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'customer_service_window_expires_at' => 'datetime',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChannel::class, 'whatsapp_channel_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function automationVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationVersion::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }
}
