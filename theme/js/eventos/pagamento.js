(() => {
    "use strict";

    const config = window.RETIRO_CHECKOUT || {};
    const formas = document.querySelectorAll("[data-forma]");
    const paineis = {
        PIX: document.getElementById("checkoutPix"),
        Boleto: document.getElementById("checkoutBoleto"),
        Cartao: document.getElementById("checkoutCartao")
    };

    const feedback = document.getElementById("checkoutFeedback");
    const cardForm = document.getElementById("checkoutCartaoForm");
    const cardButton = document.getElementById("checkoutCartaoEnviar");

    const mostrarFeedback = (mensagem, tipo = "info") => {
        if (!feedback) {
            return;
        }

        feedback.className = `alert alert-${tipo} mt-4`;
        feedback.textContent = mensagem;
    };

    const limparFeedback = () => {
        if (!feedback) {
            return;
        }

        feedback.className = "alert d-none mt-4";
        feedback.textContent = "";
    };

    const selecionar = (forma) => {
        limparFeedback();

        Object.entries(paineis).forEach(([nome, painel]) => {
            if (!painel) {
                return;
            }

            painel.classList.toggle("d-none", nome !== forma);
        });

        formas.forEach((botao) => {
            botao.classList.toggle(
                "active",
                botao.dataset.forma === forma
            );
        });
    };

    const enviar = async (dados) => {
        const body = new URLSearchParams();

        Object.entries(dados).forEach(([chave, valor]) => {
            body.set(chave, String(valor ?? ""));
        });

        body.set("_token", config.csrf || "");
        body.set("idPagamento", String(config.idPagamento || 0));

        const response = await fetch(config.url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "Accept": "application/json"
            },
            credentials: "same-origin",
            cache: "no-store",
            body
        });

        let json = null;

        try {
            json = await response.json();
        } catch {
            throw new Error(
                "O servidor retornou uma resposta inválida."
            );
        }

        if (!response.ok || !json.status) {
            throw new Error(
                json?.mensagem
                || "Não foi possível processar o pagamento."
            );
        }

        return json;
    };

    const mostrarPix = (dados) => {
        const resultado = document.getElementById(
            "checkoutPixResultado"
        );
        const imagem = document.getElementById("checkoutPixQr");
        const codigo = document.getElementById(
            "checkoutPixCodigo"
        );

        if (!resultado || !imagem || !codigo) {
            return;
        }

        if (dados.qrCode) {
            imagem.src = dados.qrCode;
            imagem.hidden = false;
        } else {
            imagem.removeAttribute("src");
            imagem.hidden = true;
        }

        codigo.value = dados.copiaCola || "";
        resultado.classList.remove("d-none");
    };

    const mostrarBoleto = (dados) => {
        const resultado = document.getElementById(
            "checkoutBoletoResultado"
        );
        const linha = document.getElementById(
            "checkoutBoletoLinha"
        );
        const abrir = document.getElementById(
            "checkoutBoletoAbrir"
        );

        if (!resultado || !linha || !abrir) {
            return;
        }

        linha.value = dados.linhaDigitavel || "";

        if (dados.boletoUrl) {
            abrir.href = dados.boletoUrl;
            abrir.classList.remove("d-none");
        } else {
            abrir.href = "#";
            abrir.classList.add("d-none");
        }

        resultado.classList.remove("d-none");
    };

    formas.forEach((botao) => {
        botao.addEventListener("click", () => {
            selecionar(botao.dataset.forma || "");
        });
    });

    document.querySelectorAll(
        "[data-confirmar-forma]"
    ).forEach((botao) => {
        botao.addEventListener("click", async () => {
            const forma = botao.dataset.confirmarForma || "";

            botao.disabled = true;

            try {
                mostrarFeedback("Gerando cobrança...", "info");

                const resposta = await enviar({ forma });
                mostrarFeedback(resposta.mensagem, "success");

                if (forma === "PIX") {
                    mostrarPix(resposta.dados || {});
                }

                if (forma === "Boleto") {
                    mostrarBoleto(resposta.dados || {});
                }
            } catch (erro) {
                mostrarFeedback(
                    erro instanceof Error
                        ? erro.message
                        : "Não foi possível gerar a cobrança.",
                    "danger"
                );
            } finally {
                botao.disabled = false;
            }
        });
    });

    if (cardForm) {
        cardForm.addEventListener("submit", async (event) => {
            event.preventDefault();

            if (!cardForm.reportValidity()) {
                return;
            }

            if (cardButton) {
                cardButton.disabled = true;
            }

            const formData = new FormData(cardForm);
            const dados = { forma: "Cartao" };

            formData.forEach((valor, chave) => {
                dados[chave] = String(valor);
            });

            try {
                mostrarFeedback(
                    "Processando o cartão...",
                    "info"
                );

                const resposta = await enviar(dados);

                /*
                 * Limpa os campos sensíveis imediatamente
                 * após o processamento no navegador.
                 */
                const numero = document.getElementById("cardNumber");
                const ccv = document.getElementById("cardCcv");

                if (numero) {
                    numero.value = "";
                }

                if (ccv) {
                    ccv.value = "";
                }

                if (
                    resposta.dados?.statusPagamento === "Pago"
                ) {
                    mostrarFeedback(
                        "Pagamento aprovado. "
                        + "Sua inscrição foi confirmada.",
                        "success"
                    );

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 900);

                    return;
                }

                mostrarFeedback(
                    resposta.mensagem,
                    "success"
                );
            } catch (erro) {
                mostrarFeedback(
                    erro instanceof Error
                        ? erro.message
                        : "Não foi possível processar o cartão.",
                    "danger"
                );
            } finally {
                if (cardButton) {
                    cardButton.disabled = false;
                }
            }
        });
    }

    if (config.pixQr || config.pixCopiaCola) {
        selecionar("PIX");
        mostrarPix({
            qrCode: config.pixQr || "",
            copiaCola: config.pixCopiaCola || ""
        });
    } else if (
        config.boletoLinha
        || config.boletoUrl
    ) {
        selecionar("Boleto");
        mostrarBoleto({
            linhaDigitavel: config.boletoLinha || "",
            boletoUrl: config.boletoUrl || ""
        });
    }
})();
