$(function () {
    "use strict";

    const token = String($("#_token").val() || "");
    const modalElemento = document.getElementById("modalView");
    const modalView = modalElemento
        ? bootstrap.Modal.getOrCreateInstance(modalElemento)
        : null;

    let paginaAtual = 1;
    let temporizador = null;

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

    function carregarInscricoes(pagina = 1) {
        paginaAtual = Math.max(1, Number.parseInt(pagina, 10) || 1);

        $("#listaInscricoes").html(`
            <div class="text-center p-5">
                <div class="spinner-border text-primary"></div>
                <div class="mt-2 text-muted">Carregando inscrições...</div>
            </div>
        `);

        $.ajax({
            url: "ajax/inscricoes-lista.php",
            type: "GET",
            cache: false,
            data: {
                fragmento: 1,
                pagina: paginaAtual,
                pesquisa: String($("#pesquisa").val() || "").trim(),
                evento: Number.parseInt($("#evento").val(), 10) || 0,
                status: String($("#status").val() || ""),
                pagamento: String($("#pagamento").val() || "")
            }
        }).done(function (html) {
            $("#listaInscricoes").html(html);
        }).fail(function () {
            $("#listaInscricoes").html(`
                <div class="alert alert-danger m-3">
                    Não foi possível carregar as inscrições.
                </div>
            `);
        });
    }

    function alterar(endpoint, id, mensagemPadrao) {
        return $.ajax({
            url: endpoint,
            type: "POST",
            dataType: "json",
            data: {
                id: id,
                _token: token
            }
        }).done(function (resposta) {
            if (!resposta || resposta.status !== true) {
                alertar(
                    "error",
                    "Erro",
                    resposta?.msg || mensagemPadrao
                );
                return;
            }

            carregarInscricoes(paginaAtual);
        }).fail(function (xhr) {
            alertar(
                "error",
                "Erro",
                xhr.responseJSON?.msg || mensagemPadrao
            );
        });
    }

    $("#pesquisa").on("input", function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(function () {
            carregarInscricoes(1);
        }, 400);
    });

    $("#evento, #status, #pagamento").on("change", function () {
        carregarInscricoes(1);
    });

    $(document).on("click", ".pagina-inscricao", function () {
        if ($(this).closest(".page-item").hasClass("disabled")) {
            return;
        }

        carregarInscricoes($(this).data("pagina"));
    });

    $(document).on("click", ".btn-presenca", function () {
        alterar(
            "ajax/inscricao-presenca.php",
            Number.parseInt($(this).data("id"), 10) || 0,
            "Não foi possível alterar a presença."
        );
    });

    $(document).on("click", ".btn-emitir-certificado", function () {
        const botao = $(this);
        const id = Number.parseInt(botao.data("id"), 10) || 0;
        const nome = String(botao.data("nome") || "");
        const emitido = Number.parseInt(botao.data("emitido"), 10) === 1;

        if (id <= 0) {
            return;
        }

        const confirmacao = typeof Swal !== "undefined"
            ? Swal.fire({
                icon: "question",
                title: emitido ? "Reenviar certificado?" : "Emitir certificado?",
                text: emitido
                    ? `Uma nova cópia será enviada para ${nome}.`
                    : `O PDF será gerado, armazenado e enviado para ${nome}.`,
                showCancelButton: true,
                confirmButtonText: emitido ? "Reenviar" : "Emitir",
                cancelButtonText: "Cancelar"
            })
            : Promise.resolve({
                isConfirmed: window.confirm(
                    emitido
                        ? `Reenviar o certificado para ${nome}?`
                        : `Emitir o certificado de ${nome}?`
                )
            });

        confirmacao.then(function (resultado) {
            if (!resultado.isConfirmed) {
                return;
            }

            botao.prop("disabled", true);

            $.ajax({
                url: "../certificado/ajax/certificado-emitir.php",
                type: "POST",
                dataType: "json",
                data: {
                    id: id,
                    _token: token
                }
            }).done(function (resposta) {
                if (!resposta || resposta.status !== true) {
                    alertar(
                        "error",
                        "Erro",
                        resposta?.msg || "Não foi possível emitir o certificado."
                    );
                    return;
                }

                alertar(
                    resposta.enviado === false ? "warning" : "success",
                    resposta.enviado === false
                        ? "Certificado armazenado"
                        : (resposta.reenvio ? "Certificado reenviado" : "Certificado emitido"),
                    resposta.msg
                );
                carregarInscricoes(paginaAtual);
            }).fail(function (xhr) {
                alertar(
                    "error",
                    "Erro",
                    xhr.responseJSON?.msg || "Não foi possível emitir o certificado."
                );
            }).always(function () {
                botao.prop("disabled", false);
            });
        });
    });

    $(document).on("click", ".btn-view", function () {
        const id = Number.parseInt($(this).data("id"), 10) || 0;

        if (id <= 0 || !modalView) {
            return;
        }

        $("#modalConteudo").html(`
            <div class="text-center p-5">
                <div class="spinner-border text-primary"></div>
                <div class="mt-2 text-muted">Carregando inscrição...</div>
            </div>
        `);
        modalView.show();

        $.ajax({
            url: "ajax/inscricao-view.php",
            type: "GET",
            cache: false,
            data: { id: id }
        }).done(function (html) {
            $("#modalConteudo").html(html);
        }).fail(function () {
            $("#modalConteudo").html(`
                <div class="alert alert-danger mb-0">
                    Não foi possível carregar a inscrição.
                </div>
            `);
        });
    });

    $(document).on("click", ".btn-delete", function () {
        const id = Number.parseInt($(this).data("id"), 10) || 0;
        const nome = String($(this).data("nome") || "");

        if (id <= 0) {
            return;
        }

        const confirmar = typeof Swal !== "undefined"
            ? Swal.fire({
                title: "Excluir inscrição?",
                text: `${nome}. O pagamento vinculado também será removido.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Excluir",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#dc3545"
            })
            : Promise.resolve({
                isConfirmed: window.confirm(
                    `Excluir a inscrição de ${nome} e o pagamento vinculado?`
                )
            });

        confirmar.then(function (resultado) {
            if (!resultado.isConfirmed) {
                return;
            }

            alterar(
                "ajax/inscricao-delete.php",
                id,
                "Não foi possível excluir a inscrição."
            );
        });
    });

    $("#modalView").on("hidden.bs.modal", function () {
        $("#modalConteudo").empty();
    });

    carregarInscricoes(1);
});
