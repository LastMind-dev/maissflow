<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppChannel;
use App\Models\Workspace;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_the_inbox_with_its_latest_message(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
            'status' => 'disconnected',
        ]);
        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'name' => 'Cliente',
            'wa_id' => '5517999999999',
            'phone_number' => '5517999999999',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'whatsapp_channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'status' => 'bot',
            'last_message_at' => now(),
        ]);
        Message::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'status' => 'received',
            'content' => ['type' => 'text', 'text' => 'Primeira'],
        ]);
        Message::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'sent',
            'content' => ['type' => 'text', 'text' => 'Última'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/app/inbox');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $conversation->public_id)
            ->assertJsonPath('data.0.last_message.content.text', 'Última');
    }

    public function test_agent_must_assume_conversation_before_replying_and_human_mode_enables_composer(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
            'status' => 'connected',
        ]);
        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'name' => 'Cliente',
            'wa_id' => '5517999999999',
            'phone_number' => '5517999999999',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'whatsapp_channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'status' => 'bot',
            'customer_service_window_expires_at' => now()->addHours(23),
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/app/inbox/{$conversation->public_id}/reply", ['text' => 'Resposta'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ative o atendimento humano antes de responder ao contato.');

        $this->actingAs($user)
            ->putJson("/api/app/inbox/{$conversation->public_id}/mode", ['status' => 'human'])
            ->assertOk()
            ->assertJsonPath('data.status', 'human')
            ->assertJsonPath('data.assignee.id', $user->id);

        $this->actingAs($user)
            ->getJson("/api/app/inbox/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.can_reply', true);

        $this->mock(WhatsAppCloudApiService::class)
            ->shouldReceive('sendText')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'wamid.manual-reply',
                'error' => null,
            ]);

        $this->actingAs($user)
            ->postJson("/api/app/inbox/{$conversation->public_id}/reply", ['text' => 'Resposta humana'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'status' => 'sent',
            'meta_message_id' => 'wamid.manual-reply',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'conversation.mode_changed',
        ]);
    }

    public function test_inbox_filters_mine_and_unread_conversations(): void
    {
        $workspace = Workspace::create(['name' => 'Empresa', 'slug' => 'empresa']);
        $user = User::factory()->create(['workspace_id' => $workspace->id, 'role' => 'agent']);
        $channel = WhatsAppChannel::create([
            'workspace_id' => $workspace->id,
            'name' => 'Principal',
        ]);

        foreach ([
            ['phone' => '5517000000001', 'assigned_to' => $user->id, 'unread_count' => 0],
            ['phone' => '5517000000002', 'assigned_to' => null, 'unread_count' => 2],
        ] as $data) {
            $contact = Contact::create([
                'workspace_id' => $workspace->id,
                'wa_id' => $data['phone'],
                'phone_number' => $data['phone'],
            ]);
            Conversation::create([
                'workspace_id' => $workspace->id,
                'whatsapp_channel_id' => $channel->id,
                'contact_id' => $contact->id,
                'status' => 'human',
                'assigned_to' => $data['assigned_to'],
                'unread_count' => $data['unread_count'],
                'last_message_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/app/inbox?filter=mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact.phone_number', '5517000000001');

        $this->actingAs($user)
            ->getJson('/api/app/inbox?filter=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact.phone_number', '5517000000002');
    }
}
