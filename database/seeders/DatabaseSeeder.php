<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Models\Workspace;
use App\Services\DefaultFlowFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'suporte-online'],
            ['name' => 'Suporte Online', 'timezone' => 'America/Sao_Paulo'],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@flowdesk.local')],
            [
                'workspace_id' => $workspace->id,
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'FlowDesk@2026')),
                'role' => 'owner',
                'is_active' => true,
            ],
        );

        $channel = WhatsAppChannel::query()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'name' => 'WhatsApp principal',
                'graph_version' => 'v25.0',
                'status' => 'disconnected',
            ],
        );

        $automation = Automation::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Atendimento principal'],
            [
                'description' => 'Menu corporativo inspirado no fluxo operacional fornecido.',
                'status' => 'active',
                'trigger_type' => 'incoming_message',
                'created_by' => $admin->id,
            ],
        );

        if (! $automation->versions()->exists()) {
            $graph = app(DefaultFlowFactory::class)->make();
            $published = $automation->versions()->create([
                'version' => 1,
                'revision' => 1,
                'status' => 'published',
                'graph' => $graph,
                'published_at' => now(),
                'created_by' => $admin->id,
            ]);
            $automation->versions()->create([
                'version' => 2,
                'revision' => 1,
                'status' => 'draft',
                'graph' => $graph,
                'created_by' => $admin->id,
            ]);
            $automation->update(['published_version_id' => $published->id]);
        }

        if (! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $samples = [
            ['wa_id' => '5517999990001', 'name' => 'Mariana Souza', 'minutes' => 4, 'unread' => 2, 'status' => 'human'],
            ['wa_id' => '5517999990002', 'name' => 'Carlos Almeida', 'minutes' => 18, 'unread' => 1, 'status' => 'bot'],
            ['wa_id' => '5517999990003', 'name' => 'Escritório Horizonte', 'minutes' => 47, 'unread' => 0, 'status' => 'bot'],
        ];

        foreach ($samples as $sample) {
            $contact = Contact::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'wa_id' => $sample['wa_id']],
                [
                    'phone_number' => $sample['wa_id'],
                    'name' => $sample['name'],
                    'opt_in' => true,
                    'opted_in_at' => now()->subDays(10),
                    'last_seen_at' => now()->subMinutes($sample['minutes']),
                ],
            );

            $conversation = Conversation::query()->firstOrCreate(
                ['whatsapp_channel_id' => $channel->id, 'contact_id' => $contact->id],
                [
                    'workspace_id' => $workspace->id,
                    'status' => $sample['status'],
                    'assigned_to' => $sample['status'] === 'human' ? $admin->id : null,
                    'unread_count' => $sample['unread'],
                    'context' => [],
                    'customer_service_window_expires_at' => now()->addHours(20),
                    'last_message_at' => now()->subMinutes($sample['minutes']),
                ],
            );

            if (! $conversation->messages()->exists()) {
                Message::create([
                    'workspace_id' => $workspace->id,
                    'conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'type' => 'interactive',
                    'status' => 'read',
                    'content' => [
                        'type' => 'menu',
                        'text' => "Olá, {$sample['name']}! 👋 Bem-vindo ao Suporte Online. Selecione o assunto que deseja tratar.",
                        'options' => [
                            ['id' => 'nfe', 'label' => 'NF-e'],
                            ['id' => 'vaf', 'label' => 'VAF'],
                            ['id' => 'suporte', 'label' => 'Suporte'],
                        ],
                    ],
                    'sent_at' => now()->subMinutes($sample['minutes'] + 2),
                    'read_at' => now()->subMinutes($sample['minutes'] + 1),
                    'created_at' => now()->subMinutes($sample['minutes'] + 2),
                    'updated_at' => now()->subMinutes($sample['minutes'] + 2),
                ]);
                Message::create([
                    'workspace_id' => $workspace->id,
                    'conversation_id' => $conversation->id,
                    'direction' => 'inbound',
                    'type' => 'text',
                    'status' => 'received',
                    'content' => ['type' => 'text', 'text' => $sample['status'] === 'human' ? 'Preciso falar com o suporte.' : 'Quero informações sobre NF-e.'],
                    'created_at' => now()->subMinutes($sample['minutes']),
                    'updated_at' => now()->subMinutes($sample['minutes']),
                ]);
            }
        }
    }
}
