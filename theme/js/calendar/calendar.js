(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        function elemento(id) {
            return document.getElementById(id);
        }

        function definirTexto(id, valor, padrao) {
            const alvo = elemento(id);

            if (!alvo) {
                return;
            }

            const texto = String(valor ?? "").trim();
            alvo.textContent = texto !== ""
                ? texto
                : (padrao || "-");
        }

        function mostrarExportFeedback(mensagem, tipo) {
            const feedback = elemento("calendarExportFeedback");

            if (!feedback) {
                return;
            }

            feedback.className = "alert alert-" + (tipo || "info");
            feedback.textContent = mensagem;
            feedback.classList.remove("d-none");
        }

        function ocultarExportFeedback() {
            const feedback = elemento("calendarExportFeedback");

            if (feedback) {
                feedback.classList.add("d-none");
            }
        }

        function urlWebcal(url) {
            return String(url || "").replace(
                /^https?:\/\//i,
                "webcal://"
            );
        }

        async function copiarTexto(texto, input) {
            if (
                navigator.clipboard
                && window.isSecureContext
            ) {
                await navigator.clipboard.writeText(texto);
                return;
            }

            if (!input) {
                throw new Error("Campo de URL não encontrado.");
            }

            input.focus();
            input.select();
            input.setSelectionRange(0, input.value.length);

            const copiado = document.execCommand("copy");

            if (!copiado) {
                throw new Error("Não foi possível copiar a URL.");
            }
        }

        function atualizarIntervaloPersonalizado() {
            const selecionado = document.querySelector(
                'input[name="periodo"]:checked'
            );
            const personalizado = selecionado
                && selecionado.value === "personalizado";
            const inicio = elemento("calendarDataInicio");
            const fim = elemento("calendarDataFim");
            const area = elemento("calendarCustomRange");

            if (inicio) {
                inicio.disabled = !personalizado;
                inicio.required = personalizado;
            }

            if (fim) {
                fim.disabled = !personalizado;
                fim.required = personalizado;
            }

            if (area) {
                area.classList.toggle(
                    "calendar-custom-range-active",
                    Boolean(personalizado)
                );
            }
        }

        function inicializarExportacao() {
            const copiar = elemento("calendarCopyUrl");
            const feedInput = elemento("calendarFeedUrl");
            const assinar = elemento("calendarAssinarUrl");
            const regenerar = elemento("calendarRegenerarUrl");
            const formulario = elemento("calendarExportForm");
            const radios = document.querySelectorAll(
                'input[name="periodo"]'
            );

            radios.forEach(function (radio) {
                radio.addEventListener(
                    "change",
                    atualizarIntervaloPersonalizado
                );
            });

            atualizarIntervaloPersonalizado();

            if (copiar && feedInput) {
                copiar.addEventListener("click", async function () {
                    ocultarExportFeedback();

                    try {
                        const url = feedInput.value.trim();

                        if (url === "") {
                            throw new Error(
                                "A URL do calendário não está disponível."
                            );
                        }

                        await copiarTexto(url, feedInput);
                        mostrarExportFeedback(
                            "URL do calendário copiada.",
                            "success"
                        );
                    } catch (erro) {
                        mostrarExportFeedback(
                            erro instanceof Error
                                ? erro.message
                                : "Não foi possível copiar a URL.",
                            "danger"
                        );
                    }
                });
            }

            if (regenerar && feedInput) {
                regenerar.addEventListener("click", async function () {
                    ocultarExportFeedback();

                    const confirmado = window.confirm(
                        "Gerar uma nova URL revogará a URL atual e poderá interromper assinaturas existentes. Deseja continuar?"
                    );

                    if (!confirmado) {
                        return;
                    }

                    const endpoint = regenerar.dataset.url || "";
                    const csrf = regenerar.dataset.csrf || "";

                    if (endpoint === "" || csrf === "") {
                        mostrarExportFeedback(
                            "Não foi possível preparar a renovação da URL.",
                            "danger"
                        );
                        return;
                    }

                    const conteudoOriginal = regenerar.innerHTML;
                    regenerar.disabled = true;
                    regenerar.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
                        + "Gerando...";

                    try {
                        const dados = new FormData();
                        dados.append("_token", csrf);

                        const resposta = await fetch(endpoint, {
                            method: "POST",
                            body: dados,
                            credentials: "same-origin",
                            headers: {
                                "X-Requested-With": "XMLHttpRequest"
                            }
                        });

                        let resultado = {};

                        try {
                            resultado = await resposta.json();
                        } catch (erroJson) {
                            resultado = {};
                        }

                        if (!resposta.ok || !resultado.url) {
                            throw new Error(
                                resultado.error
                                || "Não foi possível gerar uma nova URL."
                            );
                        }

                        feedInput.value = resultado.url;

                        if (assinar) {
                            assinar.href = resultado.webcal
                                || urlWebcal(resultado.url);
                        }

                        mostrarExportFeedback(
                            "Nova URL gerada. A URL anterior foi revogada.",
                            "success"
                        );
                    } catch (erro) {
                        mostrarExportFeedback(
                            erro instanceof Error
                                ? erro.message
                                : "Não foi possível gerar uma nova URL.",
                            "danger"
                        );
                    } finally {
                        regenerar.disabled = false;
                        regenerar.innerHTML = conteudoOriginal;
                    }
                });
            }

            if (formulario) {
                formulario.addEventListener("submit", function (evento) {
                    ocultarExportFeedback();

                    const periodo = formulario.querySelector(
                        'input[name="periodo"]:checked'
                    );

                    if (!periodo) {
                        evento.preventDefault();
                        mostrarExportFeedback(
                            "Selecione o período da exportação.",
                            "danger"
                        );
                        return;
                    }

                    if (periodo.value !== "personalizado") {
                        return;
                    }

                    const inicio = elemento("calendarDataInicio");
                    const fim = elemento("calendarDataFim");
                    const dataInicio = inicio ? inicio.value : "";
                    const dataFim = fim ? fim.value : "";

                    if (dataInicio === "" || dataFim === "") {
                        evento.preventDefault();
                        mostrarExportFeedback(
                            "Informe as datas inicial e final.",
                            "danger"
                        );
                        return;
                    }

                    if (dataInicio > dataFim) {
                        evento.preventDefault();
                        mostrarExportFeedback(
                            "A data inicial não pode ser posterior à data final.",
                            "danger"
                        );
                    }
                });
            }
        }

        inicializarExportacao();

        const calendarElement = elemento("calendar");

        if (!calendarElement) {
            return;
        }

        const erroElement = elemento("calendarErro");
        const loadingElement = elemento("calendarLoading");
        const totalElement = elemento("calendarTotal");
        const modalElement = elemento("calendarEventoModal");
        const administrador = calendarElement.dataset.admin === "1";
        const eventosUrl = calendarElement.dataset.eventsUrl || "";

        function mostrarErro(mensagem) {
            if (!erroElement) {
                return;
            }

            const texto = erroElement.querySelector("span");

            if (texto) {
                texto.textContent = mensagem;
            }

            erroElement.classList.remove("d-none");
        }

        function ocultarErro() {
            if (erroElement) {
                erroElement.classList.add("d-none");
            }
        }

        function mostrarCarregamento(carregando) {
            if (!loadingElement) {
                return;
            }

            loadingElement.classList.toggle("d-none", !carregando);
        }

        if (
            typeof FullCalendar === "undefined"
            || typeof bootstrap === "undefined"
            || eventosUrl === ""
        ) {
            mostrarErro(
                "Os componentes necessários para o calendário não foram carregados."
            );
            return;
        }

        const modal = modalElement
            ? bootstrap.Modal.getOrCreateInstance(modalElement)
            : null;

        function formatarData(data, incluirHora) {
            if (!(data instanceof Date) || Number.isNaN(data.getTime())) {
                return "";
            }

            const opcoes = incluirHora
                ? {
                    dateStyle: "long",
                    timeStyle: "short"
                }
                : {
                    dateStyle: "long"
                };

            return new Intl.DateTimeFormat("pt-BR", opcoes).format(data);
        }

        function periodoEvento(evento) {
            const inicio = formatarData(evento.start, !evento.allDay);

            if (!evento.end) {
                return inicio;
            }

            let fim = evento.end;

            if (evento.allDay) {
                fim = new Date(evento.end.getTime());
                fim.setDate(fim.getDate() - 1);
            }

            const fimFormatado = formatarData(fim, !evento.allDay);

            if (inicio === fimFormatado || fimFormatado === "") {
                return inicio;
            }

            return inicio + " até " + fimFormatado;
        }

        function abrirDetalhes(evento) {
            if (!modal) {
                return;
            }

            const propriedades = evento.extendedProps || {};

            definirTexto(
                "calendarEventoModalTitulo",
                evento.title,
                "Evento"
            );
            definirTexto("calendarModalTipo", propriedades.tipo, "Evento");
            definirTexto("calendarModalPeriodo", periodoEvento(evento), "-");
            definirTexto(
                "calendarModalLocal",
                propriedades.local,
                "Não informado"
            );
            definirTexto(
                "calendarModalEndereco",
                propriedades.endereco,
                ""
            );
            definirTexto(
                "calendarModalStatusEvento",
                propriedades.statusEvento,
                "-"
            );
            definirTexto(
                "calendarModalInscricaoAberta",
                propriedades.inscricaoAberta
                    ? "Inscrições abertas"
                    : "Inscrições fechadas",
                ""
            );
            definirTexto(
                "calendarModalDescricao",
                propriedades.descricao,
                "Nenhuma descrição informada."
            );

            if (administrador) {
                definirTexto(
                    "calendarModalInscritos",
                    propriedades.totalInscritos,
                    "0"
                );

                const editar = elemento("calendarModalEditar");
                const url = String(propriedades.editarUrl || "").trim();

                if (editar) {
                    editar.classList.toggle("d-none", url === "");
                    editar.href = url !== "" ? url : "#";
                }
            } else {
                definirTexto(
                    "calendarModalInscricaoStatus",
                    propriedades.statusInscricao,
                    "Não informado"
                );
                definirTexto(
                    "calendarModalInscricaoNumero",
                    propriedades.idInscricao
                        ? "Inscrição #" + propriedades.idInscricao
                        : "",
                    ""
                );
                definirTexto(
                    "calendarModalPagamento",
                    propriedades.pagamento,
                    "Não informado"
                );
                definirTexto(
                    "calendarModalPresenca",
                    Number(propriedades.presenca) === 1
                        ? "Registrada"
                        : "Não registrada",
                    "Não registrada"
                );
            }

            modal.show();
        }

        const calendario = new FullCalendar.Calendar(calendarElement, {
            locale: "pt-br",
            initialView: window.innerWidth < 768
                ? "listMonth"
                : "dayGridMonth",
            firstDay: 0,
            height: "auto",
            expandRows: true,
            navLinks: true,
            nowIndicator: true,
            dayMaxEvents: true,
            fixedWeekCount: false,
            displayEventEnd: true,
            eventTimeFormat: {
                hour: "2-digit",
                minute: "2-digit",
                hour12: false
            },
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,timeGridWeek,listMonth"
            },
            buttonText: {
                today: "Hoje",
                month: "Mês",
                week: "Semana",
                list: "Lista"
            },
            events: {
                url: eventosUrl,
                method: "GET",
                failure: function () {
                    mostrarErro(
                        "Não foi possível consultar os eventos. Atualize a página e tente novamente."
                    );
                }
            },
            loading: function (carregando) {
                mostrarCarregamento(carregando);

                if (carregando) {
                    ocultarErro();
                }
            },
            eventsSet: function (eventos) {
                if (!totalElement) {
                    return;
                }

                const total = eventos.length;
                totalElement.textContent = total
                    + " evento"
                    + (total === 1 ? "" : "s");
            },
            eventClick: function (informacao) {
                informacao.jsEvent.preventDefault();
                abrirDetalhes(informacao.event);
            },
            eventDidMount: function (informacao) {
                const propriedades = informacao.event.extendedProps || {};
                const local = String(propriedades.local || "").trim();
                const status = administrador
                    ? String(propriedades.statusEvento || "").trim()
                    : String(propriedades.statusInscricao || "").trim();

                informacao.el.title = [
                    informacao.event.title,
                    local,
                    status
                ].filter(Boolean).join(" — ");
            }
        });

        calendario.render();
    });
})();
