$(function () {

    /*
    |--------------------------------------------------------------------------
    | Máscaras
    |--------------------------------------------------------------------------
    */

    if ($.fn.mask) {

        $("input[name='valor'], input[name='valor_inscricao']").mask("000.000.000,00", {
            reverse: true
        });

    }

    /*
    |--------------------------------------------------------------------------
    | Preview Banner
    |--------------------------------------------------------------------------
    */

    $("#previewBanner,#btnSelecionarBanner").click(function () {

        $("#banner").trigger("click");

    });

    $("#banner").change(function () {

        let file = this.files[0];

        if (!file) return;

        let reader = new FileReader();

        reader.onload = function (e) {

            $("#previewBanner").attr("src", e.target.result);

        };

        reader.readAsDataURL(file);

    });


    /*
    |--------------------------------------------------------------------------
    | Limite de pagamento
    |--------------------------------------------------------------------------
    */

    function valorMonetario(numero) {

        let texto = String(numero || "0")
            .replace(/\./g, "")
            .replace(",", ".");

        let valor = parseFloat(texto);

        return Number.isFinite(valor) ? valor : 0;

    }

    function formatarDataHoraLocal(data) {

        const ano = data.getFullYear();
        const mes = String(data.getMonth() + 1).padStart(2, "0");
        const dia = String(data.getDate()).padStart(2, "0");
        const hora = String(data.getHours()).padStart(2, "0");
        const minuto = String(data.getMinutes()).padStart(2, "0");

        return `${ano}-${mes}-${dia}T${hora}:${minuto}`;

    }

    function atualizarLimitePagamento() {

        const $limite = $("#pagamento_fim");
        const dataInicio = $("input[name='data_inicio']").val();
        const pagamentoObrigatorio = $("#pagamento_obrigatorio").is(":checked");
        const valor = valorMonetario($("input[name='valor_inscricao']").val());
        const exigirLimite = pagamentoObrigatorio && valor > 0;
        const $repassarTaxa = $("#repassar_taxa_asaas");

        $limite.prop("required", exigirLimite);
        $repassarTaxa.prop("disabled", !exigirLimite);

        if (!exigirLimite) {
            $repassarTaxa.prop("checked", false);
        }

        if (!dataInicio) {
            $limite.removeAttr("max");
            return;
        }

        const inicio = new Date(`${dataInicio}T12:00:00`);

        if (Number.isNaN(inicio.getTime())) {
            $limite.removeAttr("max");
            return;
        }

        inicio.setDate(inicio.getDate() - 1);
        inicio.setHours(23, 59, 0, 0);

        const maximo = formatarDataHoraLocal(inicio);
        $limite.attr("max", maximo);

        if ($limite.val() && $limite.val() > maximo) {
            $limite.val(maximo);
        }

    }

    $("input[name='data_inicio'], input[name='valor_inscricao'], #pagamento_obrigatorio")
        .on("change keyup", atualizarLimitePagamento);

    atualizarLimitePagamento();

    /*
    |--------------------------------------------------------------------------
    | Salvar
    |--------------------------------------------------------------------------
    */

    $("#formEvento").submit(function (e) {

        e.preventDefault();

        let form = this;

        let dados = new FormData(form);

        $("#btnSalvar")

            .prop("disabled", true)

            .html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

        $.ajax({

            url: BASE_URL + "admin/event/ajax/evento-new.php",

            type: "POST",

            data: dados,

            processData: false,

            contentType: false,

            dataType: "json",

            success: function (ret) {

                if (ret.status) {

                    Swal.fire({

                        icon: "success",

                        title: "Sucesso",

                        text: ret.msg,

                        confirmButtonColor: "#3085d6"

                    }).then(function () {

                        window.location = BASE_URL + "admin/event/eventos.php";

                    });

                } else {

                    Swal.fire({

                        icon: "error",

                        title: "Erro",

                        text: ret.msg

                    });

                }

            },

            error: function (xhr) {

                console.log(xhr.responseText);

                Swal.fire({

                    icon: "error",

                    title: "Erro",

                    text: "Erro interno do servidor."

                });

            },

            complete: function () {

                $("#btnSalvar")

                    .prop("disabled", false)

                    .html('<i class="fa fa-save"></i> Salvar');

            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Alterações
    |--------------------------------------------------------------------------
    */

    let alterado = false;

    $("#formEvento :input").on("change keyup", function () {

        alterado = true;

    });

    $("#formEvento").submit(function () {

        alterado = false;

    });

    window.onbeforeunload = function () {

        if (alterado) {

            return "Existem alterações não salvas.";

        }

    };
    function gerarSlug(texto) {

        return texto
            .toLowerCase()

            // Remove acentos
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")

            // ç
            .replace(/ç/g, "c")

            // Remove caracteres especiais
            .replace(/[^a-z0-9\s-]/g, "")

            // Espaços para hífen
            .replace(/\s+/g, "-")

            // Remove hífens duplicados
            .replace(/-+/g, "-")

            // Remove hífens início/fim
            .replace(/^-|-$/g, "");

    }

    $("#titulo").on("keyup blur", function () {

        $("#slug").val(

            gerarSlug($(this).val())

        );

    });
});