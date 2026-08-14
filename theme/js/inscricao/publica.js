(() => {
    "use strict";

    const cfg =
        window.INSCRICAO_PUBLICA || {};

    const form =
        document.getElementById(
            "formInscricaoPublica"
        );

    if (!form) {
        return;
    }

    const alertBox =
        document.getElementById(
            "alertaInscricao"
        );

    const fluxoInput =
        document.getElementById(
            "fluxo"
        );

    const email =
        document.getElementById(
            "email"
        );

    const emailConfirmado =
        document.getElementById(
            "emailConfirmado"
        );

    const codigoArea =
        document.getElementById(
            "codigoArea"
        );

    const codigo =
        document.getElementById(
            "codigo"
        );

    const btnEnviar =
        document.getElementById(
            "btnEnviarCodigo"
        );

    const btnValidar =
        document.getElementById(
            "btnValidarCodigo"
        );

    const cpf =
        document.getElementById(
            "cpf"
        );

    const cpfStatus =
        document.getElementById(
            "cpfStatus"
        );

    const comunidade =
        document.getElementById(
            "idComunidade"
        );

    const comunidadeArea =
        document.getElementById(
            "comunidadeArea"
        );

    const pagamentoOpcoes =
        document.getElementById(
            "pagamentoOpcoes"
        );

    const resumoValor =
        document.getElementById(
            "resumoValor"
        );

    const resumoTipo =
        document.getElementById(
            "resumoTipoParticipante"
        );

    const textoBtnConcluir =
        document.getElementById(
            "textoBtnConcluir"
        );

    const resultado =
        document.getElementById(
            "resultadoPagamento"
        );

    const endpoint = (arquivo) =>
        `${cfg.baseUrl}inscricao/ajax/${arquivo}`;

    const post = async (
        arquivo,
        dados
    ) => {
        const body =
            dados instanceof FormData
                ? dados
                : new URLSearchParams(
                    dados
                );

        const response =
            await fetch(
                endpoint(arquivo),
                {
                    method: "POST",
                    body,
                    credentials:
                        "same-origin",
                    cache: "no-store",
                    headers: {
                        Accept:
                            "application/json"
                    }
                }
            );

        const text =
            await response.text();

        let json;

        try {
            json = JSON.parse(text);
        } catch {
            throw new Error(
                "O servidor retornou "
                + "uma resposta inválida."
            );
        }

        if (
            !response.ok
            || !json.status
        ) {
            throw new Error(
                json.mensagem
                || "Não foi possível "
                + "concluir a operação."
            );
        }

        return json;
    };

    const showAlert = (
        type,
        message
    ) => {
        if (!alertBox) {
            return;
        }

        alertBox.hidden = false;
        alertBox.className =
            `alert alert-${type}`;
        alertBox.textContent =
            message;

        alertBox.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    };

    const clearAlert = () => {
        if (!alertBox) {
            return;
        }

        alertBox.hidden = true;
        alertBox.textContent = "";
    };

    const setBusy = (
        button,
        busy,
        text = "Processando..."
    ) => {
        if (!button) {
            return;
        }

        if (busy) {
            if (
                !button.dataset
                    .originalHtml
            ) {
                button.dataset
                    .originalHtml =
                    button.innerHTML;
            }

            button.disabled = true;
            button.innerHTML =
                '<span class="spinner-border '
                + 'spinner-border-sm me-2" '
                + 'aria-hidden="true"></span>'
                + text;

            return;
        }

        button.disabled = false;

        if (
            button.dataset
                .originalHtml
        ) {
            button.innerHTML =
                button.dataset
                    .originalHtml;
        }
    };

    const moeda = (
        valor
    ) => {
        return Number(
            valor || 0
        ).toLocaleString(
            "pt-BR",
            {
                style: "currency",
                currency: "BRL"
            }
        );
    };

    const normalizarTexto = (
        valor
    ) => {
        return String(
            valor || ""
        )
            .normalize("NFD")
            .replace(
                /[\u0300-\u036f]/g,
                ""
            )
            .trim()
            .toLowerCase();
    };

    const opcaoComunidadeSelecionada =
        () => {
            const campo =
                document.getElementById(
                    "idComunidade"
                );

            if (!campo) {
                return null;
            }

            return (
                campo.selectedOptions?.[0]
                || null
            );
        };

    const comunidadeEhVisitante = () => {
        const opcao =
            opcaoComunidadeSelecionada();

        if (!opcao) {
            return false;
        }

        if (
            opcao.dataset.visitante
            === "1"
        ) {
            return true;
        }

        return normalizarTexto(
            opcao.textContent
        ) === "visitante";
    };

    const valorAtual = () => {
        const opcao =
            opcaoComunidadeSelecionada();

        /*
         * A própria opção selecionada informa
         * qual valor deve aparecer no resumo.
         *
         * Ex.:
         * Parobé   -> data-valor="70.00"
         * Visitante-> data-valor="100.00"
         */
        if (
            opcao
            && opcao.dataset.valor
                !== undefined
            && opcao.dataset.valor
                !== ""
        ) {
            const valorOpcao =
                Number(
                    opcao.dataset.valor
                );

            if (
                Number.isFinite(
                    valorOpcao
                )
            ) {
                return valorOpcao;
            }
        }

        const isVisitante =
            comunidadeEhVisitante();

        if (
            isVisitante
            && cfg.temValorVisitante
        ) {
            return Number(
                cfg.valorVisitante
                ?? 0
            );
        }

        return Number(
            cfg.valorPadrao
                ?? 0
        );
    };

    const atualizarVisitante = () => {
        const isVisitante =
            comunidadeEhVisitante();

        const valor =
            valorAtual();

        const precisaPagamento =
            Boolean(
                cfg.pagamentoObrigatorio
            )
            && valor > 0;

        /*
         * Busca novamente os elementos a cada
         * atualização. Assim o resumo sempre usa
         * os elementos atuais da etapa Pagamento.
         */
        const resumoValorAtual =
            document.getElementById(
                "resumoValor"
            );

        const resumoTipoAtual =
            document.getElementById(
                "resumoTipoParticipante"
            );

        const pagamentoOpcoesAtual =
            document.getElementById(
                "pagamentoOpcoes"
            );

        const textoBtnAtual =
            document.getElementById(
                "textoBtnConcluir"
            );

        if (resumoValorAtual) {
            resumoValorAtual.textContent =
                precisaPagamento
                    ? moeda(valor)
                    : "Gratuito";

            resumoValorAtual.dataset.valor =
                String(valor);
        }

        if (resumoTipoAtual) {
            resumoTipoAtual.textContent =
                isVisitante
                    ? "Visitante"
                    : "Comunidade/Paróquia";
        }

        if (pagamentoOpcoesAtual) {
            pagamentoOpcoesAtual.hidden =
                !precisaPagamento;

            pagamentoOpcoesAtual
                .querySelectorAll(
                    '[name="forma_pagamento"]'
                )
                .forEach((radio) => {
                    radio.required =
                        precisaPagamento;
                });
        }

        const cartao =
            document.getElementById(
                "cartaoCampos"
            );

        if (
            !precisaPagamento
            && cartao
        ) {
            cartao.hidden = true;

            cartao
                .querySelectorAll(
                    "input"
                )
                .forEach((input) => {
                    input.required =
                        false;

                    input.value = "";
                });
        }

        if (textoBtnAtual) {
            textoBtnAtual.textContent =
                precisaPagamento
                    ? "Concluir inscrição"
                    : "Confirmar inscrição";
        }
    };

    comunidade?.addEventListener(
        "change",
        atualizarVisitante
    );

    comunidade?.addEventListener(
        "input",
        atualizarVisitante
    );

    /*
     * Listener delegado como garantia adicional.
     */
    document.addEventListener(
        "change",
        (event) => {
            const target =
                event.target;

            if (
                target instanceof
                    HTMLSelectElement
                && target.id
                    === "idComunidade"
            ) {
                atualizarVisitante();
            }
        }
    );

    const mostrarEtapa = (
        nome
    ) => {
        document
            .querySelectorAll(
                "[data-etapa]"
            )
            .forEach((section) => {
                section.hidden =
                    section.dataset.etapa
                    !== nome;
            });

        clearAlert();

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    };

    const camposDaEtapa = (
        nome
    ) => {
        const section =
            document.querySelector(
                `[data-etapa="${nome}"]`
            );

        if (!section) {
            return [];
        }

        return Array.from(
            section.querySelectorAll(
                "input,select,textarea"
            )
        ).filter((field) => {
            return !(
                field.disabled
                || field.type
                    === "hidden"
            );
        });
    };

    const primeiroCampoInvalido = (
        nome
    ) => {
        for (
            const field
            of camposDaEtapa(nome)
        ) {
            if (
                !field.checkValidity()
            ) {
                return field;
            }
        }

        return null;
    };

    const exibirCampoInvalido = (
        nome,
        field
    ) => {
        /*
         * A etapa precisa estar visível antes
         * de focar/reportar o campo. Isso evita:
         *
         * "An invalid form control is not focusable"
         */
        mostrarEtapa(nome);

        window.setTimeout(
            () => {
                try {
                    field.focus({
                        preventScroll: true
                    });
                } catch {
                    field.focus();
                }

                field.reportValidity();

                field.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            },
            0
        );
    };

    const validarEtapa = (
        nome
    ) => {
        const invalido =
            primeiroCampoInvalido(
                nome
            );

        if (!invalido) {
            return true;
        }

        exibirCampoInvalido(
            nome,
            invalido
        );

        return false;
    };

    const validarFormularioCompleto =
        () => {
            const etapas = [
                "pessoal",
                "endereco",
                "saude"
            ];

            if (cfg.temCamiseta) {
                etapas.push(
                    "camiseta"
                );
            }

            etapas.push(
                "pagamento"
            );

            for (
                const etapa
                of etapas
            ) {
                const invalido =
                    primeiroCampoInvalido(
                        etapa
                    );

                if (invalido) {
                    exibirCampoInvalido(
                        etapa,
                        invalido
                    );

                    return false;
                }
            }

            return true;
        };

    const formatCpf = (
        value
    ) => {
        const digits = value
            .replace(/\D/g, "")
            .slice(0, 11);

        return digits
            .replace(
                /(\d{3})(\d)/,
                "$1.$2"
            )
            .replace(
                /(\d{3})(\d)/,
                "$1.$2"
            )
            .replace(
                /(\d{3})(\d{1,2})$/,
                "$1-$2"
            );
    };

    const formatPhone = (
        value
    ) => {
        const digits = value
            .replace(/\D/g, "")
            .slice(0, 11);

        if (
            digits.length <= 10
        ) {
            return digits
                .replace(
                    /(\d{2})(\d)/,
                    "($1) $2"
                )
                .replace(
                    /(\d{4})(\d)/,
                    "$1-$2"
                );
        }

        return digits
            .replace(
                /(\d{2})(\d)/,
                "($1) $2"
            )
            .replace(
                /(\d{5})(\d)/,
                "$1-$2"
            );
    };

    const formatCep = (
        value
    ) => {
        const digits = value
            .replace(/\D/g, "")
            .slice(0, 8);

        return digits.replace(
            /(\d{5})(\d{1,3})$/,
            "$1-$2"
        );
    };

    document
        .querySelectorAll(
            "[data-proximo]"
        )
        .forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    const atual =
                        button.closest(
                            "[data-etapa]"
                        )?.dataset.etapa;

                    if (
                        atual
                        && !validarEtapa(
                            atual
                        )
                    ) {
                        return;
                    }

                    if (
                        atual === "pessoal"
                        && cpf.dataset
                            .validado
                            !== "1"
                    ) {
                        showAlert(
                            "warning",
                            "Complete e valide "
                            + "o CPF antes "
                            + "de continuar."
                        );

                        return;
                    }

                    if (
                        button.dataset
                            .proximo
                        === "pagamento"
                    ) {
                        atualizarVisitante();
                        document
                            .getElementById(
                                "resumoNome"
                            )
                            .textContent =
                            form.elements
                                .nome.value
                            || "-";

                        document
                            .getElementById(
                                "resumoCpf"
                            )
                            .textContent =
                            cpf.value || "-";

                        document
                            .getElementById(
                                "resumoEmail"
                            )
                            .textContent =
                            emailConfirmado
                                .value
                            || "-";

                        document
                            .getElementById(
                                "resumoTelefone"
                            )
                            .textContent =
                            form.elements
                                .telefone
                                .value
                            || "-";
                    }

                    const proximaEtapa =
                        button.dataset
                            .proximo;

                    mostrarEtapa(
                        proximaEtapa
                    );

                    if (
                        proximaEtapa
                        === "pagamento"
                    ) {
                        /*
                         * Atualiza novamente depois
                         * que o resumo está visível.
                         */
                        window.requestAnimationFrame(
                            atualizarVisitante
                        );
                    }
                }
            );
        });

    document
        .querySelectorAll(
            "[data-voltar]"
        )
        .forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    mostrarEtapa(
                        button.dataset
                            .voltar
                    );
                }
            );
        });

    btnEnviar?.addEventListener(
        "click",
        async () => {
            clearAlert();

            if (
                !email.reportValidity()
            ) {
                return;
            }

            setBusy(
                btnEnviar,
                true,
                "Enviando..."
            );

            try {
                const response =
                    await post(
                        "enviar-codigo.php",
                        {
                            _token:
                                form.elements
                                    ._token
                                    .value,
                            idEvento:
                                cfg.idEvento,
                            email:
                                email.value
                        }
                    );

                fluxoInput.value =
                    response.dados
                        .token;

                codigoArea.hidden =
                    false;

                btnValidar.hidden =
                    false;

                showAlert(
                    "success",
                    response.mensagem
                );

                codigo.focus();
            } catch (error) {
                showAlert(
                    "danger",
                    error.message
                );
            } finally {
                setBusy(
                    btnEnviar,
                    false
                );
            }
        }
    );

    btnValidar?.addEventListener(
        "click",
        async () => {
            clearAlert();

            if (
                (codigo.value || "")
                    .replace(/\D/g, "")
                    .length !== 6
            ) {
                showAlert(
                    "warning",
                    "Informe os 6 dígitos "
                    + "do código."
                );

                return;
            }

            setBusy(
                btnValidar,
                true,
                "Validando..."
            );

            try {
                const response =
                    await post(
                        "validar-codigo.php",
                        {
                            _token:
                                form.elements
                                    ._token
                                    .value,
                            fluxo:
                                fluxoInput
                                    .value,
                            codigo:
                                codigo.value
                        }
                    );

                emailConfirmado.value =
                    response.dados
                        .email;

                email.readOnly = true;

                mostrarEtapa(
                    "pessoal"
                );

                cpf.focus();
            } catch (error) {
                showAlert(
                    "danger",
                    error.message
                );
            } finally {
                setBusy(
                    btnValidar,
                    false
                );
            }
        }
    );

    cpf?.addEventListener(
        "input",
        async () => {
            cpf.value =
                formatCpf(
                    cpf.value
                );

            cpf.dataset.validado =
                "0";

            cpfStatus.textContent =
                "";

            cpfStatus.className =
                "form-text";

            const digits =
                cpf.value
                    .replace(
                        /\D/g,
                        ""
                    );

            if (
                digits.length !== 11
            ) {
                return;
            }

            cpf.disabled = true;

            cpfStatus.textContent =
                "Consultando cadastro...";

            try {
                const response =
                    await post(
                        "buscar-cpf.php",
                        {
                            _token:
                                form.elements
                                    ._token
                                    .value,
                            fluxo:
                                fluxoInput
                                    .value,
                            cpf: digits
                        }
                    );

                const d =
                    response.dados;

                [
                    "nome",
                    "nacionalidade",
                    "data_nascimento",
                    "genero",
                    "pais",
                    "logradouro",
                    "numero",
                    "complemento",
                    "bairro",
                    "cidade",
                    "estado",
                    "idComunidade",
                    "medicacao_detalhes",
                    "deficiencia",
                    "deficiencia_detalhes",
                    "acessibilidade_detalhes",
                    "alimentar_detalhes"
                ].forEach((name) => {
                    const field =
                        form.elements[
                            name
                        ];

                    if (
                        field
                        && d[name]
                            !== undefined
                        && d[name]
                            !== null
                    ) {
                        field.value =
                            d[name];
                    }
                });

                if (d.telefone) {
                    form.elements
                        .telefone
                        .value =
                        formatPhone(
                            String(
                                d.telefone
                            )
                        );
                }

                if (d.cep) {
                    form.elements
                        .cep.value =
                        formatCep(
                            String(
                                d.cep
                            )
                        );
                }

                const setRadio = (
                    name,
                    value
                ) => {
                    const radio =
                        form.querySelector(
                            `[name="${name}"]`
                            + `[value="${String(value)}"]`
                        );

                    if (!radio) {
                        return;
                    }

                    radio.checked =
                        true;

                    radio.dispatchEvent(
                        new Event(
                            "change"
                        )
                    );
                };

                setRadio(
                    "restricao_medicacao",
                    d.restricao_medicacao
                    ?? "0"
                );

                setRadio(
                    "precisa_acessibilidade",
                    d.precisa_acessibilidade
                    ?? "0"
                );

                setRadio(
                    "restricao_alimentar",
                    d.restricao_alimentar
                    ?? "0"
                );

                /*
                 * idComunidade pode ter sido
                 * preenchido automaticamente.
                 */
                atualizarVisitante();

                cpf.dataset
                    .validado =
                    "1";

                cpfStatus.textContent =
                    response.mensagem;

                cpfStatus.className =
                    "form-text "
                    + "text-success";
            } catch (error) {
                cpf.dataset
                    .validado =
                    "0";

                cpfStatus.textContent =
                    error.message;

                cpfStatus.className =
                    "form-text "
                    + "text-danger";

                showAlert(
                    "danger",
                    error.message
                );
            } finally {
                cpf.disabled =
                    false;
            }
        }
    );

    form.elements
        .telefone
        ?.addEventListener(
            "input",
            (event) => {
                event.target.value =
                    formatPhone(
                        event.target
                            .value
                    );
            }
        );

    form.elements
        .cep
        ?.addEventListener(
            "input",
            (event) => {
                event.target.value =
                    formatCep(
                        event.target
                            .value
                    );
            }
        );

    const bindDetails = (
        radioName,
        textareaId
    ) => {
        document
            .querySelectorAll(
                `[name="${radioName}"]`
            )
            .forEach((radio) => {
                radio.addEventListener(
                    "change",
                    () => {
                        const area =
                            document
                                .getElementById(
                                    textareaId
                                );

                        const selected =
                            form.querySelector(
                                `[name="${radioName}"]:checked`
                            );

                        if (
                            !area
                            || !selected
                        ) {
                            return;
                        }

                        const show =
                            selected.value
                            === "1";

                        area.hidden =
                            !show;

                        area.required =
                            show;

                        if (!show) {
                            area.value =
                                "";
                        }
                    }
                );
            });
    };

    bindDetails(
        "restricao_medicacao",
        "medicacao_detalhes"
    );

    bindDetails(
        "precisa_acessibilidade",
        "acessibilidade_detalhes"
    );

    bindDetails(
        "restricao_alimentar",
        "alimentar_detalhes"
    );

    atualizarVisitante();

    document
        .querySelectorAll(
            '[name="forma_pagamento"]'
        )
        .forEach((radio) => {
            radio.addEventListener(
                "change",
                () => {
                    const card =
                        document
                            .getElementById(
                                "cartaoCampos"
                            );

                    if (!card) {
                        return;
                    }

                    const isCard =
                        radio.checked
                        && radio.value
                            === "Cartao";

                    card.hidden =
                        !isCard;

                    card.querySelectorAll(
                        "input"
                    ).forEach(
                        (input) => {
                            input.required =
                                isCard;

                            if (!isCard) {
                                input.value =
                                    "";
                            }
                        }
                    );
                }
            );
        });

    const cardNumber =
        document.getElementById(
            "cardNumber"
        );

    cardNumber?.addEventListener(
        "input",
        () => {
            const digits =
                cardNumber.value
                    .replace(
                        /\D/g,
                        ""
                    )
                    .slice(0, 19);

            cardNumber.value =
                digits
                    .replace(
                        /(.{4})/g,
                        "$1 "
                    )
                    .trim();
        }
    );

    form.addEventListener(
        "submit",
        async (event) => {
            event.preventDefault();
            clearAlert();

            /*
             * O formulário possui novalidate porque
             * as etapas anteriores ficam escondidas.
             *
             * Revalidamos tudo manualmente aqui.
             */
            atualizarVisitante();

            if (
                !validarFormularioCompleto()
            ) {
                return;
            }

            if (
                cpf.dataset
                    .validado
                !== "1"
            ) {
                showAlert(
                    "warning",
                    "Valide o CPF antes "
                    + "de concluir."
                );

                return;
            }

            const button =
                document
                    .getElementById(
                        "btnConcluirInscricao"
                    );

            setBusy(
                button,
                true,
                "Concluindo..."
            );

            try {
                const data =
                    new FormData(
                        form
                    );

                data.set(
                    "cpf",
                    cpf.value
                );

                const response =
                    await post(
                        "finalizar.php",
                        data
                    );

                const d =
                    response.dados;

                document
                    .querySelectorAll(
                        "[data-etapa]"
                    )
                    .forEach(
                        (section) => {
                            section.hidden =
                                true;
                        }
                    );

                resultado.hidden =
                    false;

                document
                    .getElementById(
                        "resultadoTitulo"
                    )
                    .textContent =
                    d.statusPagamento
                        === "Pago"
                    || d.gratuito
                        ? "Inscrição confirmada"
                        : "Inscrição realizada";

                document
                    .getElementById(
                        "resultadoMensagem"
                    )
                    .textContent =
                    response.mensagem;

                if (
                    d.forma === "PIX"
                ) {
                    const pix =
                        document
                            .getElementById(
                                "resultadoPix"
                            );

                    pix.hidden =
                        false;

                    const qr =
                        document
                            .getElementById(
                                "resultadoPixQr"
                            );

                    const code =
                        document
                            .getElementById(
                                "resultadoPixCodigo"
                            );

                    if (
                        d.pixQrCode
                    ) {
                        qr.src =
                            d.pixQrCode;

                        qr.hidden =
                            false;
                    } else {
                        qr.hidden =
                            true;
                    }

                    code.value =
                        d.pixCopiaCola
                        || "";
                }

                if (
                    d.forma
                    === "Boleto"
                ) {
                    const boleto =
                        document
                            .getElementById(
                                "resultadoBoleto"
                            );

                    boleto.hidden =
                        false;

                    document
                        .getElementById(
                            "resultadoBoletoLinha"
                        )
                        .value =
                        d.linhaDigitavel
                        || "";

                    const link =
                        document
                            .getElementById(
                                "resultadoBoletoLink"
                            );

                    if (
                        d.boletoUrl
                    ) {
                        link.href =
                            d.boletoUrl;

                        link.hidden =
                            false;
                    } else {
                        link.hidden =
                            true;
                    }
                }

                [
                    "cardNumber",
                    "cardCcv"
                ].forEach((id) => {
                    const field =
                        document
                            .getElementById(
                                id
                            );

                    if (field) {
                        field.value =
                            "";
                    }
                });

                resultado
                    .scrollIntoView({
                        behavior:
                            "smooth",
                        block: "start"
                    });
            } catch (error) {
                showAlert(
                    "danger",
                    error.message
                );
            } finally {
                setBusy(
                    button,
                    false
                );
            }
        }
    );
})();
