<?php

declare(strict_types=1);

/** Cadastro assistido: não valida e-mail em nome do participante nem altera pagamentos. */
final class CadastroAssistidoRetiro
{
    public const GENEROS = ['Masculino', 'Feminino', 'Outro', 'Prefiro não informar'];
    public const DEFICIENCIAS = ['Não', 'Física', 'Visual', 'Auditiva', 'Intelectual', 'Múltipla', 'Outra'];
    public const QUESTOES = [
        'restricao_medicacao' => ['perguntar_restricao_medicacao', 'Possui restrição a alguma medicação?', 'medicacao_detalhes', 'Descreva a restrição ou medicação'],
        'precisa_acessibilidade' => ['perguntar_acessibilidade', 'Você precisa de algum recurso de acessibilidade?', 'acessibilidade_detalhes', 'Informe o recurso necessário'],
        'restricao_alimentar' => ['perguntar_restricao_alimentar', 'Possui restrição alimentar?', 'alimentar_detalhes', 'Descreva a restrição alimentar'],
    ];

    private static function texto(array $dados, string $campo, int $max): string
    {
        $v = $dados[$campo] ?? '';
        if (!is_scalar($v)) { throw new InvalidArgumentException('Campo inválido: ' . $campo); }
        $v = trim((string) $v);
        $tamanho = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
        if ($tamanho > $max) { throw new InvalidArgumentException('Campo muito longo: ' . $campo); }
        return $v;
    }

    public static function data(string $valor): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, new DateTimeZone('America/Sao_Paulo'));
        if (!$dt || $dt->format('Y-m-d') !== $valor || $valor < '1900-01-01') {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
        return $dt;
    }

    public static function perfil(array $dados): array
    {
        $nasc = self::texto($dados, 'data_nascimento', 10);
        $nacionalidade = self::texto($dados, 'nacionalidade', 80);
        $genero = self::texto($dados, 'genero', 40);
        $cep = preg_replace('/\D+/', '', self::texto($dados, 'cep', 12)) ?? '';
        if (self::data($nasc) > new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'))) {
            throw new InvalidArgumentException('A data de nascimento não pode ser futura.');
        }
        if ($nacionalidade === '' || !in_array($genero, self::GENEROS, true) || strlen($cep) !== 8) {
            throw new InvalidArgumentException('Informe nacionalidade, gênero e CEP válido.');
        }
        return ['nacionalidade' => $nacionalidade, 'data_nascimento' => $nasc, 'genero' => $genero,
            'pais' => 'Brasil', 'cep' => $cep, 'complemento' => self::texto($dados, 'complemento', 120) ?: null];
    }

    public static function idade(array $usuario, array $evento): int
    {
        $nascimento = self::data((string) ($usuario['data_nascimento'] ?? ''));
        $inicio = self::data(substr((string) ($evento['data_inicio'] ?? ''), 0, 10));
        if ($nascimento > $inicio) { throw new InvalidArgumentException('Data de nascimento posterior ao evento.'); }
        return $nascimento->diff($inicio)->y;
    }

    public static function habilitada(array $evento, string $campo): bool
    {
        return (int) ($evento[$campo] ?? 1) === 1;
    }

    public static function visitante(string $comunidade): bool
    {
        return strcasecmp(trim($comunidade), 'Visitante') === 0;
    }

    public static function valor(array $evento, bool $visitante): float
    {
        $v = $evento['valor_inscricao'] ?? $evento['valor'] ?? 0;
        if ($visitante && isset($evento['valor_visitante']) && $evento['valor_visitante'] !== '') {
            $v = $evento['valor_visitante'];
        }
        return round(max(0, (float) $v), 2);
    }

    /** Validação no servidor, independentemente do HTML e JavaScript. */
    public static function respostas(array $post, array $usuario, array $evento, array $termos, array $camisetas): array
    {
        self::perfil($usuario);
        $idade = self::idade($usuario, $evento);
        $min = (int) ($evento['idade_minima'] ?? 0);
        $max = (int) ($evento['idade_maxima'] ?? 0);
        if (($min > 0 && $idade < $min) || ($max > 0 && $idade > $max)) {
            throw new InvalidArgumentException('A idade do participante não atende às regras do evento.');
        }
        $r = [];
        foreach (self::QUESTOES as $campo => [$flag, $rotulo, $detalhes, $instrucao]) {
            $r[$campo] = 0;
            $r[$detalhes] = null;
            if (!self::habilitada($evento, $flag)) { continue; }
            $resposta = self::texto($post, $campo, 1);
            if (!in_array($resposta, ['0', '1'], true)) { throw new InvalidArgumentException('Responda: ' . $rotulo); }
            $r[$campo] = (int) $resposta;
            if ($resposta === '1') {
                $r[$detalhes] = self::texto($post, $detalhes, 2000);
                if ($r[$detalhes] === '') { throw new InvalidArgumentException($instrucao . '.'); }
            }
        }
        $r['deficiencia'] = 'Não';
        $r['deficiencia_detalhes'] = null;
        if (self::habilitada($evento, 'perguntar_deficiencia')) {
            $r['deficiencia'] = self::texto($post, 'deficiencia', 40);
            if (!in_array($r['deficiencia'], self::DEFICIENCIAS, true)) { throw new InvalidArgumentException('Responda se possui deficiência.'); }
            if ($r['deficiencia'] !== 'Não') { $r['deficiencia_detalhes'] = self::texto($post, 'deficiencia_detalhes', 2000) ?: null; }
        }
        $r['responsavel_nome'] = $r['responsavel_telefone_1'] = $r['responsavel_telefone_2'] = null;
        $r['responsavel_cpf'] = null; // RETIRO_CPF_RESPONSAVEL_CADASTRO_ADMIN_V1
        $r['responsavel_autorizacao_texto'] = $r['responsavel_autorizacao_aceita_em'] = null;
        if ($idade < 18) {
            $nome = self::texto($post, 'responsavel_nome', 160);
            $cpfResponsavel = Usuario::normalizarCpf(self::texto($post, 'responsavel_cpf', 20));
            if (!Usuario::cpfValido($cpfResponsavel)) {
                throw new InvalidArgumentException('Informe CPF válido do responsável legal.');
            }
            $tel1 = preg_replace('/\D+/', '', self::texto($post, 'responsavel_telefone_1', 20)) ?? '';
            $tel2 = preg_replace('/\D+/', '', self::texto($post, 'responsavel_telefone_2', 20)) ?? '';
            if ($nome === '' || !in_array(strlen($tel1), [10, 11], true) || ($tel2 !== '' && !in_array(strlen($tel2), [10, 11], true))) {
                throw new InvalidArgumentException('Informe o nome e telefone(s) válidos do responsável legal.');
            }
            if (self::texto($post, 'responsavel_autorizacao', 1) !== '1') {
                throw new InvalidArgumentException('O responsável legal deve autorizar a participação antes de registrar a inscrição.');
            }
            $r['responsavel_nome'] = $nome;
            $r['responsavel_cpf'] = $cpfResponsavel;
            $r['responsavel_telefone_1'] = $tel1;
            $r['responsavel_telefone_2'] = $tel2 ?: null;
            // A equipe registra uma autorização realmente recebida; não simula aceite eletrônico do responsável.
            $r['responsavel_autorizacao_texto'] = InscricaoPublicaService::textoAutorizacaoMenor($evento)
                . "\nAutorização declarada pelo responsável e registrada em cadastro assistido pela equipe (usuário #" . (int) Auth::id() . '). Não constitui assinatura eletrônica do responsável.';
            $r['responsavel_autorizacao_aceita_em'] = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        }
        $enviados = $post['termos'] ?? [];
        if (!is_array($enviados)) { throw new InvalidArgumentException('Termos inválidos.'); }
        foreach ($enviados as $id) { if (!is_scalar($id)) { throw new InvalidArgumentException('Termos inválidos.'); } }
        $enviados = array_map('strval', $enviados);
        $aceites = [];
        foreach ($termos as $termo) {
            $aceito = in_array((string) $termo['idTermo'], $enviados, true);
            if (!$aceito && (int) ($termo['obrigatorio'] ?? 0) === 1) {
                throw new InvalidArgumentException('Registre o aceite real de todos os termos obrigatórios.');
            }
            if ($aceito) { $aceites[] = $termo; }
        }
        $camiseta = '';
        if ((int) ($evento['camiseta_ativa'] ?? 0) === 1 && $camisetas !== []) {
            $camiseta = self::texto($post, 'camiseta', 40);
            if (!in_array($camiseta, $camisetas, true)) { throw new InvalidArgumentException('Selecione um tamanho de camiseta válido.'); }
        }
        return ['dados' => $r, 'termos' => $aceites, 'camiseta' => $camiseta];
    }

    /** Chamado dentro da mesma transação da nova inscrição. */
    public static function gravar(PDO $db, int $id, array $usuario, array $respostas, bool $visitante): void
    {
        if (!$db->inTransaction() || $id <= 0) { throw new LogicException('Inscrição assistida fora da transação.'); }
        $dados = ['idInscricao' => $id, 'visitante' => (int) $visitante];
        foreach (['nacionalidade','genero','pais','cep','logradouro','numero','complemento','bairro','cidade','estado','idComunidade'] as $campo) {
            $dados[$campo] = $usuario[$campo] ?? null;
        }
        $dados += $respostas['dados'];
        $colunas = array_keys($dados);
        $sql = 'INSERT INTO inscricao_dados_adicionais (`' . implode('`,`', $colunas) . '`) VALUES (:' . implode(',:', $colunas) . ')';
        $db->prepare($sql)->execute($dados);
        $stmt = $db->prepare('INSERT INTO inscricao_termos_aceites (idInscricao,idTermo,titulo_snapshot,descricao_snapshot,url_snapshot,obrigatorio,aceito_em,ip,user_agent) VALUES (:inscricao,:termo,:titulo,:descricao,:url,:obrigatorio,NOW(),:ip,:origem)');
        foreach ($respostas['termos'] as $termo) {
            $stmt->execute(['inscricao'=>$id, 'termo'=>(int)$termo['idTermo'], 'titulo'=>(string)$termo['titulo'],
                'descricao'=>$termo['descricao'] ?? null, 'url'=>$termo['url'] ?? null, 'obrigatorio'=>(int)$termo['obrigatorio'],
                'ip'=>substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'origem'=>substr('Aceite declarado e registrado pela equipe (usuário #' . (int)Auth::id() . '); cadastro assistido. ' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500)]);
        }
    }
}
