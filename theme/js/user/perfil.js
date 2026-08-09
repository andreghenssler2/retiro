(() => {
    "use strict";

    const fotoInput = document.getElementById("foto");
    const previewFoto = document.getElementById("previewFoto");
    const removerFoto = document.getElementById("removerFoto");
    const telefone = document.getElementById("telefone");

    const somenteNumeros = (valor) => valor.replace(/\D+/g, "");

    const formatarTelefone = (valor) => {
        const numeros = somenteNumeros(valor).slice(0, 11);

        if (numeros.length <= 10) {
            return numeros
                .replace(/^(\d{2})(\d)/, "($1) $2")
                .replace(/(\d{4})(\d)/, "$1-$2");
        }

        return numeros
            .replace(/^(\d{2})(\d)/, "($1) $2")
            .replace(/(\d{5})(\d)/, "$1-$2");
    };

    if (telefone) {
        telefone.value = formatarTelefone(telefone.value);

        telefone.addEventListener("input", () => {
            telefone.value = formatarTelefone(telefone.value);
        });
    }

    if (fotoInput && previewFoto) {
        fotoInput.addEventListener("change", () => {
            const arquivo = fotoInput.files?.[0];

            if (!arquivo) {
                previewFoto.src = previewFoto.dataset.fotoAtual || "";
                return;
            }

            if (!arquivo.type.startsWith("image/")) {
                fotoInput.value = "";
                return;
            }

            const leitor = new FileReader();

            leitor.addEventListener("load", () => {
                previewFoto.src = String(leitor.result || "");
            });

            leitor.readAsDataURL(arquivo);

            if (removerFoto) {
                removerFoto.checked = false;
            }
        });
    }

    if (removerFoto && previewFoto) {
        removerFoto.addEventListener("change", () => {
            if (removerFoto.checked) {
                previewFoto.src =
                    previewFoto.dataset.fotoPadrao || "";

                if (fotoInput) {
                    fotoInput.value = "";
                }
            } else {
                previewFoto.src =
                    previewFoto.dataset.fotoAtual || "";
            }
        });
    }

    document.querySelectorAll(".toggle-senha").forEach((botao) => {
        botao.addEventListener("click", () => {
            const idAlvo = botao.getAttribute("data-alvo");
            const campo = idAlvo
                ? document.getElementById(idAlvo)
                : null;

            if (!(campo instanceof HTMLInputElement)) {
                return;
            }

            const mostrar = campo.type === "password";
            campo.type = mostrar ? "text" : "password";

            const icone = botao.querySelector("i");

            if (icone) {
                icone.classList.toggle("fa-eye", !mostrar);
                icone.classList.toggle(
                    "fa-eye-slash",
                    mostrar
                );
            }
        });
    });
})();
