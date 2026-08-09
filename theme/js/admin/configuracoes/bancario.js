$(function () {
    const $radios = $(".ambiente-radio");
    const $confirmacao = $("#confirmacaoProducao");
    const $confirmarProducao = $("#confirmarProducao");

    function atualizarAmbiente() {
        const ambiente = String($radios.filter(":checked").val() || "sandbox");

        $(".ambiente-card").removeClass("selecionado");
        $radios.filter(":checked").closest(".ambiente-card").addClass("selecionado");

        if (ambiente === "producao") {
            $confirmacao.stop(true, true).slideDown(150);
        } else {
            $confirmacao.stop(true, true).slideUp(150);
            $confirmarProducao.prop("checked", false);
        }
    }

    $radios.on("change", atualizarAmbiente);
    atualizarAmbiente();

    $(".alternar-senha").on("click", function () {
        const id = String($(this).data("target") || "");
        const campo = document.getElementById(id);

        if (!campo) {
            return;
        }

        const mostrar = campo.type === "password";
        campo.type = mostrar ? "text" : "password";

        $(this)
            .find("i")
            .toggleClass("fa-eye", !mostrar)
            .toggleClass("fa-eye-slash", mostrar);
    });

    $(".remover-credencial").on("change", function () {
        const id = String($(this).data("target") || "");
        const $campo = $("#" + id);
        const remover = $(this).is(":checked");

        $campo
            .prop("disabled", remover)
            .val(remover ? "" : $campo.val());

        const $botao = $(this)
            .closest(".credencial-bloco")
            .find('.alternar-senha[data-target="' + id + '"]');

        $botao.prop("disabled", remover);
    });

    $(".campo-credencial").on("input", function () {
        const id = this.id;
        $('.remover-credencial[data-target="' + id + '"]')
            .prop("checked", false);
    });

    $("#formConfiguracaoBancaria").on("submit", function (event) {
        const ambiente = String($radios.filter(":checked").val() || "sandbox");

        if (ambiente === "producao" && !$confirmarProducao.is(":checked")) {
            event.preventDefault();

            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: "warning",
                    title: "Confirme o ambiente de produção",
                    text: "Marque a confirmação de que as cobranças serão reais."
                });
            } else {
                alert("Confirme que o ambiente de produção criará cobranças reais.");
            }
        }
    });

    $("#copiarWebhook").on("click", async function () {
        const campo = document.getElementById("webhookUrl");
        campo.select();
        campo.setSelectionRange(0, campo.value.length);

        try {
            await navigator.clipboard.writeText(campo.value);
        } catch (erro) {
            document.execCommand("copy");
        }

        const $icone = $(this).find("i");
        $icone.removeClass("fa-copy").addClass("fa-check");

        setTimeout(function () {
            $icone.removeClass("fa-check").addClass("fa-copy");
        }, 1200);
    });
});
