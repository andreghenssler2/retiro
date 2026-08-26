<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';

final class DeployRecoveryValidator
{
    /**
     * @return array{
     *   zipPath:string,
     *   zipSha256:string,
     *   fileCount:int,
     *   createdAt:?string
     * }
     */
    public static function verificarBackupCodigo(
        string $zipPath
    ): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'Extensão ZipArchive não disponível.'
            );
        }

        $real = realpath($zipPath);

        if ($real === false || !is_file($real)) {
            throw new RuntimeException(
                'Backup não encontrado: ' . $zipPath
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($real) !== true) {
            throw new RuntimeException(
                'Não foi possível abrir o backup.'
            );
        }

        try {
            $entries = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nome = (string) $zip->getNameIndex($i);

                if ($nome === '' || str_ends_with($nome, '/')) {
                    continue;
                }

                if (!DeployUtil::caminhoSeguro($nome)) {
                    throw new RuntimeException(
                        'Caminho inseguro no backup: ' . $nome
                    );
                }

                $nome = DeployUtil::normalizar($nome);

                if (
                    $nome !== DeployUtil::BACKUP_MANIFEST
                    && DeployUtil::protegido($nome)
                ) {
                    throw new RuntimeException(
                        'Caminho protegido presente no backup: ' . $nome
                    );
                }

                if (isset($entries[$nome])) {
                    throw new RuntimeException(
                        'Entrada duplicada no backup: ' . $nome
                    );
                }

                $entries[$nome] = true;
            }

            $raw = $zip->getFromName(
                DeployUtil::BACKUP_MANIFEST
            );

            $manifest = is_string($raw)
                ? json_decode($raw, true)
                : null;

            if (
                !is_array($manifest)
                || !isset($manifest['files'])
                || !is_array($manifest['files'])
            ) {
                throw new RuntimeException(
                    'BACKUP-MANIFEST.json inválido.'
                );
            }

            if (
                isset($manifest['type'])
                && (string) $manifest['type'] !== 'code-backup'
            ) {
                throw new RuntimeException(
                    'Tipo de backup inválido.'
                );
            }

            if (
                array_key_exists(
                    'protectedFilesIncluded',
                    $manifest
                )
                && $manifest['protectedFilesIncluded'] !== false
            ) {
                throw new RuntimeException(
                    'Backup declara inclusão de arquivos protegidos.'
                );
            }

            $listados = [];

            foreach ($manifest['files'] as $item) {
                if (
                    !is_array($item)
                    || !isset(
                        $item['path'],
                        $item['size'],
                        $item['sha256']
                    )
                ) {
                    throw new RuntimeException(
                        'Entrada inválida no manifesto do backup.'
                    );
                }

                $path = DeployUtil::normalizar(
                    (string) $item['path']
                );

                if (
                    !DeployUtil::caminhoSeguro($path)
                    || DeployUtil::protegido($path)
                ) {
                    throw new RuntimeException(
                        'Caminho proibido no manifesto do backup: '
                        . $path
                    );
                }

                if (isset($listados[$path])) {
                    throw new RuntimeException(
                        'Arquivo duplicado no manifesto: ' . $path
                    );
                }

                if (!isset($entries[$path])) {
                    throw new RuntimeException(
                        'Arquivo do manifesto ausente no backup: '
                        . $path
                    );
                }

                $content = $zip->getFromName($path);

                if (!is_string($content)) {
                    throw new RuntimeException(
                        'Não foi possível ler do backup: ' . $path
                    );
                }

                if ((int) $item['size'] !== strlen($content)) {
                    throw new RuntimeException(
                        'Tamanho divergente no backup: ' . $path
                    );
                }

                $esperado = strtolower(
                    trim((string) $item['sha256'])
                );

                $atual = hash('sha256', $content);

                if (
                    $esperado === ''
                    || !hash_equals($esperado, $atual)
                ) {
                    throw new RuntimeException(
                        'Checksum divergente no backup: ' . $path
                    );
                }

                $listados[$path] = true;
            }

            foreach ($entries as $path => $_) {
                if (
                    $path !== DeployUtil::BACKUP_MANIFEST
                    && !isset($listados[$path])
                ) {
                    throw new RuntimeException(
                        'Arquivo extra fora do manifesto do backup: '
                        . $path
                    );
                }
            }

            return [
                'zipPath' => $real,
                'zipSha256' => hash_file('sha256', $real),
                'fileCount' => count($listados),
                'createdAt' => isset($manifest['createdAt'])
                    ? (string) $manifest['createdAt']
                    : null
            ];
        } finally {
            $zip->close();
        }
    }
}
