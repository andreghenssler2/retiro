(() => {
    "use strict";

    const iniciarWidget = (widget) => {
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

        let carregando = false;

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

        const carregar = async () => {
            if (carregando) {
                return;
            }

            carregando = true;

            try {
                const resposta = await fetch(
                    `${config.listarUrl}?_=${Date.now()}`,
                    {
                        credentials: "same-origin",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        cache: "no-store"
                    }
                );

                const dados = await resposta.json();

                if (!resposta.ok || !dados.status) {
                    throw new Error(
                        dados.msg
                        || "Não foi possível carregar as notificações."
                    );
                }

                atualizarBadge(dados.naoLidas);
                renderizar(dados.notificacoes);
            } catch (erro) {
                lista.innerHTML = `
                    <div class="message-widget-erro">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Não foi possível carregar as notificações.
                    </div>
                `;
            } finally {
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

        carregar();

        window.setInterval(carregar, 30000);
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
