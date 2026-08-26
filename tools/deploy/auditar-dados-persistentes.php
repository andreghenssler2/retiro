<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';

DeployUtil::exigirCli();

$raiz = DeployUtil::raiz();

echo "======================================" . PHP_EOL;
echo "AUDITORIA DE DADOS PERSISTENTES" . PHP_EOL;
echo "======================================" . PHP_EOL;

$erros = [];
$avisos = [];
$ok = [];

$exemplosProtegidos = [
    'uploads/usuarios/usuario-123.jpg',
    'uploads/usuarios/user_abcdef123456.png',
    'uploads/eventos/evento_abcdef.webp',
    'uploads/comunidades/comunidade-abcdef.jpg',
    'uploads/comprovantes/pagamentos/20260827_120000_abcdef.pdf',
    'uploads/certificados/modelos/modelo-1.png',
    'storage/certificados/certificado-1.pdf',
    'theme/img/favicon.png',
    'theme/img/site-imagem.webp',
    'config/conn.php',
    'config/integracoes.php',
    'config/.bancario.key',
    'storage/manutencao.json',
];

foreach ($exemplosProtegidos as $path) {
    if (!DeployUtil::protegido($path)) {
        $erros[] = 'Caminho dinâmico não protegido: ' . $path;
    }
}

if (!DeployUtil::protegido('uploads/usuarios/user.png')) {
    $ok[] = 'Imagem padrão uploads/usuarios/user.png permanece gerenciável pela release';
} else {
    $erros[] = 'uploads/usuarios/user.png não deve ser tratado como dado dinâmico';
}

$htaccess = $raiz . '/uploads/.htaccess';

if (!is_file($htaccess)) {
    $erros[] = 'uploads/.htaccess ausente';
} else {
    $conteudo = (string) file_get_contents($htaccess);

    if (
        !str_contains($conteudo, 'Options -Indexes')
        || !preg_match('/php/i', $conteudo)
        || !preg_match('/Require\s+all\s+denied/i', $conteudo)
    ) {
        $erros[] = 'uploads/.htaccess não contém a proteção esperada';
    } else {
        $ok[] = 'uploads/.htaccess protege listagem e extensões executáveis';
    }
}

$gitDir = $raiz . '/.git';

if (is_dir($gitDir)) {
    $saida = [];
    $codigo = 0;

    exec(
        'git -C '
        . escapeshellarg($raiz)
        . ' ls-files 2>&1',
        $saida,
        $codigo
    );

    if ($codigo !== 0) {
        $erros[] = 'Falha ao consultar arquivos rastreados pelo Git: ' . implode(' | ', $saida);
    } else {
        $rastreadosProtegidos = [];

        foreach ($saida as $linha) {
            $path = DeployUtil::normalizar((string) $linha);

            if ($path === '') {
                continue;
            }

            if (DeployUtil::protegido($path)) {
                $rastreadosProtegidos[] = $path;
            }
        }

        if ($rastreadosProtegidos !== []) {
            foreach ($rastreadosProtegidos as $path) {
                $erros[] = 'Dado persistente está rastreado pelo Git: ' . $path;
            }
        } else {
            $ok[] = 'Nenhum dado persistente está rastreado pelo Git';
        }
    }
} else {
    $avisos[] = 'Diretório .git ausente; auditoria de arquivos rastreados foi ignorada';
}

$manifestPath = $raiz . '/' . DeployUtil::RELEASE_MANIFEST;

if (is_file($manifestPath)) {
    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true
    );

    if (
        !is_array($manifest)
        || !isset($manifest['files'])
        || !is_array($manifest['files'])
    ) {
        $erros[] = 'RELEASE-MANIFEST.json local é inválido';
    } else {
        foreach ($manifest['files'] as $item) {
            if (!is_array($item) || !isset($item['path'])) {
                continue;
            }

            $path = DeployUtil::normalizar((string) $item['path']);

            if (DeployUtil::protegido($path)) {
                $erros[] = 'Manifesto atual contém dado persistente: ' . $path;
            }
        }

        if ($erros === []) {
            $ok[] = 'Manifesto atual não contém dados persistentes';
        }
    }
}

foreach ($ok as $mensagem) {
    echo '[OK] ' . $mensagem . PHP_EOL;
}

foreach ($avisos as $mensagem) {
    echo '[AVISO] ' . $mensagem . PHP_EOL;
}

foreach ($erros as $mensagem) {
    echo '[ERRO] ' . $mensagem . PHP_EOL;
}

echo PHP_EOL;
echo 'OK: ' . count($ok) . PHP_EOL;
echo 'AVISOS: ' . count($avisos) . PHP_EOL;
echo 'ERROS: ' . count($erros) . PHP_EOL;

exit($erros === [] ? 0 : 1);
