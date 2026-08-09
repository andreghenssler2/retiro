<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

class Certificado
{
    private PDO $db;

    public function __construct(?PDO $conexao = null)
    {
        $this->db = $conexao ?? Database::connect();
    }

    public function listarModelos(): array
    {
        $sql = "
            SELECT
                cm.*,
                e.titulo AS eventoTitulo,
                e.data_inicio,
                e.data_fim
            FROM certificado_modelos cm
            INNER JOIN eventos e
                ON e.idEvento = cm.idEvento
            ORDER BY e.data_inicio DESC, e.titulo ASC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarModelo(int $idModelo): array|false
    {
        if ($idModelo <= 0) {
            return false;
        }

        $sql = "
            SELECT
                cm.*,
                e.titulo AS eventoTitulo,
                e.data_inicio,
                e.data_fim,
                e.local AS eventoLocal,
                e.cidade AS eventoCidade,
                e.estado AS eventoEstado
            FROM certificado_modelos cm
            INNER JOIN eventos e
                ON e.idEvento = cm.idEvento
            WHERE cm.idModelo = :idModelo
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idModelo' => $idModelo]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarModeloPorEvento(int $idEvento): array|false
    {
        if ($idEvento <= 0) {
            return false;
        }

        $sql = "
            SELECT
                cm.*,
                e.titulo AS eventoTitulo,
                e.data_inicio,
                e.data_fim,
                e.local AS eventoLocal,
                e.cidade AS eventoCidade,
                e.estado AS eventoEstado
            FROM certificado_modelos cm
            INNER JOIN eventos e
                ON e.idEvento = cm.idEvento
            WHERE cm.idEvento = :idEvento
              AND cm.ativo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idEvento' => $idEvento]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function modeloExisteParaEvento(
        int $idEvento,
        int $ignorarIdModelo = 0
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM certificado_modelos
            WHERE idEvento = :idEvento
        ";

        $params = [':idEvento' => $idEvento];

        if ($ignorarIdModelo > 0) {
            $sql .= " AND idModelo <> :idModelo ";
            $params[':idModelo'] = $ignorarIdModelo;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function salvarModelo(array $dados): int|false
    {
        $sql = "
            INSERT INTO certificado_modelos (
                idEvento,
                nome,
                titulo,
                texto,
                cargaHoraria,
                localEmissao,
                corTitulo,
                corTexto,
                imagemFundo,
                logo,
                assinatura1Imagem,
                assinatura1Nome,
                assinatura1Cargo,
                assinatura2Imagem,
                assinatura2Nome,
                assinatura2Cargo,
                ativo,
                criadoPor
            ) VALUES (
                :idEvento,
                :nome,
                :titulo,
                :texto,
                :cargaHoraria,
                :localEmissao,
                :corTitulo,
                :corTexto,
                :imagemFundo,
                :logo,
                :assinatura1Imagem,
                :assinatura1Nome,
                :assinatura1Cargo,
                :assinatura2Imagem,
                :assinatura2Nome,
                :assinatura2Cargo,
                :ativo,
                :criadoPor
            )
        ";

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute($this->parametrosModelo($dados));

        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function editarModelo(array $dados): bool
    {
        $idModelo = (int) ($dados['idModelo'] ?? 0);

        if ($idModelo <= 0) {
            throw new InvalidArgumentException('Modelo de certificado inválido.');
        }

        $sql = "
            UPDATE certificado_modelos
            SET
                idEvento = :idEvento,
                nome = :nome,
                titulo = :titulo,
                texto = :texto,
                cargaHoraria = :cargaHoraria,
                localEmissao = :localEmissao,
                corTitulo = :corTitulo,
                corTexto = :corTexto,
                imagemFundo = :imagemFundo,
                logo = :logo,
                assinatura1Imagem = :assinatura1Imagem,
                assinatura1Nome = :assinatura1Nome,
                assinatura1Cargo = :assinatura1Cargo,
                assinatura2Imagem = :assinatura2Imagem,
                assinatura2Nome = :assinatura2Nome,
                assinatura2Cargo = :assinatura2Cargo,
                ativo = :ativo
            WHERE idModelo = :idModelo
            LIMIT 1
        ";

        $params = $this->parametrosModelo($dados);
        unset($params[':criadoPor']);
        $params[':idModelo'] = $idModelo;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function excluirModelo(int $idModelo): bool
    {
        if ($idModelo <= 0) {
            return false;
        }

        $stmtEmitidos = $this->db->prepare("
            SELECT COUNT(*)
            FROM certificados_emitidos
            WHERE idModelo = :idModelo
        ");
        $stmtEmitidos->execute([':idModelo' => $idModelo]);

        if ((int) $stmtEmitidos->fetchColumn() > 0) {
            throw new RuntimeException(
                'O modelo já possui certificados emitidos e não pode ser excluído. Desative-o.'
            );
        }

        $stmt = $this->db->prepare("
            DELETE FROM certificado_modelos
            WHERE idModelo = :idModelo
        ");
        $stmt->execute([':idModelo' => $idModelo]);

        return $stmt->rowCount() > 0;
    }

    public function buscarDadosInscricao(int $idInscricao): array|false
    {
        if ($idInscricao <= 0) {
            return false;
        }

        $sql = "
            SELECT
                i.idInscricao,
                i.idEvento,
                i.idUsuario,
                i.status AS inscricaoStatus,
                i.pagamento AS pagamentoStatus,
                i.presenca,
                i.certificado,
                COALESCE(u.nome, i.nome) AS nome,
                COALESCE(u.email, i.email) AS email,
                COALESCE(u.cpf, i.cpf) AS cpf,
                e.titulo AS eventoTitulo,
                e.data_inicio,
                e.data_fim,
                e.local AS eventoLocal,
                e.cidade AS eventoCidade,
                e.estado AS eventoEstado,
                e.certificado AS eventoCertificado,
                e.certificado_ativo AS eventoCertificadoAtivo
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE i.idInscricao = :idInscricao
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idInscricao' => $idInscricao]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarAtivoPorInscricao(int $idInscricao): array|false
    {
        $sql = "
            SELECT *
            FROM certificados_emitidos
            WHERE idInscricao = :idInscricao
              AND status <> 'Revogado'
            ORDER BY idCertificado DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idInscricao' => $idInscricao]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarEmitido(int $idCertificado): array|false
    {
        if ($idCertificado <= 0) {
            return false;
        }

        $sql = "
            SELECT
                ce.*,
                cm.titulo AS modeloTitulo,
                e.titulo AS eventoTituloAtual
            FROM certificados_emitidos ce
            LEFT JOIN certificado_modelos cm
                ON cm.idModelo = ce.idModelo
            LEFT JOIN eventos e
                ON e.idEvento = ce.idEvento
            WHERE ce.idCertificado = :idCertificado
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':idCertificado' => $idCertificado]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorCodigo(string $codigo): array|false
    {
        $codigo = strtoupper(trim($codigo));

        if ($codigo === '') {
            return false;
        }

        $sql = "
            SELECT
                ce.*,
                cm.titulo AS modeloTitulo,
                cm.localEmissao
            FROM certificados_emitidos ce
            LEFT JOIN certificado_modelos cm
                ON cm.idModelo = ce.idModelo
            WHERE ce.codigo = :codigo
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarEmitido(array $dados): int|false
    {
        $sql = "
            INSERT INTO certificados_emitidos (
                idModelo,
                idInscricao,
                idEvento,
                idUsuario,
                codigo,
                tokenDownload,
                arquivo,
                hashArquivo,
                nomeParticipante,
                emailDestino,
                eventoTitulo,
                cargaHoraria,
                dataEvento,
                status,
                emitidoPor
            ) VALUES (
                :idModelo,
                :idInscricao,
                :idEvento,
                :idUsuario,
                :codigo,
                :tokenDownload,
                :arquivo,
                :hashArquivo,
                :nomeParticipante,
                :emailDestino,
                :eventoTitulo,
                :cargaHoraria,
                :dataEvento,
                'Emitido',
                :emitidoPor
            )
        ";

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            ':idModelo' => (int) $dados['idModelo'],
            ':idInscricao' => (int) $dados['idInscricao'],
            ':idEvento' => (int) $dados['idEvento'],
            ':idUsuario' => (int) $dados['idUsuario'],
            ':codigo' => (string) $dados['codigo'],
            ':tokenDownload' => (string) $dados['tokenDownload'],
            ':arquivo' => (string) $dados['arquivo'],
            ':hashArquivo' => (string) $dados['hashArquivo'],
            ':nomeParticipante' => (string) $dados['nomeParticipante'],
            ':emailDestino' => (string) $dados['emailDestino'],
            ':eventoTitulo' => (string) $dados['eventoTitulo'],
            ':cargaHoraria' => (float) $dados['cargaHoraria'],
            ':dataEvento' => (string) $dados['dataEvento'],
            ':emitidoPor' => (int) $dados['emitidoPor'] ?: null
        ]);

        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function atualizarArquivo(
        int $idCertificado,
        string $arquivo,
        string $hashArquivo
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE certificados_emitidos
            SET arquivo = :arquivo,
                hashArquivo = :hashArquivo
            WHERE idCertificado = :idCertificado
        ");

        return $stmt->execute([
            ':arquivo' => $arquivo,
            ':hashArquivo' => $hashArquivo,
            ':idCertificado' => $idCertificado
        ]);
    }

    public function marcarEnviado(
        int $idCertificado,
        string $emailDestino
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE certificados_emitidos
            SET status = 'Enviado',
                emailDestino = :emailDestino,
                enviadoEm = NOW()
            WHERE idCertificado = :idCertificado
              AND status <> 'Revogado'
        ");

        return $stmt->execute([
            ':emailDestino' => $emailDestino,
            ':idCertificado' => $idCertificado
        ]);
    }

    public function marcarInscricaoCertificada(
        int $idInscricao,
        bool $certificada
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET certificado = :certificado
            WHERE idInscricao = :idInscricao
        ");

        return $stmt->execute([
            ':certificado' => $certificada ? 1 : 0,
            ':idInscricao' => $idInscricao
        ]);
    }

    public function revogar(
        int $idCertificado,
        string $motivo,
        int $idUsuario
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE certificados_emitidos
            SET status = 'Revogado',
                revogadoEm = NOW(),
                revogadoPor = :revogadoPor,
                motivoRevogacao = :motivo
            WHERE idCertificado = :idCertificado
              AND status <> 'Revogado'
        ");

        $stmt->execute([
            ':revogadoPor' => $idUsuario ?: null,
            ':motivo' => $motivo,
            ':idCertificado' => $idCertificado
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listarEmitidos(
        string $pesquisa = '',
        int $idEvento = 0,
        string $status = ''
    ): array {
        $where = [];
        $params = [];

        $pesquisa = trim($pesquisa);

        if ($pesquisa !== '') {
            $where[] = "(
                ce.nomeParticipante LIKE :pesquisaNome
                OR ce.emailDestino LIKE :pesquisaEmail
                OR ce.codigo LIKE :pesquisaCodigo
                OR ce.eventoTitulo LIKE :pesquisaEvento
            )";
            $like = "%{$pesquisa}%";
            $params = [
                ':pesquisaNome' => $like,
                ':pesquisaEmail' => $like,
                ':pesquisaCodigo' => $like,
                ':pesquisaEvento' => $like
            ];
        }

        if ($idEvento > 0) {
            $where[] = 'ce.idEvento = :idEvento';
            $params[':idEvento'] = $idEvento;
        }

        if (in_array($status, ['Emitido', 'Enviado', 'Revogado'], true)) {
            $where[] = 'ce.status = :status';
            $params[':status'] = $status;
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT ce.*
            FROM certificados_emitidos ce
            {$sqlWhere}
            ORDER BY ce.emitidoEm DESC, ce.idCertificado DESC
            LIMIT 1000
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumo(): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM certificado_modelos) AS modelos,
                (SELECT COUNT(*) FROM certificado_modelos WHERE ativo = 1) AS modelosAtivos,
                (SELECT COUNT(*) FROM certificados_emitidos WHERE status <> 'Revogado') AS validos,
                (SELECT COUNT(*) FROM certificados_emitidos WHERE status = 'Enviado') AS enviados,
                (SELECT COUNT(*) FROM certificados_emitidos WHERE status = 'Revogado') AS revogados
        ";

        $dados = $this->db
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC);

        return $dados ?: [
            'modelos' => 0,
            'modelosAtivos' => 0,
            'validos' => 0,
            'enviados' => 0,
            'revogados' => 0
        ];
    }

    public function codigoExiste(string $codigo): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM certificados_emitidos
            WHERE codigo = :codigo
        ");
        $stmt->execute([':codigo' => $codigo]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function parametrosModelo(array $dados): array
    {
        return [
            ':idEvento' => (int) ($dados['idEvento'] ?? 0),
            ':nome' => trim((string) ($dados['nome'] ?? '')),
            ':titulo' => trim((string) ($dados['titulo'] ?? 'CERTIFICADO')),
            ':texto' => trim((string) ($dados['texto'] ?? '')),
            ':cargaHoraria' => (float) ($dados['cargaHoraria'] ?? 0),
            ':localEmissao' => trim((string) ($dados['localEmissao'] ?? '')),
            ':corTitulo' => (string) ($dados['corTitulo'] ?? '#0d6efd'),
            ':corTexto' => (string) ($dados['corTexto'] ?? '#1f2937'),
            ':imagemFundo' => $dados['imagemFundo'] ?: null,
            ':logo' => $dados['logo'] ?: null,
            ':assinatura1Imagem' => $dados['assinatura1Imagem'] ?: null,
            ':assinatura1Nome' => trim((string) ($dados['assinatura1Nome'] ?? '')) ?: null,
            ':assinatura1Cargo' => trim((string) ($dados['assinatura1Cargo'] ?? '')) ?: null,
            ':assinatura2Imagem' => $dados['assinatura2Imagem'] ?: null,
            ':assinatura2Nome' => trim((string) ($dados['assinatura2Nome'] ?? '')) ?: null,
            ':assinatura2Cargo' => trim((string) ($dados['assinatura2Cargo'] ?? '')) ?: null,
            ':ativo' => (int) ($dados['ativo'] ?? 0),
            ':criadoPor' => (int) ($dados['criadoPor'] ?? 0) ?: null
        ];
    }
}
