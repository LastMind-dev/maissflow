<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAutomationManager($request);

        $queuedByName = Schema::hasTable('jobs')
            ? DB::table('jobs')->select('queue', DB::raw('COUNT(*) as total'))->groupBy('queue')->pluck('total', 'queue')
            : collect();

        return response()->json([
            'data' => [
                'queue' => [
                    'connection' => config('queue.default'),
                    'webhook_queue' => config('queue.webhook_queue'),
                    'pending' => (int) $queuedByName->sum(),
                    'by_name' => $queuedByName,
                    'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                ],
                'webhooks' => [
                    'received' => WebhookEvent::query()->where('status', 'received')->count(),
                    'failed' => WebhookEvent::query()->where('status', 'failed')->count(),
                    'last_received_at' => WebhookEvent::query()->max('received_at'),
                    'last_processed_at' => WebhookEvent::query()->max('processed_at'),
                ],
                'audit_logs' => AuditLog::query()
                    ->where('workspace_id', $request->user()->workspace_id)
                    ->with('user:id,name')
                    ->latest('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (AuditLog $log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'user' => $log->user?->name ?? 'Sistema',
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at,
                    ]),
            ],
        ]);
    }
}
