(() => {
    "use strict";

    const senha =
        document.getElementById(
            "senha"
        );

    const confirmar =
        document.getElementById(
            "confirmar"
        );

    if (!senha || !confirmar) {
        return;
    }

    const atualizarRequisito = (
        id,
        valido
    ) => {
        const elemento =
            document.getElementById(
                id
            );

        if (!elemento) {
            return;
        }

        elemento.classList.toggle(
            "text-success",
            valido
        );

        elemento.classList.toggle(
            "text-danger",
            !valido
        );

        const icone =
            elemento.querySelector(
                "i"
            );

        if (!icone) {
            return;
        }

        icone.classList.toggle(
            "fa-check",
            valido
        );

        icone.classList.toggle(
            "fa-times",
            !valido
        );
    };

    const validarSenha = () => {
        const valor =
            senha.value;

        const confirmacao =
            confirmar.value;

        atualizarRequisito(
            "reqMaiuscula",
            /[A-Z]/.test(
                valor
            )
        );

        atualizarRequisito(
            "reqMinuscula",
            /[a-z]/.test(
                valor
            )
        );

        atualizarRequisito(
            "reqNumero",
            /\d/.test(
                valor
            )
        );

        atualizarRequisito(
            "reqEspecial",
            /[!@#$&-]/.test(
                valor
            )
        );

        atualizarRequisito(
            "reqTamanho",
            valor.length >= 6
        );

        const iguais =
            valor !== ""
            && confirmacao !== ""
            && valor === confirmacao;

        atualizarRequisito(
            "reqIgual",
            iguais
        );

        if (
            confirmacao !== ""
            && valor !== confirmacao
        ) {
            confirmar.setCustomValidity(
                "As senhas não conferem."
            );
        } else {
            confirmar.setCustomValidity(
                ""
            );
        }
    };

    senha.addEventListener(
        "input",
        validarSenha
    );

    confirmar.addEventListener(
        "input",
        validarSenha
    );

    document
        .querySelectorAll(
            "[data-toggle-password]"
        )
        .forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    const seletor =
                        button.dataset
                            .togglePassword;

                    if (!seletor) {
                        return;
                    }

                    const campo =
                        document.querySelector(
                            seletor
                        );

                    if (!campo) {
                        return;
                    }

                    const mostrar =
                        campo.type
                        === "password";

                    campo.type =
                        mostrar
                            ? "text"
                            : "password";

                    button.setAttribute(
                        "aria-pressed",
                        mostrar
                            ? "true"
                            : "false"
                    );

                    button.setAttribute(
                        "aria-label",
                        mostrar
                            ? "Ocultar senha"
                            : "Mostrar senha"
                    );

                    const icone =
                        button.querySelector(
                            "i"
                        );

                    if (icone) {
                        icone.classList.toggle(
                            "fa-eye",
                            !mostrar
                        );

                        icone.classList.toggle(
                            "fa-eye-slash",
                            mostrar
                        );
                    }

                    campo.focus();
                }
            );
        });

    validarSenha();
})();
