<?php

declare(strict_types=1);

$raiz = dirname(__DIR__);

function brErro(string $mensagem): never
{
    fwrite(STDERR, '[ERRO] ' . $mensagem . PHP_EOL);
    exit(1);
}

/** @return array{0:int,1:array<int,string>} */
function brExec(string $comando, ?string $cwd = null): array
{
    if ($cwd !== null) {
        if (PHP_OS_FAMILY === 'Windows') {
            $comando = 'cd /d ' . escapeshellarg($cwd) . ' && ' . $comando;
        } else {
            $comando = 'cd ' . escapeshellarg($cwd) . ' && ' . $comando;
        }
    }

    $saida = [];
    $codigo = 0;
    exec($comando . ' 2>&1', $saida, $codigo);
    return [$codigo, $saida];
}

function brRemoverDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
}

function brRemover(string $path): void
{
    is_dir($path) ? brRemoverDir($path) : @unlink($path);
}

/** @return array<int,array{path:string,size:int,sha256:string}> */
function brManifestoArquivos(string $staging): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }

        $path = str_replace('\\', '/', substr($item->getPathname(), strlen($staging) + 1));

        if ($path === 'RELEASE-MANIFEST.json') {
            continue;
        }

        $files[] = [
            'path' => $path,
            'size' => (int) $item->getSize(),
            'sha256' => hash_file('sha256', $item->getPathname()),
        ];
    }

    usort($files, static fn (array $a, array $b): int => strnatcasecmp($a['path'], $b['path']));
    return $files;
}

function brZipDir(ZipArchive $zip, string $base, string $dir): void
{
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        $path = $item->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($base) + 1));

        if ($item->isDir()) {
            $zip->addEmptyDir($rel);
        } else {
            $zip->addFile($path, $rel);
        }
    }
}

if (!class_exists('ZipArchive')) {
    brErro('Extensão ZipArchive não disponível.');
}

[$git] = brExec('git --version');
[$composer] = brExec('composer --version');

if ($git !== 0) {
    brErro('Git não encontrado.');
}
if ($composer !== 0) {
    brErro('Composer não encontrado no PATH.');
}

[$statusCode, $status] = brExec('git -C ' . escapeshellarg($raiz) . ' status --porcelain');

if ($statusCode !== 0) {
    brErro('Não foi possível consultar git status.');
}
if ($status !== []) {
    brErro('Há alterações não commitadas. Faça commit antes de gerar a release.');
}

$versionFile = $raiz . '/mod/version.php';

if (!is_file($versionFile)) {
    brErro('mod/version.php não encontrado.');
}

$version = require $versionFile;

if (!is_array($version)) {
    brErro('mod/version.php inválido.');
}

$versao = preg_replace('/[^0-9A-Za-z._-]+/', '-', (string) ($version['version'] ?? 'sem-versao'));
$build = (int) ($version['build'] ?? 0);

[$commitCode, $commitOut] = brExec('git -C ' . escapeshellarg($raiz) . ' rev-parse HEAD');

if ($commitCode !== 0 || $commitOut === []) {
    brErro('Não foi possível obter o commit atual.');
}

$commit = trim((string) $commitOut[0]);
$dist = $raiz . '/dist';

if (!is_dir($dist) && !mkdir($dist, 0755, true) && !is_dir($dist)) {
    brErro('Não foi possível criar /dist.');
}

$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'retiro-release-' . bin2hex(random_bytes(6));
$staging = $temp . DIRECTORY_SEPARATOR . 'retiro';
$archive = $temp . DIRECTORY_SEPARATOR . 'source.zip';

if (!mkdir($staging, 0755, true)) {
    brErro('Não foi possível criar staging.');
}

try {
    echo '[1/6] Exportando HEAD...' . PHP_EOL;
    [$archiveCode, $archiveOut] = brExec(
        'git -C ' . escapeshellarg($raiz)
        . ' archive --format=zip --output=' . escapeshellarg($archive) . ' HEAD'
    );

    if ($archiveCode !== 0) {
        brErro('git archive falhou: ' . implode(PHP_EOL, $archiveOut));
    }

    $sourceZip = new ZipArchive();
    if ($sourceZip->open($archive) !== true || !$sourceZip->extractTo($staging)) {
        brErro('Falha ao extrair staging.');
    }
    $sourceZip->close();

    echo '[2/6] Limpando staging...' . PHP_EOL;
    foreach ([
        '.github',
        'arquivos',
        'dist',
        'config/conn.php',
        'config/integracoes.php',
        'config/.bancario.key',
        'lib/vendor',
    ] as $rel) {
        brRemover($staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $base = $item->getFilename();
        $lower = strtolower($base);

        if (
            str_ends_with($lower, '.log')
            || preg_match('/\.bak(?:[-.].*)?$/i', $base)
            || str_ends_with($lower, '.backup')
            || (str_starts_with($lower, 'atualizar-') && str_ends_with($lower, '.php'))
        ) {
            @unlink($item->getPathname());
        }
    }

    echo '[3/6] Instalando Composer...' . PHP_EOL;
    $lib = $staging . DIRECTORY_SEPARATOR . 'lib';

    if (!is_file($lib . '/composer.json') || !is_file($lib . '/composer.lock')) {
        brErro('composer.json/composer.lock não encontrados em /lib.');
    }

    [$installCode, $installOut] = brExec(
        'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress',
        $lib
    );

    foreach ($installOut as $line) {
        echo $line . PHP_EOL;
    }
    if ($installCode !== 0) {
        brErro('composer install falhou.');
    }

    echo '[4/6] Verificando segredos...' . PHP_EOL;
    foreach (['config/conn.php', 'config/integracoes.php', 'config/.bancario.key'] as $rel) {
        if (file_exists($staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) {
            brErro('Arquivo sensível entrou no staging: ' . $rel);
        }
    }

    echo '[5/6] Gerando manifesto...' . PHP_EOL;
    $files = brManifestoArquivos($staging);
    $manifest = [
        'schema' => 1,
        'application' => 'retiro',
        'version' => $versao,
        'build' => $build,
        'commit' => $commit,
        'generatedAt' => gmdate('c'),
        'files' => $files,
    ];
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($json) || file_put_contents($staging . '/RELEASE-MANIFEST.json', $json . PHP_EOL) === false) {
        brErro('Não foi possível gravar RELEASE-MANIFEST.json.');
    }

    echo '[6/6] Criando ZIP + SHA-256...' . PHP_EOL;
    $name = 'retiro-' . $versao . '-build' . $build . '.zip';
    $zipPath = $dist . DIRECTORY_SEPARATOR . $name;
    @unlink($zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        brErro('Não foi possível criar ZIP final.');
    }
    brZipDir($zip, $staging, $staging);
    $zip->close();

    if (!is_file($zipPath) || filesize($zipPath) === 0) {
        brErro('ZIP final não foi criado corretamente.');
    }

    $sha = hash_file('sha256', $zipPath);
    file_put_contents($zipPath . '.sha256', $sha . '  ' . basename($zipPath) . PHP_EOL);

    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo "RELEASE GERADA" . PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo '[OK] ' . $zipPath . PHP_EOL;
    echo '[OK] ' . $zipPath . '.sha256' . PHP_EOL;
    echo '[OK] Versão ' . $versao . ' | Build ' . $build . PHP_EOL;
    echo '[OK] Commit ' . $commit . PHP_EOL;
    echo '[OK] Arquivos manifestados: ' . count($files) . PHP_EOL;
    echo '[OK] SHA-256 ' . $sha . PHP_EOL;
} finally {
    brRemoverDir($temp);
}
