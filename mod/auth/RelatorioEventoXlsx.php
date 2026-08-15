<?php

declare(strict_types=1);

/**
 * Gerador XLSX simples para relatório de evento.
 * Não depende de PhpSpreadsheet nem ZipArchive.
 */
final class RelatorioEventoXlsx
{
    public static function gerar(
        array $colunas,
        array $registros,
        callable $formatador
    ): string {
        $arquivos = [
            "[Content_Types].xml" =>
                self::contentTypes(),
            "_rels/.rels" =>
                self::rels(),
            "docProps/app.xml" =>
                self::app(),
            "docProps/core.xml" =>
                self::core(),
            "xl/workbook.xml" =>
                self::workbook(),
            "xl/_rels/workbook.xml.rels" =>
                self::workbookRels(),
            "xl/styles.xml" =>
                self::styles(),
            "xl/worksheets/sheet1.xml" =>
                self::sheet(
                    $colunas,
                    $registros,
                    $formatador
                ),
        ];

        return self::criarZip($arquivos);
    }

    private static function sheet(
        array $colunas,
        array $registros,
        callable $formatador
    ): string {
        $numeroColunas = count($colunas);
        $ultimaColuna = self::letraColuna(
            max(1, $numeroColunas)
        );

        $linhas = [];
        $numeroLinha = 1;

        $cabecalho = [];
        $indice = 1;

        foreach ($colunas as $rotulo) {
            $cabecalho[] = self::celulaTexto(
                self::letraColuna($indice)
                . $numeroLinha,
                (string) $rotulo,
                1
            );

            $indice++;
        }

        $linhas[] =
            '<row r="1" ht="28" customHeight="1">'
            . implode("", $cabecalho)
            . '</row>';

        foreach ($registros as $registro) {
            $numeroLinha++;
            $celulas = [];
            $indice = 1;

            $situacao = (string) (
                $registro["situacao_relatorio"]
                ?? ""
            );

            $estilo = match ($situacao) {
                "Confirmada" => 3,
                "Inscrição não confirmada" => 4,
                "Aguardando pagamento" => 5,
                "Cancelada" => 6,
                default => 2,
            };

            foreach ($colunas as $campo => $rotulo) {
                $valor = $formatador(
                    (string) $campo,
                    $registro[$campo] ?? null
                );

                $celulas[] = self::celulaTexto(
                    self::letraColuna($indice)
                    . $numeroLinha,
                    (string) $valor,
                    $estilo
                );

                $indice++;
            }

            $linhas[] =
                '<row r="'
                . $numeroLinha
                . '">'
                . implode("", $celulas)
                . '</row>';
        }

        $ultimaLinha = max(
            1,
            $numeroLinha
        );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:'
            . $ultimaColuna
            . $ultimaLinha
            . '"/>'
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="'
            . max(1, $numeroColunas)
            . '" width="22" customWidth="1"/></cols>'
            . '<sheetData>'
            . implode("", $linhas)
            . '</sheetData>'
            . '<autoFilter ref="A1:'
            . $ultimaColuna
            . $ultimaLinha
            . '"/>'
            . '</worksheet>';
    }

    private static function celulaTexto(
        string $ref,
        string $valor,
        int $style
    ): string {
        $valor = self::limparTexto($valor);

        return '<c r="'
            . self::xml($ref)
            . '" t="inlineStr" s="'
            . $style
            . '"><is><t xml:space="preserve">'
            . self::xml($valor)
            . '</t></is></c>';
    }

    private static function limparTexto(
        string $valor
    ): string {
        $valor = preg_replace(
            '/[^\P{C}\t\r\n]/u',
            '',
            $valor
        ) ?? $valor;

        if (function_exists('mb_substr')) {
            return mb_substr(
                $valor,
                0,
                32767,
                'UTF-8'
            );
        }

        return substr(
            $valor,
            0,
            32767
        );
    }

    private static function letraColuna(
        int $numero
    ): string {
        $resultado = '';

        while ($numero > 0) {
            $numero--;

            $resultado = chr(
                65 + ($numero % 26)
            ) . $resultado;

            $numero = intdiv(
                $numero,
                26
            );
        }

        return $resultado;
    }

    private static function xml(
        string $valor
    ): string {
        return htmlspecialchars(
            $valor,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
    }

    private static function criarZip(
        array $arquivos
    ): string {
        $dadosLocais = '';
        $diretorioCentral = '';
        $offset = 0;
        $quantidade = 0;

        [$horaDos, $dataDos] =
            self::dataHoraDos();

        foreach ($arquivos as $nome => $conteudo) {
            $nome = str_replace(
                '\\',
                '/',
                (string) $nome
            );

            $conteudo = (string) $conteudo;

            $crc = (int) sprintf(
                '%u',
                crc32($conteudo)
            );

            $tamanho = strlen($conteudo);
            $tamanhoNome = strlen($nome);

            $cabecalhoLocal = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                $horaDos,
                $dataDos,
                $crc,
                $tamanho,
                $tamanho,
                $tamanhoNome,
                0
            );

            $blocoLocal =
                $cabecalhoLocal
                . $nome
                . $conteudo;

            $dadosLocais .= $blocoLocal;

            $diretorioCentral .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $horaDos,
                $dataDos,
                $crc,
                $tamanho,
                $tamanho,
                $tamanhoNome,
                0,
                0,
                0,
                0,
                0,
                $offset
            );

            $diretorioCentral .= $nome;
            $offset += strlen($blocoLocal);
            $quantidade++;
        }

        $tamanhoCentral = strlen(
            $diretorioCentral
        );

        $fim = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $quantidade,
            $quantidade,
            $tamanhoCentral,
            $offset,
            0
        );

        return $dadosLocais
            . $diretorioCentral
            . $fim;
    }

    private static function dataHoraDos(): array
    {
        $ano = max(
            1980,
            (int) date('Y')
        );

        $mes = (int) date('n');
        $dia = (int) date('j');
        $hora = (int) date('G');
        $minuto = (int) date('i');
        $segundo = (int) date('s');

        $horaDos =
            ($hora << 11)
            | ($minuto << 5)
            | intdiv($segundo, 2);

        $dataDos =
            (($ano - 1980) << 9)
            | ($mes << 5)
            | $dia;

        return [$horaDos, $dataDos];
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Inscrições" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="6">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="5" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function core(): string
    {
        $agora = gmdate(
            'Y-m-d\\TH:i:s\\Z'
        );

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<cp:coreProperties '
            . 'xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Relatório de evento</dc:title>'
            . '<dc:creator>Sistema de Eventos</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">'
            . $agora
            . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    private static function app(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Sistema de Eventos</Application>'
            . '</Properties>';
    }
}
