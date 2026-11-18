<?php
declare(strict_types=1);

/** PDF presencial alternativo. Dados nunca saem do contexto do relatório autenticado. */
final class AutorizacaoCompleta2Pdf
{
    private static function e(mixed $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function campo(mixed $valor, string $traco = '________________________'): string
    {
        $texto = trim((string) $valor);
        return self::e($texto !== '' ? $texto : $traco);
    }

    private static function data(mixed $valor): string
    {
        $str = substr(trim((string) $valor), 0, 10);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $str, new DateTimeZone('America/Sao_Paulo'));
        return $dt && $dt->format('Y-m-d') === $str ? $dt->format('d/m/Y') : '__/__/____';
    }

    public static function cpf(mixed $valor): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor) ?? '';
        if (strlen($digitos) === 11) {
            return substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.' . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2);
        }
        return '________________';
    }

    /** Data a preencher no ato da assinatura; usa mês e ano do evento. */
    public static function dataAssinatura(array $evento): string
    {
        $str = substr(trim((string) ($evento['data_inicio'] ?? '')), 0, 10);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $str, new DateTimeZone('America/Sao_Paulo'));
        if (!$dt || $dt->format('Y-m-d') !== $str) {
            return '____ de ______________ de ______';
        }
        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return '____ de ' . $meses[(int) $dt->format('n')] . ' de ' . $dt->format('Y');
    }

    public static function html(array $evento, array $inscricoes): string
    {
        $titulo = self::campo($evento['titulo'] ?? null);
        $local = self::campo($evento['local'] ?? null);
        $cidade = self::campo($evento['cidade'] ?? null);
        $uf = self::campo($evento['estado'] ?? null, '__');
        $inicio = self::data($evento['data_inicio'] ?? null);
        $fim = self::data($evento['data_fim'] ?? $evento['data_inicio'] ?? null);
        if ($fim === '__/__/____') { $fim = $inicio; }
        $periodo = $inicio === $fim ? 'no dia <b>' . self::e($inicio) . '</b>' : 'nos dias <b>' . self::e($inicio) . '</b> e <b>' . self::e($fim) . '</b>';
        $dataAssinatura = self::e(self::dataAssinatura($evento));
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><style>' . <<<'CSS'
@page { size: A4 portrait; margin: 10mm; }
* { box-sizing: content-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.32; color: #111827; margin: 0; }
.folha { page-break-after: always; }
.folha:last-child { page-break-after: auto; }
.termo2 { height: 118mm; padding: 5mm; border: 1px solid #a1a1aa; page-break-inside: avoid; }
.titulo { font-size: 13pt; font-weight: bold; text-align: center; margin: 1mm 0 5mm; letter-spacing: .4px; }
.paragrafo { margin: 0 0 4mm; text-align: justify; }
.campo { font-weight: bold; }
.assinatura { margin-top: 10mm; border-top: 1px solid #111827; width: 90mm; padding-top: 1.5mm; text-align: center; font-size: 8pt; }
.data { margin-top: 5mm; font-weight: 500; }
.corte { height: 8mm; text-align: center; font-size: 7pt; color: #475569; page-break-inside: avoid; }
.corte-linha { border-top: 1px dashed #6b7280; width: 100%; position: relative; top: 3mm; }
.corte span { background: white; padding: 0 3mm; position: relative; top: 1.5mm; }
CSS;
        $html .= '</style></head><body>';
        if ($inscricoes === []) {
            return $html . '<div>Nenhuma inscrição de menor de 18 anos encontrada para este evento.</div></body></html>';
        }
        foreach (array_chunk($inscricoes, 2) as $pagina) {
            $html .= '<div class="folha">';
            foreach ($pagina as $indice => $r) {
                $responsavel = self::campo($r['responsavel_nome'] ?? null);
                $cpfResponsavel = self::e(self::cpf($r['responsavel_cpf'] ?? null));
                $filho = self::campo($r['nome'] ?? null);
                $cpfFilho = self::e(self::cpf($r['cpf'] ?? null));
                $html .= '<div class="termo2"><div class="titulo">AUTORIZAÇÃO</div>';
                $html .= '<p class="paragrafo">Eu, <span class="campo">' . $responsavel . '</span>, número de CPF <span class="campo">' . $cpfResponsavel . '</span>, autorizo meu/minha filho/a <span class="campo">' . $filho . '</span>, número do CPF <span class="campo">' . $cpfFilho . '</span>, a participar do <span class="campo">' . $titulo . '</span>, bem como das atividades, brincadeiras e banho de piscina, que acontecerá ' . $periodo . ', no <span class="campo">' . $local . '</span> em <span class="campo">' . $cidade . '</span>, <span class="campo">' . $uf . '</span>. Além de autorizar a participação, também afirmo ter orientado meu/minha filho/a das responsabilidades e cuidados com o local e consigo mesmo. Também orientei que qualquer coisa, ele/a fale imediatamente com o pastor.</p>';
                $html .= '<p class="paragrafo">O <span class="campo">' . $local . '</span> terá rede de wi-fi à disposição e nos intervalos das atividades o/as participantes poderão se comunicar com os familiares.</p>';
                $html .= '<div class="data">' . $dataAssinatura . '</div>';
                $html .= '<div class="assinatura">Assinatura do pai, mãe ou responsável legal</div>';
                $html .= '</div>';
                if ($indice === 0 && count($pagina) === 2) {
                    $html .= '<div class="corte"><div class="corte-linha"></div><span>CORTE AQUI</span></div>';
                }
            }
            $html .= '</div>';
        }
        return $html . '</body></html>';
    }
}
