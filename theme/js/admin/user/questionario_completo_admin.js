(function () {
    'use strict';
    const form = document.getElementById('formUsuario');
    const box = document.getElementById('questionarioCompletoAdmin');
    const tipo = document.getElementById('tipo');
    if (!form || !box || !tipo) return;
    const toggle = document.getElementById('cadastrarEventoQuestionario');
    const evento = document.getElementById('eventoQuestionario');
    const area = document.getElementById('camposQuestionarioPublico');
    const nascimento = document.getElementById('nascimentoAssistido');
    let seq = 0, dataInicio = '';
    const exibirMenor = () => {
        const resp = document.getElementById('responsavelQuestionarioCompleto');
        if (!resp) return;
        const nasc = String(nascimento?.value || '').split('-').map(Number);
        const inicio = String(dataInicio).split('-').map(Number);
        const menor = nasc.length === 3 && inicio.length === 3 && nasc.every(x => x > 0) && inicio.every(x => x > 0) &&
            (inicio[0] - nasc[0] - ((inicio[1] < nasc[1] || (inicio[1] === nasc[1] && inicio[2] < nasc[2])) ? 1 : 0)) < 18 &&
            (inicio[0] - nasc[0]) >= 0;
        resp.hidden = !menor;
        resp.querySelectorAll('input').forEach(el => {
            el.disabled = !menor;
            if (el.hasAttribute('data-obrigatorio-menor')) el.required = menor;
        });
    };
    const atualizar = async () => {
        const participante = tipo.value === '3';
        box.hidden = !participante;
        if (!participante) { toggle.checked = false; }
        const habilitado = participante && toggle.checked;
        evento.disabled = !habilitado;
        evento.required = habilitado;
        area.hidden = !habilitado || !evento.value;
        area.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = !habilitado || !evento.value; });
        if (!habilitado || !evento.value) { area.innerHTML = ''; dataInicio = ''; seq++; return; }
        const atual = ++seq;
        area.hidden = false;
        area.innerHTML = '<p class="text-muted">Carregando perguntas do evento...</p>';
        try {
            const r = await fetch('ajax/questionario-publico-admin.php?evento=' + encodeURIComponent(evento.value), {credentials:'same-origin',headers:{Accept:'application/json'}});
            const dados = await r.json();
            if (atual !== seq) return;
            if (!r.ok || !dados.html) throw new Error(dados.erro || 'Falha ao carregar questionário.');
            area.innerHTML = dados.html;
            dataInicio = dados.dataInicio;
            area.querySelectorAll('[data-questionario-detalhe]').forEach(sel => {
                const det = area.querySelector('[name="' + sel.dataset.questionarioDetalhe + '"]');
                if (!det) return;
                const mudar = () => { det.disabled = sel.value !== '1'; det.required = sel.value === '1'; };
                sel.addEventListener('change', mudar); mudar();
            });
            exibirMenor();
        } catch(e) {
            if (atual !== seq) return;
            area.innerHTML = '<div class="alert alert-danger">' + 'Não foi possível carregar as perguntas. Verifique o evento e tente novamente.' + '</div>';
            // Form cannot submit without questionnaire fields.
            evento.setCustomValidity('Não foi possível carregar as perguntas do evento.');
        }
    };
    tipo.addEventListener('change', () => {
        if (tipo.value === '3') toggle.checked = true;
        atualizar();
    });
    toggle.addEventListener('change', atualizar);
    evento.addEventListener('change', () => { evento.setCustomValidity(''); atualizar(); });
    nascimento?.addEventListener('change', exibirMenor);
    nascimento?.addEventListener('input', exibirMenor);
    atualizar();
})();
