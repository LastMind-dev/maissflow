<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowExecution extends Model
{
    use HasPublicId;

    protected $fillable = [
        'workspace_id', 'conversation_id', 'automation_version_id', 'public_id',
        'status', 'current_node_id', 'context', 'started_at', 'completed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AutomationVersion::class, 'automation_version_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FlowExecutionEvent::class);
    }
}
