<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$zipPath = (string) ($args['zip'] ?? '');

if ($zipPath === '') {
    DeployUtil::erro(
        'Uso: php tools/deploy/ensaio-release.php '
        . '--zip=/caminho/release.zip'
    );
}

$manifest = DeployUtil::verificarRelease($zipPath);

$temp =
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'retiro-ensaio-release-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($temp, 0750, true)
    && !is_dir($temp)
) {
    DeployUtil::erro(
        'Não foi possível criar staging temporário do ensaio.'
    );
}

$remover = static function (
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
        if (
            $item->isDir()
            && !$item->isLink()
        ) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
};

$phpValidados = 0;
$protegidosCobertos = 0;

try {
    $zip = new ZipArchive();

    if (
        $zip->open(
            (string) $manifest['zipPath']
        ) !== true
        || !$zip->extractTo($temp)
    ) {
        DeployUtil::erro(
            'Falha ao extrair a release para o ensaio.'
        );
    }

    $zip->close();

    foreach ($manifest['files'] as $item) {
        if (
            !is_array($item)
            || !isset($item['path'])
        ) {
            DeployUtil::erro(
                'Entrada inválida no manifesto durante o ensaio.'
            );
        }

        $path = DeployUtil::normalizar(
            (string) $item['path']
        );

        if (DeployUtil::protegido($path)) {
            DeployUtil::erro(
                'Release contém caminho protegido: ' . $path
            );
        }

        if (
            !str_ends_with(
                strtolower($path),
                '.php'
            )
        ) {
            continue;
        }

        $arquivo =
            $temp
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $path
            );

        $saida = [];
        $codigo = 0;

        exec(
            escapeshellarg(PHP_BINARY)
            . ' -l '
            . escapeshellarg($arquivo)
            . ' 2>&1',
            $saida,
            $codigo
        );

        if ($codigo !== 0) {
            DeployUtil::erro(
                'PHP lint falhou na release: '
                . $path
                . ' | '
                . implode(' | ', $saida)
            );
        }

        $phpValidados++;
    }

    $autoload =
        $temp
        . DIRECTORY_SEPARATOR
        . 'lib'
        . DIRECTORY_SEPARATOR
        . 'vendor'
        . DIRECTORY_SEPARATOR
        . 'autoload.php';

    $saida = [];
    $codigo = 0;

    exec(
        escapeshellarg(PHP_BINARY)
        . ' -r '
        . escapeshellarg(
            'require '
            . var_export($autoload, true)
            . '; echo "autoload-ok";'
        )
        . ' 2>&1',
        $saida,
        $codigo
    );

    if (
        $codigo !== 0
        || !str_contains(
            implode("\n", $saida),
            'autoload-ok'
        )
    ) {
        DeployUtil::erro(
            'Composer autoload da release não pôde ser carregado.'
        );
    }

    $sentinelasProtegidas = [
        'config/conn.php',
        'config/integracoes.php',
        'config/.bancario.key',
        'storage/manutencao.json',
        'storage/seguranca/rate-limit/teste.json',
        'storage/certificados/certificado.pdf',
        'uploads/usuarios/usuario-999.jpg',
        'uploads/eventos/evento-runtime.jpg',
        'uploads/comunidades/comunidade-runtime.jpg',
        'uploads/comprovantes/pagamentos/comprovante.pdf',
        'uploads/certificados/modelos/modelo.png',
        'theme/img/favicon.png',
        'theme/img/site-imagem.webp'
    ];

    foreach ($sentinelasProtegidas as $path) {
        if (!DeployUtil::protegido($path)) {
            DeployUtil::erro(
                'Cobertura de persistência ausente para: ' . $path
            );
        }

        $protegidosCobertos++;
    }
} finally {
    $remover($temp);
}

echo "======================================" . PHP_EOL;
echo "ENSAIO DE RELEASE" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Versão: '
    . (string) ($manifest['version'] ?? 'desconhecida')
    . PHP_EOL;
echo '[OK] Build: '
    . (int) ($manifest['build'] ?? 0)
    . PHP_EOL;
echo '[OK] Commit: '
    . (string) ($manifest['commit'] ?? 'não informado')
    . PHP_EOL;
echo '[OK] Arquivos manifestados: '
    . (int) ($manifest['fileCount'] ?? 0)
    . PHP_EOL;
echo '[OK] PHPs validados na release: '
    . $phpValidados
    . PHP_EOL;
echo '[OK] Caminhos persistentes cobertos: '
    . $protegidosCobertos
    . PHP_EOL;
echo '[OK] Composer autoload carregado.'
    . PHP_EOL;
echo '[OK] Release não contém arquivos protegidos.'
    . PHP_EOL;
echo '[OK] Nenhum arquivo do projeto foi alterado pelo ensaio.'
    . PHP_EOL;
