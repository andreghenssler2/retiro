<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuração dos logs
|--------------------------------------------------------------------------
|
| ativo:
|   Habilita/desabilita a gravação dos logs pelo módulo Log.
|
| retencao_dias:
|   Quantidade de dias que cada arquivo .log permanecerá armazenado.
|   Arquivos mais antigos serão excluídos automaticamente.
|
| limpeza_automatica:
|   Quando true, o próprio Log verifica e remove arquivos antigos.
|
| intervalo_limpeza_horas:
|   Evita executar a varredura a cada requisição.
|   Com 24, a limpeza automática será realizada no máximo 1 vez por dia.
|
*/

return [
    "ativo" => true,

    "retencao_dias" => 30,

    "limpeza_automatica" => true,

    "intervalo_limpeza_horas" => 24
];
