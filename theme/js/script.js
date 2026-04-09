$(document).ready(function() {
    var url_atual = window.location.href;
	url_caminho = url_atual.length;

    $('input[data-digitar="cpf"]').mask("999.999.999-99",{reverse:false});
    $('input[data-digitar="cnpj"]').mask("99.999.999/9999-99",{reverse:false});
    $('input[data-digitar="celular"]').mask("(99) 99999-9999",{reverse:false});
    $('input[data-digitar="telefone"]').mask("(99) 9999-9999",{reverse:false});
    $('input[data-digitar="cep"]').mask("99999-999",{reverse:false});

});