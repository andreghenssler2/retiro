$(function () {
    "use strict";

    const endpoint = "ajax/dashboard.php";
    const intervaloAtualizacao = 60000;

    let carregando = false;
    let graficoInscricoes = null;
    let graficoPagamentos = null;
    let graficoFinanceiro = null;
    let graficoCamisetas = null;

    function escaparHtml(valor) {
        return $("<div>").text(valor ?? "").html();
    }

    function numero(valor) {
        const convertido = Number.parseFloat(valor);
        return Number.isFinite(convertido) ? convertido : 0;
    }

    function inteiro(valor) {
        const convertido = Number.parseInt(valor, 10);
        return Number.isFinite(convertido) ? convertido : 0;
    }

    function moeda(valor) {
        return numero(valor).toLocaleString("pt-BR", {
            style: "currency",
            currency: "BRL"
        });
    }

    function formatarData(valor, comHora = true) {
        if (!valor) {
            return "Não informada";
        }

        const texto = String(valor).trim();
        const data = new Date(texto.replace(" ", "T"));

        if (Number.isNaN(data.getTime())) {
            return texto;
        }

        return data.toLocaleString("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: comHora ? "2-digit" : undefined,
            minute: comHora ? "2-digit" : undefined
        });
    }

    function classeStatusInscricao(status) {
        return {
            Pendente: "bg-warning text-dark",
            Confirmada: "bg-success",
            Cancelada: "bg-danger"
        }[status] || "bg-secondary";
    }

    function classeStatusPagamento(status) {
        return {
            Pendente: "bg-warning text-dark",
            Vencido: "bg-danger",
            Pago: "bg-success",
            Cancelado: "bg-dark",
            Estornado: "bg-secondary"
        }[status] || "bg-secondary";
    }

    function badge(status, classe) {
        return `<span class="badge ${classe}">${escaparHtml(status || "Não informado")}</span>`;
    }

    function obterFiltros() {
        return {
            ano: inteiro($("#filtroDashboardAno").val()),
            mes: inteiro($("#filtroDashboardMes").val()),
            evento: inteiro($("#filtroDashboardEvento").val())
        };
    }

    function atualizarEstadoFiltros() {
        const evento = inteiro($("#filtroDashboardEvento").val());
        const temEvento = evento > 0;

        $("#filtroDashboardAno, #filtroDashboardMes").prop("disabled", temEvento);

        if (temEvento) {
            $("#filtroDashboardAno, #filtroDashboardMes").val("0");
        }
    }

    function atualizarUrlFiltros(filtros) {
        const url = new URL(window.location.href);

        url.searchParams.delete("ano");
        url.searchParams.delete("mes");
        url.searchParams.delete("evento");

        if (filtros.evento > 0) {
            url.searchParams.set("evento", String(filtros.evento));
        } else {
            if (filtros.ano > 0) {
                url.searchParams.set("ano", String(filtros.ano));
            }

            if (filtros.mes > 0) {
                url.searchParams.set("mes", String(filtros.mes));
            }
        }

        window.history.replaceState({}, "", url.toString());
    }

    function preencherCards(cards) {
        $("#cardEventos").text(inteiro(cards.eventos));
        $("#cardInscritos").text(inteiro(cards.inscritos));
        $("#cardConfirmados").text(inteiro(cards.confirmados));
        $("#cardPendentes").text(inteiro(cards.pendentes));
        $("#cardCanceladas").text(inteiro(cards.canceladas));
        $("#cardPresencas").text(inteiro(cards.presencas));
        $("#cardRecebido").text(moeda(cards.recebido));
        $("#cardAReceber").text(moeda(cards.aReceber));
    }

    function montarUltimasInscricoes(lista) {
        let html = "";

        (lista || []).forEach(function (item) {
            const status = String(item.status || "");
            const pagamento = String(item.pagamento || "");

            html += `
                <tr>
                    <td>#${inteiro(item.idInscricao)}</td>
                    <td>
                        <div class="fw-semibold">${escaparHtml(item.nome)}</div>
                        <small class="text-muted">${escaparHtml(item.cidade || "Cidade não informada")}</small>
                    </td>
                    <td>${escaparHtml(item.evento)}</td>
                    <td>${badge(status, classeStatusInscricao(status))}</td>
                    <td>${badge(pagamento, classeStatusPagamento(pagamento))}</td>
                    <td class="text-nowrap">${escaparHtml(formatarData(item.criado_em))}</td>
                </tr>
            `;
        });

        if (html === "") {
            html = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        Nenhuma inscrição encontrada para o filtro selecionado.
                    </td>
                </tr>
            `;
        }

        $("#listaInscricoes").html(html);
    }

    function montarPagamentosPendentes(lista) {
        let html = "";

        (lista || []).forEach(function (item) {
            html += `
                <tr>
                    <td>
                        <div class="fw-semibold">${escaparHtml(item.nome)}</div>
                        <small class="text-muted">${escaparHtml(item.evento)}</small>
                    </td>
                    <td class="text-nowrap fw-semibold">${moeda(item.valor)}</td>
                    <td class="text-nowrap">${escaparHtml(
                        item.vencimento
                            ? formatarData(item.vencimento, false)
                            : "Sem vencimento"
                    )}</td>
                </tr>
            `;
        });

        if (html === "") {
            html = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-5">
                        Nenhum pagamento pendente para o filtro selecionado.
                    </td>
                </tr>
            `;
        }

        $("#listaFinanceiro").html(html);
    }

    function criarOuAtualizarGrafico(instancia, elemento, configuracao) {
        if (!elemento || typeof Chart === "undefined") {
            return instancia;
        }

        if (instancia) {
            instancia.destroy();
        }

        return new Chart(elemento, configuracao);
    }

    function montarGraficoInscricoes(lista) {
        const labels = (lista || []).map(item => item.mes);
        const valores = (lista || []).map(item => inteiro(item.total));

        graficoInscricoes = criarOuAtualizarGrafico(
            graficoInscricoes,
            document.getElementById("graficoInscricoes"),
            {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                        label: "Inscrições",
                        data: valores,
                        borderColor: "#0d6efd",
                        backgroundColor: "rgba(13, 110, 253, .12)",
                        fill: true,
                        tension: .35,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            }
        );
    }

    function montarGraficoPagamentos(lista) {
        const labels = (lista || []).map(item => item.status);
        const valores = (lista || []).map(item => inteiro(item.total));

        graficoPagamentos = criarOuAtualizarGrafico(
            graficoPagamentos,
            document.getElementById("graficoPagamentos"),
            {
                type: "doughnut",
                data: {
                    labels,
                    datasets: [{
                        data: valores,
                        backgroundColor: [
                            "#ffc107",
                            "#dc3545",
                            "#198754",
                            "#212529",
                            "#6c757d"
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "64%",
                    plugins: {
                        legend: { position: "bottom" }
                    }
                }
            }
        );
    }

    function montarGraficoFinanceiro(lista) {
        const labels = (lista || []).map(item => item.mes);
        const recebidos = (lista || []).map(item => numero(item.receita));
        const pendentes = (lista || []).map(item => numero(item.pendente));

        graficoFinanceiro = criarOuAtualizarGrafico(
            graficoFinanceiro,
            document.getElementById("graficoFinanceiro"),
            {
                type: "bar",
                data: {
                    labels,
                    datasets: [
                        {
                            label: "Recebido",
                            data: recebidos,
                            backgroundColor: "rgba(25, 135, 84, .78)"
                        },
                        {
                            label: "Pendente",
                            data: pendentes,
                            backgroundColor: "rgba(255, 193, 7, .78)"
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: valor => moeda(valor)
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: contexto => `${contexto.dataset.label}: ${moeda(contexto.raw)}`
                            }
                        }
                    }
                }
            }
        );
    }

    function montarGraficoCamisetas(lista) {
        const labels = (lista || []).map(item => item.camiseta);
        const valores = (lista || []).map(item => inteiro(item.total));

        graficoCamisetas = criarOuAtualizarGrafico(
            graficoCamisetas,
            document.getElementById("graficoCamisetas"),
            {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                        label: "Camisetas",
                        data: valores,
                        backgroundColor: "rgba(13, 110, 253, .72)",
                        borderRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            }
        );
    }

    function mostrarErro(mensagem) {
        $("#dashboardErro").removeClass("d-none").text(mensagem);
    }

    function esconderErro() {
        $("#dashboardErro").addClass("d-none").empty();
    }

    function atualizarResumoFiltro(filtro) {
        const descricao = filtro?.descricao || "Todos os dados";
        $("#dashboardFiltroResumo").text(descricao);

        $(".dashboard-subtitle").each(function () {
            const original = $(this).data("original");

            if (!original) {
                $(this).data("original", $(this).text());
            }

            const textoOriginal = $(this).data("original");
            $(this).text(descricao === "Todos os dados"
                ? textoOriginal
                : `${textoOriginal} — ${descricao}`);
        });
    }

    function carregarDashboard(atualizarUrl = false) {
        if (carregando) {
            return;
        }

        carregando = true;
        esconderErro();

        const filtros = obterFiltros();

        if (filtros.evento > 0) {
            filtros.ano = 0;
            filtros.mes = 0;
        }

        if (atualizarUrl) {
            atualizarUrlFiltros(filtros);
        }

        $("#btnAtualizarDashboard, #btnAplicarFiltroDashboard")
            .prop("disabled", true);

        $("#btnAtualizarDashboard i").addClass("fa-spin");

        $.ajax({
            url: endpoint,
            type: "GET",
            data: filtros,
            dataType: "json",
            cache: false
        })
            .done(function (resposta) {
                if (!resposta || resposta.status !== true) {
                    mostrarErro(
                        resposta?.msg
                        || "Não foi possível carregar os dados do dashboard."
                    );
                    return;
                }

                preencherCards(resposta.cards || {});
                montarUltimasInscricoes(resposta.ultimos || []);
                montarPagamentosPendentes(resposta.pendentesFinanceiro || []);
                montarGraficoInscricoes(resposta.inscricoesMensal || []);
                montarGraficoPagamentos(resposta.pagamentosStatus || []);
                montarGraficoFinanceiro(resposta.financeiroMensal || []);
                montarGraficoCamisetas(resposta.camisetas || []);
                atualizarResumoFiltro(resposta.filtro || {});

                $("#dashboardAtualizadoEm").text(
                    `Atualizado em ${formatarData(resposta.atualizadoEm || new Date().toISOString())}`
                );
            })
            .fail(function (xhr) {
                console.error(xhr.responseText);

                mostrarErro(
                    xhr.responseJSON?.msg
                    || "Erro ao comunicar com o servidor."
                );
            })
            .always(function () {
                carregando = false;

                $("#btnAtualizarDashboard, #btnAplicarFiltroDashboard")
                    .prop("disabled", false);

                $("#btnAtualizarDashboard i").removeClass("fa-spin");
            });
    }

    $("#filtroDashboardEvento").on("change", function () {
        atualizarEstadoFiltros();
    });

    $("#filtroDashboardAno, #filtroDashboardMes").on("change", function () {
        if (inteiro($(this).val()) > 0) {
            $("#filtroDashboardEvento").val("0");
            atualizarEstadoFiltros();
        }
    });

    $("#btnAplicarFiltroDashboard").on("click", function () {
        carregarDashboard(true);
    });

    $("#btnLimparFiltroDashboard").on("click", function () {
        $("#filtroDashboardAno, #filtroDashboardMes, #filtroDashboardEvento").val("0");
        atualizarEstadoFiltros();
        carregarDashboard(true);
    });

    $("#btnAtualizarDashboard").on("click", function () {
        carregarDashboard(false);
    });

    atualizarEstadoFiltros();
    carregarDashboard(false);
    window.setInterval(function () {
        carregarDashboard(false);
    }, intervaloAtualizacao);
});
