<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomationPublisher
{
    public function __construct(private readonly FlowGraphValidator $validator) {}

    public function publish(Automation $automation, User $user): AutomationVersion
    {
        return DB::transaction(function () use ($automation, $user): AutomationVersion {
            $locked = Automation::query()
                ->whereKey($automation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $locked->versions()
                ->where('status', 'draft')
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();

            $validation = $this->validator->validate($draft->graph);
            $draft->update(['validation_result' => $validation]);

            if (! $validation['valid']) {
                throw ValidationException::withMessages(['graph' => $validation['errors']]);
            }

            $locked->versions()->where('status', 'published')->update(['status' => 'archived']);

            $draft->update([
                'status' => 'published',
                'published_by' => $user->id,
                'published_at' => now(),
            ]);

            $locked->update([
                'status' => 'active',
                'published_version_id' => $draft->id,
            ]);

            $nextVersion = ((int) $locked->versions()->max('version')) + 1;
            $locked->versions()->create([
                'version' => $nextVersion,
                'status' => 'draft',
                'graph' => $draft->graph,
                'created_by' => $user->id,
            ]);

            return $draft->fresh();
        }, 3);
    }
}
