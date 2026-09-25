<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $before = $user->only(['name']);

        $user->update([
            'name' => trim($request->validated('name')),
        ]);

        $audit->record(
            $user,
            'profile.updated',
            $user,
            $before,
            $user->only(['name']),
            $request,
        );

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'data' => $user->only(['name', 'email', 'role']),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request, AuditService $audit): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $this->invalidateOtherDatabaseSessions($request->session()->getId(), $user->id);

        $audit->record(
            $user,
            'profile.password_updated',
            $user,
            null,
            ['other_sessions_invalidated' => true],
            $request,
        );

        return response()->json([
            'message' => 'Senha atualizada com sucesso.',
        ]);
    }

    private function invalidateOtherDatabaseSessions(string $currentSessionId, int $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
