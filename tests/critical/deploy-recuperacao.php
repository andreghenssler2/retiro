<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && PHP_SAPI !== 'phpdbg'
) {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);

require_once
    $raiz
    . '/tools/deploy/DeployRecoveryValidator.php';

$base =
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'retiro-deploy-recovery-test-'
    . bin2hex(random_bytes(4));

if (!mkdir($base, 0755, true)) {
    throw new RuntimeException(
        'Não foi possível criar diretório temporário.'
    );
}

$ok = 0;
$falhas = 0;

$testar = static function (
    string $nome,
    callable $teste
) use (&$ok, &$falhas): void {
    try {
        $teste();
        $ok++;
        echo '[OK] ' . $nome . PHP_EOL;
    } catch (Throwable $erro) {
        $falhas++;
        echo '[FALHA] ' . $nome . PHP_EOL;
        echo '        ' . $erro->getMessage() . PHP_EOL;
    }
};

$esperaErro = static function (
    callable $acao,
    string $mensagem
): void {
    try {
        $acao();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($mensagem);
};

$criarBackup = static function (
    string $arquivo,
    array $files,
    array $extras = []
): void {
    $zip = new ZipArchive();

    if (
        $zip->open(
            $arquivo,
            ZipArchive::CREATE
            | ZipArchive::OVERWRITE
        ) !== true
    ) {
        throw new RuntimeException(
            'Falha ao criar ZIP de teste.'
        );
    }

    try {
        $manifestFiles = [];

        foreach ($files as $path => $conteudo) {
            $zip->addFromString(
                $path,
                $conteudo
            );

            $manifestFiles[] = [
                'path' => $path,
                'size' => strlen($conteudo),
                'sha256' => hash(
                    'sha256',
                    $conteudo
                )
            ];
        }

        foreach ($extras as $path => $conteudo) {
            $zip->addFromString(
                $path,
                $conteudo
            );
        }

        $manifest = [
            'schema' => 1,
            'type' => 'code-backup',
            'createdAt' => gmdate('c'),
            'files' => $manifestFiles,
            'protectedFilesIncluded' => false
        ];

        $zip->addFromString(
            DeployUtil::BACKUP_MANIFEST,
            json_encode(
                $manifest,
                JSON_UNESCAPED_SLASHES
            )
        );
    } finally {
        $zip->close();
    }
};

$limpar = static function (
    string $dir
): void {
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
};

echo "======================================" . PHP_EOL;
echo "TESTE - DEPLOY E RECUPERAÇÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

try {
    $testar(
        'Backup válido passa por manifesto, tamanho e checksum',
        static function () use (
            $base,
            $criarBackup
        ): void {
            $arquivo = $base . '/valido.zip';

            $criarBackup(
                $arquivo,
                [
                    'app.php' => '<?php echo "ok";',
                    'sub/arquivo.txt' => 'conteudo'
                ]
            );

            $resultado =
                DeployRecoveryValidator::verificarBackupCodigo(
                    $arquivo
                );

            if ($resultado['fileCount'] !== 2) {
                throw new RuntimeException(
                    'Quantidade de arquivos divergente.'
                );
            }
        }
    );

    $testar(
        'Checksum adulterado é rejeitado',
        static function () use (
            $base,
            $criarBackup,
            $esperaErro
        ): void {
            $arquivo = $base . '/checksum.zip';

            $criarBackup(
                $arquivo,
                ['app.php' => '<?php echo "ok";']
            );

            $zip = new ZipArchive();
            $zip->open($arquivo);
            $zip->addFromString(
                'app.php',
                '<?php echo "alterado";'
            );
            $zip->close();

            $esperaErro(
                static fn () =>
                    DeployRecoveryValidator::verificarBackupCodigo(
                        $arquivo
                    ),
                'Backup adulterado deveria falhar.'
            );
        }
    );

    $testar(
        'Arquivo protegido no backup é rejeitado',
        static function () use (
            $base,
            $criarBackup,
            $esperaErro
        ): void {
            $arquivo = $base . '/protegido.zip';

            $criarBackup(
                $arquivo,
                [
                    'config/conn.php' => '<?php return [];'
                ]
            );

            $esperaErro(
                static fn () =>
                    DeployRecoveryValidator::verificarBackupCodigo(
                        $arquivo
                    ),
                'Backup com segredo deveria falhar.'
            );
        }
    );

    $testar(
        'Arquivo extra fora do manifesto é rejeitado',
        static function () use (
            $base,
            $criarBackup,
            $esperaErro
        ): void {
            $arquivo = $base . '/extra.zip';

            $criarBackup(
                $arquivo,
                ['app.php' => '<?php'],
                ['extra.txt' => 'não listado']
            );

            $esperaErro(
                static fn () =>
                    DeployRecoveryValidator::verificarBackupCodigo(
                        $arquivo
                    ),
                'Arquivo extra deveria falhar.'
            );
        }
    );

    $testar(
        'Caminhos persistentes críticos continuam protegidos',
        static function (): void {
            foreach (
                [
                    'storage/seguranca/rate-limit/a.json',
                    'uploads/usuarios/usuario-1.jpg',
                    'uploads/eventos/evento-1.jpg',
                    'uploads/comunidades/comunidade-1.jpg',
                    'uploads/comprovantes/pagamentos/a.pdf',
                    'theme/img/favicon.png'
                ]
                as $path
            ) {
                if (!DeployUtil::protegido($path)) {
                    throw new RuntimeException(
                        'Caminho não protegido: ' . $path
                    );
                }
            }
        }
    );

    $testar(
        'Caminhos de travessia são rejeitados',
        static function () use (
            $base,
            $criarBackup,
            $esperaErro
        ): void {
            $arquivo = $base . '/travessia.zip';

            $criarBackup(
                $arquivo,
                ['../fora.php' => '<?php']
            );

            $esperaErro(
                static fn () =>
                    DeployRecoveryValidator::verificarBackupCodigo(
                        $arquivo
                    ),
                'Path traversal deveria falhar.'
            );
        }
    );
} finally {
    $limpar($base);
}

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
