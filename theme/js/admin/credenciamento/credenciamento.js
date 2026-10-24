$(function () {
    'use strict';

    const $evento = $('#eventoCredenciamento');
    const $lista = $('#listaCredenciamento');
    const $pesquisa = $('#pesquisaCredenciamento');
    const token = String($('#_tokenCredenciamento').val() || '');
    const idEvento = Number.parseInt(
        String($('#idEventoCredenciamento').val() || '0'),
        10
    ) || 0;

    let temporizadorPesquisa = null;

    function mensagem(tipo, titulo, texto) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: tipo,
                title: titulo,
                text: texto,
                confirmButtonText: 'OK'
            });
            return;
        }

        window.alert(texto);
    }

    function carregarLista() {
        if (idEvento <= 0 || !$lista.length) {
            return;
        }

        $lista.html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <div class="text-muted mt-2">Carregando lista de chamada...</div>
            </div>
        `);

        $.ajax({
            url: 'ajax/lista.php',
            method: 'GET',
            cache: false,
            data: {
                evento: idEvento,
                pesquisa: String($pesquisa.val() || '').trim()
            }
        }).done(function (html) {
            $lista.html(html);
        }).fail(function () {
            $lista.html(`
                <div class="alert alert-danger">
                    Não foi possível carregar a lista de chamada.
                </div>
            `);
        });
    }

    $evento.on('change', function () {
        const selecionado = Number.parseInt(String($(this).val() || '0'), 10) || 0;
        const url = new URL(window.location.href);

        if (selecionado > 0) {
            url.searchParams.set('evento', String(selecionado));
        } else {
            url.searchParams.delete('evento');
        }

        window.location.href = url.toString();
    });

    $pesquisa.on('input', function () {
        clearTimeout(temporizadorPesquisa);
        temporizadorPesquisa = setTimeout(carregarLista, 350);
    });

    $(document).on('change', '.credenciamento-checkbox', function () {
        const $checkbox = $(this);
        const id = Number.parseInt(String($checkbox.data('id') || '0'), 10) || 0;
        const presente = $checkbox.is(':checked');

        if (id <= 0) {
            $checkbox.prop('checked', !presente);
            return;
        }

        $checkbox.prop('disabled', true);

        $.ajax({
            url: 'ajax/salvar-presenca.php',
            method: 'POST',
            dataType: 'json',
            data: {
                id: id,
                presente: presente ? 1 : 0,
                _token: token
            }
        }).done(function (resposta) {
            if (!resposta || resposta.status !== true) {
                $checkbox.prop('checked', !presente);
                mensagem(
                    'error',
                    'Não foi possível atualizar',
                    resposta?.msg || 'Não foi possível atualizar a presença.'
                );
                return;
            }

            carregarLista();
        }).fail(function (xhr) {
            $checkbox.prop('checked', !presente);
            mensagem(
                'error',
                'Não foi possível atualizar',
                xhr.responseJSON?.msg || 'Não foi possível atualizar a presença.'
            );
        }).always(function () {
            $checkbox.prop('disabled', false);
        });
    });

    carregarLista();
});
