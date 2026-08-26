<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once dirname(__DIR__, 2) . '/config/ModoManutencao.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$acao = strtolower(
    trim(
        (string) (
            $argv[1]
            ?? 'status'
        )
    )
);

$raiz = DeployUtil::raiz();

if (
    str_starts_with(
        $acao,
        '--'
    )
) {
    $acao = 'status';
}

try {
    switch ($acao) {
        case 'on':
        case 'ativar':
            $minutos = isset($args['minutes'])
                ? max(
                    1,
                    (int) $args['minutes']
                )
                : 30;

            $mensagem = trim(
                (string) (
                    $args['message']
                    ?? 'Atualização do sistema em andamento.'
                )
            );

            $status = ModoManutencao::ativar(
                $raiz,
                $mensagem,
                $minutos
            );

            echo '[OK] Modo de manutenção ATIVO.' . PHP_EOL;
            echo '[INFO] Motivo: ' . $status['motivo'] . PHP_EOL;
            echo '[INFO] Expira em: ' . $status['expiraEm'] . PHP_EOL;
            break;

        case 'off':
        case 'desativar':
            ModoManutencao::desativar(
                $raiz
            );

            echo '[OK] Modo de manutenção DESATIVADO.' . PHP_EOL;
            break;

        case 'status':
            $status = ModoManutencao::status(
                $raiz
            );

            echo '======================================' . PHP_EOL;
            echo 'MODO DE MANUTENÇÃO' . PHP_EOL;
            echo '======================================' . PHP_EOL;

            if (!$status['ativo']) {
                echo '[OK] INATIVO' . PHP_EOL;
                exit(0);
            }

            echo '[ATIVO] Site bloqueado para requisições web.' . PHP_EOL;
            echo '[INFO] Motivo: ' . ($status['motivo'] ?? '-') . PHP_EOL;
            echo '[INFO] Iniciado em: ' . ($status['iniciadoEm'] ?? '-') . PHP_EOL;
            echo '[INFO] Expira em: ' . ($status['expiraEm'] ?? 'sem expiração') . PHP_EOL;
            exit(0);

        default:
            DeployUtil::erro(
                'Uso: php tools/deploy/manutencao.php '
                . 'status|on|off '
                . '[--minutes=30] '
                . '[--message="Atualização em andamento"]'
            );
    }
} catch (Throwable $erro) {
    DeployUtil::erro(
        $erro->getMessage()
    );
}
