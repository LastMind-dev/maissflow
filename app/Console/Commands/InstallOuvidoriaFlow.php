<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Services\FlowGraphValidator;
use App\Services\OuvidoriaFlowFactory;
use Illuminate\Console\Command;

class InstallOuvidoriaFlow extends Command
{
    protected $signature = 'automation:install-ouvidoria
        {automation? : public_id da automação (padrão: a ativa mais antiga)}
        {--menu=menu_main : id do nó de menu onde a opção Ouvidoria entra}';

    protected $description = 'Adiciona o ramo da Ouvidoria (MAISSDoc) ao rascunho da automação';

    public function handle(OuvidoriaFlowFactory $factory, FlowGraphValidator $validator): int
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

        if ($nodes->contains('id', OuvidoriaFlowFactory::ENTRY_NODE)) {
            $this->info('O ramo da ouvidoria já está instalado neste rascunho.');

            return self::SUCCESS;
        }

        $menuIndex = $nodes->search(fn (array $node): bool => $node['id'] === $menuId && ($node['type'] ?? null) === 'menu');
        if ($menuIndex === false) {
            $this->error("Nó de menu '{$menuId}' não encontrado no grafo.");

            return self::FAILURE;
        }

        $branch = $factory->branch($menuId);
        $options = data_get($graph, "nodes.{$menuIndex}.data.options", []);
        if (! collect($options)->contains('id', $branch['option']['id'])) {
            $options[] = $branch['option'];
        }
        data_set($graph, "nodes.{$menuIndex}.data.options", $options);

        $graph['nodes'] = array_merge($graph['nodes'], $branch['nodes']);
        $graph['edges'] = array_merge($graph['edges'] ?? [], $branch['edges']);

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

        $this->info("Ramo da ouvidoria adicionado ao rascunho da automação '{$automation->name}' (revisão {$draft->revision}).");
        $this->info('Revise no editor e publique para ativar.');

        return self::SUCCESS;
    }
}
