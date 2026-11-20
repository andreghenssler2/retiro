<?php

declare(strict_types=1);

/** RETIRO_ADMIN_EDICAO_COMPLETA_V1
 * Edicao administrativa do perfil e das respostas de uma inscricao JA existente.
 * Nunca cria inscricoes, nao modifica status/valor/pagamento, e preserva aceites historicos.
 */
final class CadastroAdminEdicaoService
{
    private static function texto(mixed $entrada, int $max, string $campo): string
    {
        if (!is_scalar($entrada) && $entrada !== null) {
            throw new InvalidArgumentException('Valor invalido para ' . $campo . '.');
        }
        $valor = trim((string) $entrada);
        if ((function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor)) > $max) {
            throw new InvalidArgumentException('Texto muito longo em ' . $campo . '.');
        }
        return $valor;
    }

    public static function atualizarPerfil(PDO $db, int $idUsuario, array $post): void
    {
        if ($idUsuario <= 0 || !$db->inTransaction()) {
            throw new LogicException('A edicao do perfil exige uma transacao ativa.');
        }
        $campos = [];
        $params = [':id' => $idUsuario];
        foreach (['nacionalidade' => 80, 'complemento' => 120] as $campo => $limite) {
            if (!array_key_exists($campo, $post)) { continue; }
            $texto = self::texto($post[$campo], $limite, $campo);
            if ($campo === 'nacionalidade' && $texto === '') { continue; } // legado sem o dado
            $campos[] = '`' . $campo . '` = :' . $campo;
            $params[':' . $campo] = $texto !== '' ? $texto : null;
        }
        if (array_key_exists('data_nascimento', $post)) {
            $data = self::texto($post['data_nascimento'], 10, 'data_nascimento');
            if ($data !== '') {
                $nascimento = DateTimeImmutable::createFromFormat('!Y-m-d', $data, new DateTimeZone('America/Sao_Paulo'));
                if (!$nascimento || $nascimento->format('Y-m-d') !== $data || $data < '1900-01-01'
                    || $nascimento > new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'))) {
                    throw new InvalidArgumentException('Data de nascimento invalida.');
                }
                $campos[] = '`data_nascimento` = :data_nascimento';
                $params[':data_nascimento'] = $data;
            }
        }
        if (array_key_exists('genero', $post)) {
            $genero = self::texto($post['genero'], 40, 'genero');
            if ($genero !== '') {
                if (!in_array($genero, CadastroAssistidoRetiro::GENEROS, true)) {
                    throw new InvalidArgumentException('Genero invalido.');
                }
                $campos[] = '`genero` = :genero';
                $params[':genero'] = $genero;
            }
        }
        if (array_key_exists('cep', $post)) {
            $texto = self::texto($post['cep'], 12, 'CEP');
            $cep = preg_replace('/\D+/', '', $texto) ?? '';
            if ($cep !== '') {
                if (strlen($cep) !== 8) { throw new InvalidArgumentException('CEP deve conter 8 digitos.'); }
                $campos[] = '`cep` = :cep';
                $params[':cep'] = $cep;
            }
        }
        if (array_key_exists('pais', $post) && self::texto($post['pais'], 40, 'pais') !== 'Brasil') {
            throw new InvalidArgumentException('O pais do cadastro esta fixado como Brasil.');
        }
        if ($campos === []) { return; }
        $stmt = $db->prepare('UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id AND tipo = 3');
        $stmt->execute($params);
    }

    private static function buscarInscricao(PDO $db, int $idUsuario, int $idEvento): array
    {
        $stmt = $db->prepare('SELECT i.idInscricao, i.camiseta, i.observacoes, e.* FROM inscricoes i '
            . 'INNER JOIN eventos e ON e.idEvento = i.idEvento '
            . 'WHERE i.idUsuario = :usuario AND i.idEvento = :evento '
            . 'ORDER BY i.idInscricao DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([':usuario' => $idUsuario, ':evento' => $idEvento]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$linha) { throw new InvalidArgumentException('Este usuario nao possui inscricao neste evento.'); }
        return $linha;
    }

    public static function atualizarQuestionario(PDO $db, int $idUsuario, array $post): void
    {
        if (($post['cadastro_completo_evento'] ?? '') !== '1') { return; }
        if (!$db->inTransaction()) { throw new LogicException('Transacao de inscricao ausente.'); }
        $idEvento = filter_var($post['evento_questionario'] ?? null, FILTER_VALIDATE_INT);
        if (!$idEvento || $idEvento <= 0) {
            throw new InvalidArgumentException('Escolha uma inscricao ja existente para editar as respostas.');
        }
        $inscricao = self::buscarInscricao($db, $idUsuario, $idEvento);
        $idInscricao = (int) $inscricao['idInscricao'];
        $campos = [];
        $params = [':id' => $idInscricao];
        foreach (CadastroAssistidoRetiro::QUESTOES as $campo => [$flag, $rotulo, $detalhes, $instrucao]) {
            if ((int) ($inscricao[$flag] ?? 1) !== 1 || !array_key_exists($campo, $post)) { continue; }
            $resposta = self::texto($post[$campo], 1, $rotulo);
            if (!in_array($resposta, ['0', '1'], true)) {
                throw new InvalidArgumentException('Responda: ' . $rotulo);
            }
            $valorDetalhe = null;
            if ($resposta === '1') {
                $valorDetalhe = self::texto($post[$detalhes] ?? '', 2000, $instrucao);
                if ($valorDetalhe === '') { throw new InvalidArgumentException('Detalhe obrigatorio: ' . $instrucao); }
            }
            $campos[] = '`' . $campo . '` = :' . $campo;
            $campos[] = '`' . $detalhes . '` = :' . $detalhes;
            $params[':' . $campo] = (int) $resposta;
            $params[':' . $detalhes] = $valorDetalhe;
        }
        if ((int) ($inscricao['perguntar_deficiencia'] ?? 1) === 1 && array_key_exists('deficiencia', $post)) {
            $tipo = self::texto($post['deficiencia'], 40, 'deficiencia');
            if ($tipo !== '') {
                if (!in_array($tipo, CadastroAssistidoRetiro::DEFICIENCIAS, true)) {
                    throw new InvalidArgumentException('Resposta invalida sobre deficiencia.');
                }
                $campos[] = '`deficiencia` = :deficiencia';
                $campos[] = '`deficiencia_detalhes` = :deficiencia_detalhes';
                $params[':deficiencia'] = $tipo;
                $params[':deficiencia_detalhes'] = $tipo === 'Não' ? null : (self::texto($post['deficiencia_detalhes'] ?? '', 2000, 'detalhes de deficiencia') ?: null);
            }
        }

        // Dados do responsavel somente quando declarados. Nunca forcar ou reescrever
        // autorizacao antiga; registrar uma nova declaracao apenas com checkbox explicito.
        $respColunas = ['responsavel_nome' => 160, 'responsavel_cpf' => 20,
                        'responsavel_telefone_1' => 20, 'responsavel_telefone_2' => 20];
        foreach ($respColunas as $campo => $limite) {
            if (!array_key_exists($campo, $post)) { continue; }
            $valor = self::texto($post[$campo], $limite, $campo);
            if ($valor === '') { continue; } // nao apagar historico involuntariamente
            if ($campo === 'responsavel_cpf') {
                $valor = Usuario::normalizarCpf($valor);
                if (!Usuario::cpfValido($valor)) { throw new InvalidArgumentException('CPF do responsavel invalido.'); }
            }
            if (str_starts_with($campo, 'responsavel_telefone_')) {
                $valor = preg_replace('/\D+/', '', $valor) ?? '';
                if (!in_array(strlen($valor), [10, 11], true)) { throw new InvalidArgumentException('Telefone do responsavel invalido.'); }
            }
            $campos[] = '`' . $campo . '` = :' . $campo;
            $params[':' . $campo] = $valor;
        }
        if (($post['responsavel_autorizacao'] ?? '') === '1') {
            $texto = 'Autorizacao declarada pelo responsavel e registrada pela equipe (usuario #'
                . (int) Auth::id() . ') em ' . date('d/m/Y H:i:s')
                . '. Nao constitui assinatura eletronica do responsavel.';
            // Preserve a primeira declaracao documentada no historico.
            $campos[] = 'responsavel_autorizacao_aceita_em = COALESCE(responsavel_autorizacao_aceita_em, NOW())';
            $campos[] = 'responsavel_autorizacao_texto = COALESCE(responsavel_autorizacao_texto, :texto_aut)';
            $params[':texto_aut'] = $texto;
        }
        if ($campos !== []) {
            // Cadastra estrutura complementar para inscricoes legadas sem respostas.
            $stmt = $db->prepare('INSERT IGNORE INTO inscricao_dados_adicionais (idInscricao) VALUES (:id)');
            $stmt->execute([':id' => $idInscricao]);
            $stmt = $db->prepare('UPDATE inscricao_dados_adicionais SET ' . implode(', ', $campos)
                . ' WHERE idInscricao = :id');
            $stmt->execute($params);
        }
        $svc = new InscricaoPublicaService($db);
        $camiseta = array_key_exists('camiseta', $post) ? self::texto($post['camiseta'], 40, 'camiseta') : '';
        if ($camiseta !== '') {
            $tamanhos = $svc->camisetas($idEvento);
            if ((int) ($inscricao['camiseta_ativa'] ?? 0) !== 1 || !in_array($camiseta, $tamanhos, true)) {
                throw new InvalidArgumentException('Tamanho de camiseta nao disponivel neste evento.');
            }
            $db->prepare('UPDATE inscricoes SET camiseta = :camiseta WHERE idInscricao = :id')
                ->execute([':camiseta' => $camiseta, ':id' => $idInscricao]);
        }
        if (array_key_exists('observacoes_questionario', $post)) {
            $observacoes = self::texto($post['observacoes_questionario'], 1000, 'observacoes');
            $db->prepare('UPDATE inscricoes SET observacoes = :observacoes WHERE idInscricao = :id')
                ->execute([':observacoes' => $observacoes !== '' ? $observacoes : null, ':id' => $idInscricao]);
        }
        $enviados = $post['termos'] ?? [];
        if (!is_array($enviados)) { throw new InvalidArgumentException('Lista de termos invalida.'); }
        $termos = $svc->termos($idEvento);
        $permitidos = [];
        foreach ($termos as $t) { $permitidos[(string) $t['idTermo']] = $t; }
        $ja = $db->prepare('SELECT idTermo FROM inscricao_termos_aceites WHERE idInscricao = :id');
        $ja->execute([':id' => $idInscricao]);
        $existentes = array_fill_keys(array_map('strval', $ja->fetchAll(PDO::FETCH_COLUMN)), true);
        foreach ($enviados as $idTermo) {
            if (!is_scalar($idTermo) || !isset($permitidos[(string) $idTermo])) {
                throw new InvalidArgumentException('Termo informado nao pertence ao evento.');
            }
            $chave = (string) $idTermo;
            if (isset($existentes[$chave])) { continue; }
            $t = $permitidos[$chave];
            $stmt = $db->prepare('INSERT INTO inscricao_termos_aceites '
                . '(idInscricao,idTermo,titulo_snapshot,descricao_snapshot,url_snapshot,obrigatorio,aceito_em,ip,user_agent) '
                . 'VALUES (:inscricao,:termo,:titulo,:descricao,:url,:obrigatorio,NOW(),:ip,:origem)');
            $stmt->execute([
                ':inscricao' => $idInscricao, ':termo' => (int) $t['idTermo'],
                ':titulo' => (string) $t['titulo'], ':descricao' => $t['descricao'] ?? null,
                ':url' => $t['url'] ?? null, ':obrigatorio' => (int) ($t['obrigatorio'] ?? 0),
                ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                ':origem' => substr('Aceite declarado e registrado pela equipe (usuario #' . (int) Auth::id()
                    . '); edicao administrativa. ' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
            $existentes[$chave] = true;
        }
        foreach ($permitidos as $chave => $t) {
            if ((int) ($t['obrigatorio'] ?? 0) === 1 && !isset($existentes[$chave])) {
                throw new InvalidArgumentException('Registre o aceite real dos termos obrigatorios ainda pendentes.');
            }
        }
    }
}
