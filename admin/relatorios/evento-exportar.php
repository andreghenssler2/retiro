<?php

declare(strict_types=1);

ob_start();

require_once __DIR__
    . "/../../config/settings.php";

require_once __DIR__
    . "/../../mod/auth/RelatorioEventoExportacao.php";

require_once __DIR__
    . "/../../mod/auth/RelatorioEventoXlsx.php";

Session::start();
Middleware::moderador();

$idEvento = max(
    0,
    (int) ($_GET["evento"] ?? 0)
);

$modo = trim(
    (string) ($_GET["modo"] ?? "lista_pdf")
);

$modos = [
    "lista_pdf",
    "fichas_pdf",
    "autorizacao_pdf",
    "xlsx",
    "saude_pdf"
];

if (!in_array($modo, $modos, true)) {
    $modo = "lista_pdf";
}

$service = new RelatorioEventoExportacao($db);

try {
    $evento = $service->evento($idEvento);
    $inscricoes = $service->inscricoes($idEvento);
} catch (Throwable $erro) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    http_response_code(422);

    echo htmlspecialchars(
        $erro->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    );

    exit;
}

$slug = static function (
    string $texto
): string {
    $normal = @iconv(
        "UTF-8",
        "ASCII//TRANSLIT//IGNORE",
        $texto
    );

    $normal = strtolower(
        $normal !== false
            ? $normal
            : $texto
    );

    $normal = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $normal
    ) ?? "evento";

    return trim($normal, '-')
        ?: "evento";
};

$nomeEvento = trim(
    (string) (
        $evento["titulo"]
        ?? "evento"
    )
);

$nomeBase = "evento-"
    . $slug($nomeEvento)
    . "-"
    . date("Ymd-His");

header("Cache-Control: private, no-store, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

if ($modo === "xlsx") {
    try {
        $conteudo = RelatorioEventoXlsx::gerar(
            $service->colunasXlsx(),
            $inscricoes,
            static function (
                string $campo,
                mixed $valor
            ) use ($service): string {
                return $service->formatar(
                    $campo,
                    $valor
                );
            }
        );
    } catch (Throwable $erro) {
        if (ob_get_length()) {
            ob_end_clean();
        }

        http_response_code(500);

        echo "Não foi possível gerar o XLSX: "
            . htmlspecialchars(
                $erro->getMessage(),
                ENT_QUOTES,
                "UTF-8"
            );

        exit;
    }

    if (ob_get_length()) {
        ob_end_clean();
    }

    header(
        "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    );

    header(
        'Content-Disposition: attachment; filename="'
        . $nomeBase
        . '.xlsx"'
    );

    header(
        "Content-Length: "
        . strlen($conteudo)
    );

    echo $conteudo;
    exit;
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    $autoload = dirname(__DIR__, 2)
        . "/lib/vendor/autoload.php";

    if (is_file($autoload)) {
        require_once $autoload;
    }
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    http_response_code(500);
    echo "Dompdf não está instalado em lib/vendor.";
    exit;
}

function reeEscapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function reeData(
    mixed $valor,
    bool $hora = false
): string {
    $texto = trim((string) $valor);

    if ($texto === "") {
        return "-";
    }

    $timestamp = strtotime($texto);

    if (!$timestamp) {
        return $texto;
    }

    return date(
        $hora
            ? "d/m/Y H:i"
            : "d/m/Y",
        $timestamp
    );
}

function reeMoeda(mixed $valor): string
{
    return "R$ "
        . number_format(
            (float) $valor,
            2,
            ",",
            "."
        );
}

function reeSimNao(mixed $valor): string
{
    return (int) $valor === 1
        ? "Sim"
        : "Não";
}

function reeClasseSituacao(string $situacao): string
{
    return match ($situacao) {
        "Confirmada" => "confirmada",
        "Inscrição não confirmada" =>
            "nao-confirmada",
        "Aguardando pagamento" =>
            "aguardando",
        "Cancelada" => "cancelada",
        default => "",
    };
}

function reePrazo(
    mixed $valor
): string {
    if ($valor instanceof DateTimeInterface) {
        return $valor->format(
            "d/m/Y H:i"
        );
    }

    return reeData($valor, true);
}

function reeEhMenorParaAutorizacao(
    array $registro,
    array $evento
): bool {
    $dataNascimento = substr(
        trim((string) ($registro["data_nascimento"] ?? "")),
        0,
        10
    );

    if ($dataNascimento !== "") {
        $nascimento = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dataNascimento
        );

        $dataInicio = substr(
            trim((string) ($evento["data_inicio"] ?? "")),
            0,
            10
        );

        $inicio = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dataInicio
        );

        if ($nascimento instanceof DateTimeImmutable && $inicio instanceof DateTimeImmutable && $nascimento <= $inicio) {
            return $nascimento->diff($inicio)->y < 18;
        }
    }

    return trim((string) ($registro["responsavel_nome"] ?? "")) !== ''
        || trim((string) ($registro["responsavel_autorizacao_texto"] ?? "")) !== '';
}

function reeDataAssinatura(
    mixed $valor
): string {
    $texto = trim((string) $valor);

    if ($texto === '') {
        return '____/____/______';
    }

    $timestamp = strtotime($texto);

    return $timestamp
        ? date('d/m/Y', $timestamp)
        : '____/____/______';
}

$perguntasSaude =
    $service->perguntasSaude($evento);

$inscricoesAutorizacao = array_values(
    array_filter(
        $inscricoes,
        static fn (array $registro): bool => reeEhMenorParaAutorizacao($registro, $evento)
    )
);

ob_start();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9pt;
            line-height: 1.35;
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        .cabecalho-relatorio {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 4mm;
            margin-bottom: 5mm;
        }

        .titulo {
            font-size: 18pt;
            font-weight: bold;
        }

        .subtitulo {
            color: #6b7280;
            margin-top: 1mm;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }

        .meta td {
            padding: 1mm 2mm 1mm 0;
            vertical-align: top;
        }

        .rotulo {
            color: #6b7280;
            font-size: 7pt;
            display: block;
        }

        .legenda {
            margin: 4mm 0;
            padding: 2.5mm;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .lista {
            width: 100%;
            border-collapse: collapse;
        }

        .lista th,
        .lista td {
            border: 1px solid #d1d5db;
            padding: 2mm;
            vertical-align: top;
        }

        .lista th {
            background: #e5e7eb;
            font-size: 8pt;
            text-align: left;
        }

        .lista tr.confirmada td {
            background: #ecfdf5;
        }

        .lista tr.nao-confirmada td {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .lista tr.aguardando td {
            background: #fef3c7;
        }

        .lista tr.cancelada td {
            background: #f3f4f6;
            color: #6b7280;
        }

        .situacao {
            font-weight: bold;
        }

        .ficha {
            page-break-after: always;
        }

        .ficha:last-child {
            page-break-after: auto;
        }

        .ficha-header {
            border-bottom: 2px solid #0d6efd;
            margin-bottom: 4mm;
            padding-bottom: 3mm;
        }

        .ficha-nome {
            font-size: 17pt;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            border-radius: 3mm;
            padding: 1mm 2.5mm;
            border: 1px solid #d1d5db;
            font-size: 7pt;
        }

        .badge.confirmada {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.nao-confirmada {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.aguardando {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.cancelada {
            background: #f3f4f6;
            color: #4b5563;
        }

        .grade {
            width: 100%;
            border-collapse: collapse;
        }

        .grade td {
            width: 50%;
            vertical-align: top;
            padding: 1.5mm 2mm;
            border-bottom: 1px solid #e5e7eb;
        }

        .secao {
            margin-bottom: 4mm;
            page-break-inside: avoid;
        }

        .secao-titulo {
            font-weight: bold;
            background: #eff6ff;
            border-left: 3px solid #0d6efd;
            padding: 1.5mm 2mm;
            margin-bottom: 1mm;
        }

        .saude-pessoa {
            page-break-inside: avoid;
            border: 1px solid #d1d5db;
            margin-bottom: 3mm;
        }

        .saude-nome {
            padding: 2mm;
            font-weight: bold;
            background: #f3f4f6;
        }

        .saude-conteudo {
            padding: 2mm;
        }

        .saude-item {
            margin-bottom: 2mm;
        }

        .saude-item:last-child {
            margin-bottom: 0;
        }

        .vazio {
            padding: 10mm;
            text-align: center;
            border: 1px solid #d1d5db;
            color: #6b7280;
        }

        .termo-pagina {
            page-break-after: always;
        }

        .termo-pagina:last-child {
            page-break-after: auto;
        }

        /* A4 vertical: 297mm - 20mm de margens = 277mm úteis.
           Dois termos de 131mm e linha de corte de 8mm ocupam 270mm. */
        .termo {
            border: 1px solid #d1d5db;
            padding: 6mm;
            height: 131mm;
            page-break-inside: avoid;
        }

        .termo-corte {
            height: 8mm;
            text-align: center;
            padding-top: 3mm;
            page-break-inside: avoid;
        }

        .termo-corte-linha {
            border-top: 1px dashed #6b7280;
            width: 100%;
            position: relative;
            top: 2mm;
        }

        .termo-corte-texto {
            display: inline-block;
            position: relative;
            top: -1mm;
            background: #ffffff;
            padding: 0 4mm;
            font-size: 7pt;
            color: #64748b;
            font-weight: bold;
        }

        .termo-titulo {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5mm;
            letter-spacing: 0.5px;
        }

        .termo-paragrafo {
            margin-bottom: 4mm;
            text-align: justify;
        }

        .termo-campo {
            font-weight: bold;
        }

        .termo-assinatura {
            margin-top: 16mm;
        }

        .termo-assinatura-linha {
            border-top: 1px solid #111827;
            width: 70mm;
            padding-top: 2mm;
            text-align: center;
            font-weight: bold;
        }

        .termo-data {
            margin-top: 5mm;
        }

        .termo-ajuda {
            margin-top: 4mm;
            font-size: 7.5pt;
            color: #6b7280;
        }

        .rodape {
            margin-top: 5mm;
            color: #6b7280;
            font-size: 7pt;
        }
    </style>
</head>
<body>

<?php if ($modo === "lista_pdf"): ?>

    <div class="cabecalho-relatorio">
        <div class="titulo">
            Lista de inscritos
        </div>

        <div class="subtitulo">
            <?= reeEscapar($nomeEvento); ?>
        </div>

        <table class="meta">
            <tr>
                <td>
                    <span class="rotulo">Evento</span>
                    <?= reeData(
                        $evento["data_inicio"]
                        ?? ""
                    ); ?>
                </td>

                <td>
                    <span class="rotulo">Local</span>
                    <?= reeEscapar(
                        $evento["local"]
                        ?? "-"
                    ); ?>
                </td>

                <td>
                    <span class="rotulo">Prazo de pagamento</span>
                    <?php
                    $primeiroPrazo =
                        $inscricoes[0][
                            "prazo_pagamento_relatorio"
                        ]
                        ?? null;
                    ?>
                    <?= reeEscapar(
                        reePrazo($primeiroPrazo)
                    ); ?>
                </td>

                <td>
                    <span class="rotulo">Total</span>
                    <?= count($inscricoes); ?>
                    inscrição(ões)
                </td>
            </tr>
        </table>
    </div>

    <div class="legenda">
        <strong>Legenda:</strong>
        fundo verde = confirmada ·
        fundo vermelho = inscrição não confirmada ·
        fundo amarelo = aguardando pagamento ·
        fundo cinza = cancelada.
    </div>

    <?php if ($inscricoes === []): ?>
        <div class="vazio">
            Nenhuma inscrição encontrada para este evento.
        </div>
    <?php else: ?>
        <table class="lista">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Comunidade/Paróquia</th>
                    <th>Telefone</th>
                    <th>Valor</th>
                    <th>Pagamento</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inscricoes as $registro): ?>
                <?php
                $situacao = (string) (
                    $registro["situacao_relatorio"]
                    ?? ""
                );
                ?>
                <tr class="<?= reeClasseSituacao(
                    $situacao
                ); ?>">
                    <td>
                        <?= (int) $registro[
                            "idInscricao"
                        ]; ?>
                    </td>
                    <td>
                        <?= reeEscapar(
                            $registro["nome"]
                            ?? "-"
                        ); ?>
                    </td>
                    <td>
                        <?= reeEscapar(
                            $registro["cpf"]
                            ?? "-"
                        ); ?>
                    </td>
                    <td>
                        <?= reeEscapar(
                            $registro["comunidade"]
                            ?? "-"
                        ); ?>
                    </td>
                    <td>
                        <?= reeEscapar(
                            $registro["telefone"]
                            ?? "-"
                        ); ?>
                    </td>
                    <td>
                        <?= reeMoeda(
                            $registro["valor_inscricao"]
                            ?? 0
                        ); ?>
                    </td>
                    <td>
                        <?= reeEscapar(
                            $registro["pagamento_status"]
                            ?? $registro[
                                "status_pagamento_inscricao"
                            ]
                            ?? "-"
                        ); ?>
                    </td>
                    <td class="situacao">
                        <?= reeEscapar($situacao); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php elseif ($modo === "fichas_pdf"): ?>

    <?php if ($inscricoes === []): ?>
        <div class="vazio">
            Nenhuma inscrição encontrada para este evento.
        </div>
    <?php endif; ?>

    <?php foreach ($inscricoes as $registro): ?>
        <?php
        $situacao = (string) (
            $registro["situacao_relatorio"]
            ?? ""
        );
        ?>
        <section class="ficha">
            <div class="ficha-header">
                <div class="ficha-nome">
                    <?= reeEscapar(
                        $registro["nome"]
                        ?? "Participante"
                    ); ?>
                </div>

                <div>
                    <?= reeEscapar($nomeEvento); ?>
                    · Inscrição #<?= (int) $registro[
                        "idInscricao"
                    ]; ?>
                </div>

                <div style="margin-top:2mm">
                    <span
                        class="badge <?= reeClasseSituacao(
                            $situacao
                        ); ?>"
                    >
                        <?= reeEscapar($situacao); ?>
                    </span>
                </div>
            </div>

            <div class="secao">
                <div class="secao-titulo">
                    Dados pessoais
                </div>
                <table class="grade">
                    <tr>
                        <td>
                            <span class="rotulo">CPF</span>
                            <?= reeEscapar(
                                $registro["cpf"]
                                ?? "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">RG</span>
                            <?= reeEscapar(
                                $registro["rg"]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">E-mail</span>
                            <?= reeEscapar(
                                $registro["email"]
                                ?? "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Telefone</span>
                            <?= reeEscapar(
                                $registro["telefone"]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Nascimento</span>
                            <?= reeData(
                                $registro["data_nascimento"]
                                ?? ""
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Gênero</span>
                            <?= reeEscapar(
                                $registro["genero"]
                                ?? $registro["sexo"]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="secao">
                <div class="secao-titulo">
                    Endereço e comunidade
                </div>
                <table class="grade">
                    <tr>
                        <td>
                            <span class="rotulo">Comunidade/Paróquia</span>
                            <?= reeEscapar(
                                $registro["comunidade"]
                                ?? "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">CEP</span>
                            <?= reeEscapar(
                                $registro["cep"]
                                ?? $registro["usuario_cep"]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Logradouro</span>
                            <?= reeEscapar(
                                trim(
                                    (string) (
                                        $registro["logradouro"]
                                        ?? ""
                                    )
                                    . " "
                                    . (string) (
                                        $registro["numero"]
                                        ?? ""
                                    )
                                ) ?: "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Bairro</span>
                            <?= reeEscapar(
                                $registro["bairro"]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Cidade</span>
                            <?= reeEscapar(
                                $registro["cidade"]
                                ?? $registro[
                                    "cidade_inscricao"
                                ]
                                ?? "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">UF</span>
                            <?= reeEscapar(
                                $registro["estado"]
                                ?? $registro[
                                    "estado_inscricao"
                                ]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="secao">
                <div class="secao-titulo">
                    Inscrição e pagamento
                </div>
                <table class="grade">
                    <tr>
                        <td>
                            <span class="rotulo">Status da inscrição</span>
                            <?= reeEscapar(
                                $registro["status_inscricao"]
                                ?? "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Situação do relatório</span>
                            <strong>
                                <?= reeEscapar($situacao); ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Valor</span>
                            <?= reeMoeda(
                                $registro["valor_inscricao"]
                                ?? 0
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Pagamento</span>
                            <?= reeEscapar(
                                $registro["pagamento_status"]
                                ?? $registro[
                                    "status_pagamento_inscricao"
                                ]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Forma</span>
                            <?= reeEscapar(
                                $registro["pagamento_forma"]
                                ?? $registro[
                                    "forma_pagamento_inscricao"
                                ]
                                ?? "-"
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Prazo</span>
                            <?= reeEscapar(
                                reePrazo(
                                    $registro[
                                        "prazo_pagamento_relatorio"
                                    ]
                                    ?? null
                                )
                            ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="rotulo">Pagamento realizado em</span>
                            <?= reeData(
                                $registro["dataPagamento"]
                                ?? "",
                                true
                            ); ?>
                        </td>
                        <td>
                            <span class="rotulo">Camiseta</span>
                            <?= reeEscapar(
                                $registro["camiseta"]
                                ?? "-"
                            ); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <?php if ($perguntasSaude !== []): ?>
                <div class="secao">
                    <div class="secao-titulo">
                        Saúde e Acessibilidade
                    </div>
                    <table class="grade">
                        <?php if (isset(
                            $perguntasSaude["medicacao"]
                        )): ?>
                            <tr>
                                <td>
                                    <span class="rotulo">
                                        Restrição a medicação
                                    </span>
                                    <?= reeSimNao(
                                        $registro[
                                            "restricao_medicacao"
                                        ]
                                        ?? 0
                                    ); ?>
                                </td>
                                <td>
                                    <span class="rotulo">
                                        Detalhes
                                    </span>
                                    <?= reeEscapar(
                                        $registro[
                                            "medicacao_detalhes"
                                        ]
                                        ?? "-"
                                    ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (isset(
                            $perguntasSaude["deficiencia"]
                        )): ?>
                            <tr>
                                <td>
                                    <span class="rotulo">
                                        Deficiência
                                    </span>
                                    <?= reeEscapar(
                                        $registro["deficiencia"]
                                        ?? "Não"
                                    ); ?>
                                </td>
                                <td>
                                    <span class="rotulo">
                                        Detalhes
                                    </span>
                                    <?= reeEscapar(
                                        $registro[
                                            "deficiencia_detalhes"
                                        ]
                                        ?? "-"
                                    ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (isset(
                            $perguntasSaude[
                                "acessibilidade"
                            ]
                        )): ?>
                            <tr>
                                <td>
                                    <span class="rotulo">
                                        Precisa de acessibilidade
                                    </span>
                                    <?= reeSimNao(
                                        $registro[
                                            "precisa_acessibilidade"
                                        ]
                                        ?? 0
                                    ); ?>
                                </td>
                                <td>
                                    <span class="rotulo">
                                        Recurso/detalhes
                                    </span>
                                    <?= reeEscapar(
                                        $registro[
                                            "acessibilidade_detalhes"
                                        ]
                                        ?? "-"
                                    ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (isset(
                            $perguntasSaude["alimentacao"]
                        )): ?>
                            <tr>
                                <td>
                                    <span class="rotulo">
                                        Restrição alimentar
                                    </span>
                                    <?= reeSimNao(
                                        $registro[
                                            "restricao_alimentar"
                                        ]
                                        ?? 0
                                    ); ?>
                                </td>
                                <td>
                                    <span class="rotulo">
                                        Detalhes
                                    </span>
                                    <?= reeEscapar(
                                        $registro[
                                            "alimentar_detalhes"
                                        ]
                                        ?? "-"
                                    ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>

            <div class="rodape">
                Exportado em <?= date("d/m/Y H:i"); ?>
                — Sistema de Eventos
            </div>
        </section>
    <?php endforeach; ?>

<?php elseif ($modo === "autorizacao_pdf"): ?>

    <?php if ($inscricoesAutorizacao === []): ?>
        <div class="vazio">
            Nenhuma inscrição de menor de 18 anos encontrada para este evento.
        </div>
    <?php else: ?>
        <?php
        $cidadeAutorizacao = trim((string) ($evento['cidade'] ?? ''));
        if ($cidadeAutorizacao === '') {
            $cidadeAutorizacao = trim((string) ($evento['local'] ?? '-'));
        }

        $dataInicioAutorizacao = reeData($evento['data_inicio'] ?? '');
        $dataFimFonte = trim((string) ($evento['data_fim'] ?? ''));
        if ($dataFimFonte === '' || str_starts_with($dataFimFonte, '0000-00-00')) {
            $dataFimFonte = (string) ($evento['data_inicio'] ?? '');
        }
        $dataFimAutorizacao = reeData($dataFimFonte);
        $mesmaDataAutorizacao = $dataInicioAutorizacao === $dataFimAutorizacao;

        foreach (array_chunk($inscricoesAutorizacao, 2) as $paginaTermos):
        ?>
            <div class="termo-pagina">
                <?php foreach ($paginaTermos as $indiceTermo => $registro): ?>
                    <?php
                    $responsavelNome = trim((string) ($registro['responsavel_nome'] ?? ''));
                    $participanteNome = trim((string) ($registro['nome'] ?? ''));
                    ?>
                    <div class="termo">
                        <div class="termo-titulo">AUTORIZAÇÃO</div>

                        <p class="termo-paragrafo">
                            Eu, <span class="termo-campo"><?= reeEscapar($responsavelNome !== '' ? $responsavelNome : '_______________________________________________'); ?></span>,
                        </p>

                        <p class="termo-paragrafo">
                            responsável pelo(a) jovem <span class="termo-campo"><?= reeEscapar($participanteNome !== '' ? $participanteNome : '_______________________________________________'); ?></span>,
                            <strong>AUTORIZO</strong> o(a) jovem <span class="termo-campo"><?= reeEscapar($participanteNome !== '' ? $participanteNome : '_______________________________________________'); ?></span>
                            a participar do(a) <span class="termo-campo"><?= reeEscapar($nomeEvento); ?></span>,
                            que acontecerá
                            <?= $mesmaDataAutorizacao ? 'no dia' : 'nos dias'; ?>
                            <span class="termo-campo"><?= reeEscapar($dataInicioAutorizacao); ?></span>
                            <?php if (!$mesmaDataAutorizacao): ?>
                                e <span class="termo-campo"><?= reeEscapar($dataFimAutorizacao); ?></span>
                            <?php endif; ?>
                            no(a) <span class="termo-campo"><?= reeEscapar($cidadeAutorizacao); ?></span>.
                        </p>

                        <p class="termo-paragrafo">
                            Declaro estar ciente e de acordo com sua participação no referido evento.
                        </p>

                        <div class="termo-assinatura">
                            <div class="termo-assinatura-linha">
                                Assinatura do(a) responsável
                            </div>
                        </div>

                        <div class="termo-data">
                            <strong>Data:</strong>
                            <?= reeEscapar(reeDataAssinatura($registro['responsavel_autorizacao_aceita_em'] ?? '')); ?>
                        </div>

                        <?php if (trim((string) ($registro['responsavel_telefone_1'] ?? '')) !== '' || trim((string) ($registro['responsavel_telefone_2'] ?? '')) !== ''): ?>
                            <div class="termo-ajuda">
                                Contato do responsável:
                                <?= reeEscapar(trim((string) ($registro['responsavel_telefone_1'] ?? ''))); ?>
                                <?php if (trim((string) ($registro['responsavel_telefone_2'] ?? '')) !== ''): ?>
                                    · <?= reeEscapar(trim((string) ($registro['responsavel_telefone_2'] ?? ''))); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($indiceTermo === 0 && count($paginaTermos) === 2): ?>
                        <div class="termo-corte" aria-hidden="true">
                            <div class="termo-corte-linha"></div>
                            <span class="termo-corte-texto">CORTE AQUI</span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php else: ?>

    <div class="cabecalho-relatorio">
        <div class="titulo">
            Saúde e Acessibilidade
        </div>

        <div class="subtitulo">
            <?= reeEscapar($nomeEvento); ?>
        </div>

        <div style="margin-top:2mm">
            <?= count($inscricoes); ?>
            participante(s) inscrito(s)
        </div>
    </div>

    <?php if ($perguntasSaude === []): ?>
        <div class="vazio">
            Este evento não possui perguntas de
            Saúde e Acessibilidade habilitadas.
        </div>
    <?php elseif ($inscricoes === []): ?>
        <div class="vazio">
            Nenhuma inscrição encontrada para este evento.
        </div>
    <?php else: ?>

        <div class="legenda">
            Este relatório apresenta somente as perguntas
            habilitadas na configuração de Saúde e
            Acessibilidade deste evento.
        </div>

        <?php foreach ($inscricoes as $registro): ?>
            <div class="saude-pessoa">
                <div class="saude-nome">
                    <?= reeEscapar(
                        $registro["nome"]
                        ?? "Participante"
                    ); ?>
                    —
                    <?= reeEscapar(
                        $registro["comunidade"]
                        ?? "-"
                    ); ?>
                </div>

                <div class="saude-conteudo">

                    <?php if (isset(
                        $perguntasSaude["medicacao"]
                    )): ?>
                        <div class="saude-item">
                            <strong>
                                Restrição a medicação:
                            </strong>
                            <?= reeSimNao(
                                $registro[
                                    "restricao_medicacao"
                                ]
                                ?? 0
                            ); ?>

                            <?php if (trim(
                                (string) (
                                    $registro[
                                        "medicacao_detalhes"
                                    ]
                                    ?? ""
                                )
                            ) !== ""): ?>
                                —
                                <?= reeEscapar(
                                    $registro[
                                        "medicacao_detalhes"
                                    ]
                                ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset(
                        $perguntasSaude["deficiencia"]
                    )): ?>
                        <div class="saude-item">
                            <strong>Deficiência:</strong>
                            <?= reeEscapar(
                                $registro["deficiencia"]
                                ?? "Não"
                            ); ?>

                            <?php if (trim(
                                (string) (
                                    $registro[
                                        "deficiencia_detalhes"
                                    ]
                                    ?? ""
                                )
                            ) !== ""): ?>
                                —
                                <?= reeEscapar(
                                    $registro[
                                        "deficiencia_detalhes"
                                    ]
                                ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset(
                        $perguntasSaude[
                            "acessibilidade"
                        ]
                    )): ?>
                        <div class="saude-item">
                            <strong>
                                Recurso de acessibilidade:
                            </strong>
                            <?= reeSimNao(
                                $registro[
                                    "precisa_acessibilidade"
                                ]
                                ?? 0
                            ); ?>

                            <?php if (trim(
                                (string) (
                                    $registro[
                                        "acessibilidade_detalhes"
                                    ]
                                    ?? ""
                                )
                            ) !== ""): ?>
                                —
                                <?= reeEscapar(
                                    $registro[
                                        "acessibilidade_detalhes"
                                    ]
                                ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset(
                        $perguntasSaude["alimentacao"]
                    )): ?>
                        <div class="saude-item">
                            <strong>
                                Restrição alimentar:
                            </strong>
                            <?= reeSimNao(
                                $registro[
                                    "restricao_alimentar"
                                ]
                                ?? 0
                            ); ?>

                            <?php if (trim(
                                (string) (
                                    $registro[
                                        "alimentar_detalhes"
                                    ]
                                    ?? ""
                                )
                            ) !== ""): ?>
                                —
                                <?= reeEscapar(
                                    $registro[
                                        "alimentar_detalhes"
                                    ]
                                ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

<?php endif; ?>

</body>
</html>
<?php

$html = (string) ob_get_clean();

$options = new \Dompdf\Options();
$options->set("isRemoteEnabled", false);
$options->set("isHtml5ParserEnabled", true);
$options->set("defaultFont", "DejaVu Sans");

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, "UTF-8");

$orientacao = $modo === "lista_pdf"
    ? "landscape"
    : "portrait";

$dompdf->setPaper(
    "A4",
    $orientacao
);

$dompdf->render();

if (ob_get_length()) {
    ob_end_clean();
}

$sufixo = match ($modo) {
    "lista_pdf" => "lista-inscritos",
    "fichas_pdf" => "fichas-inscritos",
    "autorizacao_pdf" => "autorizacao-presencial",
    "saude_pdf" => "saude-acessibilidade",
    default => "relatorio",
};

$dompdf->stream(
    $nomeBase
    . "-"
    . $sufixo
    . ".pdf",
    [
        "Attachment" => true
    ]
);
