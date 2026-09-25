<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use HasPublicId;

    protected $fillable = [
        'workspace_id',
        'public_id',
        'name',
        'description',
        'status',
        'trigger_type',
        'trigger_config',
        'published_version_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['trigger_config' => 'array'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AutomationVersion::class);
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationVersion::class, 'published_version_id');
    }

    public function draftVersion(): ?AutomationVersion
    {
        return $this->versions()->where('status', 'draft')->latest('version')->first();
    }
}
