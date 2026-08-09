$(function () {

    "use strict";

    const $form = $("#formUsuario");

    if (!$form.length) {
        return;
    }

    const $cpf = $("#cpf");
    const $email = $("#email");
    const $telefone = $("#telefone");
    const $senha = $("#senha");
    const $confirmarSenha = $("#confirmar_senha");
    const $botaoSalvar = $("#btnSalvar");

    const estado = {
        cpfValido: false,
        cpfLivre: true,
        emailValido: false,
        emailLivre: true,
        verificandoCpf: false,
        verificandoEmail: false
    };

    const temporizadores = {};

    function somenteNumeros(valor) {
        return String(valor ?? "").replace(/\D/g, "");
    }

    function formatarCpf(valor) {
        const numeros = somenteNumeros(valor).slice(0, 11);

        return numeros
            .replace(/^(\d{3})(\d)/, "$1.$2")
            .replace(/^(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
            .replace(/\.(\d{3})(\d)/, ".$1-$2");
    }

    function formatarTelefone(valor) {
        const numeros = somenteNumeros(valor).slice(0, 11);

        if (numeros.length <= 10) {
            return numeros
                .replace(/^(\d{2})(\d)/, "($1) $2")
                .replace(/(\d{4})(\d)/, "$1-$2");
        }

        return numeros
            .replace(/^(\d{2})(\d)/, "($1) $2")
            .replace(/(\d{5})(\d)/, "$1-$2");
    }

    function validarCpf(valor) {
        const cpf = somenteNumeros(valor);

        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
            return false;
        }

        for (let tamanho = 9; tamanho < 11; tamanho += 1) {
            let soma = 0;

            for (let indice = 0; indice < tamanho; indice += 1) {
                soma += Number(cpf[indice]) * (tamanho + 1 - indice);
            }

            let digito = (10 * soma) % 11;

            if (digito === 10) {
                digito = 0;
            }

            if (digito !== Number(cpf[tamanho])) {
                return false;
            }
        }

        return true;
    }

    function validarEmail(valor) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(valor ?? "").trim());
    }

    function atualizarFeedback($campo, $feedback, valido, mensagem) {
        $campo.removeClass("is-valid is-invalid");
        $feedback.removeClass("text-success text-danger text-muted");

        if (valido) {
            $campo.addClass("is-valid");
            $campo.get(0).setCustomValidity("");
            $feedback.addClass("text-success").text(mensagem);
            return;
        }

        $campo.addClass("is-invalid");
        $campo.get(0).setCustomValidity(mensagem);
        $feedback.addClass("text-danger").text(mensagem);
    }

    function verificarCpf() {
        const valor = $cpf.val();
        const $feedback = $("#cpfFeedback");

        estado.cpfValido = validarCpf(valor);

        if (!estado.cpfValido) {
            estado.cpfLivre = false;
            atualizarFeedback(
                $cpf,
                $feedback,
                false,
                "Informe um CPF válido."
            );
            return $.Deferred().resolve().promise();
        }

        estado.verificandoCpf = true;
        $feedback.removeClass("text-success text-danger").addClass("text-muted").text("Verificando...");

        return $.ajax({
            url: $form.data("verificar-cpf-url"),
            type: "POST",
            dataType: "json",
            data: {
                cpf: somenteNumeros(valor),
                id: ID_USUARIO
            }
        })
            .done(function (resposta) {
                estado.cpfLivre = resposta?.existe !== true;

                atualizarFeedback(
                    $cpf,
                    $feedback,
                    estado.cpfLivre,
                    estado.cpfLivre
                        ? "CPF disponível."
                        : "Este CPF já está cadastrado."
                );
            })
            .fail(function () {
                estado.cpfLivre = false;
                atualizarFeedback(
                    $cpf,
                    $feedback,
                    false,
                    "Não foi possível verificar o CPF agora."
                );
            })
            .always(function () {
                estado.verificandoCpf = false;
            });
    }

    function verificarEmail() {
        const valor = String($email.val() ?? "").trim();
        const $feedback = $("#emailFeedback");

        estado.emailValido = validarEmail(valor);

        if (!estado.emailValido) {
            estado.emailLivre = false;
            atualizarFeedback(
                $email,
                $feedback,
                false,
                "Informe um e-mail válido."
            );
            return $.Deferred().resolve().promise();
        }

        estado.verificandoEmail = true;
        $feedback.removeClass("text-success text-danger").addClass("text-muted").text("Verificando...");

        return $.ajax({
            url: $form.data("verificar-email-url"),
            type: "POST",
            dataType: "json",
            data: {
                email: valor,
                id: ID_USUARIO
            }
        })
            .done(function (resposta) {
                estado.emailLivre = resposta?.existe !== true;

                atualizarFeedback(
                    $email,
                    $feedback,
                    estado.emailLivre,
                    estado.emailLivre
                        ? "E-mail disponível."
                        : "Este e-mail já está cadastrado."
                );
            })
            .fail(function () {
                estado.emailLivre = false;
                atualizarFeedback(
                    $email,
                    $feedback,
                    false,
                    "Não foi possível verificar o e-mail agora."
                );
            })
            .always(function () {
                estado.verificandoEmail = false;
            });
    }

    function agendarVerificacao(chave, callback) {
        clearTimeout(temporizadores[chave]);
        temporizadores[chave] = setTimeout(callback, 450);
    }

    $cpf.on("input", function () {
        this.value = formatarCpf(this.value);
        this.setCustomValidity("");
        $(this).removeClass("is-valid is-invalid");
        $("#cpfFeedback").text("");

        estado.cpfValido = validarCpf(this.value);
        estado.cpfLivre = false;

        agendarVerificacao("cpf", verificarCpf);
    });

    $email.on("input", function () {
        this.setCustomValidity("");
        $(this).removeClass("is-valid is-invalid");
        $("#emailFeedback").text("");

        estado.emailValido = validarEmail(this.value);
        estado.emailLivre = false;

        agendarVerificacao("email", verificarEmail);
    });

    $cpf.on("blur", verificarCpf);
    $email.on("blur", verificarEmail);

    $telefone.on("input", function () {
        this.value = formatarTelefone(this.value);

        const quantidade = somenteNumeros(this.value).length;
        this.setCustomValidity(
            quantidade >= 10 && quantidade <= 11
                ? ""
                : "Informe um telefone válido com DDD."
        );
    });

    $("#previewFoto, #btnSelecionarFoto").on("click", function () {
        $("#foto").trigger("click");
    });

    $("#foto").on("change", function () {
        const arquivo = this.files?.[0];

        if (!arquivo) {
            return;
        }

        if (arquivo.size > 5 * 1024 * 1024) {
            Swal.fire(
                "Imagem muito grande",
                "Selecione uma imagem com até 5 MB.",
                "warning"
            );
            this.value = "";
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            $("#previewFoto").attr("src", event.target.result);
        };
        reader.readAsDataURL(arquivo);
    });

    $(".toggleSenha").on("click", function () {
        const $input = $(this).siblings("input");
        const mostrar = $input.attr("type") === "password";

        $input.attr("type", mostrar ? "text" : "password");

        $(this).find("i")
            .toggleClass("fa-eye", !mostrar)
            .toggleClass("fa-eye-slash", mostrar);
    });

    function atualizarRequisito($elemento, atende) {
        $elemento
            .toggleClass("text-success", atende)
            .toggleClass("text-danger", !atende);

        $elemento.find("i")
            .toggleClass("fa-check", atende)
            .toggleClass("fa-times", !atende);
    }

    function validarSenha() {
        const senha = String($senha.val() ?? "");
        const confirmar = String($confirmarSenha.val() ?? "");
        const senhaInformada = senha !== "" || confirmar !== "";
        const obrigatoria = !EDITANDO;

        const regras = {
            maiuscula: /[A-Z]/.test(senha),
            minuscula: /[a-z]/.test(senha),
            numero: /\d/.test(senha),
            especial: /[!@#$&-]/.test(senha),
            tamanho: senha.length >= 6,
            igual: senha !== "" && senha === confirmar
        };

        atualizarRequisito($("#reqMaiuscula"), regras.maiuscula);
        atualizarRequisito($("#reqMinuscula"), regras.minuscula);
        atualizarRequisito($("#reqNumero"), regras.numero);
        atualizarRequisito($("#reqEspecial"), regras.especial);
        atualizarRequisito($("#reqTamanho"), regras.tamanho);
        atualizarRequisito($("#reqIgual"), regras.igual);

        if (!obrigatoria && !senhaInformada) {
            $senha.get(0).setCustomValidity("");
            $confirmarSenha.get(0).setCustomValidity("");
            return true;
        }

        const valida = Object.values(regras).every(Boolean);

        $senha.get(0).setCustomValidity(
            valida
                ? ""
                : "A senha não atende aos requisitos."
        );

        $confirmarSenha.get(0).setCustomValidity(
            regras.igual
                ? ""
                : "As senhas não conferem."
        );

        return valida;
    }

    $senha.add($confirmarSenha).on("input", validarSenha);

    function restaurarBotao() {
        const texto = $botaoSalvar.data("texto-normal") || "Salvar usuário";

        $botaoSalvar
            .prop("disabled", false)
            .html('<i class="fa fa-save"></i> ' + texto);
    }

    $form.on("submit", function (event) {
        event.preventDefault();

        const formulario = this;
        validarSenha();

        const quantidadeTelefone = somenteNumeros($telefone.val()).length;
        $telefone.get(0).setCustomValidity(
            quantidadeTelefone >= 10 && quantidadeTelefone <= 11
                ? ""
                : "Informe um telefone válido com DDD."
        );

        $.when(
            verificarCpf(),
            verificarEmail()
        ).always(function () {
            if (
                !estado.cpfValido
                || !estado.cpfLivre
                || !estado.emailValido
                || !estado.emailLivre
                || !formulario.checkValidity()
            ) {
                formulario.classList.add("was-validated");

                const primeiroInvalido = formulario.querySelector(":invalid");

                if (primeiroInvalido) {
                    primeiroInvalido.focus();
                }

                return;
            }

            $botaoSalvar
                .prop("disabled", true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span>Salvando...');

            $.ajax({
                url: $form.data("salvar-url"),
                method: "POST",
                data: new FormData(formulario),
                processData: false,
                contentType: false,
                dataType: "json"
            })
                .done(function (resposta) {
                    if (!resposta || resposta.status !== true) {
                        Swal.fire(
                            "Não foi possível salvar",
                            resposta?.msg ?? "Verifique os dados informados.",
                            "error"
                        );
                        return;
                    }

                    Swal.fire({
                        icon: "success",
                        title: "Sucesso",
                        text: resposta.msg,
                        timer: 1800,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.href = "usuarios.php";
                    });
                })
                .fail(function (xhr) {
                    Swal.fire(
                        "Erro",
                        xhr.responseJSON?.msg ?? "Erro interno do servidor.",
                        "error"
                    );
                })
                .always(restaurarBotao);
        });
    });

    if ($cpf.val()) {
        $cpf.val(formatarCpf($cpf.val()));
        estado.cpfValido = validarCpf($cpf.val());
        estado.cpfLivre = true;
    }

    if ($telefone.val()) {
        $telefone.val(formatarTelefone($telefone.val()));
    }

    estado.emailValido = validarEmail($email.val());
    estado.emailLivre = true;

    validarSenha();
});
