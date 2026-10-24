(function ($) {
    'use strict';

    let grafico = null;
    let carregando = false;

    const token = String($('#_token').val() || '');

    function escaparHtml(valor) {
        return $('<div>').text(valor == null ? '' : String(valor)).html();
    }

    function numero(valor) {
        const convertido = Number(valor);
        return Number.isFinite(convertido) ? convertido : 0;
    }

    function inteiro(valor) {
        return Math.trunc(numero(valor));
    }

    function moeda(valor) {
        return numero(valor).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function plural(valor, singular, pluralTexto) {
        return `${inteiro(valor)} ${inteiro(valor) === 1 ? singular : pluralTexto}`;
    }

    function formatarData(valor) {
        if (!valor) {
            return '-';
        }

        const dataTexto = String(valor).slice(0, 10);
        const partes = dataTexto.split('-');

        if (partes.length !== 3) {
            return escaparHtml(valor);
        }

        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    function classeStatus(status) {
        return {
            Pago: 'text-bg-success',
            Pendente: 'text-bg-warning',
            Cancelado: 'text-bg-danger',
            Estornado: 'text-bg-dark'
        }[status] || 'text-bg-secondary';
    }

    function dadosFiltro() {
        return {
            _token: token,
            dataInicio: $('#dataInicio').val(),
            dataFim: $('#dataFim').val(),
            idEvento: $('#idEvento').val() || 0
        };
    }

    function validarPeriodo() {
        const inicio = String($('#dataInicio').val() || '');
        const fim = String($('#dataFim').val() || '');

        if (!inicio || !fim) {
            mostrarErro('Informe a data inicial e a data final.');
            return false;
        }

        if (inicio > fim) {
            mostrarErro('A data inicial não pode ser maior que a data final.');
            return false;
        }

        ocultarErro();
        return true;
    }

    function mostrarErro(mensagem) {
        $('#alertaRelatorio')
            .removeClass('d-none')
            .text(mensagem || 'Ocorreu um erro.');
    }

    function ocultarErro() {
        $('#alertaRelatorio').addClass('d-none').empty();
    }

    function alternarCarregamento(ativo) {
        carregando = ativo;
        $('#btnConsultar').prop('disabled', ativo);
        $('#btnExportarPdf').prop('disabled', ativo);
        $('#loaderGrafico').toggleClass('d-none', !ativo);
    }

    function atualizarCards(resumo) {
        const canceladoEstornado = numero(resumo.cancelado) + numero(resumo.estornado);
        const quantidadeCancelada = inteiro(resumo.quantidadeCancelado) + inteiro(resumo.quantidadeEstornado);

        $('#totalPrevisto').text(moeda(resumo.previsto));
        $('#totalRecebido').text(moeda(resumo.recebido));
        $('#totalPendente').text(moeda(resumo.pendente));
        $('#totalCancelado').text(moeda(canceladoEstornado));

        $('#quantidadeTotal').text(plural(resumo.quantidade, 'pagamento', 'pagamentos'));
        $('#quantidadePago').text(plural(resumo.quantidadePago, 'recebimento', 'recebimentos'));
        $('#quantidadePendente').text(plural(resumo.quantidadePendente, 'pendência', 'pendências'));
        $('#quantidadeCancelado').text(plural(quantidadeCancelada, 'registro', 'registros'));
    }

    function montarGrafico(relatorio) {
        const serie = Array.isArray(relatorio.serie) ? relatorio.serie : [];
        const labels = serie.map(item => item.rotulo);
        const recebido = serie.map(item => numero(item.recebido));
        const pendente = serie.map(item => numero(item.pendente));

        const elemento = document.getElementById('graficoFinanceiro');
        if (!elemento || typeof Chart === 'undefined') {
            mostrarErro('A biblioteca do gráfico não foi carregada.');
            return;
        }

        if (grafico) {
            grafico.destroy();
        }

        grafico = new Chart(elemento, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Recebido',
                        data: recebido,
                        backgroundColor: 'rgba(25, 135, 84, .78)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        borderRadius: 5
                    },
                    {
                        label: 'Pendente',
                        data: pendente,
                        backgroundColor: 'rgba(255, 193, 7, .72)',
                        borderColor: '#ffc107',
                        borderWidth: 1,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: valor => moeda(valor)
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: contexto => `${contexto.dataset.label}: ${moeda(contexto.raw)}`
                        }
                    }
                }
            }
        });

        const periodo = relatorio.periodo || {};
        const agrupamento = periodo.agrupamento === 'mensal' ? 'por mês' : 'por dia';
        $('#textoPeriodoGrafico').text(
            `${periodo.inicioFormatado || ''} até ${periodo.fimFormatado || ''} - agrupado ${agrupamento}.`
        );
    }

    function montarFormas(lista) {
        let html = '';

        (lista || []).forEach(item => {
            html += `
                <tr>
                    <td class="fw-semibold">${escaparHtml(item.forma)}</td>
                    <td class="text-center">${inteiro(item.quantidade)}</td>
                    <td class="text-end fw-semibold">${moeda(item.valor)}</td>
                </tr>
            `;
        });

        if (!html) {
            html = '<tr><td colspan="3" class="text-center text-muted py-4">Nenhum recebimento no período.</td></tr>';
        }

        $('#tabelaFormas').html(html);
    }

    function montarEventos(lista) {
        let html = '';

        (lista || []).forEach(item => {
            html += `
                <tr>
                    <td class="fw-semibold">${escaparHtml(item.evento)}</td>
                    <td class="text-center">${inteiro(item.quantidade)}</td>
                    <td class="text-end text-success fw-semibold">${moeda(item.recebido)}</td>
                    <td class="text-end text-warning-emphasis fw-semibold">${moeda(item.pendente)}</td>
                </tr>
            `;
        });

        if (!html) {
            html = '<tr><td colspan="4" class="text-center text-muted py-4">Nenhum evento encontrado no período.</td></tr>';
        }

        $('#tabelaEventos').html(html);
    }

    function montarMovimentos(lista) {
        let html = '';

        (lista || []).forEach(item => {
            html += `
                <tr>
                    <td class="text-nowrap">${formatarData(item.dataReferencia)}</td>
                    <td class="text-nowrap"><code>${escaparHtml(item.codigo)}</code></td>
                    <td>${escaparHtml(item.participante)}</td>
                    <td>${escaparHtml(item.evento)}</td>
                    <td>${escaparHtml(item.forma)}</td>
                    <td><span class="badge ${classeStatus(String(item.status || ''))}">${escaparHtml(item.status)}</span></td>
                    <td class="text-end fw-semibold text-nowrap">${moeda(item.valor)}</td>
                </tr>
            `;
        });

        if (!html) {
            html = '<tr><td colspan="7" class="text-center text-muted py-5">Nenhuma movimentação encontrada.</td></tr>';
        }

        $('#tabelaMovimentos').html(html);
        $('#totalMovimentos').text(plural((lista || []).length, 'registro', 'registros'));
    }

    function aplicarRelatorio(relatorio) {
        atualizarCards(relatorio.resumo || {});
        montarGrafico(relatorio);
        montarFormas(relatorio.formas || []);
        montarEventos(relatorio.eventos || []);
        montarMovimentos(relatorio.movimentos || []);
    }

    function consultar() {
        if (carregando || !validarPeriodo()) {
            return;
        }

        alternarCarregamento(true);
        ocultarErro();

        $.ajax({
            url: 'ajax/relatorio-financeiro.php',
            type: 'POST',
            dataType: 'json',
            data: dadosFiltro()
        })
            .done(function (resposta) {
                if (!resposta || resposta.sucesso !== true) {
                    mostrarErro(resposta?.mensagem || 'Não foi possível gerar o relatório.');
                    return;
                }

                aplicarRelatorio(resposta.relatorio || {});
            })
            .fail(function (xhr) {
                console.error(xhr.responseText);
                mostrarErro(
                    xhr.responseJSON?.mensagem || 'Erro ao consultar o relatório financeiro.'
                );
            })
            .always(function () {
                alternarCarregamento(false);
            });
    }

    function exportarPdf() {
        if (!validarPeriodo()) {
            return;
        }

        const parametros = new URLSearchParams({
            dataInicio: String($('#dataInicio').val() || ''),
            dataFim: String($('#dataFim').val() || ''),
            idEvento: String($('#idEvento').val() || '0')
        });

        window.open(`relatorio-pdf.php?${parametros.toString()}`, '_blank', 'noopener');
    }

    $('#formRelatorioFinanceiro').on('submit', function (evento) {
        evento.preventDefault();
        consultar();
    });

    $('#btnExportarPdf').on('click', exportarPdf);

    consultar();
})(jQuery);
