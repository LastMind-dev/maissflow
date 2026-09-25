<?php

namespace App\Services\Actions;

use App\Models\Conversation;
use App\Models\FlowExecution;

interface FlowAction
{
    public function id(): string;

    public function label(): string;

    public function description(): string;

    /**
     * Executa a ação. Retorno:
     * success   bool   determina a saída seguida (handle "success" ou "error")
     * variables array  mescladas em execution.context['variables'] (uso em {{flow.*}})
     * summary   string resumo seguro gravado no evento da execução
     * error     string motivo da falha, quando success = false
     *
     * @return array{success: bool, variables?: array<string, string>, summary?: string, error?: string}
     */
    public function execute(array $config, Conversation $conversation, FlowExecution $execution): array;

    /**
     * Variáveis fictícias usadas pelo simulador (a ação real não roda na prévia).
     *
     * @return array<string, string>
     */
    public function simulatedVariables(): array;
}
