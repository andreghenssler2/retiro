$(function () {
    "use strict";

    const token = String($("#_token").val() || "");
    const modalViewElemento = document.getElementById("modalView");
    const modalPagamentoElemento = document.getElementById("modalPagamento");

    const modalView = modalViewElemento
        ? bootstrap.Modal.getOrCreateInstance(modalViewElemento)
        : null;

    const modalPagamento = modalPagamentoElemento
        ? bootstrap.Modal.getOrCreateInstance(modalPagamentoElemento)
        : null;

    let paginaAtual = 1;
    let temporizadorPesquisa = null;
    let carregando = false;

    function mensagemErro(xhr, padrao) {
        return xhr.responseJSON?.mensagem
            || xhr.responseJSON?.msg
            || padrao;
    }

    function alertar(tipo, titulo, mensagem) {
        if (typeof Swal !== "undefined") {
            Swal.fire({
                icon: tipo,
                title: titulo,
                text: mensagem,
                confirmButtonText: "OK"
            });
            return;
        }

        window.alert(mensagem);
    }

    function toast(tipo, mensagem) {
        if (typeof Swal !== "undefined") {
            Swal.fire({
                icon: tipo,
                title: mensagem,
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            });
            return;
        }

        console.log(mensagem);
    }

    function formatarMoeda(valor) {
        const numero = Number.parseFloat(valor);

        return Number.isFinite(numero)
            ? numero.toLocaleString("pt-BR", {
                style: "currency",
                currency: "BRL"
            })
            : "R$ 0,00";
    }

    async function copiarTexto(texto) {
        const conteudo = String(texto || "").trim();

        if (!conteudo) {
            throw new Error("Não há conteúdo para copiar.");
        }

        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(conteudo);
            return;
        }

        const area = document.createElement("textarea");
        area.value = conteudo;
        area.setAttribute("readonly", "readonly");
        area.style.position = "fixed";
        area.style.opacity = "0";
        document.body.appendChild(area);
        area.select();

        const copiado = document.execCommand("copy");
        document.body.removeChild(area);

        if (!copiado) {
            throw new Error("O navegador não permitiu copiar o conteúdo.");
        }
    }

    function filtros(pagina = paginaAtual) {
        return {
            _token: token,
            pagina: pagina,
            pesquisa: String($("#pesquisa").val() || "").trim(),
            evento: Number.parseInt($("#evento").val(), 10) || 0,
            status: String($("#status").val() || ""),
            forma: String($("#forma").val() || "")
        };
    }

    function montarPaginacao(pagina, totalPaginas) {
        const $paginacao = $("#paginacao").empty();

        if (totalPaginas <= 1) {
            return;
        }

        const inicio = Math.max(1, pagina - 2);
        const fim = Math.min(totalPaginas, pagina + 2);

        $paginacao.append(`
            <li class="page-item ${pagina <= 1 ? "disabled" : ""}">
                <button type="button" class="page-link btn-pagina" data-pagina="${Math.max(1, pagina - 1)}">
                    <i class="fa fa-chevron-left"></i>
                </button>
            </li>
        `);

        for (let p = inicio; p <= fim; p += 1) {
            $paginacao.append(`
                <li class="page-item ${p === pagina ? "active" : ""}">
                    <button type="button" class="page-link btn-pagina" data-pagina="${p}">${p}</button>
                </li>
            `);
        }

        $paginacao.append(`
            <li class="page-item ${pagina >= totalPaginas ? "disabled" : ""}">
                <button type="button" class="page-link btn-pagina" data-pagina="${Math.min(totalPaginas, pagina + 1)}">
                    <i class="fa fa-chevron-right"></i>
                </button>
            </li>
        `);
    }

    function carregarCards() {
        $.ajax({
            url: "ajax/pagamentos-cards.php",
            type: "POST",
            dataType: "json",
            data: {
                _token: token,
                evento: Number.parseInt($("#evento").val(), 10) || 0
            }
        }).done(function (resposta) {
            if (!resposta || resposta.sucesso !== true) {
                return;
            }

            $("#cardRecebido").text(formatarMoeda(resposta.recebido));
            $("#cardPendente").text(formatarMoeda(resposta.pendente));
            $("#cardVencido").text(formatarMoeda(resposta.vencido));
            $("#cardCancelado").text(formatarMoeda(resposta.cancelado));
        }).fail(function (xhr) {
            console.error("Falha ao carregar cards financeiros:", xhr.responseText);
        });
    }

    function carregarPagamentos(pagina = 1) {
        if (carregando) {
            return;
        }

        paginaAtual = Math.max(1, Number.parseInt(pagina, 10) || 1);
        carregando = true;

        $("#loaderPagamentos").show();
        $("#listaPagamentos").hide();

        $.ajax({
            url: "ajax/pagamentos-listar.php",
            type: "POST",
            dataType: "json",
            data: filtros(paginaAtual)
        }).done(function (resposta) {
            if (!resposta || resposta.sucesso !== true) {
                alertar(
                    "error",
                    "Erro",
                    resposta?.mensagem || "Não foi possível carregar os pagamentos."
                );
                return;
            }

            paginaAtual = Number(resposta.pagina) || 1;
            $("#listaPagamentos").html(resposta.html || "");

            const total = Number(resposta.totalRegistros) || 0;
            $("#textoPaginacao").text(
                total > 0
                    ? `Exibindo ${resposta.inicio} até ${resposta.fim} de ${total} pagamentos.`
                    : "Nenhum pagamento encontrado."
            );

            montarPaginacao(
                paginaAtual,
                Number(resposta.totalPaginas) || 1
            );
        }).fail(function (xhr) {
            alertar(
                "error",
                "Erro",
                mensagemErro(xhr, "Não foi possível carregar os pagamentos.")
            );
        }).always(function () {
            carregando = false;
            $("#loaderPagamentos").hide();
            $("#listaPagamentos").show();
        });
    }

    function abrirVisualizacao(idPagamento) {
        if (!modalView || idPagamento <= 0) {
            return;
        }

        $("#modalConteudo").html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <div class="mt-2 text-muted">Carregando pagamento...</div>
            </div>
        `);
        modalView.show();

        $.ajax({
            url: "ajax/pagamento-visualizar.php",
            type: "POST",
            dataType: "json",
            data: {
                _token: token,
                idPagamento: idPagamento
            }
        }).done(function (resposta) {
            if (!resposta || resposta.sucesso !== true) {
                $("#modalConteudo").html(`
                    <div class="alert alert-danger mb-0">
                        ${resposta?.mensagem || "Não foi possível carregar o pagamento."}
                    </div>
                `);
                return;
            }

            $("#modalConteudo").html(resposta.html || "");
        }).fail(function (xhr) {
            $("#modalConteudo").html(`
                <div class="alert alert-danger mb-0">
                    ${mensagemErro(xhr, "Não foi possível carregar o pagamento.")}
                </div>
            `);
        });
    }

    function abrirRecebimento(idPagamento) {
        if (!modalPagamento || idPagamento <= 0) {
            return;
        }

        $("#tituloModalPagamento").html(`
            <i class="fa fa-hand-holding-dollar me-2"></i>
            Atualizar recebimento
        `);

        $("#conteudoModalPagamento").html(`
            <div class="text-center py-5">
                <div class="spinner-border text-success"></div>
                <div class="mt-2 text-muted">Carregando recebimento...</div>
            </div>
        `);

        modalPagamento.show();

        $.ajax({
            url: "ajax/pagamento-form.php",
            type: "GET",
            data: { id: idPagamento },
            cache: false
        }).done(function (html) {
            $("#conteudoModalPagamento").html(html);
        }).fail(function () {
            $("#conteudoModalPagamento").html(`
                <div class="alert alert-danger m-3">
                    Não foi possível carregar o formulário de recebimento.
                </div>
            `);
        });
    }

    async function confirmarOperacaoPagamento(opcoes) {
        if (typeof Swal !== "undefined") {
            const resultado = await Swal.fire({
                icon: opcoes.icone || "warning",
                title: opcoes.titulo,
                text: opcoes.texto,
                input: "textarea",
                inputLabel: "Motivo ou observação",
                inputPlaceholder: "Opcional",
                inputAttributes: {
                    maxlength: 500
                },
                showCancelButton: true,
                confirmButtonText: opcoes.confirmar,
                cancelButtonText: "Voltar",
                confirmButtonColor: opcoes.cor || "#dc3545",
                reverseButtons: true,
                focusCancel: true
            });

            return {
                confirmado: resultado.isConfirmed === true,
                motivo: String(resultado.value || "").trim()
            };
        }

        return {
            confirmado: window.confirm(opcoes.texto),
            motivo: ""
        };
    }

    function enviarOperacaoPagamento(configuracao) {
        const $botao = configuracao.botao;
        const htmlOriginal = $botao.html();

        $botao
            .prop("disabled", true)
            .html('<i class="fa fa-spinner fa-spin me-1"></i> Processando...');

        return $.ajax({
            url: configuracao.url,
            type: "POST",
            dataType: "json",
            data: {
                _token: token,
                idPagamento: configuracao.idPagamento,
                motivo: configuracao.motivo || ""
            }
        }).done(function (resposta) {
            if (!resposta || resposta.sucesso !== true) {
                alertar(
                    "error",
                    "Erro",
                    resposta?.mensagem || configuracao.erroPadrao
                );
                return;
            }

            configuracao.aoConcluir(resposta);
        }).fail(function (xhr) {
            alertar(
                "error",
                "Erro",
                mensagemErro(xhr, configuracao.erroPadrao)
            );
        }).always(function () {
            $botao.prop("disabled", false).html(htmlOriginal);
        });
    }

    $("#formFiltrosPagamento").on("submit", function (evento) {
        evento.preventDefault();
        carregarPagamentos(1);
        carregarCards();
    });

    $("#pesquisa").on("input", function () {
        clearTimeout(temporizadorPesquisa);
        temporizadorPesquisa = setTimeout(function () {
            carregarPagamentos(1);
        }, 400);
    });

    $("#evento, #status, #forma").on("change", function () {
        carregarPagamentos(1);
        carregarCards();
    });

    $("#btnAtualizar").on("click", function () {
        carregarPagamentos(paginaAtual);
        carregarCards();
    });

    $(document).on("click", ".btn-pagina", function () {
        if ($(this).closest(".page-item").hasClass("disabled")) {
            return;
        }

        carregarPagamentos($(this).data("pagina"));
    });

    $(document).on("click", ".btn-visualizar", function () {
        abrirVisualizacao(Number.parseInt($(this).data("id"), 10) || 0);
    });

    $(document).on("click", ".btn-editar", function () {
        abrirRecebimento(Number.parseInt($(this).data("id"), 10) || 0);
    });

    $(document).on("click", ".btn-cancelar-pagamento", async function () {
        const $botao = $(this);
        const idPagamento = Number.parseInt($botao.data("id"), 10) || 0;

        if (idPagamento <= 0) {
            return;
        }

        const confirmacao = await confirmarOperacaoPagamento({
            icone: "warning",
            titulo: "Cancelar pagamento?",
            texto: "A cobrança deixará de aceitar pagamentos. A inscrição será cancelada e a presença será removida.",
            confirmar: "Sim, cancelar",
            cor: "#dc3545"
        });

        if (!confirmacao.confirmado) {
            return;
        }

        enviarOperacaoPagamento({
            botao: $botao,
            url: "ajax/pagamento-cancelar.php",
            idPagamento: idPagamento,
            motivo: confirmacao.motivo,
            erroPadrao: "Não foi possível cancelar o pagamento.",
            aoConcluir: function (resposta) {
                modalPagamento?.hide();
                toast("success", resposta.mensagem || "Pagamento cancelado.");
                carregarPagamentos(paginaAtual);
                carregarCards();
            }
        });
    });

    $(document).on("click", ".btn-estornar-pagamento", async function () {
        const $botao = $(this);
        const idPagamento = Number.parseInt($botao.data("id"), 10) || 0;
        const integrado = String($botao.data("integracao") || "") === "asaas";

        if (idPagamento <= 0) {
            return;
        }

        const confirmacao = await confirmarOperacaoPagamento({
            icone: "warning",
            titulo: integrado ? "Estornar pagamento no Asaas?" : "Marcar pagamento como estornado?",
            texto: integrado
                ? "Será solicitado o estorno integral ao Asaas. A inscrição será cancelada e a presença será removida."
                : "O pagamento será marcado como Estornado. A devolução do valor deverá ser conferida manualmente.",
            confirmar: integrado ? "Sim, estornar no Asaas" : "Sim, marcar como estornado",
            cor: "#dc3545"
        });

        if (!confirmacao.confirmado) {
            return;
        }

        enviarOperacaoPagamento({
            botao: $botao,
            url: "ajax/pagamento-estornar.php",
            idPagamento: idPagamento,
            motivo: confirmacao.motivo,
            erroPadrao: "Não foi possível estornar o pagamento.",
            aoConcluir: function (resposta) {
                toast("success", resposta.mensagem || "Pagamento estornado.");
                carregarPagamentos(paginaAtual);
                carregarCards();
                abrirVisualizacao(idPagamento);
            }
        });
    });

    $(document).on("submit", "#formRecebimento", function (evento) {
        evento.preventDefault();

        const formulario = this;
        const $formulario = $(formulario);
        const $botao = $("#btnSalvarRecebimento");

        if (String($formulario.data("pagamento-bloqueado")) === "1") {
            const statusAtual = String($formulario.data("status-pagamento") || "");
            alertar(
                "info",
                "Pagamento bloqueado",
                statusAtual === "Vencido"
                    ? "Este boleto está vencido. Consulte os detalhes para verificar a tolerância e a situação da inscrição."
                    : "A forma de pagamento e a geração de cobranças não podem mais ser alteradas."
            );
            return;
        }
        const htmlOriginal = $botao.html();
        const dados = new FormData(formulario);
        const idPagamento = Number.parseInt(dados.get("idPagamento"), 10) || 0;

        $botao
            .prop("disabled", true)
            .html('<i class="fa fa-spinner fa-spin me-1"></i> Processando...');

        $.ajax({
            url: "ajax/pagamento-salvar.php",
            type: "POST",
            dataType: "json",
            data: dados,
            processData: false,
            contentType: false
        }).done(function (resposta) {
            if (!resposta || resposta.sucesso !== true) {
                alertar(
                    "error",
                    "Erro",
                    resposta?.mensagem || "Não foi possível atualizar o pagamento."
                );
                return;
            }

            modalPagamento?.hide();
            toast("success", resposta.mensagem || "Pagamento atualizado.");
            carregarPagamentos(paginaAtual);
            carregarCards();

            if (resposta.acao === "visualizar" && idPagamento > 0) {
                window.setTimeout(function () {
                    abrirVisualizacao(idPagamento);
                }, 350);
            }
        }).fail(function (xhr) {
            alertar(
                "error",
                "Erro",
                mensagemErro(xhr, "Não foi possível atualizar o pagamento.")
            );
        }).always(function () {
            $botao.prop("disabled", false).html(htmlOriginal);
        });
    });

    $(document).on("click", ".btn-copiar-conteudo", async function () {
        const alvo = String($(this).data("alvo") || "");
        const elemento = alvo ? document.querySelector(alvo) : null;
        const texto = elemento ? (elemento.value || elemento.textContent || "") : "";

        try {
            await copiarTexto(texto);
            toast("success", "Código copiado.");
        } catch (erro) {
            alertar("error", "Erro", erro.message || "Não foi possível copiar.");
        }
    });

    $(document).on("click", ".btn-sincronizar-asaas", function () {
        const $botao = $(this);
        const idPagamento = Number.parseInt($botao.data("id"), 10) || 0;

        if (idPagamento <= 0) {
            return;
        }

        const htmlOriginal = $botao.html();
        $botao
            .prop("disabled", true)
            .html('<i class="fa fa-spinner fa-spin me-1"></i> Consultando...');

        $.ajax({
            url: "ajax/pagamento-sincronizar.php",
            type: "POST",
            dataType: "json",
            data: {
                _token: token,
                idPagamento: idPagamento
            }
        }).done(function (resposta) {
            if (!resposta || resposta.sucesso !== true) {
                alertar("error", "Erro", resposta?.mensagem || "Não foi possível consultar o Asaas.");
                return;
            }

            toast("success", resposta.mensagem || "Situação atualizada.");
            carregarPagamentos(paginaAtual);
            carregarCards();
            abrirVisualizacao(idPagamento);
        }).fail(function (xhr) {
            alertar("error", "Erro", mensagemErro(xhr, "Não foi possível consultar o Asaas."));
        }).always(function () {
            $botao.prop("disabled", false).html(htmlOriginal);
        });
    });

    $("#modalView").on("hidden.bs.modal", function () {
        $("#modalConteudo").empty();
    });

    $("#modalPagamento").on("hidden.bs.modal", function () {
        $("#conteudoModalPagamento").empty();
    });

    carregarPagamentos(1);
    carregarCards();
});
