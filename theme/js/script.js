
$(document).ready(function () {
    var url_atual = window.location.href;
    url_caminho = url_atual.length;

    $('.toggleSenha').on('click', function () {

        let input = $(this).siblings('input');

        if (input.attr('type') === 'password') {

            input.attr('type', 'text');

            $(this)
                .find('i')
                .removeClass('fa-eye')
                .addClass('fa-eye-slash');

        } else {

            input.attr('type', 'password');

            $(this)
                .find('i')
                .removeClass('fa-eye-slash')
                .addClass('fa-eye');

        }

    });

    $(".cpf").on("input", function () {

        let cpf = $(this).val();

        cpf = cpf.replace(/\D/g, '');

        if (cpf.length > 11)
            cpf = cpf.substring(0, 11);

        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d)/, '$1.$2');
        cpf = cpf.replace(/(\d{3})(\d{1,2})$/, '$1-$2');

        $(this).val(cpf);

    });

    let timeoutCpf;

    $(".cpf").on("keyup blur", function () {

        clearTimeout(timeoutCpf);

        timeoutCpf = setTimeout(verificarCPF, 400);

    });

    function verificarCPF() {

        let cpf = $(".cpf").val().replace(/\D/g, '');

        $(".cpf")
            .removeClass("is-valid is-invalid");

        $("#cpf-msg")
            .addClass("d-none")
            .text("");

        if (cpf.length != 11) {
            return;
        }

        if (!cpfValido(cpf)) {

            $(".cpf")
                .addClass("is-invalid");

            $("#cpf-msg")
                .removeClass("d-none")
                .text("CPF inválido.");

            return;

        }

        $.ajax({

            url: "/mod/ajax/verificar-cpf.php",

            type: "POST",

            data: {

                cpf: cpf,

                id: $("input[name=id]").val()

            },

            dataType: "json",

            success: function (ret) {

                if (ret.existe) {

                    $(".cpf")
                        .removeClass("is-valid")
                        .addClass("is-invalid");

                    $("#cpf-msg")
                        .removeClass("d-none")
                        .text("CPF já cadastrado.");

                } else {

                    $(".cpf")
                        .removeClass("is-invalid")
                        .addClass("is-valid");

                    $("#cpf-msg")
                        .addClass("d-none")
                        .text("");

                }

            }

        });

    }
    function cpfValido(cpf) {

        cpf = cpf.replace(/\D/g, '');

        if (cpf.length != 11)
            return false;

        // elimina CPFs iguais
        if (/^(\d)\1+$/.test(cpf))
            return false;

        let soma = 0;

        for (let i = 0; i < 9; i++) {

            soma += parseInt(cpf.charAt(i)) * (10 - i);

        }

        let resto = (soma * 10) % 11;

        if (resto == 10 || resto == 11)
            resto = 0;

        if (resto != parseInt(cpf.charAt(9)))
            return false;

        soma = 0;

        for (let i = 0; i < 10; i++) {

            soma += parseInt(cpf.charAt(i)) * (11 - i);

        }

        resto = (soma * 10) % 11;

        if (resto == 10 || resto == 11)
            resto = 0;

        if (resto != parseInt(cpf.charAt(10)))
            return false;

        return true;

    }
    $(".telefone").on("input", function () {

        let v = $(this).val().replace(/\D/g, "");

        if (v.length > 11) {
            v = v.substring(0, 11);
        }

        if (v.length > 10) {

            v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, "($1) $2-$3");

        } else {

            v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, "($1) $2-$3");

        }

        $(this).val(v);

    });

});
