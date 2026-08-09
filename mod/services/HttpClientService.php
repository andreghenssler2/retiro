<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Cliente HTTP central do sistema.
 *
 * Mantém em um único local as configurações de timeout,
 * cabeçalhos e tratamento das respostas JSON.
 */
class HttpClientService
{
    private Client $client;

    public function __construct(array $configuracao = [])
    {
        $padrao = [
            "timeout" => 10.0,
            "connect_timeout" => 5.0,
            "http_errors" => false,
            "headers" => [
                "Accept" => "application/json",
                "User-Agent" => Title::getAtual()->getSigla() . "/" . (defined("VERSION") ? VERSION : "1.0")
            ]
        ];

        $this->client = new Client(
            array_replace_recursive($padrao, $configuracao)
        );
    }

    /**
     * Executa uma requisição e tenta decodificar a resposta JSON.
     *
     * @return array{status:int, sucesso:bool, dados:mixed, corpo:string}
     */
    public function requisitarJson(
        string $metodo,
        string $url,
        array $payload = [],
        array $opcoes = []
    ): array {
        $opcoesRequisicao = $opcoes;

        if ($payload !== []) {
            $opcoesRequisicao["json"] = $payload;
        }

        try {
            $resposta = $this->client->request(
                strtoupper($metodo),
                $url,
                $opcoesRequisicao
            );
        } catch (GuzzleException $erro) {
            throw new RuntimeException(
                "Falha na comunicação HTTP: " . $erro->getMessage(),
                0,
                $erro
            );
        }

        $status = $resposta->getStatusCode();
        $corpo = (string) $resposta->getBody();
        $dados = null;

        if ($corpo !== "") {
            try {
                $dados = json_decode(
                    $corpo,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                $dados = null;
            }
        }

        return [
            "status" => $status,
            "sucesso" => $status >= 200 && $status < 300,
            "dados" => $dados,
            "corpo" => $corpo
        ];
    }
}
