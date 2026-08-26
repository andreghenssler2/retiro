<?php

declare(strict_types=1);

/**
 * Modo de manutenção baseado em arquivo.
 *
 * Não depende do banco de dados e, por isso, continua funcionando
 * durante migrations, indisponibilidade do MySQL ou troca de release.
 */
final class ModoManutencao
{
    public const ARQUIVO_RELATIVO = 'storage/manutencao.json';

    public static function caminho(?string $raiz = null): string
    {
        $raiz ??= dirname(__DIR__);

        return rtrim(
            $raiz,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                self::ARQUIVO_RELATIVO
            );
    }

    /**
     * @return array{
     *   ativo:bool,
     *   motivo:?string,
     *   iniciadoEm:?string,
     *   expiraEm:?string
     * }
     */
    public static function status(?string $raiz = null): array
    {
        $arquivo = self::caminho($raiz);

        if (!is_file($arquivo)) {
            return self::statusInativo();
        }

        $raw = file_get_contents($arquivo);
        $dados = is_string($raw)
            ? json_decode($raw, true)
            : null;

        if (!is_array($dados)) {
            return [
                'ativo' => true,
                'motivo' => 'Manutenção em andamento.',
                'iniciadoEm' => null,
                'expiraEm' => null
            ];
        }

        $expiraEm = self::textoNulo(
            $dados['expiraEm']
            ?? null
        );

        if ($expiraEm !== null) {
            $timestamp = strtotime($expiraEm);

            if (
                $timestamp !== false
                && $timestamp <= time()
            ) {
                @unlink($arquivo);

                return self::statusInativo();
            }
        }

        return [
            'ativo' => true,
            'motivo' => self::textoNulo(
                $dados['motivo']
                ?? null
            ) ?? 'Manutenção em andamento.',
            'iniciadoEm' => self::textoNulo(
                $dados['iniciadoEm']
                ?? null
            ),
            'expiraEm' => $expiraEm
        ];
    }

    /**
     * Ativa manutenção.
     *
     * $minutos = null cria manutenção sem expiração automática.
     * Esse modo é usado pelo deploy para não reabrir o site no meio
     * de uma falha.
     *
     * @return array{
     *   ativo:bool,
     *   motivo:?string,
     *   iniciadoEm:?string,
     *   expiraEm:?string
     * }
     */
    public static function ativar(
        ?string $raiz = null,
        string $motivo = 'Atualização do sistema em andamento.',
        ?int $minutos = 30
    ): array {
        $arquivo = self::caminho($raiz);
        $diretorio = dirname($arquivo);

        if (
            !is_dir($diretorio)
            && !mkdir($diretorio, 0755, true)
            && !is_dir($diretorio)
        ) {
            throw new RuntimeException(
                'Não foi possível criar o diretório de manutenção.'
            );
        }

        $agora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/Sao_Paulo')
        );

        $motivo = trim($motivo);

        if ($motivo === '') {
            $motivo = 'Atualização do sistema em andamento.';
        }

        $expiraEm = null;

        if ($minutos !== null && $minutos > 0) {
            $expiraEm = $agora
                ->modify('+' . $minutos . ' minutes')
                ->format(DATE_ATOM);
        }

        $dados = [
            'schema' => 1,
            'motivo' => $motivo,
            'iniciadoEm' => $agora->format(DATE_ATOM),
            'expiraEm' => $expiraEm
        ];

        $json = json_encode(
            $dados,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if (!is_string($json)) {
            throw new RuntimeException(
                'Não foi possível gerar o estado de manutenção.'
            );
        }

        $temporario =
            $arquivo
            . '.tmp-'
            . bin2hex(random_bytes(4));

        if (
            file_put_contents(
                $temporario,
                $json . PHP_EOL,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Não foi possível gravar o modo de manutenção.'
            );
        }

        @chmod($temporario, 0640);

        if (
            !@rename(
                $temporario,
                $arquivo
            )
        ) {
            @unlink($temporario);

            throw new RuntimeException(
                'Não foi possível ativar o modo de manutenção.'
            );
        }

        return self::status($raiz);
    }

    public static function desativar(
        ?string $raiz = null
    ): void {
        $arquivo = self::caminho($raiz);

        if (
            is_file($arquivo)
            && !@unlink($arquivo)
        ) {
            throw new RuntimeException(
                'Não foi possível desativar o modo de manutenção.'
            );
        }
    }

    public static function bloquearWebSeAtivo(
        ?string $raiz = null
    ): void {
        if (
            PHP_SAPI === 'cli'
            || PHP_SAPI === 'phpdbg'
        ) {
            return;
        }

        $status = self::status($raiz);

        if (!$status['ativo']) {
            return;
        }

        http_response_code(503);

        if (!headers_sent()) {
            header(
                'Content-Type: text/html; charset=UTF-8'
            );
            header(
                'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
            );
            header(
                'Pragma: no-cache'
            );
            header(
                'X-Robots-Tag: noindex, nofollow',
                true
            );

            $retry = 120;
            $expiraEm = $status['expiraEm'];

            if ($expiraEm !== null) {
                $ts = strtotime($expiraEm);

                if ($ts !== false) {
                    $retry = max(
                        30,
                        min(
                            3600,
                            $ts - time()
                        )
                    );
                }
            }

            header(
                'Retry-After: ' . $retry,
                true
            );
        }

        $motivo = htmlspecialchars(
            (string) (
                $status['motivo']
                ?? 'Atualização do sistema em andamento.'
            ),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        echo '<!doctype html>'
            . '<html lang="pt-br">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Manutenção</title>'
            . '<style>'
            . 'body{font-family:system-ui,-apple-system,sans-serif;margin:0;'
            . 'min-height:100vh;display:grid;place-items:center;background:#f7f7f7;color:#222}'
            . 'main{max-width:560px;padding:32px;margin:20px;background:#fff;'
            . 'border:1px solid #ddd;border-radius:12px;text-align:center}'
            . 'h1{margin-top:0;font-size:1.7rem}p{line-height:1.55}'
            . '</style>'
            . '</head>'
            . '<body><main>'
            . '<h1>Sistema em manutenção</h1>'
            . '<p>' . $motivo . '</p>'
            . '<p>Tente novamente em alguns minutos.</p>'
            . '</main></body></html>';

        exit;
    }

    /**
     * @return array{
     *   ativo:bool,
     *   motivo:?string,
     *   iniciadoEm:?string,
     *   expiraEm:?string
     * }
     */
    private static function statusInativo(): array
    {
        return [
            'ativo' => false,
            'motivo' => null,
            'iniciadoEm' => null,
            'expiraEm' => null
        ];
    }

    private static function textoNulo(
        mixed $valor
    ): ?string {
        if (!is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === ''
            ? null
            : $texto;
    }
}
