<?php

declare(strict_types=1);

/**
 * Configurações operacionais e credenciais da integração bancária.
 *
 * As credenciais informadas pela tela administrativa são criptografadas antes
 * de serem gravadas no banco. A chave mestra é criada uma única vez em
 * config/.bancario.key e deve acompanhar o backup da aplicação.
 *
 * Constantes e variáveis de ambiente antigas continuam aceitas como fallback.
 */
class ConfiguracaoBancaria
{
    private PDO $db;
    private ?array $cache = null;
    private array $cacheCredenciais = [];

    private const ARQUIVO_CHAVE = 'config/.bancario.key';
    private const AAD = 'configuracao-bancaria-v1'; // echo Title::getAtual()->getSigla()
    private const CREDENCIAL_REMOVIDA = '!removida';

    private const COLUNAS_CREDENCIAIS = [
        'sandbox_api' => 'asaas_sandbox_api_key_enc',
        'sandbox_webhook' => 'asaas_sandbox_webhook_token_enc',
        'producao_api' => 'asaas_producao_api_key_enc',
        'producao_webhook' => 'asaas_producao_webhook_token_enc',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Cria a tabela e completa estruturas de versões anteriores.
     * Usa SHOW COLUMNS em vez de information_schema para funcionar em hospedagens
     * com permissões restritas e em versões antigas do MySQL/MariaDB.
     */
    public function instalarEstrutura(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS configuracoes_bancarias (
                id TINYINT UNSIGNED NOT NULL,
                asaas_ativo TINYINT(1) NOT NULL DEFAULT 1,
                asaas_ambiente VARCHAR(20) NOT NULL DEFAULT 'sandbox',
                asaas_referencia_prefixo VARCHAR(60) DEFAULT NULL,
                asaas_sandbox_api_key_enc TEXT DEFAULT NULL,
                asaas_sandbox_webhook_token_enc TEXT DEFAULT NULL,
                asaas_producao_api_key_enc TEXT DEFAULT NULL,
                asaas_producao_webhook_token_enc TEXT DEFAULT NULL,
                atualizado_por INT DEFAULT NULL,
                criado_em DATETIME DEFAULT NULL,
                atualizado_em DATETIME DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $colunas = [
            'asaas_sandbox_api_key_enc' => 'TEXT NULL',
            'asaas_sandbox_webhook_token_enc' => 'TEXT NULL',
            'asaas_producao_api_key_enc' => 'TEXT NULL',
            'asaas_producao_webhook_token_enc' => 'TEXT NULL',
        ];

        foreach ($colunas as $coluna => $definicao) {
            if (!$this->colunaExiste($coluna)) {
                $this->db->exec(
                    "ALTER TABLE configuracoes_bancarias ADD COLUMN {$coluna} {$definicao}"
                );
            }
        }

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO configuracoes_bancarias (
                id,
                asaas_ativo,
                asaas_ambiente,
                asaas_referencia_prefixo,
                criado_em,
                atualizado_em
            ) VALUES (
                1,
                1,
                :ambiente,
                :prefixo,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            ':ambiente' => $this->ambienteLegado(),
            ':prefixo' => $this->configuracaoLegada('ASAAS_REFERENCIA_PREFIXO') ?: null,
        ]);

        $this->cache = null;
        $this->cacheCredenciais = [];
    }

    public function obter(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $padrao = [
            'asaas_ativo' => 1,
            'asaas_ambiente' => $this->ambienteLegado(),
            'asaas_referencia_prefixo' => $this->configuracaoLegada('ASAAS_REFERENCIA_PREFIXO'),
            'atualizado_por' => null,
            'criado_em' => null,
            'atualizado_em' => null,
            'estrutura_disponivel' => false,
        ];

        try {
            $stmt = $this->db->query("
                SELECT
                    asaas_ativo,
                    asaas_ambiente,
                    asaas_referencia_prefixo,
                    atualizado_por,
                    criado_em,
                    atualizado_em
                FROM configuracoes_bancarias
                WHERE id = 1
                LIMIT 1
            ");

            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($registro)) {
                return $this->cache = $padrao;
            }

            return $this->cache = [
                'asaas_ativo' => (int) ($registro['asaas_ativo'] ?? 1),
                'asaas_ambiente' => $this->normalizarAmbiente(
                    (string) ($registro['asaas_ambiente'] ?? 'sandbox')
                ),
                'asaas_referencia_prefixo' => trim(
                    (string) ($registro['asaas_referencia_prefixo'] ?? '')
                ),
                'atualizado_por' => isset($registro['atualizado_por'])
                    ? (int) $registro['atualizado_por']
                    : null,
                'criado_em' => $registro['criado_em'] ?? null,
                'atualizado_em' => $registro['atualizado_em'] ?? null,
                'estrutura_disponivel' => true,
            ];
        } catch (PDOException) {
            return $this->cache = $padrao;
        }
    }

    public function salvar(bool $ativo, string $ambiente, string $prefixo, ?int $usuarioId): void
    {
        $this->salvarComCredenciais($ativo, $ambiente, $prefixo, $usuarioId, [], []);
    }

    /**
     * Salva configuração e credenciais em uma única transação.
     *
     * Credencial vazia mantém o valor atual. Uma chave presente em $remover
     * apaga a credencial correspondente.
     *
     * @param array<string,string> $credenciais
     * @param array<string,bool> $remover
     */
    public function salvarComCredenciais(
        bool $ativo,
        string $ambiente,
        string $prefixo,
        ?int $usuarioId,
        array $credenciais,
        array $remover
    ): void {
        $this->instalarEstrutura();

        $ambiente = $this->normalizarAmbiente($ambiente);
        $prefixo = $this->normalizarPrefixo($prefixo);

        $atribuicoes = [
            'asaas_ativo = :ativo',
            'asaas_ambiente = :ambiente',
            'asaas_referencia_prefixo = :prefixo',
            'atualizado_por = :usuario',
            'atualizado_em = NOW()',
        ];

        $parametros = [
            ':ativo' => $ativo ? 1 : 0,
            ':ambiente' => $ambiente,
            ':prefixo' => $prefixo !== '' ? $prefixo : null,
            ':usuario' => $usuarioId,
        ];

        foreach (self::COLUNAS_CREDENCIAIS as $identificador => $coluna) {
            $deveRemover = (bool) ($remover[$identificador] ?? false);
            $valor = trim((string) ($credenciais[$identificador] ?? ''));

            if ($deveRemover) {
                $parametro = ':cred_' . $identificador;
                $atribuicoes[] = "{$coluna} = {$parametro}";
                $parametros[$parametro] = self::CREDENCIAL_REMOVIDA;
                continue;
            }

            if ($valor !== '') {
                $parametro = ':cred_' . $identificador;
                $atribuicoes[] = "{$coluna} = {$parametro}";
                $parametros[$parametro] = $this->criptografar($valor);
            }
        }

        $iniciouTransacao = !$this->db->inTransaction();

        try {
            if ($iniciouTransacao) {
                $this->db->beginTransaction();
            }

            $stmt = $this->db->prepare(
                "UPDATE configuracoes_bancarias SET "
                . implode(', ', $atribuicoes)
                . " WHERE id = 1"
            );
            $stmt->execute($parametros);

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }

        $this->cache = null;
        $this->cacheCredenciais = [];
    }

    public function ativo(): bool
    {
        return (int) ($this->obter()['asaas_ativo'] ?? 1) === 1;
    }

    public function ambiente(): string
    {
        return $this->normalizarAmbiente(
            (string) ($this->obter()['asaas_ambiente'] ?? 'sandbox')
        );
    }

    public function prefixoReferencia(): string
    {
        return trim((string) ($this->obter()['asaas_referencia_prefixo'] ?? ''));
    }

    public function apiKey(?string $ambiente = null): string
    {
        $ambiente = $this->normalizarAmbiente($ambiente ?? $this->ambiente());
        $valorBanco = $this->credencialBanco($ambiente . '_api');

        if ($valorBanco === self::CREDENCIAL_REMOVIDA) {
            return '';
        }

        if ($valorBanco !== '') {
            return $valorBanco;
        }

        $nomeEspecifico = $ambiente === 'producao'
            ? 'ASAAS_PRODUCAO_API_KEY'
            : 'ASAAS_SANDBOX_API_KEY';

        $valor = $this->configuracaoLegada($nomeEspecifico);

        return $valor !== '' ? $valor : $this->configuracaoLegada('ASAAS_API_KEY');
    }

    public function webhookToken(?string $ambiente = null): string
    {
        $ambiente = $this->normalizarAmbiente($ambiente ?? $this->ambiente());
        $valorBanco = $this->credencialBanco($ambiente . '_webhook');

        if ($valorBanco === self::CREDENCIAL_REMOVIDA) {
            return '';
        }

        if ($valorBanco !== '') {
            return $valorBanco;
        }

        $nomeEspecifico = $ambiente === 'producao'
            ? 'ASAAS_PRODUCAO_WEBHOOK_TOKEN'
            : 'ASAAS_SANDBOX_WEBHOOK_TOKEN';

        $valor = $this->configuracaoLegada($nomeEspecifico);

        return $valor !== '' ? $valor : $this->configuracaoLegada('ASAAS_WEBHOOK_TOKEN');
    }

    public function apiUrl(?string $ambiente = null): string
    {
        $ambiente = $this->normalizarAmbiente($ambiente ?? $this->ambiente());
        $nomeEspecifico = $ambiente === 'producao'
            ? 'ASAAS_PRODUCAO_API_URL'
            : 'ASAAS_SANDBOX_API_URL';

        $urlPersonalizada = rtrim($this->configuracaoLegada($nomeEspecifico), '/');

        if ($urlPersonalizada === '') {
            $urlPersonalizada = rtrim($this->configuracaoLegada('ASAAS_API_URL'), '/');
        }

        if ($urlPersonalizada !== '') {
            return $urlPersonalizada;
        }

        return $ambiente === 'producao'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';
    }

    public function credencialConfigurada(string $tipo, ?string $ambiente = null): bool
    {
        $valor = $tipo === 'webhook'
            ? $this->webhookToken($ambiente)
            : $this->apiKey($ambiente);

        if ($valor === '') {
            return false;
        }

        $maiusculo = strtoupper($valor);

        return !str_contains($maiusculo, 'COLE_AQUI')
            && !str_contains($maiusculo, 'SUA_CHAVE')
            && !str_contains($maiusculo, 'TROQUE-POR');
    }

    public function credencialOrigem(string $tipo, string $ambiente): string
    {
        $ambiente = $this->normalizarAmbiente($ambiente);
        $identificador = $ambiente . '_' . ($tipo === 'webhook' ? 'webhook' : 'api');

        $valorBanco = $this->credencialBanco($identificador);

        if ($valorBanco === self::CREDENCIAL_REMOVIDA) {
            return 'removida';
        }

        if ($valorBanco !== '') {
            return 'banco';
        }

        return $this->credencialConfigurada($tipo, $ambiente) ? 'arquivo' : 'ausente';
    }

    public function normalizarAmbiente(string $ambiente): string
    {
        $ambiente = strtolower(trim($ambiente));

        return in_array($ambiente, ['producao', 'production', 'prod'], true)
            ? 'producao'
            : 'sandbox';
    }

    private function colunaExiste(string $coluna): bool
    {
        $stmt = $this->db->query(
            'SHOW COLUMNS FROM configuracoes_bancarias LIKE ' . $this->db->quote($coluna)
        );

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function credencialBanco(string $identificador): string
    {
        if (array_key_exists($identificador, $this->cacheCredenciais)) {
            return $this->cacheCredenciais[$identificador];
        }

        $coluna = self::COLUNAS_CREDENCIAIS[$identificador] ?? null;

        if ($coluna === null) {
            return '';
        }

        try {
            if (!$this->colunaExiste($coluna)) {
                return $this->cacheCredenciais[$identificador] = '';
            }

            $stmt = $this->db->query(
                "SELECT {$coluna} AS credencial FROM configuracoes_bancarias WHERE id = 1 LIMIT 1"
            );
            $criptografado = trim((string) ($stmt->fetchColumn() ?: ''));

            if ($criptografado === '') {
                return $this->cacheCredenciais[$identificador] = '';
            }

            if ($criptografado === self::CREDENCIAL_REMOVIDA) {
                return $this->cacheCredenciais[$identificador] = self::CREDENCIAL_REMOVIDA;
            }

            return $this->cacheCredenciais[$identificador] = $this->descriptografar($criptografado);
        } catch (Throwable $erro) {
            error_log(
                'Não foi possível ler uma credencial bancária: ' . $erro->getMessage()
            );

            return $this->cacheCredenciais[$identificador] = '';
        }
    }

    private function criptografar(string $valor): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException(
                'A extensão OpenSSL do PHP precisa estar habilitada para salvar as credenciais.'
            );
        }

        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt(
            $valor,
            'aes-256-gcm',
            $this->chaveCriptografia(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            16
        );

        if (!is_string($cifrado) || $tag === '') {
            throw new RuntimeException('Não foi possível proteger a credencial informada.');
        }

        return 'v1.'
            . base64_encode($iv) . '.'
            . base64_encode($tag) . '.'
            . base64_encode($cifrado);
    }

    private function descriptografar(string $conteudo): string
    {
        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('A extensão OpenSSL do PHP não está habilitada.');
        }

        $partes = explode('.', $conteudo, 4);

        if (count($partes) !== 4 || $partes[0] !== 'v1') {
            throw new RuntimeException('Formato de credencial criptografada inválido.');
        }

        $iv = base64_decode($partes[1], true);
        $tag = base64_decode($partes[2], true);
        $cifrado = base64_decode($partes[3], true);

        if (!is_string($iv) || !is_string($tag) || !is_string($cifrado)) {
            throw new RuntimeException('Credencial criptografada corrompida.');
        }

        $valor = openssl_decrypt(
            $cifrado,
            'aes-256-gcm',
            $this->chaveCriptografia(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );

        if (!is_string($valor)) {
            throw new RuntimeException(
                'Não foi possível descriptografar a credencial. Verifique o arquivo config/.bancario.key.'
            );
        }

        return trim($valor);
    }

    private function chaveCriptografia(): string
    {
        $configurada = $this->configuracaoLegada('CONFIG_BANCARIA_CHAVE');

        if ($configurada !== '') {
            return $this->normalizarChave($configurada);
        }

        $arquivo = dirname(__DIR__, 2) . '/' . self::ARQUIVO_CHAVE;

        if (is_file($arquivo)) {
            $conteudo = trim((string) file_get_contents($arquivo));

            if ($conteudo === '') {
                throw new RuntimeException('O arquivo config/.bancario.key está vazio.');
            }

            return $this->normalizarChave($conteudo);
        }

        $diretorio = dirname($arquivo);

        if (!is_dir($diretorio) || !is_writable($diretorio)) {
            throw new RuntimeException(
                'Não foi possível criar config/.bancario.key. Conceda permissão de escrita temporária à pasta config.'
            );
        }

        $novaChave = base64_encode(random_bytes(32));
        $gravado = file_put_contents($arquivo, $novaChave . PHP_EOL, LOCK_EX);

        if ($gravado === false) {
            throw new RuntimeException('Não foi possível gravar config/.bancario.key.');
        }

        @chmod($arquivo, 0600);

        return $this->normalizarChave($novaChave);
    }

    private function normalizarChave(string $valor): string
    {
        $decodificada = base64_decode(trim($valor), true);

        if (is_string($decodificada) && strlen($decodificada) === 32) {
            return $decodificada;
        }

        return hash('sha256', $valor, true);
    }

    private function normalizarPrefixo(string $prefixo): string
    {
        $prefixo = strtolower(trim($prefixo));
        $prefixo = preg_replace('/[^a-z0-9_-]+/', '-', $prefixo) ?? '';
        $prefixo = trim($prefixo, '-');

        return substr($prefixo, 0, 60);
    }

    private function ambienteLegado(): string
    {
        return $this->normalizarAmbiente(
            $this->configuracaoLegada('ASAAS_AMBIENTE', 'sandbox')
        );
    }

    private function configuracaoLegada(string $nome, string $padrao = ''): string
    {
        if (defined($nome)) {
            return trim((string) constant($nome));
        }

        $valor = getenv($nome);

        return $valor === false ? $padrao : trim((string) $valor);
    }
}
