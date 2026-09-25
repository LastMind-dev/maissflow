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

    private function bootContext(bool $api = false): array
    {
        config([
            'services.ged.ouvidoria_url' => 'https://ged.test/ouvidoria',
            'services.ged.api_url' => $api ? 'https://ged.test/api/portal/v1' : '',
            'services.ged.api_token' => $api ? 'token-ged-integracao' : null,
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
            '0',
            'continue',
        ]);

        $this->assertSame('waiting_input', $result['status']);
        $this->assertSame('ouv_sucesso', $result['current_node_id']);

        $texts = collect($result['transcript'])->pluck('text')->implode("\n");
        $this->assertStringContainsString('Ação simulada', $texts);
        $this->assertStringContainsString('2026000000', $texts);
    }

    /**
     * Fake da Graph API (mensagens + mídia) e da API oficial do GED.
     * $overrides permite customizar respostas por endpoint nos testes.
     */
    private function fakeWhatsAppAndApi(array $overrides = []): void
    {
        $sent = 0;
        Http::fake(function ($request) use (&$sent, $overrides) {
            $url = $request->url();

            if (str_contains($url, 'lookaside.fbsbx.com')) {
                return Http::response('CONTEUDO-BINARIO-FAKE', 200);
            }

            if (str_contains($url, 'graph.facebook.com')) {
                if (str_ends_with($url, '/messages') || str_contains($url, '/register')) {
                    $sent++;

                    return Http::response(['messages' => [['id' => "wamid.out-{$sent}"]]], 200);
                }

                // GET /{media-id}: metadados da mídia inbound.
                return Http::response([
                    'url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/abc',
                    'mime_type' => 'application/pdf',
                    'sha256' => 'deadbeef',
                    'file_size' => 1234,
                ], 200);
            }

            if (str_contains($url, '/manifestacoes/') && str_ends_with($url, '/anexos')) {
                return $overrides['anexos'] ?? Http::response(['anexos_total' => 1], 201);
            }

            if (str_ends_with($url, '/manifestacoes')) {
                return $overrides['manifestacoes'] ?? Http::response([
                    'protocolo' => '2026000099',
                    'codigo' => 'XY9Z2',
                    'canal' => 'ouvidoria',
                    'status' => 'aberto',
                    'data_limite' => '2026-10-20',
                ], 201);
            }

            if (str_ends_with($url, '/consulta')) {
                return $overrides['consulta'] ?? Http::response([
                    'protocolo' => '2026000099',
                    'canal' => 'ouvidoria',
                    'tipo' => 'reclamacao',
                    'status' => 'em_analise',
                    'status_label' => 'Em análise',
                    'assunto' => 'Lâmpada queimada',
                    'criado_em' => '2026-09-30T10:00:00-03:00',
                    'data_limite' => '2026-10-20',
                    'setor' => 'Iluminação Pública',
                    'resposta' => 'Equipe acionada para reparo.',
                    'anexos_total' => 1,
                ], 200);
            }

            return Http::response('<form><input type="hidden" name="_token" value="token-123"></form>', 200);
        });
    }

    private function walker(WhatsAppChannel $channel, array $value, string $prefix): callable
    {
        $runtime = app(ConversationRuntime::class);
        $step = 0;

        return function (array $inbound) use ($runtime, $channel, $value, &$step, $prefix): void {
            $step++;
            $runtime->ingestInbound($channel, $value, [
                'from' => '5517999999999',
                'id' => "wamid.{$prefix}-{$step}",
                ...$inbound,
            ]);
        };
    }

    private function button(string $id, string $title): array
    {
        return ['type' => 'interactive', 'interactive' => ['button_reply' => ['id' => $id, 'title' => $title]]];
    }

    private function listOption(string $id, string $title): array
    {
        return ['type' => 'interactive', 'interactive' => ['list_reply' => ['id' => $id, 'title' => $title]]];
    }

    private function texto(string $body): array
    {
        return ['type' => 'text', 'text' => ['body' => $body]];
    }

    public function test_submission_uses_the_official_api_when_configured(): void
    {
        $this->fakeWhatsAppAndApi();

        [$channel, $value] = $this->bootContext(api: true);
        $this->walkOuvidoria($channel, $value);

        $execution = FlowExecution::query()->firstOrFail();
        $variables = $execution->context['variables'];
        $this->assertSame('2026000099', $variables['ouvidoria_protocolo']);
        $this->assertSame('XY9Z2', $variables['ouvidoria_codigo']);
        $this->assertSame('1', $variables['ouvidoria_enviada']);
        $this->assertSame('ouv_sucesso', Conversation::query()->firstOrFail()->current_node_id);

        Http::assertSent(fn ($request) => $request->url() === 'https://ged.test/api/portal/v1/manifestacoes'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer token-ged-integracao')
            && $request->hasHeader('Idempotency-Key')
            && ($request['canal'] ?? null) === 'ouvidoria'
            && ($request['assunto'] ?? null) === 'Lâmpada queimada'
            && ($request['nome_solicitante'] ?? null) === 'João da Silva'
            && ($request['cpf'] ?? null) === '111.444.777-35');

        // O formulário público NÃO deve ser chamado quando a API está ativa.
        Http::assertNotSent(fn ($request) => $request->url() === 'https://ged.test/ouvidoria');
    }

    public function test_media_attachment_is_collected_downloaded_and_uploaded(): void
    {
        $this->fakeWhatsAppAndApi();

        [$channel, $value] = $this->bootContext(api: true);
        $send = $this->walker($channel, $value, 'media');

        $send($this->texto('Oi'));
        $send($this->listOption('ouvidoria', 'Ouvidoria'));
        $send($this->button('continue', 'Iniciar registro'));
        $send($this->button('identificado', 'Me identificar'));
        $send($this->texto('João da Silva'));
        $send($this->texto('11144477735'));
        $send($this->texto('cidadao@exemplo.com'));
        $send($this->listOption('reclamacao', 'Reclamação'));
        $send($this->texto('0'));
        $send($this->texto('Lâmpada queimada'));
        $send($this->texto('Poste apagado há três dias'));
        $send($this->texto('0'));
        $send($this->texto('0'));
        $send($this->texto('0'));

        // Nó de anexos: documento inbound é acumulado (não avança).
        $send([
            'type' => 'document',
            'document' => [
                'id' => 'media-doc-1',
                'mime_type' => 'application/pdf',
                'filename' => 'foto-do-poste.pdf',
            ],
        ]);

        $execution = FlowExecution::query()->firstOrFail();
        $this->assertSame('waiting', $execution->status);
        $this->assertSame('ouv_anexos', $execution->current_node_id);
        $this->assertSame('media-doc-1', $execution->context['variables']['anexos'][0]['media_id']);
        $this->assertSame('foto-do-poste.pdf', $execution->context['variables']['anexos'][0]['filename']);

        $send($this->texto('0'));
        $send($this->button('continue', 'Confirmar envio'));

        $variables = FlowExecution::query()->firstOrFail()->context['variables'];
        $this->assertSame('2026000099', $variables['ouvidoria_protocolo']);
        $this->assertSame('1', $variables['anexos_total']);
        $this->assertSame('1', $variables['ouvidoria_anexos']);

        // Metadados da mídia buscados na Graph API e binário baixado da URL assinada.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
            && str_ends_with($request->url(), '/media-doc-1')
            && $request->method() === 'GET');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'lookaside.fbsbx.com'));

        // Upload autenticado pelo código do cidadão.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/manifestacoes/2026000099/anexos')
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer token-ged-integracao'));
    }

    public function test_esic_branch_registers_with_cpf_and_no_anonymous(): void
    {
        $this->fakeWhatsAppAndApi();

        [$channel, $value] = $this->bootContext(api: true);
        $send = $this->walker($channel, $value, 'esic');

        $send($this->texto('Oi'));
        $send($this->listOption('esic', 'Pedido e-SIC'));
        $send($this->button('continue', 'Iniciar pedido'));
        $send($this->texto('Maria Souza'));
        $send($this->texto('11144477735'));
        $send($this->texto('maria@exemplo.com'));
        $send($this->texto('Cópia do contrato'));
        $send($this->texto('Solicito acesso integral ao contrato 12/2025'));
        $send($this->texto('0'));
        $send($this->button('continue', 'Enviar pedido'));

        $execution = FlowExecution::query()->firstOrFail();
        $variables = $execution->context['variables'];
        $this->assertSame('2026000099', $variables['ouvidoria_protocolo']);
        $this->assertSame('esic_sucesso', Conversation::query()->firstOrFail()->current_node_id);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/manifestacoes')
            && $request->method() === 'POST'
            && ($request['canal'] ?? null) === 'esic'
            && ($request['cpf'] ?? null) === '111.444.777-35'
            && ($request['nome_solicitante'] ?? null) === 'Maria Souza'
            && ! isset($request['anonimo'])
            && ! isset($request['tipo']));
    }

    public function test_esic_rejects_api_path_missing(): void
    {
        $this->fakeWhatsAppAndApi();

        // Sem GED_API_URL/GED_API_TOKEN o e-SIC não tem fallback de formulário.
        [$channel, $value] = $this->bootContext(api: false);
        $send = $this->walker($channel, $value, 'esicnoapi');

        $send($this->texto('Oi'));
        $send($this->listOption('esic', 'Pedido e-SIC'));
        $send($this->button('continue', 'Iniciar pedido'));
        $send($this->texto('Maria Souza'));
        $send($this->texto('11144477735'));
        $send($this->texto('maria@exemplo.com'));
        $send($this->texto('Cópia do contrato'));
        $send($this->texto('Solicito acesso integral ao contrato'));
        $send($this->texto('0'));
        $send($this->button('continue', 'Enviar pedido'));

        $this->assertSame('esic_erro', Conversation::query()->firstOrFail()->current_node_id);
    }

    public function test_acompanhamento_shows_status_from_the_api(): void
    {
        $this->fakeWhatsAppAndApi();

        [$channel, $value] = $this->bootContext(api: true);
        $send = $this->walker($channel, $value, 'ac');

        $send($this->texto('Oi'));
        $send($this->listOption('acompanhamento', 'Acompanhar pedido'));
        $send($this->button('continue', 'Consultar'));
        $send($this->texto('2026000099'));
        $send($this->texto('XY9Z2'));

        $this->assertSame('ac_resultado', Conversation::query()->firstOrFail()->current_node_id);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->pluck('content')
                ->contains(fn ($content): bool => str_contains($content['text'] ?? '', 'Em análise')),
        );

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/consulta')
            && $request->method() === 'POST'
            && ($request['protocolo'] ?? null) === '2026000099'
            && ($request['codigo'] ?? null) === 'XY9Z2');
    }

    public function test_acompanhamento_wrong_credentials_follows_error_edge(): void
    {
        $this->fakeWhatsAppAndApi([
            'consulta' => Http::response(['message' => 'Protocolo ou código de acompanhamento inválidos.'], 404),
        ]);

        [$channel, $value] = $this->bootContext(api: true);
        $send = $this->walker($channel, $value, 'ac404');

        $send($this->texto('Oi'));
        $send($this->listOption('acompanhamento', 'Acompanhar pedido'));
        $send($this->button('continue', 'Consultar'));
        $send($this->texto('2026000099'));
        $send($this->texto('ERRADO'));

        $this->assertSame('ac_erro', Conversation::query()->firstOrFail()->current_node_id);
        $this->assertTrue(
            Message::query()
                ->where('direction', 'outbound')
                ->pluck('content')
                ->contains(fn ($content): bool => str_contains($content['text'] ?? '', 'não localizados')),
        );
    }
}
