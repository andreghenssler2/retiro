<?php

declare(strict_types=1);

/**
 * Rotas permitidas à equipe Moderadora. Lista explícita, negação por padrão.
 * Esta verificação roda em Middleware::auth() antes das ações do controlador.
 * Não altera a autorização dos administradores.
 */
final class RotaModerador
{
    public static function permitido(
        int $tipo,
        string $rota,
        string $metodo = 'GET',
        array $get = [],
        array $post = []
    ): bool {
        $rota = ltrim(str_replace('\\', '/', $rota), '/');
        $metodo = strtoupper($metodo);

        if ($tipo === 1) {
            return true;
        }
        if ($tipo !== 2 || !str_starts_with($rota, 'admin/')) {
            return false;
        }

        // Exclusivamente leitura: nenhum CRUD de evento.
        $somenteLeitura = [
            'admin/index.php',
            'admin/event/eventos.php',
            'admin/event/ajax/eventos-lista.php',
            'admin/event/ajax/evento-view.php',
            'admin/inscricao/inscricoes.php',
            'admin/inscricao/ajax/inscricoes-lista.php',
            'admin/inscricao/ajax/inscricao-view.php',
            'admin/inscricao/ajax/evento-get.php',
            'admin/inscricao/ajax/usuario-get.php',
            'admin/inscricao/ajax/usuario-search.php',
            'admin/credenciamento/index.php',
            'admin/credenciamento/ajax/lista.php',
            'admin/credenciamento/ajax/buscar.php',
            'admin/financeiro/index.php',
            'admin/financeiro/financeiro.php',
            'admin/financeiro/pagamentos.php',
            'admin/financeiro/ajax/pagamentos-listar.php',
            'admin/financeiro/ajax/pagamentos-cards.php',
            'admin/financeiro/ajax/pagamento-visualizar.php',
            'admin/financeiro/ajax/pagamento-form.php',
            'admin/financeiro/relatorio-pdf.php',
            'admin/relatorios.php',
            'admin/relatorios/index.php',
            'admin/relatorios/pdf.php',
            'admin/relatorios/evento-exportacao.php',
            'admin/relatorios/evento-exportar.php',
        ];
        if (in_array($rota, $somenteLeitura, true)) {
            // Relatórios via POST geram somente consulta/exportação; o tratamento
            // de consultas ainda exige token CSRF quando previsto no endpoint.
            return in_array($metodo, ['GET', 'POST'], true);
        }

        if ($rota === 'admin/user/usuario.php') {
            return $metodo === 'GET' && (int) ($get['id'] ?? 0) === 0;
        }
        if ($rota === 'admin/user/ajax/usuario-new.php') {
            // Anti-escalada: um moderador não edita contas e não cria novos
            // moderadores ou administradores, mesmo adulterando o POST.
            return $metodo === 'POST'
                && (int) ($post['id'] ?? 0) === 0
                && (int) ($post['tipo'] ?? 3) === 3;
        }
        if ($rota === 'admin/inscricao/inscricao.php') {
            return $metodo === 'GET' && (int) ($get['id'] ?? 0) === 0;
        }
        if ($rota === 'admin/inscricao/ajax/inscricao-new.php') {
            return $metodo === 'POST' && (int) ($post['idInscricao'] ?? 0) === 0;
        }
        if ($rota === 'admin/credenciamento/ajax/salvar-presenca.php') {
            return $metodo === 'POST';
        }
        if ($rota === 'admin/financeiro/ajax/relatorio-financeiro.php'
            || $rota === 'admin/relatorios/ajax/consultar.php') {
            return $metodo === 'POST';
        }
        if ($rota === 'admin/financeiro/ajax/pagamento-sincronizar.php') {
            return $metodo === 'POST';
        }
        if ($rota === 'admin/financeiro/ajax/pagamento-salvar.php') {
            // Confirmação manual; validação do pagamento existente é feita
            // também dentro do controlador (forma de pagamento e estado).
            return $metodo === 'POST'
                && trim((string) ($post['status'] ?? '')) === 'Pago';
        }

        // Desabilita exclusões, edição de eventos, configurações, certificados,
        // estornos, cancelamentos e qualquer rota administrativa desconhecida.
        return false;
    }

    public static function validar(): void
    {
        $raiz = realpath(dirname(__DIR__, 2));
        $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($raiz === false || $script === false) {
            return;
        }

        $raiz = rtrim(str_replace('\\', '/', $raiz), '/');
        $script = str_replace('\\', '/', $script);
        if (!str_starts_with($script, $raiz . '/admin/')) {
            return;
        }

        $rota = ltrim(substr($script, strlen($raiz)), '/');
        $tipo = (int) Auth::tipo();
        if (self::permitido(
            $tipo,
            $rota,
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $_GET,
            $_POST
        )) {
            return;
        }

        http_response_code(403);
        $ajax = str_contains($rota, '/ajax/')
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        if ($ajax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'status' => false,
                'sucesso' => false,
                'msg' => 'Você não possui permissão para executar esta ação.',
                'mensagem' => 'Você não possui permissão para executar esta ação.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Acesso negado: operação não permitida para este perfil.';
        }
        exit;
    }
}
