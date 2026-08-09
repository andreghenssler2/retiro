(function ($) {
    "use strict";

    const $form = $("#formRelatorios");
    const token = $("#_token").val();
    let grafico = null;

    const statusPorTipo = {
        financeiro: ["Pendente", "Vencido", "Pago", "Cancelado", "Estornado"],
        pagamentos: ["Pendente", "Vencido", "Pago", "Cancelado", "Estornado"],
        inscricoes: ["Pendente", "Confirmada", "Cancelada"]
    };

    function escapar(valor) {
        return $("<div>").text(valor == null ? "" : String(valor)).html();
    }

    function slug(valor) {
        return String(valor || "")
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            .toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    }

    function moeda(valor) {
        return Number(valor || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }

    function inteiro(valor) {
        return Number.parseInt(valor || 0, 10).toLocaleString("pt-BR");
    }

    function data(valor, comHora) {
        if (!valor || valor === "0000-00-00" || valor.startsWith("0000-00-00")) return "-";
        const texto = String(valor).replace(" ", "T");
        const d = new Date(texto.length === 10 ? texto + "T00:00:00" : texto);
        if (Number.isNaN(d.getTime())) return escapar(valor);
        return d.toLocaleString("pt-BR", comHora ? { dateStyle: "short", timeStyle: "short" } : { dateStyle: "short" });
    }

    function formatar(valor, formato) {
        switch (formato) {
            case "moeda": return moeda(valor);
            case "inteiro": return inteiro(valor);
            case "data": return data(valor, false);
            case "datahora": return data(valor, true);
            case "status": return '<span class="status-badge status-' + slug(valor) + '">' + escapar(valor || "-") + '</span>';
            default: return escapar(valor || "-");
        }
    }

    function tipoAtual() {
        return $("#tipoRelatorio").val();
    }

    function atualizarFiltros() {
        const tipo = tipoAtual();
        $(".filtro-campo").each(function () {
            const tipos = String($(this).data("tipos") || "").split(/\s+/);
            $(this).toggleClass("d-none", !tipos.includes(tipo));
        });

        const $status = $("#status").empty().append('<option value="">Todas</option>');
        (statusPorTipo[tipo] || []).forEach(function (item) {
            $status.append($("<option>").val(item).text(item));
        });
    }

    function selecionarTipo(tipo) {
        $("#tipoRelatorio").val(tipo);
        $(".relatorio-tipo-card").removeClass("active").filter('[data-tipo="' + tipo + '"]').addClass("active");
        atualizarFiltros();
        consultar();
    }

    function mostrarErro(mensagem) {
        $("#alertaRelatorio").removeClass("d-none").text(mensagem || "Não foi possível gerar o relatório.");
    }

    function ocultarErro() {
        $("#alertaRelatorio").addClass("d-none").empty();
    }

    function renderizarCards(cards) {
        const html = (cards || []).map(function (card) {
            return '<div class="col-12 col-sm-6 col-xl-3">' +
                '<div class="card relatorio-resumo-card cor-' + escapar(card.cor || "primary") + ' h-100">' +
                '<div class="card-body d-flex align-items-center gap-3">' +
                '<span class="relatorio-card-icon"><i class="fa-solid ' + escapar(card.icone || "fa-chart-simple") + '"></i></span>' +
                '<div><span class="relatorio-card-label">' + escapar(card.rotulo || "") + '</span>' +
                '<strong class="relatorio-card-value">' + formatar(card.valor, card.formato) + '</strong></div>' +
                '</div></div></div>';
        }).join("");
        $("#cardsRelatorio").html(html);
    }

    function renderizarTabela(relatorio) {
        const colunas = relatorio.colunas || [];
        const linhas = relatorio.linhas || [];
        const cabecalho = colunas.map(c => '<th class="' + escapar(c.classe || "") + '">' + escapar(c.rotulo) + '</th>').join("");
        let corpo = '';

        if (!linhas.length) {
            corpo = '<tr><td colspan="' + Math.max(1, colunas.length) + '" class="text-center py-5 text-muted">Nenhum registro encontrado.</td></tr>';
        } else {
            corpo = linhas.map(function (linha) {
                return '<tr>' + colunas.map(function (c) {
                    return '<td class="' + escapar(c.classe || "") + '">' + formatar(linha[c.chave], c.formato) + '</td>';
                }).join("") + '</tr>';
            }).join("");
        }

        $("#tabelaRelatorio thead").html("<tr>" + cabecalho + "</tr>");
        $("#tabelaRelatorio tbody").html(corpo);
        $("#tituloResultado").text(relatorio.titulo || "Resultado");
        $("#descricaoResultado").text(relatorio.descricao || "");
        const textoTotal = relatorio.limitado
            ? relatorio.exibidos + " de " + relatorio.total + " registros"
            : relatorio.total + (Number(relatorio.total) === 1 ? " registro" : " registros");
        $("#totalResultado").text(textoTotal);

        if (relatorio.observacao) {
            $("#observacaoRelatorio").removeClass("d-none").text(relatorio.observacao);
        } else {
            $("#observacaoRelatorio").addClass("d-none").empty();
        }
    }

    function renderizarGrafico(dados) {
        if (grafico) {
            grafico.destroy();
            grafico = null;
        }
        const labels = dados?.labels || [];
        const valores = dados?.valores || [];
        $("#tituloGrafico").text(dados?.titulo || "Resumo");
        $("#graficoVazio").toggleClass("d-none", labels.length > 0);
        $("#graficoRelatorio").toggleClass("d-none", labels.length === 0);
        if (!labels.length || typeof Chart === "undefined") return;

        grafico = new Chart(document.getElementById("graficoRelatorio"), {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{ label: dados.formato === "moeda" ? "Valor" : "Quantidade", data: valores, borderWidth: 1 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => dados.formato === "moeda" ? moeda(ctx.raw) : inteiro(ctx.raw) } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: value => dados.formato === "moeda" ? moeda(value) : inteiro(value) } }
                }
            }
        });
    }

    function consultar() {
        ocultarErro();
        const $botao = $("#btnConsultar");
        $botao.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Consultando');
        $("#tabelaRelatorio tbody").html('<tr><td class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Carregando relatório...</td></tr>');

        const dados = $form.serializeArray();
        dados.push({ name: "_token", value: token });

        $.ajax({ url: "ajax/consultar.php", method: "POST", dataType: "json", data: dados })
            .done(function (resposta) {
                if (!resposta?.sucesso) {
                    mostrarErro(resposta?.mensagem);
                    return;
                }
                const relatorio = resposta.relatorio || {};
                renderizarCards(relatorio.cards);
                renderizarTabela(relatorio);
                renderizarGrafico(relatorio.grafico);
            })
            .fail(function (xhr) {
                mostrarErro(xhr.responseJSON?.mensagem || "Não foi possível gerar o relatório.");
            })
            .always(function () {
                $botao.prop("disabled", false).html('<i class="fa-solid fa-magnifying-glass me-1"></i> Consultar');
            });
    }

    $(document).on("click", ".relatorio-tipo-card", function () {
        selecionarTipo($(this).data("tipo"));
    });

    $form.on("submit", function (event) {
        event.preventDefault();
        consultar();
    });

    $("#btnLimpar").on("click", function () {
        const tipo = tipoAtual();
        $form[0].reset();
        $("#tipoRelatorio").val(tipo);
        atualizarFiltros();
        consultar();
    });

    $("#btnExportarPdf").on("click", function () {
        const query = $form.serialize();
        window.open("pdf.php?" + query, "_blank", "noopener");
    });

    atualizarFiltros();
    consultar();
})(jQuery);
