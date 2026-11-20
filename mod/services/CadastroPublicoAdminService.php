<?php

declare(strict_types=1);

/** Cadastro de participante e questionário público, preenchidos pelo administrador no mesmo formulário.
 * Não recebe meio de pagamento, não registra recebimento, não valida e-mail por terceiros.
 */
final class CadastroPublicoAdminService
{
    public static function criar(PDO $db, array $dadosUsuario, array $perfil, array $post): array
    {
        // Usuario e Evento utilizam Database::connect(), enquanto settings.php
        // expõe Config::getDB(). A transação DEVE usar a mesma conexão PDO
        // dos models, senão um erro após criar o usuário não desfaz a criação.
        $db = Database::connect();
        if ((int) ($dadosUsuario['tipo'] ?? 0) !== 3 || (int) ($dadosUsuario['ativo'] ?? 0) !== 1) {
            throw new InvalidArgumentException('O questionário completo exige cadastro de participante ativo.');
        }
        $idEvento = filter_var($post['evento_questionario'] ?? null, FILTER_VALIDATE_INT);
        if (!$idEvento || $idEvento <= 0) {
            throw new InvalidArgumentException('Selecione o evento para preencher o questionário público.');
        }
        $eventoModel = new Evento();
        $config = $eventoModel->buscarConfiguracao($idEvento);
        if (!$config || (int) ($config['ativo'] ?? 0) !== 1) {
            throw new InvalidArgumentException('O evento não está ativo.');
        }
        if ((int) ($config['inscricao_aberta'] ?? 1) !== 1) {
            throw new InvalidArgumentException('As inscrições deste evento estão fechadas.');
        }
        $agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        foreach ([['inscricao_inicio', 'O período de inscrições ainda não começou.'], ['inscricao_fim', 'O período de inscrições foi encerrado.']] as $i => [$campo, $mensagem]) {
            $data = trim((string) ($config[$campo] ?? ''));
            if ($data === '' || str_starts_with($data, '0000-00-00')) { continue; }
            $limite = new DateTimeImmutable($data, new DateTimeZone('America/Sao_Paulo'));
            if (($i === 0 && $agora < $limite) || ($i === 1 && $agora > $limite)) {
                throw new InvalidArgumentException($mensagem);
            }
        }
        $vagas = (int) ($config['vagas'] ?? 0);
        $inscricaoModel = new Inscricao($db);
        if ($vagas > 0 && $inscricaoModel->totalEvento($idEvento) >= $vagas) {
            throw new InvalidArgumentException('Não existem vagas disponíveis neste evento.');
        }
        $svc = new InscricaoPublicaService($db);
        $evento = $svc->buscarEvento($idEvento);
        $perfilUsuario = array_merge($dadosUsuario, $perfil, [
            'idComunidade' => (int) ($dadosUsuario['comunidade'] ?? 0),
            'complemento' => $perfil['complemento'] ?? null,
        ]);
        $respostas = CadastroAssistidoRetiro::respostas($post, $perfilUsuario, $evento,
            $svc->termos($idEvento), $svc->camisetas($idEvento));
        $stmt = $db->prepare('SELECT nome_comunidade FROM minha_comunidade WHERE id = :id');
        $stmt->execute([':id' => $perfilUsuario['idComunidade']]);
        $nomeComunidade = $stmt->fetchColumn();
        if ($nomeComunidade === false) { throw new InvalidArgumentException('Comunidade inválida.'); }
        $visitante = CadastroAssistidoRetiro::visitante((string) $nomeComunidade);
        $valor = CadastroAssistidoRetiro::valor($evento, $visitante);
        $pagoObrigatorio = (int) ($evento['pagamento_obrigatorio'] ?? 1) === 1 && $valor > 0;
        if ($db->inTransaction()) { throw new LogicException('Transação inesperada no cadastro completo.'); }
        $db->beginTransaction();
        try {
            // Criação de usuário + respostas + inscrição + pagamento pendente são atômicos.
            $usuario = new Usuario();
            if (!$usuario->salvar($dadosUsuario)) { throw new RuntimeException('Não foi possível salvar o participante.'); }
            $idUsuario = (int) $db->lastInsertId();
            if ($idUsuario <= 0) { throw new RuntimeException('ID do participante não disponível.'); }
            $up = $db->prepare('UPDATE usuarios SET nacionalidade=:nacionalidade, data_nascimento=:nascimento, genero=:genero, pais=:pais, cep=:cep, complemento=:complemento WHERE id=:id');
            $up->execute([
                ':nacionalidade' => $perfil['nacionalidade'], ':nascimento' => $perfil['data_nascimento'],
                ':genero' => $perfil['genero'], ':pais' => $perfil['pais'], ':cep' => $perfil['cep'],
                ':complemento' => $perfil['complemento'], ':id' => $idUsuario,
            ]);
            $inscricaoDados = [
                'idInscricao' => 0, 'idEvento' => $idEvento, 'idUsuario' => $idUsuario,
                'nome' => $dadosUsuario['nome'], 'cpf' => $dadosUsuario['cpf'], 'rg' => null,
                'email' => $dadosUsuario['email'], 'telefone' => $dadosUsuario['telefone'],
                'sexo' => $perfil['genero'], 'data_nascimento' => $perfil['data_nascimento'],
                'cidade' => $dadosUsuario['cidade'], 'estado' => $dadosUsuario['estado'],
                'camiseta' => $respostas['camiseta'], 'observacoes' => trim((string) ($post['observacoes_questionario'] ?? '')),
                'contato_emergencia' => null, 'telefone_emergencia' => null,
                'status' => $pagoObrigatorio ? 'Pendente' : 'Confirmada',
                'pagamento' => $pagoObrigatorio ? 'Pendente' : 'Pago',
                'presenca' => 0, 'certificado' => (int) ($evento['certificado_ativo'] ?? $evento['certificado'] ?? 0),
                'valor' => $valor, 'valor_pago' => $pagoObrigatorio ? 0 : $valor,
                'forma_pagamento' => null, 'codigo_pagamento' => null,
            ];
            if (!$inscricaoModel->salvar($inscricaoDados)) { throw new RuntimeException('Não foi possível salvar a inscrição.'); }
            $idInscricao = $inscricaoModel->ultimoId();
            if ($idInscricao <= 0) { throw new RuntimeException('ID da inscrição indisponível.'); }
            CadastroAssistidoRetiro::gravar($db, $idInscricao, $perfilUsuario, $respostas, $visitante);
            $idPagamento = (new Pagamento($db))->criarParaInscricao($idInscricao);
            $db->commit();
            return ['idUsuario' => $idUsuario, 'idInscricao' => $idInscricao, 'idPagamento' => $idPagamento];
        } catch (Throwable $erro) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $erro;
        }
    }
}
