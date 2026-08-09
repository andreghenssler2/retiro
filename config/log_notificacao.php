<?php

declare(strict_types=1);

/**
 * Configuração das notificações de logs.
 *
 * O cron lê somente os trechos novos dos arquivos de log do dia e envia
 * um resumo por e-mail. O conteúdo já enviado não será enviado novamente.
 */
$config = require __DIR__ . '/mail.php';

return [
    'ativo' => true,

    'email_responsavel' => $config['from_email'],
    'nome_responsavel' => $config['from_name'],

    /*
     * Assunto usado nos e-mails.
     */
    'nome_sistema' => 'Sistema de Eventos',

    /*
     * Diretórios de log, relativos à raiz do projeto.
     *
     * A classe Log atual grava em mod/logs porque está localizada em
     * mod/classes/logs.php e usa __DIR__ . '/../logs'.
     *
     * O diretório logs também é verificado para manter compatibilidade.
     */
    'diretorios' => [
        'mod/logs',
        'logs',
    ],

    /*
     * Extensões aceitas.
     */
    'extensoes' => [
        'log',
        'txt',
    ],

    /*
     * Quantidade máxima lida por execução.
     *
     * Caso existam mais dados, o restante será enviado na próxima execução.
     */
    'maximo_bytes_por_execucao' => 500 * 1024,

    /*
     * Quantidade máxima exibida no corpo do e-mail.
     * O conteúdo completo processado é anexado ao e-mail quando ultrapassa
     * este limite.
     */
    'maximo_bytes_no_corpo' => 120 * 1024,

    /*
     * Envia somente arquivos cujo nome contenha a data atual ou que tenham
     * sido modificados no dia atual.
     */
    'somente_arquivos_do_dia' => true,
];
