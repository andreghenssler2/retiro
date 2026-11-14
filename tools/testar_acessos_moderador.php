<?php

declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/mod/auth/RotaModerador.php';

$casos = [
    ['Evento: visualizar lista', 2, 'admin/event/eventos.php', 'GET', [], [], true],
    ['Evento: visualizar detalhes', 2, 'admin/event/ajax/evento-view.php', 'GET', [], [], true],
    ['Evento: criar', 2, 'admin/event/evento.php', 'GET', [], [], false],
    ['Evento: salvar', 2, 'admin/event/ajax/evento-new.php', 'POST', [], [], false],
    ['Evento: excluir', 2, 'admin/event/ajax/evento-delete.php', 'POST', [], [], false],
    ['Evento: ativar', 2, 'admin/event/ajax/evento-status.php', 'POST', [], [], false],
    ['Cadastro: novo participante', 2, 'admin/user/usuario.php', 'GET', [], [], true],
    ['Cadastro: editar existente', 2, 'admin/user/usuario.php', 'GET', ['id'=>55], [], false],
    ['Cadastro: criar participante', 2, 'admin/user/ajax/usuario-new.php', 'POST', [], ['id'=>0,'tipo'=>3], true],
    ['Cadastro: criar moderador', 2, 'admin/user/ajax/usuario-new.php', 'POST', [], ['id'=>0,'tipo'=>2], false],
    ['Cadastro: criar administrador', 2, 'admin/user/ajax/usuario-new.php', 'POST', [], ['id'=>0,'tipo'=>1], false],
    ['Cadastro: editar pelo POST', 2, 'admin/user/ajax/usuario-new.php', 'POST', [], ['id'=>6,'tipo'=>3], false],
    ['Cadastro: listar todos os usuários', 2, 'admin/user/usuarios.php', 'GET', [], [], false],
    ['Cadastro: excluir usuário', 2, 'admin/user/usuario-delete.php', 'GET', ['id'=>7], [], false],
    ['Inscrição: visualizar lista', 2, 'admin/inscricao/inscricoes.php', 'GET', [], [], true],
    ['Inscrição: nova manual', 2, 'admin/inscricao/inscricao.php', 'GET', [], [], true],
    ['Inscrição: edição negada', 2, 'admin/inscricao/inscricao.php', 'GET', ['id'=>7], [], false],
    ['Inscrição: salvar nova', 2, 'admin/inscricao/ajax/inscricao-new.php', 'POST', [], ['idInscricao'=>0], true],
    ['Inscrição: editar pelo POST', 2, 'admin/inscricao/ajax/inscricao-new.php', 'POST', [], ['idInscricao'=>7], false],
    ['Inscrição: excluir', 2, 'admin/inscricao/ajax/inscricao-delete.php', 'POST', [], ['id'=>7], false],
    ['Credenciamento: lista', 2, 'admin/credenciamento/index.php', 'GET', [], [], true],
    ['Credenciamento: registrar presença', 2, 'admin/credenciamento/ajax/salvar-presenca.php', 'POST', [], ['id'=>7], true],
    ['Relatório financeiro: visualizar', 2, 'admin/financeiro/index.php', 'GET', [], [], true],
    ['Relatório financeiro: exportar', 2, 'admin/financeiro/relatorio-pdf.php', 'GET', [], [], true],
    ['Pagamento: lista', 2, 'admin/financeiro/pagamentos.php', 'GET', [], [], true],
    ['Pagamento: consultar Asaas', 2, 'admin/financeiro/ajax/pagamento-sincronizar.php', 'POST', [], [], true],
    ['Pagamento: baixa manual paga', 2, 'admin/financeiro/ajax/pagamento-salvar.php', 'POST', [], ['status'=>'Pago'], true],
    ['Pagamento: baixa manual pendente', 2, 'admin/financeiro/ajax/pagamento-salvar.php', 'POST', [], ['status'=>'Pendente'], false],
    ['Pagamento: estornar', 2, 'admin/financeiro/ajax/pagamento-estornar.php', 'POST', [], [], false],
    ['Pagamento: cancelar', 2, 'admin/financeiro/ajax/pagamento-cancelar.php', 'POST', [], [], false],
    ['Pagamento: excluir', 2, 'admin/financeiro/ajax/pagamento-excluir.php', 'POST', [], [], false],
    ['Relatórios: geral', 2, 'admin/relatorios/index.php', 'GET', [], [], true],
    ['Relatórios: exportação evento', 2, 'admin/relatorios/evento-exportacao.php', 'GET', [], [], true],
    ['Relatórios: PDF fichas', 2, 'admin/relatorios/evento-exportar.php', 'GET', ['modo'=>'fichas_pdf'], [], true],
    ['Relatórios: consultar via AJAX', 2, 'admin/relatorios/ajax/consultar.php', 'POST', [], [], true],
    ['Admin: acesso banco Asaas', 2, 'admin/configuracoes/bancario.php', 'GET', [], [], false],
    ['Admin: acesso SMTP', 2, 'admin/configuracoes/email.php', 'GET', [], [], false],
    ['Admin: certificados', 2, 'admin/certificado/index.php', 'GET', [], [], false],
    ['Admin: dashboard', 2, 'admin/dashboard/index.php', 'GET', [], [], false],
    ['Admin: usuário participante', 3, 'admin/event/eventos.php', 'GET', [], [], false],
    ['Admin: acesso não logado', 0, 'admin/event/eventos.php', 'GET', [], [], false],
    ['Administrador: manter acesso', 1, 'admin/configuracoes/bancario.php', 'GET', [], [], true],
    ['Administrador: eventos', 1, 'admin/event/ajax/evento-new.php', 'POST', [], [], true],
];

$total = 0;
$falhas = 0;
foreach ($casos as [$rotulo, $tipo, $rota, $metodo, $get, $post, $esperado]) {
    $recebido = RotaModerador::permitido($tipo, $rota, $metodo, $get, $post);
    $total++;
    if ($recebido === $esperado) {
        echo '[OK] ' . $rotulo . PHP_EOL;
    } else {
        $falhas++;
        echo '[ERRO] ' . $rotulo . ' (esperado: ' . ($esperado?'permitido':'negado')
            . ', recebido: ' . ($recebido?'permitido':'negado') . ')' . PHP_EOL;
    }
}
echo PHP_EOL . $total . ' testes, ' . $falhas . ' falha(s).' . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
