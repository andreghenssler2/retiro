(() => {
    "use strict";

    const form =
        document.querySelector(
            "form"
        );

    const email =
        document.querySelector(
            'input[name="email"]'
        );

    const remember =
        document.querySelector(
            'input[name="remember"]'
        );

    /*
     * Lembrar e-mail.
     */
    try {
        if (
            remember
            && email
            && localStorage.getItem(
                "remember"
            ) === "true"
        ) {
            remember.checked = true;

            email.value =
                localStorage.getItem(
                    "remember_email"
                ) || "";
        }
    } catch (error) {
        /*
         * Alguns navegadores/modos privados
         * podem limitar localStorage.
         * O login continua funcionando.
         */
    }

    form?.addEventListener(
        "submit",
        () => {
            if (
                !remember
                || !email
            ) {
                return;
            }

            try {
                if (
                    remember.checked
                ) {
                    localStorage.setItem(
                        "remember",
                        "true"
                    );

                    localStorage.setItem(
                        "remember_email",
                        email.value
                    );
                } else {
                    localStorage.removeItem(
                        "remember"
                    );

                    localStorage.removeItem(
                        "remember_email"
                    );
                }
            } catch (error) {
                /*
                 * Não impede o envio do formulário.
                 */
            }
        }
    );

    /*
     * Mostrar / ocultar senha.
     *
     * Não depende de jQuery.
     */
    document
        .querySelectorAll(
            "[data-toggle-password]"
        )
        .forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    const seletor =
                        button.getAttribute(
                            "data-toggle-password"
                        );

                    if (!seletor) {
                        return;
                    }

                    const input =
                        document.querySelector(
                            seletor
                        );

                    if (!input) {
                        return;
                    }

                    const mostrar =
                        input.type
                        === "password";

                    input.type =
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

                    const icon =
                        button.querySelector(
                            "i"
                        );

                    if (icon) {
                        icon.classList.toggle(
                            "fa-eye",
                            !mostrar
                        );

                        icon.classList.toggle(
                            "fa-eye-slash",
                            mostrar
                        );
                    }

                    input.focus();
                }
            );
        });
})();
