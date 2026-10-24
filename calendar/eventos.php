<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/CalendarExport.php";

Middleware::auth();
AtividadeUsuario::ignorarRequisicaoAtual();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: private, no-store, no-cache, must-revalidate");

/**
 * Retorna apenas a data da faixa solicitada pelo FullCalendar.
 */
function calendarDataFiltro(string $valor, string $padrao): string
{
    $valor = trim($valor);

    if ($valor === "") {
        return $padrao;
    }

    try {
        $data = new DateTimeImmutable($valor);

        return $data->format("Y-m-d");
    } catch (Throwable) {
        return $padrao;
    }
}

function calendarHorario(?string $horario): string
{
    $horario = trim((string) $horario);

    if ($horario === "" || $horario === "00:00:00") {
        return "";
    }

    return substr($horario, 0, 8);
}

function calendarDataValida(?string $data): string
{
    $data = trim((string) $data);

    if (
        $data === ""
        || $data === "0000-00-00"
    ) {
        return "";
    }

    return $data;
}

/**
 * @return array{start:string,end:?string,allDay:bool}
 */
function calendarPeriodoEvento(array $evento): array
{
    $dataInicio = calendarDataValida(
        (string) ($evento["data_inicio"] ?? "")
    );

    $dataFim = calendarDataValida(
        (string) ($evento["data_fim"] ?? "")
    );

    $horaInicio = calendarHorario(
        $evento["hora_inicio"] ?? null
    );

    $horaFim = calendarHorario(
        $evento["hora_fim"] ?? null
    );

    if ($dataInicio === "") {
        throw new RuntimeException(
            "Evento sem data inicial válida."
        );
    }

    $allDay = $horaInicio === "";

    if ($allDay) {
        $fimInclusivo = $dataFim !== ""
            ? $dataFim
            : $dataInicio;

        $fimExclusivo = (new DateTimeImmutable($fimInclusivo))
            ->modify("+1 day")
            ->format("Y-m-d");

        return [
            "start" => $dataInicio,
            "end" => $fimExclusivo,
            "allDay" => true
        ];
    }

    $inicio = $dataInicio . "T" . $horaInicio;
    $fim = null;

    if ($horaFim !== "") {
        $fim = ($dataFim !== "" ? $dataFim : $dataInicio)
            . "T"
            . $horaFim;
    } elseif ($dataFim !== "" && $dataFim !== $dataInicio) {
        $fim = $dataFim . "T23:59:59";
    }

    return [
        "start" => $inicio,
        "end" => $fim,
        "allDay" => false
    ];
}

/**
 * @return array{background:string,border:string,text:string}
 */
function calendarCoresEvento(
    array $evento,
    bool $administrador
): array {
    if (!$administrador) {
        $status = strtolower(
            trim((string) ($evento["statusInscricao"] ?? ""))
        );

        return match ($status) {
            "confirmada" => [
                "background" => "#198754",
                "border" => "#198754",
                "text" => "#ffffff"
            ],
            "cancelada" => [
                "background" => "#dc3545",
                "border" => "#dc3545",
                "text" => "#ffffff"
            ],
            default => [
                "background" => "#ffc107",
                "border" => "#e0a800",
                "text" => "#212529"
            ]
        };
    }

    if ((int) ($evento["ativo"] ?? 0) !== 1) {
        return [
            "background" => "#6c757d",
            "border" => "#6c757d",
            "text" => "#ffffff"
        ];
    }

    $tipo = strtolower(
        trim((string) ($evento["tipo"] ?? ""))
    );

    return match ($tipo) {
        "retiro" => [
            "background" => "#0d6efd",
            "border" => "#0d6efd",
            "text" => "#ffffff"
        ],
        "congresso" => [
            "background" => "#198754",
            "border" => "#198754",
            "text" => "#ffffff"
        ],
        "acampamento" => [
            "background" => "#fd7e14",
            "border" => "#fd7e14",
            "text" => "#ffffff"
        ],
        "curso" => [
            "background" => "#0dcaf0",
            "border" => "#0aa2c0",
            "text" => "#172033"
        ],
        "encontro" => [
            "background" => "#6f42c1",
            "border" => "#6f42c1",
            "text" => "#ffffff"
        ],
        "culto" => [
            "background" => "#212529",
            "border" => "#212529",
            "text" => "#ffffff"
        ],
        default => [
            "background" => "#0d6efd",
            "border" => "#0d6efd",
            "text" => "#ffffff"
        ]
    };
}

try {
    $inicioPadrao = (new DateTimeImmutable("first day of -1 month"))
        ->format("Y-m-d");

    $fimPadrao = (new DateTimeImmutable("first day of +2 months"))
        ->format("Y-m-d");

    $inicio = calendarDataFiltro(
        (string) ($_GET["start"] ?? ""),
        $inicioPadrao
    );

    $fim = calendarDataFiltro(
        (string) ($_GET["end"] ?? ""),
        $fimPadrao
    );

    $administrador = Auth::isAdmin();
    $idUsuario = (int) (Auth::id() ?? 0);

    if ($idUsuario <= 0) {
        throw new RuntimeException(
            "Usuário autenticado não identificado."
        );
    }

    /*
     * O parâmetro end do FullCalendar é exclusivo. O serviço de
     * exportação usa datas finais inclusivas, portanto subtrai um dia.
     */
    $fimInclusivo = (new DateTimeImmutable($fim))
        ->modify("-1 day")
        ->format("Y-m-d");

    $registros = CalendarExport::listarEventos(
        $db,
        $idUsuario,
        $administrador,
        $inicio,
        $fimInclusivo
    );
    $eventos = [];

    foreach ($registros as $registro) {
        try {
            $periodo = calendarPeriodoEvento($registro);
        } catch (Throwable $erroPeriodo) {
            error_log(
                "Evento ignorado no calendário"
                . " | idEvento="
                . (int) ($registro["idEvento"] ?? 0)
                . " | erro="
                . $erroPeriodo->getMessage()
            );

            continue;
        }

        $cores = calendarCoresEvento(
            $registro,
            $administrador
        );

        $partesEndereco = array_filter(
            [
                trim((string) ($registro["endereco"] ?? "")),
                trim((string) ($registro["cidade"] ?? "")),
                trim((string) ($registro["estado"] ?? ""))
            ],
            static fn (string $valor): bool => $valor !== ""
        );

        $evento = [
            "id" => (string) $registro["idEvento"],
            "title" => (string) $registro["titulo"],
            "start" => $periodo["start"],
            "end" => $periodo["end"],
            "allDay" => $periodo["allDay"],
            "backgroundColor" => $cores["background"],
            "borderColor" => $cores["border"],
            "textColor" => $cores["text"],
            "extendedProps" => [
                "tipo" => (string) ($registro["tipo"] ?? "Evento"),
                "descricao" => trim(
                    (string) ($registro["descricao_curta"] ?? "")
                ),
                "local" => trim(
                    (string) ($registro["local"] ?? "")
                ),
                "endereco" => implode(" - ", $partesEndereco),
                "statusEvento" => (int) $registro["ativo"] === 1
                    ? "Ativo"
                    : "Inativo",
                "inscricaoAberta" => (int) $registro["inscricao_aberta"] === 1,
                "idInscricao" => isset($registro["idInscricao"])
                    ? (int) $registro["idInscricao"]
                    : null,
                "statusInscricao" => $registro["statusInscricao"] ?? null,
                "pagamento" => $registro["pagamento"] ?? null,
                "presenca" => isset($registro["presenca"])
                    ? (int) $registro["presenca"]
                    : null,
                "totalInscritos" => isset($registro["totalInscritos"])
                    ? (int) $registro["totalInscritos"]
                    : null,
                "editarUrl" => $administrador
                    ? BASE_URL
                        . "admin/event/evento.php?id="
                        . (int) $registro["idEvento"]
                    : null
            ]
        ];

        if ($evento["end"] === null) {
            unset($evento["end"]);
        }

        $eventos[] = $evento;
    }

    echo json_encode(
        $eventos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (Throwable $erro) {
    http_response_code(500);

    error_log(
        "Erro ao carregar calendário"
        . " | usuario="
        . (int) (Auth::id() ?? 0)
        . " | erro="
        . $erro->getMessage()
    );

    echo json_encode(
        [
            "error" => "Não foi possível carregar os eventos."
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
}
