<?php

declare(strict_types=1);

final class DeployUtil
{
    public const RELEASE_MANIFEST = 'RELEASE-MANIFEST.json';
    public const BACKUP_MANIFEST = 'BACKUP-MANIFEST.json';

    public static function exigirCli(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            http_response_code(404);
            exit;
        }
    }

    public static function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function erro(string $mensagem): never
    {
        fwrite(STDERR, '[ERRO] ' . $mensagem . PHP_EOL);
        exit(1);
    }

    /** @return array<string,string|bool> */
    public static function args(array $argv): array
    {
        $r = [];

        foreach (array_slice($argv, 1) as $arg) {
            $arg = (string) $arg;

            if (!str_starts_with($arg, '--')) {
                continue;
            }

            $arg = substr($arg, 2);

            if (str_contains($arg, '=')) {
                [$k, $v] = explode('=', $arg, 2);
                $r[$k] = $v;
            } else {
                $r[$arg] = true;
            }
        }

        return $r;
    }

    public static function normalizar(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('~/+~', '/', $path) ?? $path;
        return ltrim($path, '/');
    }

    public static function caminhoSeguro(string $path): bool
    {
        $raw = str_replace('\\', '/', trim($path));

        if ($raw === '' || str_starts_with($raw, '/') || preg_match('~^[A-Za-z]:/~', $raw)) {
            return false;
        }

        return !in_array('..', explode('/', $raw), true);
    }

    public static function protegido(string $path): bool
    {
        $path = self::normalizar($path);
        $base = basename($path);

        if (in_array($path, [
            'config/conn.php',
            'config/integracoes.php',
            'config/.bancario.key',
        ], true)) {
            return true;
        }

        if (str_starts_with($path, 'logs/') || str_starts_with($path, 'mod/logs/')) {
            return !in_array($base, ['.gitkeep', '.htaccess'], true);
        }

        if (str_starts_with($path, 'storage/certificados/')) {
            return !in_array($base, ['.gitkeep', '.htaccess'], true);
        }

        if (str_starts_with($path, 'uploads/certificados/modelos/')) {
            return $base !== '.gitkeep';
        }

        return str_starts_with($path, 'ieclb-mail/') || str_starts_with($path, 'ofertas/');
    }

    public static function proibidoNaRelease(string $path): bool
    {
        $path = self::normalizar($path);
        $base = basename($path);
        $lower = strtolower($path);

        if (self::protegido($path) && !in_array($base, ['.gitkeep', '.htaccess'], true)) {
            return true;
        }

        if (
            $path === '.git'
            || str_starts_with($path, '.git/')
            || $path === '.github'
            || str_starts_with($path, '.github/')
            || str_starts_with($path, 'arquivos/')
            || str_starts_with($path, 'dist/')
            || str_starts_with($path, 'portal_ieclb_parobe/')
        ) {
            return true;
        }

        if (
            str_ends_with($lower, '.log')
            || preg_match('/\.bak(?:[-.].*)?$/i', $base)
            || str_ends_with($lower, '.backup')
        ) {
            return true;
        }

        return str_starts_with(strtolower($base), 'atualizar-') && str_ends_with($lower, '.php');
    }

    /** @return array<string,mixed> */
    public static function verificarRelease(string $zipPath): array
    {
        if (!class_exists('ZipArchive')) {
            self::erro('Extensão ZipArchive não disponível.');
        }

        $real = realpath($zipPath);

        if ($real === false || !is_file($real)) {
            self::erro('ZIP não encontrado: ' . $zipPath);
        }

        $zip = new ZipArchive();

        if ($zip->open($real) !== true) {
            self::erro('Não foi possível abrir o ZIP.');
        }

        $entries = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if (!self::caminhoSeguro($name)) {
                    self::erro('Caminho inseguro no ZIP: ' . $name);
                }

                $name = self::normalizar($name);

                if (self::proibidoNaRelease($name)) {
                    self::erro('Arquivo proibido na release: ' . $name);
                }

                $entries[$name] = $i;
            }

            foreach ([
                self::RELEASE_MANIFEST,
                'lib/vendor/autoload.php',
                'lib/composer.lock',
                'database/migrate.php',
                'tools/smoke-test.php',
                'mod/version.php',
            ] as $required) {
                if (!isset($entries[$required])) {
                    self::erro('Arquivo obrigatório ausente: ' . $required);
                }
            }

            $raw = $zip->getFromName(self::RELEASE_MANIFEST);
            $manifest = is_string($raw) ? json_decode($raw, true) : null;

            if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
                self::erro('RELEASE-MANIFEST.json inválido.');
            }

            $listed = [];

            foreach ($manifest['files'] as $item) {
                if (!is_array($item) || !isset($item['path'], $item['sha256'], $item['size'])) {
                    self::erro('Entrada inválida no manifesto.');
                }

                $path = self::normalizar((string) $item['path']);

                if (!isset($entries[$path])) {
                    self::erro('Arquivo do manifesto ausente no ZIP: ' . $path);
                }

                $content = $zip->getFromName($path);

                if (!is_string($content)) {
                    self::erro('Falha ao ler do ZIP: ' . $path);
                }

                if ((int) $item['size'] !== strlen($content)) {
                    self::erro('Tamanho divergente: ' . $path);
                }

                if (!hash_equals(strtolower((string) $item['sha256']), hash('sha256', $content))) {
                    self::erro('Checksum divergente: ' . $path);
                }

                $listed[$path] = true;
            }

            foreach ($entries as $path => $_) {
                if ($path !== self::RELEASE_MANIFEST && !isset($listed[$path])) {
                    self::erro('Arquivo extra fora do manifesto: ' . $path);
                }
            }

            $manifest['zipPath'] = $real;
            $manifest['zipSha256'] = hash_file('sha256', $real);
            $manifest['fileCount'] = count($listed);

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    public static function destinoExterno(string $destino): string
    {
        $raiz = realpath(self::raiz());

        if ($destino === '') {
            self::erro('Informe --dest=CAMINHO fora do projeto.');
        }

        if (!str_starts_with($destino, DIRECTORY_SEPARATOR) && !preg_match('~^[A-Za-z]:[\\\\/]~', $destino)) {
            $destino = getcwd() . DIRECTORY_SEPARATOR . $destino;
        }

        if (!is_dir($destino) && !mkdir($destino, 0750, true) && !is_dir($destino)) {
            self::erro('Não foi possível criar diretório de backup.');
        }

        $real = realpath($destino);

        if ($raiz === false || $real === false) {
            self::erro('Não foi possível resolver caminho de backup.');
        }

        $a = rtrim(str_replace('\\', '/', $raiz), '/');
        $b = rtrim(str_replace('\\', '/', $real), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $a = strtolower($a);
            $b = strtolower($b);
        }

        if ($a === $b || str_starts_with($b . '/', $a . '/')) {
            self::erro('Backup deve ficar fora da raiz do projeto.');
        }

        return $real;
    }

    /** @return array<int,array{path:string,size:int,sha256:string}> */
    public static function arquivosGerenciadosAtuais(): array
    {
        $raiz = self::raiz();
        $manifestPath = $raiz . DIRECTORY_SEPARATOR . self::RELEASE_MANIFEST;
        $paths = [];

        if (is_file($manifestPath)) {
            $m = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($m) && isset($m['files']) && is_array($m['files'])) {
                foreach ($m['files'] as $item) {
                    if (is_array($item) && isset($item['path'])) {
                        $paths[] = self::normalizar((string) $item['path']);
                    }
                }
            }

            $paths[] = self::RELEASE_MANIFEST;
        } else {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($it as $item) {
                if (!$item->isFile() || $item->isLink()) {
                    continue;
                }

                $path = self::normalizar(substr($item->getPathname(), strlen($raiz) + 1));
                $base = basename($path);
                $lower = strtolower($path);

                if (
                    str_starts_with($path, '.git/')
                    || str_starts_with($path, '.github/')
                    || str_starts_with($path, 'dist/')
                    || str_starts_with($path, 'arquivos/')
                    || self::protegido($path)
                    || str_ends_with($lower, '.log')
                    || preg_match('/\.bak(?:[-.].*)?$/i', $base)
                    || str_ends_with($lower, '.backup')
                    || str_ends_with($lower, '.zip')
                ) {
                    continue;
                }

                $paths[] = $path;
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        $files = [];

        foreach ($paths as $path) {
            if (self::protegido($path)) {
                continue;
            }

            $abs = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (!is_file($abs)) {
                continue;
            }

            $files[] = [
                'path' => $path,
                'size' => (int) filesize($abs),
                'sha256' => hash_file('sha256', $abs),
            ];
        }

        return $files;
    }

    /** @return array{zip:string,sha256:string,files:int} */
    public static function backupCodigo(string $destino): array
    {
        if (!class_exists('ZipArchive')) {
            self::erro('Extensão ZipArchive não disponível.');
        }

        $raiz = self::raiz();
        $destino = self::destinoExterno($destino);
        $files = self::arquivosGerenciadosAtuais();

        if ($files === []) {
            self::erro('Nenhum arquivo encontrado para backup.');
        }

        $manifest = [
            'schema' => 1,
            'type' => 'code-backup',
            'createdAt' => gmdate('c'),
            'files' => $files,
            'protectedFilesIncluded' => false,
        ];

        $path = $destino . DIRECTORY_SEPARATOR . 'retiro-codigo-' . gmdate('Ymd-His') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            self::erro('Não foi possível criar backup.');
        }

        try {
            foreach ($files as $item) {
                $abs = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['path']);
                $zip->addFile($abs, $item['path']);
            }

            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!is_string($json) || !$zip->addFromString(self::BACKUP_MANIFEST, $json . PHP_EOL)) {
                self::erro('Falha ao escrever manifesto do backup.');
            }
        } finally {
            $zip->close();
        }

        $sha = hash_file('sha256', $path);
        file_put_contents($path . '.sha256', $sha . '  ' . basename($path) . PHP_EOL);

        return ['zip' => $path, 'sha256' => $sha, 'files' => count($files)];
    }

    public static function copiar(string $origem, string $destino): void
    {
        $dir = dirname($destino);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            self::erro('Falha ao criar diretório: ' . $dir);
        }

        if (!copy($origem, $destino)) {
            self::erro('Falha ao copiar: ' . $destino);
        }
    }
}
