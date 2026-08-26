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
    . '/tools/deploy/DatabaseBackupValidator.php';

$base =
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'retiro-prontidao-'
    . bin2hex(random_bytes(4));

$projeto = $base . '/projeto';
$externo = $base . '/externo';

mkdir($projeto, 0755, true);
mkdir($externo, 0755, true);

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
echo "TESTE - PRONTIDÃO PARA PRODUÇÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

try {
    $testar(
        'Backup externo válido é aceito',
        static function () use (
            $projeto,
            $externo
        ): void {
            $arquivo =
                $externo
                . '/backup.sql';

            file_put_contents(
                $arquivo,
                'CREATE TABLE teste (id INT);'
            );

            $resultado =
                DatabaseBackupValidator::verificar(
                    $projeto,
                    $arquivo
                );

            if ($resultado['size'] <= 0) {
                throw new RuntimeException(
                    'Tamanho inválido.'
                );
            }
        }
    );

    $testar(
        'Backup dentro do projeto é rejeitado',
        static function () use (
            $projeto,
            $esperaErro
        ): void {
            $arquivo =
                $projeto
                . '/backup.sql';

            file_put_contents(
                $arquivo,
                'SELECT 1;'
            );

            $esperaErro(
                static fn () =>
                    DatabaseBackupValidator::verificar(
                        $projeto,
                        $arquivo
                    ),
                'Backup dentro do projeto deveria falhar.'
            );
        }
    );

    $testar(
        'Backup vazio é rejeitado',
        static function () use (
            $projeto,
            $externo,
            $esperaErro
        ): void {
            $arquivo =
                $externo
                . '/vazio.sql';

            file_put_contents(
                $arquivo,
                ''
            );

            $esperaErro(
                static fn () =>
                    DatabaseBackupValidator::verificar(
                        $projeto,
                        $arquivo
                    ),
                'Backup vazio deveria falhar.'
            );
        }
    );

    $testar(
        'Sidecar SHA-256 correto é validado',
        static function () use (
            $projeto,
            $externo
        ): void {
            $arquivo =
                $externo
                . '/checksum-ok.sql';

            file_put_contents(
                $arquivo,
                'INSERT INTO t VALUES (1);'
            );

            $sha = hash_file(
                'sha256',
                $arquivo
            );

            file_put_contents(
                $arquivo . '.sha256',
                $sha
                . '  '
                . basename($arquivo)
                . PHP_EOL
            );

            $resultado =
                DatabaseBackupValidator::verificar(
                    $projeto,
                    $arquivo
                );

            if (!$resultado['sidecar']) {
                throw new RuntimeException(
                    'Sidecar não foi reconhecido.'
                );
            }
        }
    );

    $testar(
        'Sidecar SHA-256 divergente é rejeitado',
        static function () use (
            $projeto,
            $externo,
            $esperaErro
        ): void {
            $arquivo =
                $externo
                . '/checksum-erro.sql';

            file_put_contents(
                $arquivo,
                'UPDATE t SET id = 2;'
            );

            file_put_contents(
                $arquivo . '.sha256',
                str_repeat('0', 64)
                . '  '
                . basename($arquivo)
                . PHP_EOL
            );

            $esperaErro(
                static fn () =>
                    DatabaseBackupValidator::verificar(
                        $projeto,
                        $arquivo
                    ),
                'Checksum divergente deveria falhar.'
            );
        }
    );

    $testar(
        'Deploy exige evidência de backup antes de migration',
        static function () use ($raiz): void {
            $fonte = (string) file_get_contents(
                $raiz
                . '/tools/deploy/aplicar-release.php'
            );

            foreach (
                [
                    'db-backup',
                    'db-backup-confirm',
                    'DatabaseBackupValidator::verificar'
                ]
                as $trecho
            ) {
                if (!str_contains($fonte, $trecho)) {
                    throw new RuntimeException(
                        'Gate ausente no deploy: ' . $trecho
                    );
                }
            }
        }
    );

    $testar(
        'Deploy executa preflight e ensaio antes de alterar código',
        static function () use ($raiz): void {
            $fonte = (string) file_get_contents(
                $raiz
                . '/tools/deploy/aplicar-release.php'
            );

            $posPreflight = strpos(
                $fonte,
                'preflight.php'
            );
            $posEnsaio = strpos(
                $fonte,
                'ensaio-release.php'
            );
            $posBackup = strpos(
                $fonte,
                'backupCodigo'
            );

            if (
                $posPreflight === false
                || $posEnsaio === false
                || $posBackup === false
                || $posPreflight > $posBackup
                || $posEnsaio > $posBackup
            ) {
                throw new RuntimeException(
                    'Ordem dos gates pré-deploy está incorreta.'
                );
            }
        }
    );

    $testar(
        'Smoke test é obrigatório antes de desativar manutenção',
        static function () use ($raiz): void {
            $fonte = (string) file_get_contents(
                $raiz
                . '/tools/deploy/aplicar-release.php'
            );

            $posSmoke = strrpos(
                $fonte,
                'smoke-test.php'
            );
            $posOff = strpos(
                $fonte,
                'ModoManutencao::desativar'
            );

            if (
                $posSmoke === false
                || $posOff === false
                || $posSmoke > $posOff
            ) {
                throw new RuntimeException(
                    'Manutenção poderia ser desligada antes do smoke.'
                );
            }
        }
    );
} finally {
    $limpar($base);
}

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
