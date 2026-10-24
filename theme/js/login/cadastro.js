$(function () {

    "use strict";

    const $form = $("#formCadastro");

    if (!$form.length) {
        return;
    }

    const urlVerificacao = $form.data(
        "verificar-url"
    );

    const token = $("#_token").val();

    const temporizadores = {};

    function somenteNumeros(valor) {
        return String(valor ?? "")
            .replace(/\D/g, "");
    }

    function formatarCpf(valor) {

        const numeros = somenteNumeros(valor)
            .slice(0, 11);

        return numeros
            .replace(
                /^(\d{3})(\d)/,
                "$1.$2"
            )
            .replace(
                /^(\d{3})\.(\d{3})(\d)/,
                "$1.$2.$3"
            )
            .replace(
                /\.(\d{3})(\d)/,
                ".$1-$2"
            );
    }

    function formatarTelefone(valor) {

        const numeros = somenteNumeros(valor)
            .slice(0, 11);

        if (numeros.length <= 10) {

            return numeros
                .replace(
                    /^(\d{2})(\d)/,
                    "($1) $2"
                )
                .replace(
                    /(\d{4})(\d)/,
                    "$1-$2"
                );
        }

        return numeros
            .replace(
                /^(\d{2})(\d)/,
                "($1) $2"
            )
            .replace(
                /(\d{5})(\d)/,
                "$1-$2"
            );
    }

    $("#cpf").on(
        "input",
        function () {

            this.value = formatarCpf(
                this.value
            );
        }
    );

    $("#telefone").on(
        "input",
        function () {

            this.value = formatarTelefone(
                this.value
            );
        }
    );

    function atualizarFeedback(
        $campo,
        $feedback,
        resposta
    ) {

        $campo.removeClass(
            "is-valid is-invalid"
        );

        $feedback.removeClass(
            "text-success text-danger text-muted"
        );

        if (
            resposta
            && resposta.disponivel === true
            && resposta.valido === true
        ) {

            $campo
                .addClass("is-valid")
                .get(0)
                .setCustomValidity("");

            $feedback
                .addClass("text-success")
                .text(resposta.mensagem);

            return;
        }

        const mensagem = resposta?.mensagem
            ?? "Não foi possível verificar este campo.";

        $campo
            .addClass("is-invalid")
            .get(0)
            .setCustomValidity(mensagem);

        $feedback
            .addClass("text-danger")
            .text(mensagem);
    }

    function verificarDisponibilidade(
        campo
    ) {

        const $campo = $("#" + campo);
        const $feedback = $(
            "#" + campo + "Feedback"
        );

        const valor = String(
            $campo.val() ?? ""
        ).trim();

        if (valor === "") {

            $campo
                .removeClass(
                    "is-valid is-invalid"
                )
                .get(0)
                .setCustomValidity("");

            $feedback.text("");

            return;
        }

        $feedback
            .removeClass(
                "text-success text-danger"
            )
            .addClass("text-muted")
            .text("Verificando...");

        $.ajax({
            url: urlVerificacao,
            type: "POST",
            dataType: "json",
            data: {
                _token: token,
                campo: campo,
                valor: valor
            }
        })
            .done(function (resposta) {

                atualizarFeedback(
                    $campo,
                    $feedback,
                    resposta
                );
            })
            .fail(function (xhr) {

                const resposta = xhr.responseJSON
                    ?? {
                        disponivel: false,
                        valido: false,
                        mensagem:
                            "Não foi possível verificar agora."
                    };

                atualizarFeedback(
                    $campo,
                    $feedback,
                    resposta
                );
            });
    }

    function agendarVerificacao(
        campo
    ) {

        clearTimeout(
            temporizadores[campo]
        );

        temporizadores[campo] = setTimeout(
            function () {
                verificarDisponibilidade(
                    campo
                );
            },
            450
        );
    }

    $("#cpf, #email").on(
        "input blur",
        function () {

            const campo = this.id;

            this.setCustomValidity("");

            $(this).removeClass(
                "is-valid is-invalid"
            );

            $("#" + campo + "Feedback")
                .text("");

            agendarVerificacao(
                campo
            );
        }
    );

    $form.on(
        "submit",
        function (event) {

            if (!this.checkValidity()) {

                event.preventDefault();
                event.stopPropagation();

                this.classList.add(
                    "was-validated"
                );

                const primeiroInvalido =
                    this.querySelector(":invalid");

                if (primeiroInvalido) {
                    primeiroInvalido.focus();
                }

                return;
            }

            $("#btnCadastrar")
                .prop("disabled", true)
                .html(
                    '<span class="spinner-border spinner-border-sm me-1"></span>'
                    + "Cadastrando..."
                );
        }
    );

    if ($("#cpf").val()) {
        $("#cpf").val(
            formatarCpf(
                $("#cpf").val()
            )
        );
    }

    if ($("#telefone").val()) {
        $("#telefone").val(
            formatarTelefone(
                $("#telefone").val()
            )
        );
    }
});
