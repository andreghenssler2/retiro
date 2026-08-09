$(function () {

    "use strict";

    /*
     * Login: lembrar e-mail.
     */
    if (
        localStorage.getItem("remember")
        === "true"
    ) {

        $('input[name="remember"]')
            .prop("checked", true);

        $('input[name="email"]').val(
            localStorage.getItem(
                "remember_email"
            )
        );
    }

    $("form").on(
        "submit",
        function () {

            const $lembrar = $(
                'input[name="remember"]'
            );

            if (!$lembrar.length) {
                return;
            }

            if ($lembrar.is(":checked")) {

                localStorage.setItem(
                    "remember",
                    "true"
                );

                localStorage.setItem(
                    "remember_email",
                    $('input[name="email"]')
                        .val()
                );

            } else {

                localStorage.removeItem(
                    "remember"
                );

                localStorage.removeItem(
                    "remember_email"
                );
            }
        }
    );

    function atualizarRequisito(
        $elemento,
        valido
    ) {

        if (!$elemento.length) {
            return;
        }

        $elemento
            .toggleClass(
                "text-success",
                valido
            )
            .toggleClass(
                "text-danger",
                !valido
            );

        $elemento
            .find("i")
            .toggleClass(
                "fa-check",
                valido
            )
            .toggleClass(
                "fa-times",
                !valido
            );
    }

    function validarSenha() {

        const senha = String(
            $('input[name="senha"]').val()
            ?? ""
        );

        const confirmacao = String(
            $('input[name="confirmar_senha"]')
                .val()
            ?? ""
        );

        atualizarRequisito(
            $("#reqMaiuscula"),
            /[A-Z]/.test(senha)
        );

        atualizarRequisito(
            $("#reqMinuscula"),
            /[a-z]/.test(senha)
        );

        atualizarRequisito(
            $("#reqNumero"),
            /\d/.test(senha)
        );

        atualizarRequisito(
            $("#reqEspecial"),
            /[!@#$&-]/.test(senha)
        );

        atualizarRequisito(
            $("#reqTamanho"),
            senha.length >= 6
        );

        atualizarRequisito(
            $("#reqIgual"),
            senha !== ""
            && confirmacao !== ""
            && senha === confirmacao
        );
    }

    $(
        'input[name="senha"], '
        + 'input[name="confirmar_senha"]'
    ).on(
        "input keyup",
        validarSenha
    );

    $(".toggleSenha").on("click", function () {
        const $input = $(this).siblings("input");
        const mostrar = $input.attr("type") === "password";

        $input.attr("type", mostrar ? "text" : "password");

        $(this).find("i")
            .toggleClass("fa-eye", !mostrar)
            .toggleClass("fa-eye-slash", mostrar);
    });

    validarSenha();
});
