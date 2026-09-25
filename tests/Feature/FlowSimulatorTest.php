<?php

namespace Tests\Feature;

use App\Services\DefaultFlowFactory;
use App\Services\FlowSimulator;
use Tests\TestCase;

class FlowSimulatorTest extends TestCase
{
    public function test_simulator_walks_through_nested_menus_and_back(): void
    {
        $result = app(FlowSimulator::class)->run(
            app(DefaultFlowFactory::class)->make(),
            ['nfe', 'boleto', 'continue'],
        );

        $this->assertSame('waiting_input', $result['status']);
        $this->assertSame('menu_nfe', $result['current_node_id']);
        $this->assertSame('Imprimir Boleto', $result['transcript'][4]['text']);
        $this->assertSame(' Emissão de Boleto📃:', $result['transcript'][5]['text']);
        $this->assertStringContainsString('Como-Encerrar-Competencia-Prestador', $result['transcript'][5]['links'][0]['url']);
        $this->assertSame('Voltar', $result['transcript'][7]['text']);
    }

    public function test_support_option_preserves_the_legacy_whatsapp_link(): void
    {
        $result = app(FlowSimulator::class)->run(
            app(DefaultFlowFactory::class)->make(),
            ['suporte'],
        );

        $this->assertSame('waiting_input', $result['status']);
        $this->assertSame('suporte', $result['current_node_id']);
        $this->assertSame('https://wa.me/5516991038606', $result['transcript'][3]['links'][0]['url']);
        $this->assertSame('Voltar', $result['transcript'][4]['options'][0]['label']);
    }

    public function test_emission_block_preserves_all_seven_downloads(): void
    {
        $result = app(FlowSimulator::class)->run(
            app(DefaultFlowFactory::class)->make(),
            ['nfe', 'emissao'],
        );

        $downloads = collect($result['transcript'])
            ->where('node_id', 'nfe_emissao')
            ->values();

        $this->assertSame('completed', $result['status']);
        $this->assertCount(7, $downloads);
        $this->assertSame('Download', $downloads->first()['links'][0]['label']);
        $this->assertStringContainsString('Regime-Tribut-rio-Inicio-M-s', $downloads->last()['links'][0]['url']);
    }
}
