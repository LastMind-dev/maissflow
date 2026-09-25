<?php

namespace App\Services\Actions;

class ActionRegistry
{
    /**
     * Allowlist de ações executáveis pelo runtime. O grafo publicado só pode
     * referenciar identificadores desta lista — nunca URL, SQL ou código livre.
     *
     * @var array<string, class-string<FlowAction>>
     */
    private const ACTIONS = [
        'ouvidoria.submit' => OuvidoriaSubmitAction::class,
    ];

    public function find(string $id): ?FlowAction
    {
        $class = self::ACTIONS[$id] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys(self::ACTIONS);
    }

    /**
     * @return array<int, array{id: string, label: string, description: string}>
     */
    public function describe(): array
    {
        return collect(self::ACTIONS)
            ->map(fn (string $class, string $id): array => [
                'id' => $id,
                'label' => app($class)->label(),
                'description' => app($class)->description(),
            ])
            ->values()
            ->all();
    }
}
