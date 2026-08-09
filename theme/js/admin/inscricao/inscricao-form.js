$(function () {
    "use strict";

    const $form = $("#formInscricao");
    const $evento = $("#idEvento");
    const $usuario = $("#buscarUsuario");
    const $idUsuario = $("#idUsuario");
    const $camiseta = $("#camiseta");
    const $cardCamiseta = $("#cardCamiseta");

    function mensagem(tipo, titulo, texto) {
        if (typeof Swal !== "undefined") {
            Swal.fire({ icon: tipo, title: titulo, text: texto });
            return;
        }

        alert(texto);
    }

    function preencherUsuario(dados) {
        $("#participanteNome").val(dados.nome || "");
        $("#participanteCpf").val(dados.cpf || "");
        $("#participanteTelefone").val(dados.telefone || "");
        $("#participanteEmail").val(dados.email || "");
        $("#participanteIgreja").val(dados.igreja || "");
    }

    function carregarUsuario(id) {
        id = Number.parseInt(id, 10) || 0;

        if (id <= 0) {
            preencherUsuario({});
            return;
        }

        $.getJSON("ajax/usuario-get.php", { id: id })
            .done(function (retorno) {
                if (!retorno.status) {
                    mensagem("error", "Erro", retorno.msg || "Participante não encontrado.");
                    return;
                }

                preencherUsuario(retorno.dados || {});
            })
            .fail(function () {
                mensagem("error", "Erro", "Não foi possível carregar o participante.");
            });
    }

    function aplicarEvento(dados) {
        const camisetaAtiva = Number(dados.camiseta_ativa || 0) === 1;
        const pagamentoObrigatorio = Number(dados.pagamento_obrigatorio || 0) === 1;

        $("#eventoSemSelecao").addClass("d-none");
        $("#eventoResumo").removeClass("d-none");
        $("#eventoValor").text(dados.valor_formatado || "R$ 0,00");
        $("#eventoDisponiveis").text(
            Number(dados.vagas || 0) > 0
                ? String(dados.disponiveis)
                : "Sem limite"
        );
        $("#eventoPagamento").html(
            pagamentoObrigatorio
                ? '<span class="badge bg-warning text-dark">Obrigatório</span>'
                : '<span class="badge bg-success">Não exigido</span>'
        );
        $("#eventoCamiseta").html(
            camisetaAtiva
                ? '<span class="badge bg-primary">Solicitada</span>'
                : '<span class="badge bg-secondary">Não solicitada</span>'
        );

        $cardCamiseta.toggleClass("d-none", !camisetaAtiva);
        $camiseta.prop("required", camisetaAtiva);

        if (!camisetaAtiva) {
            $camiseta.val("");
        }
    }

    function carregarEvento(id) {
        id = Number.parseInt(id, 10) || 0;

        if (id <= 0) {
            $("#eventoSemSelecao").removeClass("d-none");
            $("#eventoResumo").addClass("d-none");
            $cardCamiseta.addClass("d-none");
            $camiseta.prop("required", false);
            return;
        }

        $.getJSON("ajax/evento-get.php", { id: id })
            .done(function (retorno) {
                if (!retorno.status) {
                    mensagem("error", "Erro", retorno.msg || "Evento não encontrado.");
                    return;
                }

                aplicarEvento(retorno.dados || {});
            })
            .fail(function () {
                mensagem("error", "Erro", "Não foi possível carregar o evento.");
            });
    }

    $usuario.select2({
        theme: "bootstrap-5",
        width: "100%",
        placeholder: "Digite o nome, CPF ou e-mail",
        minimumInputLength: 2,
        ajax: {
            url: "ajax/usuario-search.php",
            dataType: "json",
            delay: 300,
            data: function (params) {
                return { q: params.term || "" };
            },
            processResults: function (dados) {
                return { results: Array.isArray(dados) ? dados : [] };
            },
            cache: true
        },
        language: {
            inputTooShort: function () {
                return "Digite pelo menos 2 caracteres.";
            },
            searching: function () {
                return "Pesquisando...";
            },
            noResults: function () {
                return "Nenhum usuário encontrado.";
            }
        }
    });

    $usuario.on("select2:select", function (eventoSelecao) {
        const id = Number.parseInt(eventoSelecao.params.data.id, 10) || 0;
        $idUsuario.val(id);
        carregarUsuario(id);
    });

    $usuario.on("select2:clear", function () {
        $idUsuario.val("");
        preencherUsuario({});
    });

    $evento.on("change", function () {
        carregarEvento($(this).val());
    });

    $form.on("submit", function (eventoSubmit) {
        eventoSubmit.preventDefault();

        if ((Number.parseInt($idUsuario.val(), 10) || 0) <= 0) {
            mensagem("warning", "Atenção", "Selecione o participante.");
            return;
        }

        const $botao = $("#btnSalvar");

        $.ajax({
            url: "ajax/inscricao-new.php",
            type: "POST",
            dataType: "json",
            data: $form.serialize(),
            beforeSend: function () {
                $botao
                    .prop("disabled", true)
                    .html('<i class="fa fa-spinner fa-spin me-1"></i>Salvando...');
            }
        })
            .done(function (retorno) {
                if (!retorno.status) {
                    mensagem("error", "Erro", retorno.msg || "Não foi possível salvar a inscrição.");
                    return;
                }

                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso",
                        text: retorno.msg,
                        timer: 1700,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.href = "inscricoes.php";
                    });
                } else {
                    window.location.href = "inscricoes.php";
                }
            })
            .fail(function (xhr) {
                const texto = xhr.responseJSON?.msg || "Erro ao processar a inscrição.";
                mensagem("error", "Erro", texto);
            })
            .always(function () {
                $botao
                    .prop("disabled", false)
                    .html('<i class="fa fa-save me-1"></i>Salvar inscrição');
            });
    });

    if ((Number.parseInt($idUsuario.val(), 10) || 0) > 0) {
        carregarUsuario($idUsuario.val());
    }

    if ((Number.parseInt($evento.val(), 10) || 0) > 0) {
        carregarEvento($evento.val());
    }
});
