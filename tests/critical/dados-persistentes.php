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
    . '/tools/deploy/DeployUtil.php';

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

$verdadeiro = static function (
    bool $valor,
    string $mensagem
): void {
    if (!$valor) {
        throw new RuntimeException($mensagem);
    }
};

$falso = static function (
    bool $valor,
    string $mensagem
): void {
    if ($valor) {
        throw new RuntimeException($mensagem);
    }
};

echo "======================================" . PHP_EOL;
echo "TESTE - DADOS PERSISTENTES NO DEPLOY" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

$testar(
    'Fotos de usuários são persistentes e user.png continua estático',
    static function () use ($verdadeiro, $falso): void {
        $verdadeiro(
            DeployUtil::protegido(
                'uploads/usuarios/usuario-15.jpg'
            ),
            'Foto usuario-15.jpg deveria ser protegida.'
        );

        $verdadeiro(
            DeployUtil::protegido(
                'uploads/usuarios/user_a1b2c3.png'
            ),
            'Foto user_a1b2c3.png deveria ser protegida.'
        );

        $falso(
            DeployUtil::protegido(
                'uploads/usuarios/user.png'
            ),
            'user.png padrão deve continuar na release.'
        );
    }
);

$testar(
    'Imagens de eventos são persistentes',
    static function () use ($verdadeiro, $falso): void {
        $verdadeiro(
            DeployUtil::protegido(
                'uploads/eventos/evento_123.jpg'
            ),
            'Imagem de evento deveria ser protegida.'
        );

        $falso(
            DeployUtil::protegido(
                'uploads/eventos/.htaccess'
            ),
            '.htaccess precisa poder ser distribuído.'
        );
    }
);

$testar(
    'Imagens de comunidades são persistentes',
    static function () use ($verdadeiro): void {
        $verdadeiro(
            DeployUtil::protegido(
                'uploads/comunidades/comunidade-123.webp'
            ),
            'Imagem de comunidade deveria ser protegida.'
        );
    }
);

$testar(
    'Comprovantes financeiros são persistentes',
    static function () use ($verdadeiro): void {
        $verdadeiro(
            DeployUtil::protegido(
                'uploads/comprovantes/pagamentos/recibo.pdf'
            ),
            'Comprovante deveria ser protegido.'
        );
    }
);

$testar(
    'Certificados e modelos continuam protegidos',
    static function () use ($verdadeiro): void {
        $verdadeiro(
            DeployUtil::protegido(
                'storage/certificados/certificado.pdf'
            ),
            'Certificado gerado deveria ser protegido.'
        );

        $verdadeiro(
            DeployUtil::protegido(
                'uploads/certificados/modelos/modelo.png'
            ),
            'Modelo enviado deveria ser protegido.'
        );
    }
);

$testar(
    'Favicon e imagem administrativa do site são persistentes',
    static function () use ($verdadeiro, $falso): void {
        $verdadeiro(
            DeployUtil::protegido(
                'theme/img/favicon.ico'
            ),
            'favicon.ico administrativo deveria ser protegido.'
        );

        $verdadeiro(
            DeployUtil::protegido(
                'theme/img/site-imagem.webp'
            ),
            'site-imagem.webp administrativa deveria ser protegida.'
        );

        $falso(
            DeployUtil::protegido(
                'theme/img/image.png'
            ),
            'Assets estáticos normais devem continuar na release.'
        );
    }
);

$testar(
    'Dados persistentes são proibidos na release',
    static function () use ($verdadeiro): void {
        foreach (
            [
                'uploads/usuarios/usuario-1.jpg',
                'uploads/eventos/evento_1.jpg',
                'uploads/comunidades/comunidade-1.png',
                'uploads/comprovantes/pagamentos/recibo.pdf',
                'theme/img/favicon.png'
            ]
            as $path
        ) {
            $verdadeiro(
                DeployUtil::proibidoNaRelease($path),
                'Deveria ser proibido na release: ' . $path
            );
        }
    }
);

$testar(
    'uploads/.htaccess bloqueia extensões executáveis',
    static function () use ($raiz, $verdadeiro): void {
        $arquivo = $raiz . '/uploads/.htaccess';

        $verdadeiro(
            is_file($arquivo),
            'uploads/.htaccess ausente.'
        );

        $conteudo = (string) file_get_contents($arquivo);

        $verdadeiro(
            str_contains(
                $conteudo,
                'Options -Indexes'
            ),
            'Options -Indexes ausente.'
        );

        $verdadeiro(
            preg_match(
                '/Require\s+all\s+denied/i',
                $conteudo
            ) === 1,
            'Bloqueio Require all denied ausente.'
        );
    }
);

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
