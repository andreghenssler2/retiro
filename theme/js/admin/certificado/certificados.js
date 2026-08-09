$(function () {
    "use strict";

    const token = String(window.CERTIFICADO_CSRF || $("input[name='_token']").val() || "");

    function mensagemErro(xhr, padrao) {
        return xhr?.responseJSON?.msg || padrao;
    }

    function avisar(icon, title, text) {
        if (typeof Swal !== "undefined") {
            return Swal.fire({
                icon,
                title,
                text,
                confirmButtonText: "OK"
            });
        }

        window.alert(text);
        return Promise.resolve();
    }

    $("#formModeloCertificado").on("submit", function (event) {
        event.preventDefault();

        const form = this;
        const botao = $("#btnSalvarModelo");
        const textoOriginal = botao.html();
        const dados = new FormData(form);

        botao.prop("disabled", true).html(
            '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...'
        );

        $.ajax({
            url: "ajax/modelo-salvar.php",
            method: "POST",
            data: dados,
            dataType: "json",
            processData: false,
            contentType: false
        }).done(function (resposta) {
            if (!resposta?.status) {
                avisar("error", "Erro", resposta?.msg || "Não foi possível salvar o modelo.");
                return;
            }

            avisar("success", "Modelo salvo", resposta.msg).then(function () {
                window.location.href = resposta.redirect || "index.php";
            });
        }).fail(function (xhr) {
            avisar("error", "Erro", mensagemErro(xhr, "Não foi possível salvar o modelo."));
        }).always(function () {
            botao.prop("disabled", false).html(textoOriginal);
        });
    });

    $(document).on("click", ".btn-excluir-modelo", function () {
        const id = Number.parseInt($(this).data("id"), 10) || 0;
        const nome = String($(this).data("nome") || "");

        const confirmacao = typeof Swal !== "undefined"
            ? Swal.fire({
                icon: "warning",
                title: "Excluir modelo?",
                text: nome,
                showCancelButton: true,
                confirmButtonText: "Excluir",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#dc3545"
            })
            : Promise.resolve({ isConfirmed: window.confirm(`Excluir o modelo ${nome}?`) });

        confirmacao.then(function (resultado) {
            if (!resultado.isConfirmed) {
                return;
            }

            $.ajax({
                url: "ajax/modelo-excluir.php",
                method: "POST",
                dataType: "json",
                data: { id, _token: token }
            }).done(function (resposta) {
                if (!resposta?.status) {
                    avisar("error", "Erro", resposta?.msg || "Não foi possível excluir o modelo.");
                    return;
                }

                window.location.reload();
            }).fail(function (xhr) {
                avisar("error", "Erro", mensagemErro(xhr, "Não foi possível excluir o modelo."));
            });
        });
    });

    $(document).on("click", ".btn-reenviar-certificado", function () {
        const id = Number.parseInt($(this).data("id"), 10) || 0;
        const nome = String($(this).data("nome") || "");
        const botao = $(this);

        const confirmacao = typeof Swal !== "undefined"
            ? Swal.fire({
                icon: "question",
                title: "Reenviar certificado?",
                text: `Uma nova cópia será enviada para ${nome}.`,
                showCancelButton: true,
                confirmButtonText: "Reenviar",
                cancelButtonText: "Cancelar"
            })
            : Promise.resolve({ isConfirmed: window.confirm(`Reenviar certificado para ${nome}?`) });

        confirmacao.then(function (resultado) {
            if (!resultado.isConfirmed) {
                return;
            }

            botao.prop("disabled", true);

            $.ajax({
                url: "ajax/certificado-reenviar.php",
                method: "POST",
                dataType: "json",
                data: { id, _token: token }
            }).done(function (resposta) {
                if (!resposta?.status) {
                    avisar("error", "Erro", resposta?.msg || "Não foi possível reenviar o certificado.");
                    return;
                }

                avisar(
                    resposta.enviado ? "success" : "warning",
                    resposta.enviado ? "Certificado enviado" : "Certificado armazenado",
                    resposta.msg
                ).then(function () {
                    window.location.reload();
                });
            }).fail(function (xhr) {
                avisar("error", "Erro", mensagemErro(xhr, "Não foi possível reenviar o certificado."));
            }).always(function () {
                botao.prop("disabled", false);
            });
        });
    });

    $(document).on("click", ".btn-revogar-certificado", function () {
        const id = Number.parseInt($(this).data("id"), 10) || 0;
        const codigo = String($(this).data("codigo") || "");

        if (typeof Swal === "undefined") {
            const motivo = window.prompt(`Informe o motivo da revogação de ${codigo}:`);
            if (!motivo) {
                return;
            }
            revogar(id, motivo);
            return;
        }

        Swal.fire({
            icon: "warning",
            title: "Revogar certificado?",
            text: `${codigo} deixará de ser considerado válido.`,
            input: "textarea",
            inputLabel: "Motivo da revogação",
            inputPlaceholder: "Informe o motivo...",
            inputAttributes: { maxlength: 500 },
            showCancelButton: true,
            confirmButtonText: "Revogar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#dc3545",
            inputValidator: function (valor) {
                if (!String(valor || "").trim()) {
                    return "Informe o motivo da revogação.";
                }
                return null;
            }
        }).then(function (resultado) {
            if (resultado.isConfirmed) {
                revogar(id, String(resultado.value || "").trim());
            }
        });
    });

    function revogar(id, motivo) {
        $.ajax({
            url: "ajax/certificado-revogar.php",
            method: "POST",
            dataType: "json",
            data: { id, motivo, _token: token }
        }).done(function (resposta) {
            if (!resposta?.status) {
                avisar("error", "Erro", resposta?.msg || "Não foi possível revogar o certificado.");
                return;
            }

            avisar("success", "Certificado revogado", resposta.msg).then(function () {
                window.location.reload();
            });
        }).fail(function (xhr) {
            avisar("error", "Erro", mensagemErro(xhr, "Não foi possível revogar o certificado."));
        });
    }
});
