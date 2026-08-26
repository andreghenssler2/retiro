<?php

declare(strict_types=1);

final class DatabaseBackupValidator
{
    /**
     * @return array{
     *   path:string,
     *   size:int,
     *   sha256:string,
     *   sidecar:bool
     * }
     */
    public static function verificar(
        string $raizProjeto,
        string $backupPath
    ): array {
        $raiz = realpath($raizProjeto);
        $backup = realpath($backupPath);

        if ($raiz === false) {
            throw new RuntimeException(
                'Não foi possível resolver a raiz do projeto.'
            );
        }

        if (
            $backup === false
            || !is_file($backup)
        ) {
            throw new RuntimeException(
                'Backup de banco não encontrado.'
            );
        }

        if (is_link($backup)) {
            throw new RuntimeException(
                'Backup de banco não pode ser um link simbólico.'
            );
        }

        $raizNormalizada = self::normalizarAbsoluto(
            $raiz
        );
        $backupNormalizado = self::normalizarAbsoluto(
            $backup
        );

        if (
            $backupNormalizado === $raizNormalizada
            || str_starts_with(
                $backupNormalizado . '/',
                $raizNormalizada . '/'
            )
        ) {
            throw new RuntimeException(
                'Backup de banco deve ficar fora da raiz do projeto.'
            );
        }

        $size = filesize($backup);

        if (
            $size === false
            || $size <= 0
        ) {
            throw new RuntimeException(
                'Backup de banco está vazio.'
            );
        }

        $sha = hash_file(
            'sha256',
            $backup
        );

        if (!is_string($sha) || $sha === '') {
            throw new RuntimeException(
                'Não foi possível calcular SHA-256 do backup de banco.'
            );
        }

        $sidecarPath = $backup . '.sha256';
        $sidecar = false;

        if (is_file($sidecarPath)) {
            $raw = file_get_contents(
                $sidecarPath
            );

            if (!is_string($raw)) {
                throw new RuntimeException(
                    'Não foi possível ler o checksum do backup de banco.'
                );
            }

            if (
                preg_match(
                    '/\b([a-f0-9]{64})\b/i',
                    $raw,
                    $match
                ) !== 1
            ) {
                throw new RuntimeException(
                    'Arquivo .sha256 do backup de banco é inválido.'
                );
            }

            $esperado = strtolower(
                (string) $match[1]
            );

            if (!hash_equals($esperado, $sha)) {
                throw new RuntimeException(
                    'Checksum do backup de banco não confere.'
                );
            }

            $sidecar = true;
        }

        return [
            'path' => $backup,
            'size' => (int) $size,
            'sha256' => $sha,
            'sidecar' => $sidecar
        ];
    }

    private static function normalizarAbsoluto(
        string $path
    ): string {
        $path = rtrim(
            str_replace('\\', '/', $path),
            '/'
        );

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
        }

        return $path;
    }
}
