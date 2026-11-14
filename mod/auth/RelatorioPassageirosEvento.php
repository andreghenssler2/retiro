<?php

declare(strict_types=1);

/** Helpers exclusivos da listagem de transporte e filtro anual. */
final class RelatorioPassageirosEvento
{
    private static function data(mixed $valor): ?DateTimeImmutable
    {
        $texto = trim((string) $valor);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$|[ T])/', $texto, $m)) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', substr($texto, 0, 10), new DateTimeZone('America/Sao_Paulo'));
        return $data ?: null;
    }

    public static function idade(mixed $nascimento, mixed $inicio): ?int
    {
        $nasc = self::data($nascimento);
        $data = self::data($inicio);
        if (!$nasc || !$data || $nasc > $data) {
            return null;
        }
        return $nasc->diff($data)->y;
    }

    public static function filtrarAno(array $eventos, mixed $anoSolicitado, int $anoAtual): array
    {
        $anos = [$anoAtual];
        foreach ($eventos as $evento) {
            $data = self::data($evento['data_inicio'] ?? null);
            if ($data) { $anos[] = (int) $data->format('Y'); }
        }
        $anos = array_values(array_unique($anos));
        rsort($anos, SORT_NUMERIC);
        $ano = is_string($anoSolicitado) || is_int($anoSolicitado) ? (string) $anoSolicitado : '';
        $ano = preg_match('/^\d{4}$/D', $ano) ? (int) $ano : $anoAtual;
        if (!in_array($ano, $anos, true)) { $ano = $anoAtual; }
        $filtrados = array_values(array_filter($eventos, static function (array $e) use ($ano): bool {
            $inicio = self::data($e['data_inicio'] ?? null);
            return $inicio !== null && (int) $inicio->format('Y') === $ano;
        }));
        return ['ano' => $ano, 'anos' => $anos, 'eventos' => $filtrados];
    }

    private static function primeiroTexto(mixed ...$valores): string
    {
        foreach ($valores as $v) {
            $texto = trim((string) $v);
            if ($texto !== '' && $texto !== '-') { return $texto; }
        }
        return '—';
    }

    public static function linhas(array $inscricoes, array $evento): array
    {
        $linhas = [];
        foreach ($inscricoes as $registro) {
            $status = strtolower(trim((string) ($registro['status_inscricao'] ?? '')));
            $situacao = trim((string) ($registro['situacao_relatorio'] ?? ''));
            if (in_array($status, ['cancelada', 'cancelado'], true) || in_array(strtolower($situacao), ['cancelada', 'cancelado'], true)) {
                continue;
            }
            $pagamento = trim((string) ($registro['pagamento_status'] ?? ''));
            if ($pagamento === '') { $pagamento = trim((string) ($registro['status_pagamento_inscricao'] ?? '')); }
            // A situação do transporte usa a inscrição ativa e o pagamento efetivamente recebido.
            // Preserva a ressalva dos outros relatórios sem alterar dados nem confirmar presença.
            $recebido = strcasecmp($pagamento, 'Pago') === 0;
            $confirmada = $situacao === 'Confirmada' || $recebido;
            $ressalva = $recebido && $situacao !== '' && $situacao !== 'Confirmada';
            $idade = self::idade($registro['data_nascimento'] ?? null, $evento['data_inicio'] ?? null);
            $cpf = self::primeiroTexto($registro['cpf'] ?? null);
            $digitos = preg_replace('/\D/', '', $cpf);
            if (is_string($digitos) && strlen($digitos) === 11) {
                $cpf = substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.' . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2);
            }
            $linhas[] = [
                'nome' => self::primeiroTexto($registro['nome'] ?? null, $registro['participante_nome_atual'] ?? null),
                'cpf' => $cpf,
                'contato' => self::primeiroTexto($registro['telefone'] ?? null, $registro['participante_telefone_atual'] ?? null),
                'idade' => $idade === null ? '—' : (string) $idade,
                'situacao' => $confirmada ? 'Confirmada' : 'Aguardando pagamento',
                'ressalva' => $ressalva,
            ];
        }
        // A consulta existente já ordena por nome e ID; mantém a ordem do banco (collation).
        return $linhas;
    }

    public static function html(array $inscricoes, array $evento): string
    {
        $linhas = self::linhas($inscricoes, $evento);
        $confirmadas = count(array_filter($linhas, static fn(array $r): bool => $r['situacao'] === 'Confirmada'));
        $pendentes = count($linhas) - $confirmadas;
        $ressalvas = count(array_filter($linhas, static fn(array $r): bool => $r['ressalva']));
        $inicio = self::data($evento['data_inicio'] ?? null);
        $fim = self::data($evento['data_fim'] ?? null);
        $periodo = $inicio ? $inicio->format('d/m/Y') : '—';
        if ($fim && $inicio && $fim > $inicio) { $periodo .= ' a ' . $fim->format('d/m/Y'); }
        $esc = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        ?>
        <div class="cabecalho-relatorio">
            <div class="titulo">Lista de passageiros</div>
            <div class="subtitulo"><?= $esc(self::primeiroTexto($evento['titulo'] ?? null)); ?></div>
            <table class="meta">
                <tr>
                    <td><span class="rotulo">Data do evento</span><?= $esc($periodo); ?></td>
                    <td><span class="rotulo">Local</span><?= $esc(self::primeiroTexto($evento['local'] ?? null, $evento['cidade'] ?? null)); ?></td>
                    <td><span class="rotulo">Total de passageiros</span><strong><?= count($linhas); ?></strong></td>
                    <td><span class="rotulo">Confirmadas</span><strong><?= $confirmadas; ?></strong></td>
                    <td><span class="rotulo">Aguardando pagamento</span><strong><?= $pendentes; ?></strong></td>
                </tr>
            </table>
        </div>
        <div class="legenda">Inscrições canceladas não são exibidas. Idade calculada no início do evento. Pendências vencidas continuam aguardando pagamento enquanto a inscrição estiver ativa. <?= $ressalvas > 0 ? '(*): pagamento registrado; confira a situação no relatório geral antes de autorizar a participação.' : ''; ?></div>
        <?php if (!$linhas): ?>
            <div class="vazio">Nenhuma inscrição não cancelada encontrada para este evento.</div>
        <?php else: ?>
            <table class="lista" style="table-layout:fixed; width:100%;">
                <thead style="display:table-header-group;">
                    <tr>
                        <th style="width:4%;">Nº</th>
                        <th style="width:35%;">Nome</th>
                        <th style="width:17%;">CPF</th>
                        <th style="width:19%;">Contato</th>
                        <th style="width:7%;">Idade</th>
                        <th style="width:18%;">Situação da inscrição</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($linhas as $i => $r): ?>
                    <tr class="<?= $r['situacao'] === 'Confirmada' ? 'confirmada' : 'aguardando'; ?>" style="page-break-inside:avoid;">
                        <td><?= $i + 1; ?></td>
                        <td style="overflow-wrap:break-word; word-wrap:break-word;"><?= $esc($r['nome']); ?></td>
                        <td><?= $esc($r['cpf']); ?></td>
                        <td style="overflow-wrap:break-word; word-wrap:break-word;"><?= $esc($r['contato']); ?></td>
                        <td><?= $esc($r['idade']); ?></td>
                        <td class="situacao"><?= $esc($r['situacao']); ?><?= $r['ressalva'] ? ' (*)' : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="rodape">Emitido em <?= $esc((new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i')); ?> · Uso interno — dados pessoais de passageiros.</div>
        <?php
        return (string) ob_get_clean();
    }
}
