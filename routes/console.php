<?php

use App\Models\Automation;
use App\Models\User;
use App\Services\LegacyManyChatFlowImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'flow:import-manychat-legacy
        {--email=admin@primaxonline.com.br : Usuário responsável pela publicação}
        {--automation= : UUID público da automação de destino}
        {--force : Publica uma nova versão mesmo se o conteúdo for idêntico}',
    function (LegacyManyChatFlowImporter $importer): int {
        $user = User::query()->where('email', (string) $this->option('email'))->first();
        if (! $user) {
            $this->error('Usuário responsável não encontrado.');

            return 1;
        }

        $automation = null;
        if ($publicId = $this->option('automation')) {
            $automation = Automation::query()
                ->where('workspace_id', $user->workspace_id)
                ->where('public_id', $publicId)
                ->first();

            if (! $automation) {
                $this->error('Automação de destino não encontrada no workspace do usuário.');

                return 1;
            }
        }

        $published = $importer->import($user, $automation, (bool) $this->option('force'));
        $graph = $published->graph;

        $this->info("Versão {$published->version} publicada com o fluxo legado do ManyChat.");
        $this->line('Nós: '.count($graph['nodes'] ?? []).' | Conexões: '.count($graph['edges'] ?? []));

        return 0;
    },
)->purpose('Importa e publica o fluxo legado Newchatbot do ManyChat');
