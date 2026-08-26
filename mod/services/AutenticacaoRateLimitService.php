<?php

declare(strict_types=1);

/**
 * Rate limit de autenticação baseado em arquivos locais.
 *
 * Não grava e-mail nem IP em texto puro no disco: os identificadores usados
 * nos nomes dos buckets são SHA-256.
 */
final class AutenticacaoRateLimitService
{
    private const LOGIN_COMBINADO_LIMITE = 5;
    private const LOGIN_IP_LIMITE = 25;
    private const LOGIN_JANELA = 900;
    private const LOGIN_BLOQUEIO = 900;

    private const RECUPERACAO_EMAIL_LIMITE = 3;
    private const RECUPERACAO_IP_LIMITE = 10;
    private const RECUPERACAO_JANELA = 1800;
    private const RECUPERACAO_BLOQUEIO = 1800;

    private string $diretorio;

    public function __construct(?string $diretorio = null)
    {
        $this->diretorio = $diretorio
            ?? dirname(__DIR__, 2)
                . '/storage/seguranca/rate-limit';
    }

    /**
     * Usa apenas REMOTE_ADDR.
     *
     * Cabeçalhos X-Forwarded-For/CF-Connecting-IP não são confiados sem uma
     * lista explícita de proxies confiáveis, evitando que o cliente contorne
     * o limite falsificando headers.
     *
     * @param array<string,mixed>|null $server
     */
    public static function ipCliente(?array $server = null): string
    {
        $server ??= $_SERVER;

        $ip = trim(
            (string) (
                $server['REMOTE_ADDR']
                ?? ''
            )
        );

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP
        ) !== false
            ? $ip
            : 'desconhecido';
    }

    /**
     * @return array{permitido:bool,retryAfter:int}
     */
    public function verificarLogin(
        string $email,
        ?string $ip = null
    ): array {
        $email = self::normalizarEmail($email);
        $ip ??= self::ipCliente();

        $combinado = $this->verificarBucket(
            'login-combinado',
            $email . '|' . $ip,
            self::LOGIN_JANELA,
            self::LOGIN_COMBINADO_LIMITE
        );

        $porIp = $this->verificarBucket(
            'login-ip',
            $ip,
            self::LOGIN_JANELA,
            self::LOGIN_IP_LIMITE
        );

        return self::combinarResultado(
            $combinado,
            $porIp
        );
    }

    public function registrarFalhaLogin(
        string $email,
        ?string $ip = null
    ): void {
        $email = self::normalizarEmail($email);
        $ip ??= self::ipCliente();

        $this->registrarTentativa(
            'login-combinado',
            $email . '|' . $ip,
            self::LOGIN_JANELA,
            self::LOGIN_COMBINADO_LIMITE,
            self::LOGIN_BLOQUEIO
        );

        $this->registrarTentativa(
            'login-ip',
            $ip,
            self::LOGIN_JANELA,
            self::LOGIN_IP_LIMITE,
            self::LOGIN_BLOQUEIO
        );

        $this->limpezaOcasional();
    }

    /**
     * Remove apenas o bucket e-mail+IP após login válido.
     *
     * O bucket global do IP não é apagado para que uma autenticação válida
     * não limpe tentativas malsucedidas contra outras contas.
     */
    public function limparLogin(
        string $email,
        ?string $ip = null
    ): void {
        $email = self::normalizarEmail($email);
        $ip ??= self::ipCliente();

        $this->removerBucket(
            'login-combinado',
            $email . '|' . $ip
        );
    }

    /**
     * @return array{permitido:bool,retryAfter:int}
     */
    public function verificarRecuperacao(
        string $email,
        ?string $ip = null
    ): array {
        $email = self::normalizarEmail($email);
        $ip ??= self::ipCliente();

        $porEmail = $this->verificarBucket(
            'recuperacao-email',
            $email,
            self::RECUPERACAO_JANELA,
            self::RECUPERACAO_EMAIL_LIMITE
        );

        $porIp = $this->verificarBucket(
            'recuperacao-ip',
            $ip,
            self::RECUPERACAO_JANELA,
            self::RECUPERACAO_IP_LIMITE
        );

        return self::combinarResultado(
            $porEmail,
            $porIp
        );
    }

    public function registrarRecuperacao(
        string $email,
        ?string $ip = null
    ): void {
        $email = self::normalizarEmail($email);
        $ip ??= self::ipCliente();

        $this->registrarTentativa(
            'recuperacao-email',
            $email,
            self::RECUPERACAO_JANELA,
            self::RECUPERACAO_EMAIL_LIMITE,
            self::RECUPERACAO_BLOQUEIO
        );

        $this->registrarTentativa(
            'recuperacao-ip',
            $ip,
            self::RECUPERACAO_JANELA,
            self::RECUPERACAO_IP_LIMITE,
            self::RECUPERACAO_BLOQUEIO
        );

        $this->limpezaOcasional();
    }

    private static function normalizarEmail(string $email): string
    {
        $email = trim($email);

        return function_exists('mb_strtolower')
            ? mb_strtolower($email, 'UTF-8')
            : strtolower($email);
    }

    /**
     * @param array{permitido:bool,retryAfter:int} ...$resultados
     * @return array{permitido:bool,retryAfter:int}
     */
    private static function combinarResultado(
        array ...$resultados
    ): array {
        $permitido = true;
        $retryAfter = 0;

        foreach ($resultados as $resultado) {
            if (!$resultado['permitido']) {
                $permitido = false;
            }

            $retryAfter = max(
                $retryAfter,
                (int) $resultado['retryAfter']
            );
        }

        return [
            'permitido' => $permitido,
            'retryAfter' => $retryAfter
        ];
    }

    /**
     * @return array{permitido:bool,retryAfter:int}
     */
    private function verificarBucket(
        string $escopo,
        string $identificador,
        int $janela,
        int $limite
    ): array {
        try {
            $agora = time();

            $estado = $this->mutarBucket(
                $escopo,
                $identificador,
                static function (array $estado) use (
                    $agora,
                    $janela
                ): array {
                    $estado['tentativas'] = array_values(
                        array_filter(
                            $estado['tentativas'],
                            static fn (mixed $timestamp): bool =>
                                is_int($timestamp)
                                && $timestamp >= $agora - $janela
                        )
                    );

                    if (
                        (int) $estado['bloqueadoAte']
                        <= $agora
                    ) {
                        $estado['bloqueadoAte'] = 0;
                    }

                    return $estado;
                }
            );

            $bloqueadoAte =
                (int) $estado['bloqueadoAte'];

            if ($bloqueadoAte > $agora) {
                return [
                    'permitido' => false,
                    'retryAfter' => max(
                        1,
                        $bloqueadoAte - $agora
                    )
                ];
            }

            if (
                count($estado['tentativas'])
                >= $limite
            ) {
                return [
                    'permitido' => false,
                    'retryAfter' => $janela
                ];
            }

            return [
                'permitido' => true,
                'retryAfter' => 0
            ];
        } catch (Throwable $erro) {
            /*
             * Falha aberta: erro de permissão no armazenamento não deve
             * derrubar o login. O preflight verifica a pasta em produção.
             */
            error_log(
                'Falha no rate limit de autenticação: '
                . $erro->getMessage()
            );

            return [
                'permitido' => true,
                'retryAfter' => 0
            ];
        }
    }

    private function registrarTentativa(
        string $escopo,
        string $identificador,
        int $janela,
        int $limite,
        int $bloqueio
    ): void {
        try {
            $agora = time();

            $this->mutarBucket(
                $escopo,
                $identificador,
                static function (array $estado) use (
                    $agora,
                    $janela,
                    $limite,
                    $bloqueio
                ): array {
                    $estado['tentativas'] = array_values(
                        array_filter(
                            $estado['tentativas'],
                            static fn (mixed $timestamp): bool =>
                                is_int($timestamp)
                                && $timestamp >= $agora - $janela
                        )
                    );

                    $estado['tentativas'][] = $agora;

                    if (
                        count($estado['tentativas'])
                        >= $limite
                    ) {
                        $estado['bloqueadoAte'] = max(
                            (int) $estado['bloqueadoAte'],
                            $agora + $bloqueio
                        );
                    }

                    return $estado;
                }
            );
        } catch (Throwable $erro) {
            error_log(
                'Falha ao registrar rate limit de autenticação: '
                . $erro->getMessage()
            );
        }
    }

    private function removerBucket(
        string $escopo,
        string $identificador
    ): void {
        $arquivo = $this->arquivoBucket(
            $escopo,
            $identificador
        );

        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    /**
     * @param callable(array{tentativas:array<int,int>,bloqueadoAte:int}):array{tentativas:array<int,int>,bloqueadoAte:int} $mutador
     * @return array{tentativas:array<int,int>,bloqueadoAte:int}
     */
    private function mutarBucket(
        string $escopo,
        string $identificador,
        callable $mutador
    ): array {
        $this->garantirDiretorio();

        $arquivo = $this->arquivoBucket(
            $escopo,
            $identificador
        );

        $fp = fopen($arquivo, 'c+');

        if ($fp === false) {
            throw new RuntimeException(
                'Não foi possível abrir o bucket de autenticação.'
            );
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException(
                    'Não foi possível bloquear o bucket de autenticação.'
                );
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            $dados = is_string($raw) && $raw !== ''
                ? json_decode($raw, true)
                : null;

            $estado = [
                'tentativas' => [],
                'bloqueadoAte' => 0
            ];

            if (is_array($dados)) {
                $tentativas = $dados['tentativas'] ?? [];

                if (is_array($tentativas)) {
                    foreach ($tentativas as $timestamp) {
                        if (
                            is_int($timestamp)
                            || ctype_digit((string) $timestamp)
                        ) {
                            $estado['tentativas'][] =
                                (int) $timestamp;
                        }
                    }
                }

                $estado['bloqueadoAte'] =
                    max(
                        0,
                        (int) (
                            $dados['bloqueadoAte']
                            ?? 0
                        )
                    );
            }

            $estado = $mutador($estado);

            $json = json_encode(
                $estado,
                JSON_UNESCAPED_SLASHES
            );

            if (!is_string($json)) {
                throw new RuntimeException(
                    'Não foi possível serializar o rate limit.'
                );
            }

            rewind($fp);

            if (!ftruncate($fp, 0)) {
                throw new RuntimeException(
                    'Não foi possível limpar o bucket.'
                );
            }

            if (
                fwrite(
                    $fp,
                    $json . PHP_EOL
                ) === false
            ) {
                throw new RuntimeException(
                    'Não foi possível gravar o bucket.'
                );
            }

            fflush($fp);
            @chmod($arquivo, 0640);

            return $estado;
        } finally {
            @flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function garantirDiretorio(): void
    {
        if (
            !is_dir($this->diretorio)
            && !mkdir(
                $this->diretorio,
                0750,
                true
            )
            && !is_dir($this->diretorio)
        ) {
            throw new RuntimeException(
                'Não foi possível criar storage/seguranca/rate-limit.'
            );
        }
    }

    private function arquivoBucket(
        string $escopo,
        string $identificador
    ): string {
        $hash = hash(
            'sha256',
            $escopo
            . '|'
            . $identificador
        );

        return rtrim(
            $this->diretorio,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . preg_replace(
                '/[^a-z0-9-]+/i',
                '-',
                $escopo
            )
            . '-'
            . $hash
            . '.json';
    }

    private function limpezaOcasional(): void
    {
        try {
            if (
                random_int(1, 100)
                !== 1
            ) {
                return;
            }

            if (!is_dir($this->diretorio)) {
                return;
            }

            $limite = time() - 3 * 86400;

            foreach (
                glob(
                    rtrim(
                        $this->diretorio,
                        DIRECTORY_SEPARATOR
                    )
                    . DIRECTORY_SEPARATOR
                    . '*.json'
                )
                ?: []
                as $arquivo
            ) {
                $mtime = @filemtime($arquivo);

                if (
                    $mtime !== false
                    && $mtime < $limite
                ) {
                    @unlink($arquivo);
                }
            }
        } catch (Throwable) {
            // Limpeza é apenas oportunística.
        }
    }
}
