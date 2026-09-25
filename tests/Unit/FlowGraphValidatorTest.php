<?php

namespace Tests\Unit;

use App\Services\DefaultFlowFactory;
use App\Services\FlowGraphValidator;
use Tests\TestCase;

class FlowGraphValidatorTest extends TestCase
{
    public function test_reference_flow_is_valid_and_all_nodes_are_reachable(): void
    {
        $result = app(FlowGraphValidator::class)->validate(app(DefaultFlowFactory::class)->make());

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(['O nó nfe_emissao não possui uma próxima etapa.'], $result['warnings']);
        $this->assertSame(50, $result['stats']['nodes']);
        $this->assertSame(91, $result['stats']['edges']);
    }

    public function test_invalid_menu_and_broken_edge_are_rejected(): void
    {
        $graph = [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'menu', 'type' => 'menu', 'position' => ['x' => 100, 'y' => 0], 'data' => ['text' => '', 'options' => []]],
            ],
            'edges' => [
                ['id' => 'broken', 'source' => 'trigger', 'target' => 'missing'],
            ],
        ];

        $result = app(FlowGraphValidator::class)->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThanOrEqual(3, count($result['errors']));
    }
}
