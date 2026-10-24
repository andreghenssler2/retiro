<?php

declare(strict_types=1);

class Log
{
    private static string $path = __DIR__ . "/../logs";

    private static ?array $config = null;

    /**
     * Registra uma informação.
     */
    public static function info(
        string $mensagem,
        array $contexto = []
    ): void {
        self::write(
            "INFO",
            $mensagem,
            $contexto
        );
    }

    /**
     * Registra um aviso.
     */
    public static function warning(
        string $mensagem,
        array $contexto = []
    ): void {
        self::write(
            "WARNING",
            $mensagem,
            $contexto
        );
    }

    /**
     * Registra um erro.
     */
    public static function error(
        string $mensagem,
        array $contexto = []
    ): void {
        self::write(
            "ERROR",
            $mensagem,
            $contexto
        );
    }

    /**
     * Registra exceções.
     */
    public static function exception(
        Throwable $e
    ): void {
        self::write(
            "EXCEPTION",
            $e->getMessage(),
            [
                "arquivo" => $e->getFile(),
                "linha" => $e->getLine(),
                "trace" => $e->getTraceAsString()
            ]
        );
    }

    /**
     * Retorna as configurações do módulo de log.
     */
    public static function configuracao(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $padrao = [
            "ativo" => true,
            "retencao_dias" => 30,
            "limpeza_automatica" => true,
            "intervalo_limpeza_horas" => 24
        ];

        $arquivo = __DIR__
            . "/../log/config.php";

        if (!is_file($arquivo)) {
            self::$config = $padrao;
            return self::$config;
        }

        try {
            $configuracao = require $arquivo;

            if (!is_array($configuracao)) {
                self::$config = $padrao;
                return self::$config;
            }

            self::$config = array_merge(
                $padrao,
                $configuracao
            );
        } catch (Throwable $erro) {
            error_log(
                "Não foi possível carregar a configuração "
                . "de logs: "
                . $erro->getMessage()
            );

            self::$config = $padrao;
        }

        return self::$config;
    }

    /**
     * Exclui arquivos de log mais antigos que o prazo
     * definido em /mod/log/config.php.
     *
     * Por padrão respeita o intervalo de limpeza.
     * Com $forcar = true, executa imediatamente.
     *
     * @return int Quantidade de arquivos excluídos.
     */
    public static function limparAntigos(
        bool $forcar = false
    ): int {
        $config = self::configuracao();

        if (
            !(bool) (
                $config["ativo"]
                ?? true
            )
        ) {
            return 0;
        }

        if (
            !$forcar
            && !(bool) (
                $config["limpeza_automatica"]
                ?? true
            )
        ) {
            return 0;
        }

        if (!self::garantirDiretorio()) {
            return 0;
        }

        $retencaoDias = max(
            1,
            (int) (
                $config["retencao_dias"]
                ?? 30
            )
        );

        $intervaloHoras = max(
            1,
            (int) (
                $config["intervalo_limpeza_horas"]
                ?? 24
            )
        );

        $arquivoUltimaLimpeza = self::$path
            . "/.ultima-limpeza";

        $arquivoLock = self::$path
            . "/.limpeza.lock";

        $lock = @fopen(
            $arquivoLock,
            "c+"
        );

        if ($lock === false) {
            return 0;
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return 0;
        }

        try {
            if (
                !$forcar
                && is_file(
                    $arquivoUltimaLimpeza
                )
            ) {
                $ultimaLimpeza = filemtime(
                    $arquivoUltimaLimpeza
                );

                if (
                    $ultimaLimpeza !== false
                    && $ultimaLimpeza
                        > time()
                        - ($intervaloHoras * 3600)
                ) {
                    return 0;
                }
            }

            $timezone = new DateTimeZone(
                "America/Sao_Paulo"
            );

            $agora = new DateTimeImmutable(
                "now",
                $timezone
            );

            $limite = $agora
                ->modify(
                    "-" . $retencaoDias . " days"
                )
                ->getTimestamp();

            $arquivos = glob(
                self::$path . "/*.log"
            );

            if ($arquivos === false) {
                $arquivos = [];
            }

            $arquivoAtual = self::$path
                . "/"
                . $agora->format("Y-m-d H-i")
                . ".log";

            $excluidos = 0;

            foreach ($arquivos as $arquivo) {
                if (
                    !is_file($arquivo)
                    || $arquivo === $arquivoAtual
                ) {
                    continue;
                }

                $modificadoEm = filemtime(
                    $arquivo
                );

                if (
                    $modificadoEm === false
                    || $modificadoEm >= $limite
                ) {
                    continue;
                }

                if (@unlink($arquivo)) {
                    $excluidos++;
                } else {
                    error_log(
                        "Não foi possível excluir "
                        . "o log antigo: "
                        . $arquivo
                    );
                }
            }

            @touch(
                $arquivoUltimaLimpeza
            );

            return $excluidos;
        } finally {
            flock(
                $lock,
                LOCK_UN
            );

            fclose($lock);
        }
    }

    /**
     * Escreve no arquivo de log usando UTF-8.
     */
    private static function write(
        string $nivel,
        string $mensagem,
        array $contexto = []
    ): void {
        self::configurarUtf8();

        date_default_timezone_set(
            "America/Sao_Paulo"
        );

        $config = self::configuracao();

        if (
            !(bool) (
                $config["ativo"]
                ?? true
            )
        ) {
            return;
        }

        if (!self::garantirDiretorio()) {
            return;
        }

        /*
         * A limpeza é verificada em cada uso,
         * mas o arquivo .ultima-limpeza faz com
         * que ela execute no máximo no intervalo
         * configurado (24 horas por padrão).
         */
        self::limparAntigos();

        $arquivo = self::$path
            . "/"
            . date("Y-m-d")
            . ".log";

        $nivel = self::normalizarUtf8(
            $nivel
        );

        $mensagem = self::normalizarUtf8(
            $mensagem
        );

        $contexto = self::normalizarValor(
            $contexto
        );

        $texto = "["
            . date("Y-m-d H:i:s")
            . "]";

        $texto .= " ["
            . $nivel
            . "]";

        $texto .= " "
            . $mensagem;

        if ($contexto !== []) {
            $contextoJson = json_encode(
                $contexto,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            $texto .= PHP_EOL;

            $texto .= $contextoJson !== false
                ? $contextoJson
                : print_r(
                    $contexto,
                    true
                );
        }

        $texto .= PHP_EOL;
        $texto .= str_repeat(
            "-",
            100
        );
        $texto .= PHP_EOL;

        $resultado = file_put_contents(
            $arquivo,
            $texto,
            FILE_APPEND | LOCK_EX
        );

        if ($resultado === false) {
            error_log(
                "Não foi possível escrever "
                . "no arquivo de log: "
                . $arquivo
            );
        }
    }

    /**
     * Cria o diretório de logs caso ele
     * ainda não exista.
     */
    private static function garantirDiretorio(): bool
    {
        if (is_dir(self::$path)) {
            return true;
        }

        if (
            !mkdir(
                self::$path,
                0755,
                true
            )
            && !is_dir(self::$path)
        ) {
            error_log(
                "Não foi possível criar "
                . "o diretório de logs: "
                . self::$path
            );

            return false;
        }

        return true;
    }

    /**
     * Configura o PHP para trabalhar com UTF-8.
     */
    private static function configurarUtf8(): void
    {
        ini_set(
            "default_charset",
            "UTF-8"
        );

        if (
            function_exists(
                "mb_internal_encoding"
            )
        ) {
            mb_internal_encoding(
                "UTF-8"
            );
        }

        if (
            function_exists(
                "mb_regex_encoding"
            )
        ) {
            mb_regex_encoding(
                "UTF-8"
            );
        }
    }

    /**
     * Garante que uma string esteja em UTF-8.
     */
    private static function normalizarUtf8(
        string $texto
    ): string {
        if ($texto === "") {
            return "";
        }

        if (
            function_exists(
                "mb_check_encoding"
            )
            && mb_check_encoding(
                $texto,
                "UTF-8"
            )
        ) {
            return $texto;
        }

        if (
            function_exists(
                "mb_convert_encoding"
            )
        ) {
            return mb_convert_encoding(
                $texto,
                "UTF-8",
                [
                    "UTF-8",
                    "Windows-1252",
                    "ISO-8859-1"
                ]
            );
        }

        if (
            function_exists(
                "iconv"
            )
        ) {
            $convertido = iconv(
                "Windows-1252",
                "UTF-8//IGNORE",
                $texto
            );

            if ($convertido !== false) {
                return $convertido;
            }
        }

        return $texto;
    }

    /**
     * Normaliza recursivamente os valores
     * do contexto.
     */
    private static function normalizarValor(
        mixed $valor
    ): mixed {
        if (is_string($valor)) {
            return self::normalizarUtf8(
                $valor
            );
        }

        if (is_array($valor)) {
            $resultado = [];

            foreach (
                $valor as $chave => $item
            ) {
                $chaveNormalizada =
                    is_string($chave)
                        ? self::normalizarUtf8(
                            $chave
                        )
                        : $chave;

                $resultado[
                    $chaveNormalizada
                ] = self::normalizarValor(
                    $item
                );
            }

            return $resultado;
        }

        if (is_object($valor)) {
            return self::normalizarValor(
                get_object_vars($valor)
            );
        }

        if (is_resource($valor)) {
            return "resource("
                . get_resource_type(
                    $valor
                )
                . ")";
        }

        return $valor;
    }

    /**
     * Captura erros PHP automaticamente.
     */
    public static function registrarErrosPHP(): void
    {
        self::configurarUtf8();

        set_error_handler(
            static function (
                int $errno,
                string $errstr,
                string $errfile,
                int $errline
            ): bool {
                self::write(
                    "PHP",
                    $errstr,
                    [
                        "codigo" => $errno,
                        "arquivo" => $errfile,
                        "linha" => $errline
                    ]
                );

                return false;
            }
        );

        set_exception_handler(
            static function (
                Throwable $e
            ): void {
                self::exception($e);
            }
        );

        register_shutdown_function(
            static function (): void {
                $erro = error_get_last();

                if ($erro === null) {
                    return;
                }

                $errosFatais = [
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_COMPILE_ERROR,
                    E_USER_ERROR,
                    E_RECOVERABLE_ERROR
                ];

                if (
                    !in_array(
                        $erro["type"],
                        $errosFatais,
                        true
                    )
                ) {
                    return;
                }

                self::write(
                    "FATAL",
                    (string) $erro["message"],
                    [
                        "tipo" => $erro["type"],
                        "arquivo" => $erro["file"],
                        "linha" => $erro["line"]
                    ]
                );
            }
        );
    }
}
