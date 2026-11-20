<?php
// Este arquivo só é incluído pela rota administrativa autorizada.
if (!defined('RETIRO_CADASTRO_ASSISTIDO_VIEW')) {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__, 3) . '/mod/services/CadastroAssistidoRetiro.php';
$escA = static fn(mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$idPessoa = (int) ($_GET['usuario'] ?? 0);
$idEventoAssistido = (int) ($_GET['evento'] ?? 0);
$usuarioAssistido = (new Usuario())->buscar($idPessoa);
if (!$usuarioAssistido || (int) ($usuarioAssistido['ativo'] ?? 0) !== 1 || (int) ($usuarioAssistido['tipo'] ?? 0) !== 3) {
    http_response_code(422);
    exit('Participante não encontrado ou inativo.');
}
$erroAssistido = '';
$eventoAssistido = null;
$termosAssistidos = [];
$camisetasAssistidas = [];
$idadeAssistida = null;
$valorAssistido = 0;
$eventosAssistidos = [];
try {
    CadastroAssistidoRetiro::perfil($usuarioAssistido);
    $eventosAssistidos = (new Evento())->listarAtivos();
    if ($idEventoAssistido > 0) {
        $svcAssistido = new InscricaoPublicaService($db);
        $eventoAssistido = $svcAssistido->buscarEvento($idEventoAssistido);
        $termosAssistidos = $svcAssistido->termos($idEventoAssistido);
        $camisetasAssistidas = $svcAssistido->camisetas($idEventoAssistido);
        $idadeAssistida = CadastroAssistidoRetiro::idade($usuarioAssistido, $eventoAssistido);
        $stmtCom = $db->prepare('SELECT nome_comunidade FROM minha_comunidade WHERE id = ?');
        $stmtCom->execute([(int) ($usuarioAssistido['idComunidade'] ?? 0)]);
        $visitanteAssistido = CadastroAssistidoRetiro::visitante((string) $stmtCom->fetchColumn());
        $valorAssistido = CadastroAssistidoRetiro::valor($eventoAssistido, $visitanteAssistido);
    }
} catch (Throwable $ex) {
    $erroAssistido = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Não foi possível carregar o questionário ou os dados do cadastro. Confira a configuração do evento.';
}
require_once dirname(__DIR__) . '/../includes/header.php';
require_once dirname(__DIR__) . '/../includes/navbar.php';
require_once dirname(__DIR__) . '/../includes/sidebar.php';
?>
<div class="content" id="content"><div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div><h2 class="fw-bold mb-1">Questionário de inscrição assistida</h2><p class="text-muted mb-0">Etapa 2 de 2 — responda com o participante ou responsável. O cadastro do usuário já está salvo.</p></div>
        <a href="../user/usuario.php" class="btn btn-outline-secondary">Novo usuário</a>
    </div>
    <div class="card shadow-sm border-0 mb-3"><div class="card-body">
        <h5 class="fw-bold">Dados do participante</h5>
        <p class="mb-1"><strong><?= $escA($usuarioAssistido['nome'] ?? '') ?></strong> — <?= $escA($usuarioAssistido['email'] ?? '') ?></p>
        <p class="mb-0 small text-muted">Nascimento: <?= $escA($usuarioAssistido['data_nascimento'] ?? '') ?> · Gênero: <?= $escA($usuarioAssistido['genero'] ?? '') ?> · Telefone: <?= $escA($usuarioAssistido['telefone'] ?? '') ?></p>
    </div></div>
    <div class="card shadow-sm border-0 mb-3"><div class="card-body">
        <form method="get" action="inscricao.php">
            <input type="hidden" name="questionario" value="1"><input type="hidden" name="usuario" value="<?= $idPessoa ?>">
            <label class="form-label fw-semibold" for="eventoAssistido">Evento</label>
            <div class="d-flex flex-wrap gap-2"><select class="form-select flex-grow-1" style="max-width:650px" id="eventoAssistido" name="evento" required>
                <option value="">Selecione o evento</option>
                <?php foreach ($eventosAssistidos as $ev): ?><option value="<?= (int) $ev['idEvento'] ?>" <?= $idEventoAssistido === (int) $ev['idEvento'] ? 'selected' : '' ?>><?= $escA($ev['titulo']) ?> — <?= $escA($ev['data_inicio'] ?? '') ?></option><?php endforeach; ?>
            </select><button class="btn btn-primary" type="submit">Carregar questionário</button></div>
        </form>
    </div></div>
    <?php if ($erroAssistido !== ''): ?><div class="alert alert-warning"><?= $escA($erroAssistido) ?></div><?php endif; ?>
    <?php if ($eventoAssistido && $erroAssistido === ''): ?>
    <form id="formCadastroAssistido" method="post" action="ajax/inscricao-new.php">
        <input type="hidden" name="_token" value="<?= $escA(Session::csrf()) ?>">
        <input type="hidden" name="idInscricao" value="0"><input type="hidden" name="idUsuario" value="<?= $idPessoa ?>">
        <input type="hidden" name="idEvento" value="<?= $idEventoAssistido ?>"><input type="hidden" name="cadastro_assistido" value="1">
        <?php if ($idadeAssistida < 18): ?>
        <div class="card shadow-sm border-0 mb-3"><div class="card-body">
            <h5 class="fw-bold">Autorização do responsável legal</h5><p class="text-muted">Idade no início do evento: <?= (int) $idadeAssistida ?> anos. Registre a autorização apenas se efetivamente concedida pelo responsável.</p>
            <div class="row g-3"><div class="col-md-6"><label class="form-label">Nome completo do responsável<input class="form-control" name="responsavel_nome" maxlength="160" required></label></div>
            <div class="col-md-3"><label class="form-label">Telefone 1<input class="form-control" name="responsavel_telefone_1" inputmode="tel" maxlength="20" required></label></div>
            <div class="col-md-3"><label class="form-label">Telefone 2 (opcional)<input class="form-control" name="responsavel_telefone_2" inputmode="tel" maxlength="20"></label></div></div>
            <p class="mt-3 mb-2"><?= $escA(InscricaoPublicaService::textoAutorizacaoMenor($eventoAssistido)) ?></p>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="responsavel_autorizacao" value="1" required> O responsável legal declarou esta autorização e estou registrando-a como membro da equipe. Não equivale a assinatura eletrônica do responsável.</label>
        </div></div>
        <?php endif; ?>
        <div class="card shadow-sm border-0 mb-3"><div class="card-body">
            <h5 class="fw-bold">Saúde e acessibilidade</h5><p class="small text-muted">Respostas confidenciais. Registre somente as informações fornecidas pelo participante ou responsável.</p>
            <?php $temPergunta = false; foreach (CadastroAssistidoRetiro::QUESTOES as $campo => [$flag, $rotulo, $detalhes, $instrucao]): if (!CadastroAssistidoRetiro::habilitada($eventoAssistido, $flag)) continue; $temPergunta = true; ?>
                <div class="border-bottom pb-3 mb-3"><label class="form-label fw-semibold" for="<?= $escA($campo) ?>"><?= $escA($rotulo) ?></label>
                <select class="form-select" id="<?= $escA($campo) ?>" name="<?= $escA($campo) ?>" data-detalhe="<?= $escA($detalhes) ?>" required><option value="">Selecione</option><option value="1">Sim</option><option value="0">Não</option></select>
                <label class="form-label mt-2" for="<?= $escA($detalhes) ?>"><?= $escA($instrucao) ?> (quando Sim)</label><textarea class="form-control" name="<?= $escA($detalhes) ?>" id="<?= $escA($detalhes) ?>" maxlength="2000" rows="2"></textarea></div>
            <?php endforeach; ?>
            <?php if (CadastroAssistidoRetiro::habilitada($eventoAssistido, 'perguntar_deficiencia')): $temPergunta = true; ?>
                <label class="form-label fw-semibold" for="deficienciaAssistida">Possui deficiência?</label><select class="form-select" name="deficiencia" id="deficienciaAssistida" required><option value="">Selecione</option><?php foreach (CadastroAssistidoRetiro::DEFICIENCIAS as $def): ?><option value="<?= $escA($def) ?>"><?= $escA($def) ?></option><?php endforeach; ?></select>
                <label class="form-label mt-2" for="deficienciaDetalheAssistida">Se necessário, detalhe</label><textarea class="form-control" id="deficienciaDetalheAssistida" name="deficiencia_detalhes" maxlength="2000" rows="2"></textarea>
            <?php endif; ?>
            <?php if (!$temPergunta): ?><p class="text-muted mb-0">Nenhuma pergunta de saúde foi habilitada para este evento.</p><?php endif; ?>
        </div></div>
        <?php if ((int) ($eventoAssistido['camiseta_ativa'] ?? 0) === 1 && $camisetasAssistidas !== []): ?>
        <div class="card shadow-sm border-0 mb-3"><div class="card-body"><h5 class="fw-bold">Camiseta</h5><label class="form-label" for="camisetaAssistida">Tamanho da camiseta</label><select class="form-select" name="camiseta" id="camisetaAssistida" required><option value="">Selecione</option><?php foreach ($camisetasAssistidas as $tam): ?><option value="<?= $escA($tam) ?>"><?= $escA($tam) ?></option><?php endforeach; ?></select></div></div>
        <?php endif; ?>
        <?php if ($termosAssistidos !== []): ?>
        <div class="card shadow-sm border-0 mb-3"><div class="card-body"><h5 class="fw-bold">Termos e consentimentos</h5><p class="small text-muted">Apresente os termos ao participante ou responsável e marque somente o aceite que ele realmente declarou. A origem administrativa ficará registrada.</p>
        <?php foreach ($termosAssistidos as $t): ?><div class="border rounded p-3 mb-2"><strong><?= $escA($t['titulo']) ?></strong><?php if ((int) $t['obrigatorio'] === 1): ?> <span class="text-danger">(obrigatório)</span><?php endif; ?><p class="mt-2 mb-2" style="white-space:pre-wrap"><?= $escA($t['descricao'] ?? '') ?></p>
            <?php $urlT = trim((string) ($t['url'] ?? '')); if (filter_var($urlT, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($urlT, PHP_URL_SCHEME)), ['http','https'], true)): ?><p><a href="<?= $escA($urlT) ?>" target="_blank" rel="noopener noreferrer">Ler termo completo</a></p><?php endif; ?>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="termos[]" value="<?= (int) $t['idTermo'] ?>" <?= (int) $t['obrigatorio'] === 1 ? 'required' : '' ?>> Aceite declarado pelo participante ou responsável, registrado pela equipe.</label></div><?php endforeach; ?>
        </div></div><?php endif; ?>
        <div class="card shadow-sm border-0 mb-3"><div class="card-body"><h5 class="fw-bold">Resumo da inscrição</h5><p>Idade na data do evento: <?= (int) $idadeAssistida ?> anos. <?= !empty($visitanteAssistido) ? 'Categoria: visitante. ' : '' ?>Valor: <strong>R$ <?= number_format($valorAssistido, 2, ',', '.') ?></strong>.</p><p class="text-muted">Se houver pagamento obrigatório, será gerado o registro pendente pelo fluxo manual existente. Nenhum pagamento será marcado como recebido nesta tela.</p><label class="form-label" for="obsAssistida">Observações (opcional)</label><textarea class="form-control" name="observacoes" id="obsAssistida" rows="3" maxlength="1000"></textarea></div></div>
        <div id="resultadoAssistido" role="status" aria-live="polite"></div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mb-5"><a href="../user/usuario.php" class="btn btn-outline-secondary">Encerrar (somente usuário)</a><button type="submit" id="salvarAssistido" class="btn btn-success">Salvar inscrição</button></div>
    </form>
    <script>
    (() => {
        const form = document.getElementById('formCadastroAssistido'), btn = document.getElementById('salvarAssistido'), result = document.getElementById('resultadoAssistido');
        for (const field of form.querySelectorAll('[data-detalhe]')) {
            const detail = document.getElementById(field.dataset.detalhe);
            const update = () => { detail.required = field.value === '1'; detail.disabled = field.value !== '1'; };
            field.addEventListener('change', update); update();
        }
        form.addEventListener('submit', async e => {
            e.preventDefault(); if (btn.disabled || !form.reportValidity()) return;
            btn.disabled = true; result.className = 'alert alert-info'; result.textContent = 'Salvando inscrição...';
            try {
                const response = await fetch(form.action, { method:'POST', body:new FormData(form), credentials:'same-origin', headers:{'Accept':'application/json'} });
                const data = await response.json();
                if (!response.ok || !data.status) throw new Error(data.msg || 'Não foi possível salvar a inscrição.');
                result.className = 'alert alert-success'; result.textContent = data.msg + ' '; const link = document.createElement('a'); link.href = 'inscricoes.php'; link.textContent = 'Ver inscrições'; result.append(link);
            } catch (err) { result.className = 'alert alert-danger'; result.textContent = err.message || 'Falha ao consultar o servidor.'; btn.disabled = false; }
        });
    })();
    </script>
    <?php endif; ?>
</div></div>
<?php require_once dirname(__DIR__) . '/../includes/footer.php'; ?>
