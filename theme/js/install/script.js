$(document).ready(function() {
    var url_atual = window.location.href;
	url_caminho = url_atual.length;
    const baseUrl = window.location.href.split("/install/")[0] + "/install/";


    $("#idioma_install").on("change", function () {
        var idioma_selecionado = $(this).val();

        var url = new URL(window.location.href);
        url.searchParams.set("lang", idioma_selecionado);

        window.location.href = url.toString();
    });
    $("#confirm_lang").on("change", function () {
        if ($(this).is(":checked")) {
            $("#btn_next_idioma").prop("disabled", false);
        } else {
            $("#btn_next_idioma").prop("disabled", true);
        }
    });

    $("#btn_next_idioma").on("click", function (e) {
        e.preventDefault();

        $.ajax({
            url: baseUrl+"idioma/padrao_install.php",
            type: "POST",
            data: $("#form_idioma").serialize(),
            
            success: function (resposta) {
                window.location.href = "admin.php?id=2&lang=" + $("#idioma_install").val();
                // console.log(resposta);
            },
            error: function () {
                alert("Erro ao salvar os dados");
            }
        });
    });

    $("#formbtnnext").on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: "#",
            type: "POST",
            data: $("#formbtnnext").serialize(),
            success: function (resposta) {
                window.location.href = "admin.php?id="+$("#id_form").val()+"&lang=" + $("#idioma").val();
            },
            error: function () {
                alert("Erro ao salvar os dados");
            }

        })
    });

    $("#configura_banco").on('submit', function(e){
        e.preventDefault();

        let vazio = false;

        $("#configura_banco input, #configura_banco select, #configura_banco textarea").each(function () {
            if ($(this).val().trim() === "") {
                vazio = true;
                $(this).addClass("is-invalid");
            } else {
                $(this).removeClass("is-invalid");
            }
        });

        if (vazio) {
            Swal.fire({
                icon: "warning",
                title: "Atenção",
                text: "Preencha todos os campos obrigatórios."
            });
            return;
        }

        const baseUrl = window.location.href.split("/install/")[0] + "/install/";

        $.ajax({
            url: baseUrl + "teste/test_db.php",
            type: "POST",
            data: $("#configura_banco").serialize(),
            dataType: "json", // 🔴 MUITO IMPORTANTE
            success: function (resposta) {

                if (resposta.status === "ok") {

                    Swal.fire({
                        icon: "success",
                        title: "Sucesso",
                        text: resposta.message
                    });
                    $.ajax({
                        url: "configuraction/bd/conexao.php",
                        type: "POST",
                        data: $("#configura_banco").serialize(),
                        success: function (resposta) {
                            Swal.fire({
                                icon: "success",
                                title: "Sucesso",
                                text: "Configurações do banco de dados salvas com sucesso."
                            });
                        },
                        error: function () {
                            Swal.fire({
                                icon: "error",
                                title: "Erro",
                                text: "Erro ao salvar as configurações do banco de dados."
                            });
                        }
                    });

                    $("#btn_next").prop("disabled", false);

                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: resposta.message
                    });
                    $("#btn_next").prop("disabled", true);
                }
            },
            error: function () {
                Swal.fire({
                    icon: "error",
                    title: "Oops...",
                    text: "Erro inesperado ao testar a conexão."
                });
            }
        });
    });

    $("#configura_caminho").on("submit", function (e) {
        e.preventDefault();

        $.ajax({
            url: baseUrl + "configuraction/config_caminho.php",
            type: "POST",
            data: $("#configura_caminho").serialize(),
            success: function (resposta) {
                window.location.href = "admin.php?id=5&lang=" + $("#idioma").val();
            },
            error: function () {
                alert("Erro ao salvar os dados");
            }
        });
    });

});