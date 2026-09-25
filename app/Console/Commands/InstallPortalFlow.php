<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Services\AcompanhamentoFlowFactory;
use App\Services\EsicFlowFactory;
use App\Services\FlowGraphValidator;
use App\Services\OuvidoriaFlowFactory;
use Illuminate\Console\Command;

class InstallPortalFlow extends Command
{
    protected $signature = 'automation:install-portal
        {automation? : public_id da automação (padrão: a ativa mais antiga)}
        {--menu=menu_main : id do nó de menu onde as opções entram}';

    protected $description = 'Adiciona os ramos Ouvidoria + e-SIC + Acompanhamento (MAISSDoc) ao rascunho da automação';

    public function handle(FlowGraphValidator $validator): int
    {
        $automation = $this->argument('automation')
            ? Automation::query()->where('public_id', $this->argument('automation'))->first()
            : Automation::query()
                ->where('status', 'active')
                ->where('trigger_type', 'incoming_message')
                ->oldest('id')
                ->first();

        if (! $automation) {
            $this->error('Automação não encontrada.');

            return self::FAILURE;
        }

        $menuId = (string) $this->option('menu');
        $draft = $automation->draftVersion();

        if (! $draft) {
            $published = $automation->publishedVersion;
            if (! $published) {
                $this->error('A automação não possui versão publicada nem rascunho.');

                return self::FAILURE;
            }

            $draft = $automation->versions()->create([
                'version' => $automation->versions()->max('version') + 1,
                'revision' => 1,
                'status' => 'draft',
                'graph' => $published->graph,
            ]);
            $this->info("Nenhum rascunho existia — criada versão {$draft->version} a partir da publicada.");
        }

        $graph = $draft->graph;
        $nodes = collect($graph['nodes'] ?? []);

        $menuIndex = $nodes->search(fn (array $node): bool => $node['id'] === $menuId && ($node['type'] ?? null) === 'menu');
        if ($menuIndex === false) {
            $this->error("Nó de menu '{$menuId}' não encontrado no grafo.");

            return self::FAILURE;
        }

        $branches = [
            'ouvidoria' => [new OuvidoriaFlowFactory, OuvidoriaFlowFactory::ENTRY_NODE],
            'e-SIC' => [new EsicFlowFactory, EsicFlowFactory::ENTRY_NODE],
            'acompanhamento' => [new AcompanhamentoFlowFactory, AcompanhamentoFlowFactory::ENTRY_NODE],
        ];

        $instalados = [];
        $options = data_get($graph, "nodes.{$menuIndex}.data.options", []);

        foreach ($branches as $nome => [$factory, $entryNode]) {
            if (collect($graph['nodes'] ?? [])->contains('id', $entryNode)) {
                $this->info("Ramo {$nome} já está instalado neste rascunho — mantido.");

                continue;
            }

            $branch = $factory->branch($menuId);
            if (! collect($options)->contains('id', $branch['option']['id'])) {
                $options[] = $branch['option'];
            }
            $graph['nodes'] = array_merge($graph['nodes'], $branch['nodes']);
            $graph['edges'] = array_merge($graph['edges'] ?? [], $branch['edges']);
            $instalados[] = $nome;
        }

        data_set($graph, "nodes.{$menuIndex}.data.options", $options);

        if ($instalados === []) {
            $this->info('Todos os ramos já estavam instalados — nada a fazer.');

            return self::SUCCESS;
        }

        if (count($options) > 10) {
            $this->warn('O menu principal passou de 10 opções — o limite de lista do WhatsApp é 10. Considere um submenu.');
        }

        $validation = $validator->validate($graph);
        $draft->update([
            'graph' => $graph,
            'validation_result' => $validation,
            'revision' => $draft->revision + 1,
        ]);

        if (! $validation['valid']) {
            $this->warn('Rascunho salvo com pendências de validação:');
            foreach ($validation['errors'] as $error) {
                $this->warn(" - {$error}");
            }
        }

        $this->info('Ramos '.implode(', ', $instalados)." adicionados ao rascunho da automação '{$automation->name}' (revisão {$draft->revision}).");
        $this->info('Revise no editor e publique para ativar.');

        return self::SUCCESS;
    }
}
