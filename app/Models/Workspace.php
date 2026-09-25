<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = ['public_id', 'name', 'slug', 'timezone'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }
}
