$(function () {

    let idExcluir = 0;

    /*
    |--------------------------------------------------------------------------
    | Excluir
    |--------------------------------------------------------------------------
    */

    $(document).on("click", ".btn-excluir", function () {

        idExcluir = $(this).data("id");

        $("#nomeEventoExcluir").text(
            $(this).data("titulo")
        );

        const modal = new bootstrap.Modal(
            document.getElementById("modalExcluir")
        );

        modal.show();

    });

    /*
    |--------------------------------------------------------------------------
    | Confirmar Exclusão
    |--------------------------------------------------------------------------
    */

    $("#confirmarExcluirEvento").click(function () {

        $.ajax({

            url: "ajax/evento-delete.php",

            type: "POST",

            dataType: "json",

            data: {

                id: idExcluir,

                _token: $("input[name='_token']").val()

            },

            beforeSend: function () {

                $("#confirmarExcluirEvento")
                    .prop("disabled", true)
                    .html('<i class="fa fa-spinner fa-spin"></i> Excluindo...');

            },

            success: function (ret) {

                if (ret.status) {

                    Swal.fire({

                        icon: "success",

                        title: "Sucesso",

                        text: ret.msg,

                        timer: 1200,

                        showConfirmButton: false

                    }).then(function () {

                        location.reload();

                    });

                } else {

                    Swal.fire(

                        "Erro",

                        ret.msg,

                        "error"

                    );

                }

            },

            error: function () {

                Swal.fire(

                    "Erro",

                    "Falha na comunicação.",

                    "error"

                );

            },

            complete: function () {

                $("#confirmarExcluirEvento")
                    .prop("disabled", false)
                    .html("Excluir");

            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Ativar / Inativar
    |--------------------------------------------------------------------------
    */
    /*
        $(document).on("click", ".btn-status", function () {
    
            let id = $(this).data("id");
            let tokens = $(this).data("tokens");
    
            $.ajax({
    
                url: "ajax/evento-status.php",
    
                type: "POST",
    
                dataType: "json",
    
                data: {
    
                    id: id,
    
                    _token: tokens
    
                },
    
                success: function (ret) {
    
                    if (ret.status) {
    
                        location.reload();
    
                    } else {
    
                        Swal.fire(
    
                            "Erro",
    
                            ret.msg,
    
                            "error"
    
                        );
    
                    }
    
                }
    
            });
    
        });*/
    $(document).on("click", ".btn-editar", function () {
        let botao = $(this)
        let id = botao.data('id')
        window.location = BASE_URL + "admin/event/evento.php?id=" + id;

    })

    $(document).on("click", ".btn-status", function () {

        let botao = $(this);
        let id = botao.data("id");
        let tokens = $(this).data("token");

        $.ajax({

            url: "ajax/evento-status.php",

            type: "POST",

            dataType: "json",

            data: {
                id: id,
                _token: tokens
            },

            beforeSend: function () {

                botao.prop("disabled", true);

            },

            success: function (ret) {

                if (ret.status) {

                    Swal.fire({
                        icon: "success",
                        title: "Sucesso",
                        text: ret.msg,
                        timer: 1200,
                        showConfirmButton: false
                    });

                    carregarEventos(); // Atualiza somente a tabela

                } else {

                    Swal.fire(
                        "Erro",
                        ret.msg,
                        "error"
                    );

                }

            },

            error: function (xhr) {

                console.log(xhr.responseText);

                Swal.fire(
                    "Erro",
                    "Falha na comunicação.",
                    "error"
                );

            },

            complete: function () {

                botao.prop("disabled", false);

            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Visualizar
    |--------------------------------------------------------------------------
    */

    $(document).on("click", ".btn-visualizar", function () {

        let id = $(this).data("id");

        $("#conteudoEvento").html(

            '<div class="text-center p-5">' +

            '<div class="spinner-border text-primary"></div>' +

            '</div>'

        );

        const modal = new bootstrap.Modal(

            document.getElementById("modalVisualizar")

        );

        modal.show();

        $.get(

            "ajax/evento-view.php",

            {

                id: id

            },

            function (html) {

                $("#conteudoEvento").html(html);

            }

        );

    });
    let tempo;
    // RETIRO_FILTRO_ANO_EVENTOS_V1
    let requisicaoEventos = null;
    let sequenciaEventos = 0;
    carregarEventos(Number($('#eventosPaginaFiltro').text()) || 1);

    function carregarEventos(pagina = 1) {
        const sequencia = ++sequenciaEventos;
        if (requisicaoEventos) requisicaoEventos.abort();
        requisicaoEventos = $.ajax({

            url: "ajax/eventos-lista.php",

            type: "GET",

            data: {

                pagina: pagina,

                pesquisa: $("#pesquisa").val(),

                tipo: $("#tipo").val(),

                status: $("#status").val(),
                ano: $("#ano").val()

            },

            beforeSend: function () {

                $("#listaEventos").html(

                    '<div class="text-center p-5">' +

                    '<div class="spinner-border text-primary"></div>' +

                    '</div>'

                );

            },

            success: function (html) {
                if (sequencia !== sequenciaEventos) return;
                $("#listatabela").empty().hide();
                $("#paginacaoInicialEventos").hide();
                $("#listaEventos").html(html);
                const meta = $("#metaFiltroAnoEventos");
                if (meta.length) {
                    $("#eventosTotalFiltro").text(meta.attr('data-total'));
                    $("#eventosPaginaFiltro").text(meta.attr('data-pagina'));
                    $("#eventosRegistrosFiltro").text(meta.attr('data-registros'));
                    $("#eventosPaginasFiltro").text(meta.attr('data-paginas'));
                    if (window.history && window.history.replaceState) {
                        const url = new URL(window.location.href);
                        for (const campo of ['pesquisa', 'tipo', 'status', 'ano']) url.searchParams.set(campo, $('#' + campo).val() || '');
                        url.searchParams.set('pagina', meta.attr('data-pagina'));
                        window.history.replaceState(null, '', url.toString());
                    }
                }

            }

        });

    }

    $("#pesquisa").on("keyup", function () {

        clearTimeout(tempo);

        tempo = setTimeout(function () {

            carregarEventos();

        }, 400);

    });

    $("#tipo,#status,#ano").on("change", function () {
        clearTimeout(tempo);

        carregarEventos();

    });

    $(document).on("click", ".pagina-evento", function (e) {

        e.preventDefault();

        carregarEventos($(this).data("pagina"));

    });

});