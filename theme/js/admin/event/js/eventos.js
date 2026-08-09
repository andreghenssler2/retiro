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

    carregarEventos();

    function carregarEventos(pagina = 1) {

        $.ajax({

            url: "ajax/eventos-lista.php",

            type: "GET",

            data: {

                pagina: pagina,

                pesquisa: $("#pesquisa").val(),

                tipo: $("#tipo").val(),

                status: $("#status").val()

            },

            beforeSend: function () {

                $("#listaEventos").html(

                    '<div class="text-center p-5">' +

                    '<div class="spinner-border text-primary"></div>' +

                    '</div>'

                );

            },

            success: function (html) {
                $("#listatabela").html('');
                $("#listaEventos").html(html);

            }

        });

    }

    $("#pesquisa").on("keyup", function () {

        clearTimeout(tempo);

        tempo = setTimeout(function () {

            carregarEventos();

        }, 400);

    });

    $("#tipo,#status").on("change", function () {

        carregarEventos();

    });

    $(document).on("click", ".pagina-evento", function (e) {

        e.preventDefault();

        carregarEventos($(this).data("pagina"));

    });

});