<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\FlowExecution;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\WhatsAppChannel;
use App\Models\Workspace;
use App\Services\ConversationRuntime;
use App\Services\DefaultFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_message_creates_contact_conversation_and_waiting_flow(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.outbound-intro']]], 200)
                ->push(['messages' => [['id' => 'wamid.outbound-menu']]], 200),
        ]);

        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
            'phone_number_id' => '123456789',
            'waba_id' => '998877',
            'access_token' => 'access-token',
            'app_secret' => 'application-secret',
            'verify_token' => 'verify-token-with-adequate-length',
            'graph_version' => 'v25.0',
            'status' => 'connected',
        ]);
        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Atendimento',
            'status' => 'active',
            'trigger_type' => 'incoming_message',
        ]);
        $version = $automation->versions()->create([
            'version' => 1,
            'status' => 'published',
            'graph' => app(DefaultFlowFactory::class)->make(),
            'published_at' => now(),
        ]);
        $automation->update(['published_version_id' => $version->id]);

        $event = WebhookEvent::create([
            'whatsapp_channel_id' => $channel->id,
            'event_id' => str_repeat('a', 64),
            'status' => 'received',
            'payload' => [
                'entry' => [[
                    'changes' => [[
                        'value' => [
                            'metadata' => ['phone_number_id' => $channel->phone_number_id],
                            'contacts' => [['profile' => ['name' => 'Cliente Teste'], 'wa_id' => '5517999999999']],
                            'messages' => [[
                                'from' => '5517999999999',
                                'id' => 'wamid.inbound-1',
                                'timestamp' => (string) now()->timestamp,
                                'type' => 'text',
                                'text' => ['body' => 'Olá'],
                            ]],
                        ],
                    ]],
                ]],
            ],
            'received_at' => now(),
        ]);

        (new ProcessWhatsAppWebhook($event->id))->handle(app(ConversationRuntime::class));

        $this->assertDatabaseHas('contacts', ['workspace_id' => $workspace->id, 'name' => 'Cliente Teste']);
        $this->assertDatabaseHas('conversations', ['workspace_id' => $workspace->id, 'status' => 'bot', 'current_node_id' => 'menu_main']);
        $this->assertDatabaseHas('flow_executions', ['status' => 'waiting', 'current_node_id' => 'menu_main']);
        $this->assertSame(3, Message::count());
        $this->assertSame(1, Contact::count());
        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, FlowExecution::count());
        $this->assertSame('processed', $event->fresh()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => data_get($request->data(), 'text.body')
            === "Olá! Bem-vindo ao Suporte Online! 😄 Para facilitar, selecione abaixo o assunto que gostaria de tratar. Estou aqui para ajudar!\n");
        Http::assertSent(fn ($request) => data_get($request->data(), 'interactive.header.text')
            === 'Selecione a Opção Desejada'
            && data_get($request->data(), 'interactive.action.button') === 'Selecione uma Opção'
            && data_get($request->data(), 'interactive.action.sections.0.title') === 'Menu');
    }

    public function test_runtime_waits_for_the_legacy_back_button_before_returning_to_a_menu(): void
    {
        $sent = 0;
        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response(['messages' => [['id' => "wamid.outbound-{$sent}"]]], 200);
        });

        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
            'phone_number_id' => '123456789',
            'waba_id' => '998877',
            'access_token' => 'access-token',
            'app_secret' => 'application-secret',
            'verify_token' => 'verify-token-with-adequate-length',
            'graph_version' => 'v25.0',
            'status' => 'connected',
        ]);
        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Atendimento',
            'status' => 'active',
            'trigger_type' => 'incoming_message',
        ]);
        $version = $automation->versions()->create([
            'version' => 1,
            'status' => 'published',
            'graph' => app(DefaultFlowFactory::class)->make(),
            'published_at' => now(),
        ]);
        $automation->update(['published_version_id' => $version->id]);

        $runtime = app(ConversationRuntime::class);
        $value = [
            'contacts' => [[
                'profile' => ['name' => 'Cliente Teste'],
                'wa_id' => '5517999999999',
            ]],
        ];
        $runtime->ingestInbound($channel, $value, [
            'from' => '5517999999999',
            'id' => 'wamid.inbound-start',
            'type' => 'text',
            'text' => ['body' => 'Olá'],
        ]);
        $runtime->ingestInbound($channel, $value, [
            'from' => '5517999999999',
            'id' => 'wamid.inbound-nfe',
            'type' => 'interactive',
            'interactive' => ['list_reply' => ['id' => 'nfe', 'title' => '🏬 NF-E']],
        ]);
        $runtime->ingestInbound($channel, $value, [
            'from' => '5517999999999',
            'id' => 'wamid.inbound-boleto',
            'type' => 'interactive',
            'interactive' => ['list_reply' => ['id' => 'boleto', 'title' => 'Imprimir Boleto']],
        ]);

        $conversation = Conversation::query()->firstOrFail();
        $this->assertSame('nfe_boleto', $conversation->fresh()->current_node_id);
        $this->assertSame('waiting', FlowExecution::query()->firstOrFail()->status);

        $runtime->ingestInbound($channel, $value, [
            'from' => '5517999999999',
            'id' => 'wamid.inbound-voltar',
            'type' => 'interactive',
            'interactive' => ['button_reply' => ['id' => 'continue', 'title' => 'Voltar']],
        ]);

        $this->assertSame('menu_nfe', $conversation->fresh()->current_node_id);
        $this->assertSame('waiting', FlowExecution::query()->firstOrFail()->status);
        $this->assertSame(6, $sent);
        $this->assertSame(10, Message::count());
    }

    public function test_failed_delivery_status_is_recorded_without_writing_an_invalid_timestamp_column(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
            'phone_number_id' => '123456789',
            'waba_id' => '998877',
            'access_token' => 'access-token',
            'app_secret' => 'application-secret',
            'verify_token' => 'verify-token-with-adequate-length',
            'graph_version' => 'v25.0',
            'status' => 'connected',
        ]);
        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'name' => 'Cliente Teste',
            'wa_id' => '5517999999999',
            'phone_number' => '5517999999999',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'whatsapp_channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'status' => 'bot',
        ]);
        $message = Message::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'sent',
            'meta_message_id' => 'wamid.failed-status',
            'content' => ['type' => 'text', 'text' => 'Teste'],
        ]);

        app(ConversationRuntime::class)->applyStatuses([[
            'id' => 'wamid.failed-status',
            'status' => 'failed',
            'errors' => [['code' => 131026, 'message' => 'Message undeliverable']],
        ]]);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame(131026, $message->error[0]['code']);
        $this->assertNull($message->delivered_at);
        $this->assertNull($message->read_at);
    }
}
