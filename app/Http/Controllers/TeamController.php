<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAutomationManager($request);

        return response()->json([
            'data' => User::query()
                ->where('workspace_id', $request->user()->workspace_id)
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']),
        ]);
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $this->requireAutomationManager($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'agent'])],
        ]);

        $member = User::create([
            'workspace_id' => $request->user()->workspace_id,
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        $audit->record($request->user(), 'team.member_created', $member, null, [
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->role,
            'is_active' => true,
        ], $request);

        return response()->json(['data' => $this->data($member)], 201);
    }

    public function update(Request $request, string $teamMember, AuditService $audit): JsonResponse
    {
        $this->requireAutomationManager($request);
        $member = User::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->findOrFail($teamMember);

        abort_if($member->role === 'owner', 422, 'O usuário master não pode ser alterado por esta tela.');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'agent'])],
            'is_active' => ['required', 'boolean'],
        ]);

        abort_if(
            $member->is($request->user()) && ! $validated['is_active'],
            422,
            'Você não pode desativar o próprio acesso.',
        );

        $before = $member->only(['role', 'is_active']);
        $member->update($validated);
        $audit->record($request->user(), 'team.member_updated', $member, $before, $validated, $request);

        return response()->json(['data' => $this->data($member)]);
    }

    private function data(User $member): array
    {
        return $member->only(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
    }
}
