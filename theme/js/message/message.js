(() => {
    "use strict";

    const iniciarWidget = (widget) => {
        if (widget.dataset.retiroNotificacoesIniciado === "1") {
            return;
        }
        widget.dataset.retiroNotificacoesIniciado = "1";
        const config = window.RETIRO_MESSAGE || {};
        const botao = widget.querySelector(
            ".message-widget-botao"
        );
        const badge = widget.querySelector(
            ".message-widget-badge"
        );
        const lista = widget.querySelector(
            ".message-widget-lista"
        );
        const marcarTodas = widget.querySelector(
            ".message-widget-marcar-todas"
        );
        const marcarTodasPagina = document.getElementById(
            "btnMarcarTodasPagina"
        );

        if (
            !botao
            || !badge
            || !lista
            || !config.listarUrl
        ) {
            return;
        }

        // Atualiza a tela automaticamente sem exigir F5.
        // Não dispara alertas de notificações antigas no carregamento inicial.
        let carregando = false;
        let carregouUmaVez = false;
        let maiorIdConhecido = 0;
        const INTERVALO_MS = 10000;

        const escapar = (valor) => {
            const div = document.createElement("div");
            div.textContent = String(valor ?? "");
            return div.innerHTML;
        };

        const icone = (tipo) => {
            if (tipo === "usuario") {
                return {
                    classe: "message-icone-usuario",
                    icone: "fa-user-plus"
                };
            }

            if (tipo === "inscricao") {
                return {
                    classe: "message-icone-inscricao",
                    icone: "fa-clipboard-check"
                };
            }

            if (tipo === "pagamento") {
                return {
                    classe: "message-icone-pagamento",
                    icone: "fa-circle-dollar-to-slot"
                };
            }

            return {
                classe: "",
                icone: "fa-bell"
            };
        };

        const dataRelativa = (dataBanco) => {
            const data = new Date(
                String(dataBanco || "").replace(" ", "T")
            );

            if (Number.isNaN(data.getTime())) {
                return "";
            }

            const segundos = Math.floor(
                (Date.now() - data.getTime()) / 1000
            );

            if (segundos < 60) {
                return "agora";
            }

            const minutos = Math.floor(segundos / 60);

            if (minutos < 60) {
                return `há ${minutos} min`;
            }

            const horas = Math.floor(minutos / 60);

            if (horas < 24) {
                return `há ${horas} h`;
            }

            return data.toLocaleString("pt-BR", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit"
            });
        };

        const atualizarBadge = (total) => {
            const quantidade = Math.max(
                0,
                Number.parseInt(String(total), 10) || 0
            );

            badge.textContent = quantidade > 99
                ? "99+"
                : String(quantidade);

            badge.hidden = quantidade === 0;

            botao.setAttribute(
                "aria-label",
                quantidade === 0
                    ? "Nenhuma notificação não lida"
                    : `${quantidade} notificação`
                        + (quantidade === 1 ? "" : "ões")
                        + " não lida"
                        + (quantidade === 1 ? "" : "s")
            );

            if (marcarTodas) {
                marcarTodas.disabled = quantidade === 0;
            }

            if (marcarTodasPagina) {
                marcarTodasPagina.disabled = quantidade === 0;
            }
        };

        const renderizar = (notificacoes) => {
            if (
                !Array.isArray(notificacoes)
                || notificacoes.length === 0
            ) {
                lista.innerHTML = `
                    <div class="message-widget-vazia">
                        <i class="fa-regular fa-bell-slash fa-2x mb-2"></i>
                        <div>Nenhuma notificação disponível.</div>
                    </div>
                `;
                return;
            }

            lista.innerHTML = notificacoes.map((item) => {
                const dadosIcone = icone(
                    String(item.tipo || "")
                );
                const naoLida = Number(item.lida) !== 1;
                const id = Number.parseInt(
                    String(item.idNotificacao),
                    10
                ) || 0;
                const abrirUrl =
                    `${config.abrirUrl}?id=${id}`;

                return `
                    <a
                        href="${escapar(abrirUrl)}"
                        class="message-widget-item ${naoLida ? "nao-lida" : ""}"
                    >
                        <span class="message-icone ${dadosIcone.classe}">
                            <i class="fa-solid ${dadosIcone.icone}"></i>
                        </span>

                        <span class="message-widget-conteudo">
                            <strong class="message-widget-titulo">
                                ${escapar(item.titulo)}
                            </strong>

                            <span class="message-widget-mensagem">
                                ${escapar(item.mensagem)}
                            </span>

                            <small class="message-widget-hora">
                                ${escapar(dataRelativa(item.criadoEm))}
                            </small>
                        </span>

                        ${naoLida
                            ? '<span class="message-indicador"></span>'
                            : '<span></span>'}
                    </a>
                `;
            }).join("");
        };

        // Aviso discreto somente para novos pagamentos recebidos.
        const avisarPagamentoRecebido = (item) => {
            let area = document.getElementById(
                "retiro-avisos-pagamento"
            );

            if (!area) {
                area = document.createElement("div");
                area.id = "retiro-avisos-pagamento";
                area.className = "retiro-avisos-pagamento";
                area.setAttribute("aria-live", "polite");
                document.body.appendChild(area);
            }

            const aviso = document.createElement("div");
            aviso.className = "retiro-aviso-pagamento";
            aviso.setAttribute("role", "status");

            const conteudo = document.createElement("div");
            const titulo = document.createElement("strong");
            titulo.textContent = item.titulo || "Pagamento recebido";
            const mensagem = document.createElement("small");
            mensagem.textContent = item.mensagem || "Novo pagamento confirmado.";
            conteudo.append(titulo, mensagem);

            const fechar = document.createElement("button");
            fechar.type = "button";
            fechar.className = "retiro-aviso-fechar";
            fechar.setAttribute("aria-label", "Fechar aviso");
            fechar.textContent = "×";

            const remover = () => {
                aviso.remove();
                if (area && !area.childElementCount) {
                    area.remove();
                }
            };

            fechar.addEventListener("click", remover);
            aviso.append(conteudo, fechar);
            area.prepend(aviso);

            // Não deixa alertas acumularem indefinidamente.
            while (area.children.length > 3) {
                area.lastElementChild.remove();
            }

            window.setTimeout(remover, 8000);
        };

        const carregar = async () => {
            if (carregando) {
                return;
            }

            carregando = true;

            const controlador = new AbortController();
            const tempoMaximo = window.setTimeout(
                () => controlador.abort(),
                12000
            );

            try {
                const separador = config.listarUrl.includes("?") ? "&" : "?";
                const resposta = await fetch(
                    `${config.listarUrl}${separador}_=${Date.now()}`,
                    {
                        credentials: "same-origin",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest",
                            "Accept": "application/json"
                        },
                        cache: "no-store",
                        signal: controlador.signal
                    }
                );

                if (!resposta.ok) {
                    throw new Error(`Erro HTTP ${resposta.status}`);
                }

                const dados = await resposta.json();

                if (!resposta.ok || !dados.status) {
                    throw new Error(
                        dados.msg
                        || "Não foi possível carregar as notificações."
                    );
                }

                if (!Array.isArray(dados.notificacoes)) {
                    throw new Error("Lista de notificações inválida.");
                }

                const notificacoes = dados.notificacoes;
                const maiorIdNaResposta = notificacoes.reduce(
                    (maior, item) => Math.max(
                        maior,
                        Number.parseInt(item.idNotificacao, 10) || 0
                    ),
                    0
                );

                if (carregouUmaVez && maiorIdNaResposta > maiorIdConhecido) {
                    const novosPagamentos = notificacoes.filter((item) => {
                        const id = Number.parseInt(item.idNotificacao, 10) || 0;
                        return id > maiorIdConhecido
                            && item.tipo === "pagamento"
                            && Number(item.lida) !== 1;
                    });

                    // Uma mensagem por atualização, sem repetir avisos antigos.
                    if (novosPagamentos.length > 0) {
                        avisarPagamentoRecebido(novosPagamentos[0]);
                    }
                }

                maiorIdConhecido = Math.max(
                    maiorIdConhecido,
                    maiorIdNaResposta
                );
                carregouUmaVez = true;
                atualizarBadge(dados.naoLidas);
                renderizar(notificacoes);
            } catch (erro) {
                console.error("Falha na sincronização de notificações:", erro);
                // Conserve a lista atual se houver falha temporária de conexão.
                if (!carregouUmaVez) {
                    lista.innerHTML = `
                        <div class="message-widget-erro">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Não foi possível carregar as notificações.
                        </div>
                    `;
                }
            } finally {
                window.clearTimeout(tempoMaximo);
                carregando = false;
            }
        };

        const marcarTodasComoLidas = async () => {
            if (
                !config.marcarTodasUrl
                || !config.csrf
            ) {
                return;
            }

            const corpo = new URLSearchParams();
            corpo.set("_token", config.csrf);

            try {
                const resposta = await fetch(
                    config.marcarTodasUrl,
                    {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type":
                                "application/x-www-form-urlencoded;charset=UTF-8",
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        body: corpo.toString()
                    }
                );

                const dados = await resposta.json();

                if (!resposta.ok || !dados.status) {
                    throw new Error(
                        dados.msg
                        || "Não foi possível atualizar as notificações."
                    );
                }

                atualizarBadge(0);
                await carregar();

                document
                    .querySelectorAll(
                        ".message-pagina-item.nao-lida"
                    )
                    .forEach((item) => {
                        item.classList.remove("nao-lida");

                        const indicador = item.querySelector(
                            ".message-indicador"
                        );

                        if (indicador) {
                            indicador.remove();
                        }
                    });
            } catch (erro) {
                console.error(erro);
            }
        };

        if (marcarTodas) {
            marcarTodas.addEventListener(
                "click",
                marcarTodasComoLidas
            );
        }

        if (marcarTodasPagina) {
            marcarTodasPagina.addEventListener(
                "click",
                marcarTodasComoLidas
            );
        }

        botao.addEventListener(
            "show.bs.dropdown",
            carregar
        );

        // Primeira consulta imediata.
        carregar();

        // Consulta a cada 10 segundos, com o portal aberto/visível.
        window.setInterval(() => {
            if (document.visibilityState === "visible") {
                carregar();
            }
        }, INTERVALO_MS);

        // Recupera atualizações assim que o usuário volta para o portal.
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible") {
                carregar();
            }
        });
        window.addEventListener("focus", carregar);
        window.addEventListener("online", carregar);
    };

    const iniciar = () => {
        document
            .querySelectorAll("[data-message-widget]")
            .forEach(iniciarWidget);
    };

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            iniciar
        );
    } else {
        iniciar();
    }
})();
