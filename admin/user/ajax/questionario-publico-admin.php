<?php

declare(strict_types=1);
require_once __DIR__ . '/../../../config/settings.php';
Middleware::moderador();
require_once dirname(__DIR__, 3) . '/mod/services/CadastroAssistidoRetiro.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$id = filter_input(INPUT_GET, 'evento', FILTER_VALIDATE_INT) ?: 0;
if ($id <= 0) { http_response_code(422); echo json_encode(['erro'=>'Selecione o evento.']); exit; }
try {
    $ev = (new InscricaoPublicaService($db))->buscarEvento($id);
    if ((int) ($ev['ativo'] ?? 0) !== 1) { throw new InvalidArgumentException('Evento inativo.'); }
    $svc = new InscricaoPublicaService($db);
    $terms = $svc->termos($id);
    $shirts = $svc->camisetas($id);
    $h = static fn(mixed $v):string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    ?>
    <div class="mb-3 small text-muted">Perguntas habilitadas para <strong><?= $h($ev['titulo'] ?? 'o evento') ?></strong>. Responda conforme declarado pelo participante ou responsável. Nenhum pagamento será marcado como recebido.</div>
    <div id="responsavelQuestionarioCompleto" class="border rounded p-3 mb-3" hidden>
        <h6 class="fw-bold">Autorização do responsável legal — menor de 18 anos</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nome completo do responsável<input name="responsavel_nome" class="form-control" maxlength="160" data-obrigatorio-menor></label></div>
            <div class="col-md-6"><label class="form-label">CPF do responsável<input name="responsavel_cpf" class="form-control" maxlength="14" inputmode="numeric" data-obrigatorio-menor></label></div>
            <div class="col-md-6"><label class="form-label">Telefone / WhatsApp 1<input name="responsavel_telefone_1" class="form-control" maxlength="20" inputmode="tel" data-obrigatorio-menor></label></div>
            <div class="col-md-6"><label class="form-label">Telefone / WhatsApp 2 (opcional)<input name="responsavel_telefone_2" class="form-control" maxlength="20" inputmode="tel"></label></div>
        </div>
        <p class="small mt-3" style="white-space:pre-wrap"><?= $h(InscricaoPublicaService::textoAutorizacaoMenor($ev)) ?></p>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="responsavel_autorizacao" value="1" data-obrigatorio-menor> O responsável declarou expressamente esta autorização à equipe. Este registro não equivale a assinatura eletrônica do responsável.</label>
    </div>
    <h6 class="fw-bold">Saúde e acessibilidade</h6>
    <p class="text-muted small">Dados sensíveis. Somente informações declaradas pelo participante ou responsável.</p>
    <?php $tem=false; foreach (CadastroAssistidoRetiro::QUESTOES as $campo=>[$flag,$rotulo,$detalhes,$instrucao]): if (!CadastroAssistidoRetiro::habilitada($ev,$flag)) continue; $tem=true; ?>
        <div class="row g-2 mb-3">
            <div class="col-md-5"><label class="form-label fw-semibold" for="qc_<?= $h($campo) ?>"><?= $h($rotulo) ?></label><select class="form-select" id="qc_<?= $h($campo) ?>" name="<?= $h($campo) ?>" data-questionario-detalhe="<?= $h($detalhes) ?>" required><option value="">Selecione</option><option value="1">Sim</option><option value="0">Não</option></select></div>
            <div class="col-md-7"><label class="form-label" for="qc_<?= $h($detalhes) ?>"><?= $h($instrucao) ?> (quando Sim)</label><textarea class="form-control" id="qc_<?= $h($detalhes) ?>" name="<?= $h($detalhes) ?>" rows="2" maxlength="2000" disabled></textarea></div>
        </div>
    <?php endforeach; ?>
    <?php if (CadastroAssistidoRetiro::habilitada($ev,'perguntar_deficiencia')): $tem=true; ?>
        <div class="row g-2 mb-3"><div class="col-md-5"><label class="form-label fw-semibold">Possui deficiência?</label><select class="form-select" name="deficiencia" required><option value="">Selecione</option><?php foreach (CadastroAssistidoRetiro::DEFICIENCIAS as $d): ?><option value="<?= $h($d) ?>"><?= $h($d) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-7"><label class="form-label">Detalhes (se necessário)</label><textarea class="form-control" name="deficiencia_detalhes" rows="2" maxlength="2000"></textarea></div></div>
    <?php endif; ?>
    <?php if (!$tem): ?><p class="text-muted">Nenhuma pergunta de saúde habilitada.</p><?php endif; ?>
    <?php if ((int)($ev['camiseta_ativa']??0) === 1 && $shirts !== []): ?>
        <hr><label class="form-label fw-semibold">Tamanho da camiseta</label><select class="form-select" name="camiseta" required><option value="">Selecione</option><?php foreach ($shirts as $tam): ?><option value="<?= $h($tam) ?>"><?= $h($tam) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
    <?php if ($terms !== []): ?>
        <hr><h6 class="fw-bold">Termos e consentimentos do evento</h6><p class="small text-muted">Marque somente aceites efetivamente declarados; a origem administrativa ficará registrada.</p>
        <?php foreach ($terms as $t): ?><div class="border rounded p-3 mb-2"><strong><?= $h($t['titulo']) ?></strong><?php if ((int)($t['obrigatorio']??0)===1): ?> <span class="text-danger">Obrigatório</span><?php endif; ?><p class="mb-2" style="white-space:pre-wrap"><?= $h($t['descricao']??'') ?></p>
        <?php $u=trim((string)($t['url']??'')); if(filter_var($u,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($u,PHP_URL_SCHEME)),['http','https'],true)): ?><a href="<?= $h($u) ?>" target="_blank" rel="noopener noreferrer">Ler termo completo</a><?php endif; ?>
        <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="termos[]" value="<?= (int)$t['idTermo'] ?>" <?= (int)($t['obrigatorio']??0)===1?'required':'' ?>> Aceite declarado pelo participante ou responsável.</label></div><?php endforeach; ?>
    <?php endif; ?>
    <label class="form-label mt-2">Observações adicionais (opcional)</label><textarea class="form-control" rows="2" maxlength="1000" name="observacoes_questionario"></textarea>
    <?php
    $html = ob_get_clean();
    echo json_encode(['html'=>$html,'dataInicio'=>substr((string)($ev['data_inicio']??''),0,10)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['erro'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar o questionário.'], JSON_UNESCAPED_UNICODE);
}
