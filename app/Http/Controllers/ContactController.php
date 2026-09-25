<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $contacts = Contact::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->withCount('conversations')
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($filter) => $filter
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->latest('last_seen_at')
            ->paginate(50);

        return response()->json($contacts);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->query('search'));
        $query = Contact::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->withCount('conversations')
            ->when($search !== '', fn ($builder) => $builder
                ->where(fn ($filter) => $filter
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Nome', 'Telefone', 'E-mail', 'Opt-in', 'Conversas', 'Última interação'], ';');

            $query->chunkById(500, function ($contacts) use ($output): void {
                foreach ($contacts as $contact) {
                    fputcsv($output, [
                        $contact->name,
                        $contact->phone_number,
                        $contact->email,
                        $contact->opt_in ? 'Sim' : 'Não',
                        $contact->conversations_count,
                        $contact->last_seen_at?->toIso8601String(),
                    ], ';');
                }
            });

            fclose($output);
        }, 'contatos-flowdesk-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
