<?php

declare(strict_types=1);

/**
 * Integração HTTP opcional para alterações de recebimentos.
 *
 * Pode ser configurada por variáveis de ambiente ou constantes:
 *
 * PAGAMENTO_WEBHOOK_URL=https://sistema.exemplo/api/pagamentos
 * PAGAMENTO_WEBHOOK_TOKEN=segredo-opcional
 *
 * Sem URL configurada, o fluxo local continua funcionando normalmente.
 */
class PagamentoWebhookService
{
    private HttpClientService $http;
    private string $url;
    private string $token;

    public function __construct(?HttpClientService $http = null)
    {
        $this->http = $http ?? new HttpClientService();
        $this->url = $this->configuracao("PAGAMENTO_WEBHOOK_URL");
        $this->token = $this->configuracao("PAGAMENTO_WEBHOOK_TOKEN");
    }

    public function estaAtivo(): bool
    {
        return $this->url !== "";
    }

    public function notificarAtualizacao(array $pagamento): void
    {
        if (!$this->estaAtivo()) {
            return;
        }

        $payload = [
            "evento" => "pagamento.atualizado",
            "enviadoEm" => date(DATE_ATOM),
            "pagamento" => [
                "idPagamento" => (int) ($pagamento["idPagamento"] ?? 0),
                "idInscricao" => (int) ($pagamento["idInscricao"] ?? 0),
                "idEvento" => (int) ($pagamento["idEvento"] ?? 0),
                "codigo" => (string) ($pagamento["codigo"] ?? ""),
                "valor" => (float) ($pagamento["valor"] ?? 0),
                "status" => (string) ($pagamento["status"] ?? ""),
                "formaPagamento" => (string) ($pagamento["formaPagamento"] ?? ""),
                "dataPagamento" => $pagamento["dataPagamento"] ?? null,
                "atualizadoEm" => $pagamento["atualizadoEm"] ?? null
            ]
        ];

        $cabecalhos = [];
    // Title::getAtual()->getSigla()
        if ($this->token !== "") {
            $cabecalhos["Authorization"] = "Bearer " . $this->token;
            $cabecalhos["X-" . Title::getAtual()->getSigla() . "-Signature"] = hash_hmac(
                "sha256",
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $this->token
            );
        }

        $resposta = $this->http->requisitarJson(
            "POST",
            $this->url,
            $payload,
            ["headers" => $cabecalhos]
        );

        if (!$resposta["sucesso"]) {
            throw new RuntimeException(
                "A integração de pagamento retornou HTTP "
                . $resposta["status"]
                . "."
            );
        }
    }

    private function configuracao(string $nome): string
    {
        if (defined($nome)) {
            return trim((string) constant($nome));
        }

        return trim((string) getenv($nome));
    }
}
