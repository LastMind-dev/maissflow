<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API oficial de integração do portal público do MAISSDoc
 * (/api/portal/v1). Autenticação Bearer server-side; a credencial do cidadão
 * (protocolo + código) viaja no corpo onde aplicável.
 */
class GedPortalClient
{
    /** A API está habilitada apenas com URL e token configurados. */
    public function disponivel(): bool
    {
        return filled($this->apiUrl()) && filled(config('services.ged.api_token'));
    }

    /**
     * POST /manifestacoes — registra manifestação/pedido e devolve
     * protocolo + código do cidadão.
     *
     * @return array{success: bool, status: int, data: array, error: ?string}
     */
    public function registrar(array $payload, ?string $idempotencyKey = null): array
    {
        $request = $this->request();
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        return $this->normalize($request->post($this->apiUrl().'/manifestacoes', $payload));
    }

    /**
     * POST /consulta — andamento autenticado pelo par protocolo + código.
     *
     * @return array{success: bool, status: int, data: array, error: ?string}
     */
    public function consultar(string $protocolo, string $codigo): array
    {
        return $this->normalize($this->request()->post($this->apiUrl().'/consulta', [
            'protocolo' => $protocolo,
            'codigo' => $codigo,
        ]));
    }

    /**
     * POST /manifestacoes/{protocolo}/anexos — anexa um arquivo à manifestação
     * (autenticado pelo código de acompanhamento do cidadão).
     *
     * @return array{success: bool, status: int, data: array, error: ?string}
     */
    public function enviarAnexo(string $protocolo, string $codigo, string $filename, string $binary): array
    {
        $protocolo = preg_replace('/\D/', '', $protocolo);

        return $this->normalize(
            $this->request()
                ->attach('anexos[]', $binary, $filename)
                ->post($this->apiUrl()."/manifestacoes/{$protocolo}/anexos", ['codigo' => $codigo])
        );
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) config('services.ged.api_token'))
            ->timeout((int) config('services.ged.timeout', 20))
            ->retry(2, 300, throw: false);
    }

    private function apiUrl(): string
    {
        return rtrim((string) config('services.ged.api_url', ''), '/');
    }

    /**
     * @return array{success: bool, status: int, data: array, error: ?string}
     */
    private function normalize(Response $response): array
    {
        $data = $response->json() ?? [];

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => is_array($data) ? $data : [],
            'error' => $response->successful()
                ? null
                : (string) (data_get($data, 'message') ?: "HTTP {$response->status()}"),
        ];
    }
}
