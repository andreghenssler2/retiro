$(function () {

    let tabela = $("table tbody");
    let linhas = tabela.find("tr");

    /*
    |--------------------------------------------------------------------------
    | Pesquisa
    |--------------------------------------------------------------------------
    */

    $("#pesquisar").on("keyup", function () {

        filtrarTabela();

    });

    /*
    |--------------------------------------------------------------------------
    | Perfil
    |--------------------------------------------------------------------------
    */

    $("#filtroPerfil").on("change", function () {

        filtrarTabela();

    });

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    $("#filtroStatus").on("change", function () {

        filtrarTabela();

    });

    /*
    |--------------------------------------------------------------------------
    | Função principal
    |--------------------------------------------------------------------------
    */

    function filtrarTabela() {

        let texto = $("#pesquisar").val().toLowerCase().trim();

        let perfil = $("#filtroPerfil").val();

        let status = $("#filtroStatus").val();

        let encontrados = 0;

        linhas.each(function () {

            let tr = $(this);

            let usuario = tr.find("td:eq(1)").text().toLowerCase();

            let perfilTexto = tr.find("td:eq(2)").text().trim();

            let statusTexto = tr.find("td:eq(4)").text().trim();

            let mostrar = true;

            /*
            -------------------------
            Pesquisa
            -------------------------
            */

            if (texto.length > 0) {

                if (usuario.indexOf(texto) === -1) {

                    mostrar = false;

                }

            }

            /*
            -------------------------
            Perfil
            -------------------------
            */

            if (perfil !== "") {

                if (perfilTexto !== perfil) {

                    mostrar = false;

                }

            }

            /*
            -------------------------
            Status
            -------------------------
            */

            if (status !== "") {

                if (statusTexto !== status) {

                    mostrar = false;

                }

            }

            if (mostrar) {

                tr.show();

                encontrados++;

            } else {

                tr.hide();

            }

        });

        atualizarMensagem(encontrados);

    }

    /*
    |--------------------------------------------------------------------------
    | Mensagem "Nenhum registro"
    |--------------------------------------------------------------------------
    */

    function atualizarMensagem(total) {

        $("#linha-sem-registro").remove();

        if (total > 0)
            return;

        tabela.append(`
            <tr id="linha-sem-registro">
                <td colspan="6" class="text-center text-muted py-5">
                    <i class="fa fa-search fa-2x mb-3"></i><br>
                    Nenhum usuário encontrado.
                </td>
            </tr>
        `);

    }

    /*
    |--------------------------------------------------------------------------
    | Ordenação
    |--------------------------------------------------------------------------
    */

    $("table thead th").css("cursor", "pointer");

    $("table thead th").click(function () {

        let indice = $(this).index();

        ordenar(indice);

    });

    let asc = true;

    function ordenar(indice) {

        let trs = tabela.find("tr").get();

        trs.sort(function (a, b) {

            let A = $(a).children("td").eq(indice).text().toUpperCase();

            let B = $(b).children("td").eq(indice).text().toUpperCase();

            if ($.isNumeric(A) && $.isNumeric(B)) {

                A = parseFloat(A);

                B = parseFloat(B);

            }

            if (A < B)
                return asc ? -1 : 1;

            if (A > B)
                return asc ? 1 : -1;

            return 0;

        });

        $.each(trs, function (i, tr) {

            tabela.append(tr);

        });

        asc = !asc;

    }

    /*
    |--------------------------------------------------------------------------
    | Zebra após filtro
    |--------------------------------------------------------------------------
    */

    function zebra() {

        tabela.find("tr:visible").each(function (i) {

            $(this).removeClass("table-light");

            if (i % 2 === 0) {

                $(this).addClass("table-light");

            }

        });

    }

    setInterval(zebra, 300);

});