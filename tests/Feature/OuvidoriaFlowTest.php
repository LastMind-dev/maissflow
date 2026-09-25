<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Conversation;
use App\Models\FlowExecution;
use App\Models\Message;
use App\Models\WhatsAppChannel;
use App\Models\Workspace;
use App\Services\ConversationRuntime;
use App\Services\DefaultFlowFactory;
use App\Services\FlowSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OuvidoriaFlowTest extends TestCase
{
    use RefreshDatabase;

    private function bootContext(): array
    {
        config(['services.ged.ouvidoria_url' => 'https://ged.test/ouvidoria']);

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

        return [$channel, ['contacts' => [['profile' => ['name' => 'Cidadão'], 'wa_id' => '5517999999999']]]];
    }

    private function walkOuvidoria(WhatsAppChannel $channel, array $value, string $cpf = '11144477735'): void
    {
        $runtime = app(ConversationRuntime::class);
        $step = 0;
        $send = function (array $inbound) use ($runtime, $channel, $value, &$step): void {
            $step++;
            $runtime->ingestInbound($channel, $value, [
                'from' => '5517999999999',
                'id' => "wamid.ouv-{$step}",
                ...$inbound,
            ]);
        };
        $button = static fn (string $id, string $title): array => [
            'type' => 'interactive',
            'interactive' => ['button_reply' => ['id' => $id, 'title' => $title]],
        ];
        $list = static fn (string $id, string $title): array => [
            'type' => 'interactive',
            'interactive' => ['list_reply' => ['id' => $id, 'title' => $title]],
        ];
        $text = static fn (string $body): array => ['type' => 'text', 'text' => ['body' => $body]];

        $send($text('Oi'));
        $send($list('ouvidoria', 'Ouvidoria'));
        $send($button('continue', 'Iniciar registro'));
        $send($button('identificado', 'Me identificar'));
        $send($text('João da Silva'));
        $send($text($cpf));
        $send($text('cidadao@exemplo.com'));
        $send($list('reclamacao', 'Reclamação'));
        $send($text('0'));
        $send($text('Lâmpada queimada'));
        $send($text('Poste apagado há três dias na rua principal'));
        $send($text('0'));
        $send($text('0'));
        $send($text('0'));
        $send($button('continue', 'Confirmar envio'));
    }

    private function fakeWhatsAppAndGed(string $postBody): void
    {
        $sent = 0;
        Http::fake(function ($request) use (&$sent, $postBody) {
            if (str_contains($request->url(), 'graph.facebook.com')) {
                $sent++;

                return Http::response(['messages' => [['id' => "wamid.out-{$sent}"]]], 200);
            }

            if ($request->method() === 'GET') {
                return Http::response('<form><input type="hidden" name="_token" value="token-123"></form>', 200);
            }

            return Http::response($postBody, 200);
        });
    }

    public function test_flow_collects_answers_and_submits_to_the_ged(): void
    {
        $this->fakeWhatsAppAndGed(
            '<h1>Manifestação registrada com sucesso!</h1>'
            .'<div class="small text-muted text-uppercase">Protocolo</div>'
            .'<div class="fw-bold">2026000099</div>'
            .'<div class="small text-muted text-uppercase">Código de acompanhamento</div>'
            .'<div class="fw-bold text-primary">XY9Z2</div>',
        );

        [$channel, $value] = $this->bootContext();
        $this->walkOuvidoria($channel, $value);

        $execution = FlowExecution::query()->firstOrFail();
        $variables = $execution->context['variables'];
        $this->assertSame('identificado', $variables['identificacao']);
        $this->assertSame('João da Silva', $variables['nome']);
        $this->assertSame('11144477735', $variables['cpf']);
        $this->assertSame('cidadao@exemplo.com', $variables['email']);
        $this->assertSame('reclamacao', $variables['tipo']);
        $this->assertSame('Lâmpada queimada', $variables['assunto']);
        $this->assertSame('2026000099', $variables['ouvidoria_protocolo']);
        $this->assertSame('XY9Z2', $variables['ouvidoria_codigo']);

        $this->assertSame('ouv_sucesso', Conversation::query()->firstOrFail()->current_node_id);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->pluck('content')
                ->contains(fn ($content): bool => str_contains($content['text'] ?? '', '2026000099')),
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://ged.test/ouvidoria'
            && $request->method() === 'POST'
            && ($request['tipo'] ?? null) === 'reclamacao'
            && ($request['assunto'] ?? null) === 'Lâmpada queimada'
            && ($request['nome_solicitante'] ?? null) === 'João da Silva'
            && ($request['cpf'] ?? null) === '111.444.777-35'
            && ($request['telefone'] ?? null) === '(17) 99999-9999'
            && ($request['website'] ?? 'x') === ''
            && ! isset($request['anonimo']));
    }

    public function test_invalid_cpf_asks_for_retry_and_keeps_waiting(): void
    {
        $sent = 0;
        Http::fake(function ($request) use (&$sent) {
            $sent++;

            return Http::response(['messages' => [['id' => "wamid.out-{$sent}"]]], 200);
        });

        [$channel, $value] = $this->bootContext();

        $runtime = app(ConversationRuntime::class);
        $step = 0;
        $send = function (array $inbound) use ($runtime, $channel, $value, &$step): void {
            $step++;
            $runtime->ingestInbound($channel, $value, [
                'from' => '5517999999999',
                'id' => "wamid.cpf-{$step}",
                ...$inbound,
            ]);
        };

        $send(['type' => 'text', 'text' => ['body' => 'Oi']]);
        $send(['type' => 'interactive', 'interactive' => ['list_reply' => ['id' => 'ouvidoria', 'title' => 'Ouvidoria']]]);
        $send(['type' => 'interactive', 'interactive' => ['button_reply' => ['id' => 'continue', 'title' => 'Iniciar']]]);
        $send(['type' => 'interactive', 'interactive' => ['button_reply' => ['id' => 'identificado', 'title' => 'Identificar']]]);
        $send(['type' => 'text', 'text' => ['body' => 'João']]);
        $send(['type' => 'text', 'text' => ['body' => '123']]);

        $execution = FlowExecution::query()->firstOrFail();
        $this->assertSame('waiting', $execution->status);
        $this->assertSame('ouv_cpf', $execution->current_node_id);
        $this->assertArrayNotHasKey('cpf', $execution->context['variables'] ?? []);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->pluck('content')
                ->contains(fn ($content): bool => str_contains($content['text'] ?? '', 'CPF inválido')),
        );
    }

    public function test_ged_rejection_follows_the_error_edge(): void
    {
        $this->fakeWhatsAppAndGed('<span class="invalid-feedback">O campo assunto é obrigatório.</span>');

        [$channel, $value] = $this->bootContext();
        $this->walkOuvidoria($channel, $value);

        $conversation = Conversation::query()->firstOrFail();
        $this->assertSame('ouv_erro', $conversation->current_node_id);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->pluck('content')
                ->contains(fn ($content): bool => str_contains($content['text'] ?? '', 'Não consegui registrar')),
        );
    }

    public function test_simulator_walks_the_branch_without_http(): void
    {
        $result = app(FlowSimulator::class)->run(app(DefaultFlowFactory::class)->make(), [
            'ouvidoria',
            'continue',
            'identificado',
            'João da Silva',
            '11144477735',
            'cidadao@exemplo.com',
            'reclamacao',
            '0',
            'Lâmpada queimada',
            'Poste apagado há dias',
            '0',
            '0',
            '0',
            'continue',
        ]);

        $this->assertSame('waiting_input', $result['status']);
        $this->assertSame('ouv_sucesso', $result['current_node_id']);

        $texts = collect($result['transcript'])->pluck('text')->implode("\n");
        $this->assertStringContainsString('Ação simulada', $texts);
        $this->assertStringContainsString('2026000000', $texts);
    }
}
