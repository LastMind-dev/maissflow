<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowExecutionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'flow_execution_id', 'node_id', 'event_type', 'input', 'output', 'elapsed_ms', 'created_at',
    ];

    protected function casts(): array
    {
        return ['input' => 'array', 'output' => 'array', 'created_at' => 'datetime'];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(FlowExecution::class, 'flow_execution_id');
    }
}
