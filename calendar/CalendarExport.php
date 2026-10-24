<?php

declare(strict_types=1);

final class CalendarExport
{
    private const TIMEZONE = "America/Sao_Paulo";

    public static function colunaTokenDisponivel(PDO $db): bool
    {
        $stmt = $db->query(
            "SHOW COLUMNS FROM usuarios LIKE 'calendar_token'"
        );

        return $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public static function garantirToken(PDO $db, int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException("Usuário inválido.");
        }

        if (!self::colunaTokenDisponivel($db)) {
            throw new RuntimeException(
                "A migração calendar_token ainda não foi executada."
            );
        }

        $stmt = $db->prepare(
            "SELECT calendar_token
             FROM usuarios
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([":id" => $idUsuario]);

        $token = strtolower(trim((string) $stmt->fetchColumn()));

        if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
            return $token;
        }

        $novoToken = bin2hex(random_bytes(32));

        $atualizar = $db->prepare(
            "UPDATE usuarios
             SET calendar_token = :token
             WHERE id = :id
               AND (
                    calendar_token IS NULL
                    OR calendar_token = ''
                    OR CHAR_LENGTH(calendar_token) <> 64
               )
             LIMIT 1"
        );
        $atualizar->execute([
            ":token" => $novoToken,
            ":id" => $idUsuario
        ]);

        $consultar = $db->prepare(
            "SELECT calendar_token
             FROM usuarios
             WHERE id = :id
             LIMIT 1"
        );
        $consultar->execute([":id" => $idUsuario]);

        $tokenFinal = strtolower(
            trim((string) $consultar->fetchColumn())
        );

        if (preg_match('/^[a-f0-9]{64}$/', $tokenFinal) !== 1) {
            throw new RuntimeException(
                "Não foi possível gerar o token do calendário."
            );
        }

        return $tokenFinal;
    }

    public static function regenerarToken(PDO $db, int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException("Usuário inválido.");
        }

        if (!self::colunaTokenDisponivel($db)) {
            throw new RuntimeException(
                "A migração calendar_token ainda não foi executada."
            );
        }

        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $token = bin2hex(random_bytes(32));

            try {
                $stmt = $db->prepare(
                    "UPDATE usuarios
                     SET calendar_token = :token
                     WHERE id = :id
                     LIMIT 1"
                );
                $stmt->execute([
                    ":token" => $token,
                    ":id" => $idUsuario
                ]);

                if ($stmt->rowCount() > 0) {
                    return $token;
                }

                $verificar = $db->prepare(
                    "SELECT id
                     FROM usuarios
                     WHERE id = :id
                     LIMIT 1"
                );
                $verificar->execute([":id" => $idUsuario]);

                if ($verificar->fetchColumn() === false) {
                    throw new RuntimeException("Usuário não encontrado.");
                }

                return $token;
            } catch (PDOException $erro) {
                if ($tentativa === 2) {
                    throw $erro;
                }
            }
        }

        throw new RuntimeException(
            "Não foi possível gerar o token do calendário."
        );
    }

    public static function buscarUsuarioPorToken(
        PDO $db,
        string $token
    ): array|false {
        $token = strtolower(trim($token));

        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return false;
        }

        if (!self::colunaTokenDisponivel($db)) {
            return false;
        }

        $stmt = $db->prepare(
            "SELECT
                id,
                nome,
                email,
                tipo,
                ativo
             FROM usuarios
             WHERE calendar_token = :token
               AND ativo = 1
             LIMIT 1"
        );
        $stmt->execute([":token" => $token]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{inicio:string,fim:string,rotulo:string}
     */
    public static function resolverPeriodo(
        string $periodo,
        string $inicioPersonalizado = "",
        string $fimPersonalizado = ""
    ): array {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $hoje = new DateTimeImmutable("today", $timezone);

        return match ($periodo) {
            "semana" => [
                "inicio" => $hoje
                    ->modify("monday this week")
                    ->format("Y-m-d"),
                "fim" => $hoje
                    ->modify("sunday this week")
                    ->format("Y-m-d"),
                "rotulo" => "Esta semana"
            ],
            "personalizado" => self::periodoPersonalizado(
                $inicioPersonalizado,
                $fimPersonalizado
            ),
            default => [
                "inicio" => $hoje
                    ->modify("-30 days")
                    ->format("Y-m-d"),
                "fim" => $hoje
                    ->modify("+60 days")
                    ->format("Y-m-d"),
                "rotulo" => "Recentes e próximos 60 dias"
            ]
        };
    }

    /**
     * @return array{inicio:string,fim:string,rotulo:string}
     */
    private static function periodoPersonalizado(
        string $inicio,
        string $fim
    ): array {
        $inicio = trim($inicio);
        $fim = trim($fim);

        if (!self::dataValida($inicio) || !self::dataValida($fim)) {
            throw new InvalidArgumentException(
                "Informe as datas inicial e final do intervalo personalizado."
            );
        }

        if ($inicio > $fim) {
            throw new InvalidArgumentException(
                "A data inicial não pode ser posterior à data final."
            );
        }

        return [
            "inicio" => $inicio,
            "fim" => $fim,
            "rotulo" => "Intervalo personalizado"
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listarEventos(
        PDO $db,
        int $idUsuario,
        bool $administrador,
        string $inicio = "",
        string $fim = ""
    ): array {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException("Usuário inválido.");
        }

        $where = [];
        $params = [];

        if ($inicio !== "") {
            if (!self::dataValida($inicio)) {
                throw new InvalidArgumentException("Data inicial inválida.");
            }

            $where[] = "COALESCE(
                NULLIF(e.data_fim, '0000-00-00'),
                e.data_inicio
            ) >= :data_inicio";
            $params[":data_inicio"] = $inicio;
        }

        if ($fim !== "") {
            if (!self::dataValida($fim)) {
                throw new InvalidArgumentException("Data final inválida.");
            }

            $where[] = "e.data_inicio <= :data_fim";
            $params[":data_fim"] = $fim;
        }

        $sqlWhere = $where !== []
            ? " AND " . implode(" AND ", $where)
            : "";

        if ($administrador) {
            $sql = "
                SELECT
                    e.idEvento,
                    e.titulo,
                    e.slug,
                    e.descricao_curta,
                    e.tipo,
                    e.data_inicio,
                    e.data_fim,
                    e.hora_inicio,
                    e.hora_fim,
                    e.local,
                    e.endereco,
                    e.cidade,
                    e.estado,
                    e.ativo,
                    e.inscricao_aberta,
                    NULL AS idInscricao,
                    NULL AS statusInscricao,
                    NULL AS pagamento,
                    NULL AS presenca,
                    (
                        SELECT COUNT(*)
                        FROM inscricoes inscricao_contagem
                        WHERE inscricao_contagem.idEvento = e.idEvento
                          AND inscricao_contagem.status <> 'Cancelada'
                          AND inscricao_contagem.pagamento NOT IN (
                              'Cancelado',
                              'Estornado'
                          )
                    ) AS totalInscritos
                FROM eventos e
                WHERE 1 = 1
                {$sqlWhere}
                ORDER BY
                    e.data_inicio ASC,
                    e.hora_inicio ASC,
                    e.titulo ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql = "
            SELECT
                e.idEvento,
                e.titulo,
                e.slug,
                e.descricao_curta,
                e.tipo,
                e.data_inicio,
                e.data_fim,
                e.hora_inicio,
                e.hora_fim,
                e.local,
                e.endereco,
                e.cidade,
                e.estado,
                e.ativo,
                e.inscricao_aberta,
                i.idInscricao,
                i.status AS statusInscricao,
                i.pagamento,
                i.presenca,
                NULL AS totalInscritos
            FROM eventos e
            INNER JOIN inscricoes i
                ON i.idEvento = e.idEvento
               AND i.idUsuario = :id_usuario
            WHERE i.idInscricao = (
                SELECT MAX(i2.idInscricao)
                FROM inscricoes i2
                WHERE i2.idEvento = e.idEvento
                  AND i2.idUsuario = :id_usuario_ultima
            )
            {$sqlWhere}
            ORDER BY
                e.data_inicio ASC,
                e.hora_inicio ASC,
                e.titulo ASC
        ";

        $params[":id_usuario"] = $idUsuario;
        $params[":id_usuario_ultima"] = $idUsuario;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int,array<string,mixed>> $eventos
     */
    public static function gerarIcs(
        array $eventos,
        string $nomeCalendario,
        int $idUsuario
    ): string {
        $nomeCalendario = trim($nomeCalendario);

        if ($nomeCalendario === "") {
            $nomeCalendario = "Calendário de eventos";
        }

        $agoraUtc = new DateTimeImmutable(
            "now",
            new DateTimeZone("UTC")
        );

        $linhas = [
            "BEGIN:VCALENDAR",
            "PRODID:-//Retiro IECLB//Calendario de Eventos//PT-BR",
            "VERSION:2.0",
            "CALSCALE:GREGORIAN",
            "METHOD:PUBLISH",
            "X-WR-CALNAME:" . self::escaparIcs($nomeCalendario),
            "X-WR-TIMEZONE:" . self::TIMEZONE,
            "REFRESH-INTERVAL;VALUE=DURATION:PT1H",
            "X-PUBLISHED-TTL:PT1H"
        ];

        $host = parse_url(
            defined("BASE_URL") ? (string) BASE_URL : "",
            PHP_URL_HOST
        );
        $host = is_string($host) && $host !== ""
            ? $host
            : "retiro.local";

        foreach ($eventos as $evento) {
            try {
                $datas = self::datasEventoIcs($evento);
            } catch (Throwable $erro) {
                error_log(
                    "Evento ignorado na exportação ICS"
                    . " | idEvento="
                    . (int) ($evento["idEvento"] ?? 0)
                    . " | erro="
                    . $erro->getMessage()
                );

                continue;
            }

            $idEvento = (int) ($evento["idEvento"] ?? 0);
            $titulo = trim((string) ($evento["titulo"] ?? "Evento"));
            $statusInscricao = trim(
                (string) ($evento["statusInscricao"] ?? "")
            );

            if (
                $statusInscricao !== ""
                && strcasecmp($statusInscricao, "Cancelada") === 0
            ) {
                $titulo = "[Inscrição cancelada] " . $titulo;
            } elseif ((int) ($evento["ativo"] ?? 1) !== 1) {
                $titulo = "[Inativo] " . $titulo;
            }

            $descricao = self::descricaoEvento($evento);
            $local = self::localEvento($evento);

            $linhas[] = "BEGIN:VEVENT";
            $linhas[] = "UID:evento-"
                . $idEvento
                . "-usuario-"
                . $idUsuario
                . "@"
                . $host;
            $linhas[] = "DTSTAMP:"
                . $agoraUtc->format("Ymd\\THis\\Z");

            foreach ($datas as $linhaData) {
                $linhas[] = $linhaData;
            }

            $linhas[] = "SUMMARY:" . self::escaparIcs($titulo);

            if ($descricao !== "") {
                $linhas[] = "DESCRIPTION:"
                    . self::escaparIcs($descricao);
            }

            if ($local !== "") {
                $linhas[] = "LOCATION:" . self::escaparIcs($local);
            }

            $tipo = trim((string) ($evento["tipo"] ?? ""));

            if ($tipo !== "") {
                $linhas[] = "CATEGORIES:" . self::escaparIcs($tipo);
            }

            $linhas[] = "STATUS:CONFIRMED";
            $linhas[] = "TRANSP:OPAQUE";
            $linhas[] = "END:VEVENT";
        }

        $linhas[] = "END:VCALENDAR";

        $linhasDobradas = array_map(
            [self::class, "dobrarLinha"],
            $linhas
        );

        return implode("\r\n", $linhasDobradas) . "\r\n";
    }

    /**
     * @return array<int,string>
     */
    private static function datasEventoIcs(array $evento): array
    {
        $dataInicio = self::normalizarDataEvento(
            (string) ($evento["data_inicio"] ?? "")
        );
        $dataFim = self::normalizarDataEvento(
            (string) ($evento["data_fim"] ?? "")
        );
        $horaInicio = self::normalizarHora(
            (string) ($evento["hora_inicio"] ?? "")
        );
        $horaFim = self::normalizarHora(
            (string) ($evento["hora_fim"] ?? "")
        );

        if ($dataInicio === "") {
            throw new RuntimeException("Evento sem data inicial.");
        }

        if ($horaInicio === "") {
            $fimInclusivo = $dataFim !== ""
                ? $dataFim
                : $dataInicio;
            $fimExclusivo = (new DateTimeImmutable($fimInclusivo))
                ->modify("+1 day")
                ->format("Ymd");

            return [
                "DTSTART;VALUE=DATE:"
                    . str_replace("-", "", $dataInicio),
                "DTEND;VALUE=DATE:" . $fimExclusivo
            ];
        }

        $timezoneLocal = new DateTimeZone(self::TIMEZONE);
        $timezoneUtc = new DateTimeZone("UTC");
        $inicio = new DateTimeImmutable(
            $dataInicio . " " . $horaInicio,
            $timezoneLocal
        );

        $linhas = [
            "DTSTART:"
                . $inicio
                    ->setTimezone($timezoneUtc)
                    ->format("Ymd\\THis\\Z")
        ];

        $fim = null;

        if ($horaFim !== "") {
            $dataFinal = $dataFim !== ""
                ? $dataFim
                : $dataInicio;
            $fim = new DateTimeImmutable(
                $dataFinal . " " . $horaFim,
                $timezoneLocal
            );

            if ($fim <= $inicio && $dataFinal === $dataInicio) {
                $fim = $fim->modify("+1 day");
            }
        } elseif ($dataFim !== "" && $dataFim !== $dataInicio) {
            $fim = new DateTimeImmutable(
                $dataFim . " 23:59:59",
                $timezoneLocal
            );
        }

        if ($fim instanceof DateTimeImmutable) {
            $linhas[] = "DTEND:"
                . $fim
                    ->setTimezone($timezoneUtc)
                    ->format("Ymd\\THis\\Z");
        }

        return $linhas;
    }

    private static function descricaoEvento(array $evento): string
    {
        $partes = [];
        $descricao = trim(
            (string) ($evento["descricao_curta"] ?? "")
        );

        if ($descricao !== "") {
            $partes[] = $descricao;
        }

        $tipo = trim((string) ($evento["tipo"] ?? ""));

        if ($tipo !== "") {
            $partes[] = "Tipo: " . $tipo;
        }

        $partes[] = "Status do evento: "
            . ((int) ($evento["ativo"] ?? 0) === 1
                ? "Ativo"
                : "Inativo");

        $statusInscricao = trim(
            (string) ($evento["statusInscricao"] ?? "")
        );

        if ($statusInscricao !== "") {
            $partes[] = "Inscrição: " . $statusInscricao;
        }

        $pagamento = trim(
            (string) ($evento["pagamento"] ?? "")
        );

        if ($pagamento !== "") {
            $partes[] = "Pagamento: " . $pagamento;
        }

        if (array_key_exists("presenca", $evento)) {
            $partes[] = "Presença: "
                . ((int) ($evento["presenca"] ?? 0) === 1
                    ? "Registrada"
                    : "Não registrada");
        }

        return implode("\n", $partes);
    }

    private static function localEvento(array $evento): string
    {
        $partes = array_filter(
            [
                trim((string) ($evento["local"] ?? "")),
                trim((string) ($evento["endereco"] ?? "")),
                trim((string) ($evento["cidade"] ?? "")),
                trim((string) ($evento["estado"] ?? ""))
            ],
            static fn (string $valor): bool => $valor !== ""
        );

        return implode(" - ", $partes);
    }

    private static function escaparIcs(string $valor): string
    {
        $valor = str_replace("\\", "\\\\", $valor);
        $valor = str_replace(
            ["\r\n", "\r", "\n"],
            "\\n",
            $valor
        );
        $valor = str_replace(
            [",", ";"],
            ["\\,", "\\;"],
            $valor
        );

        return $valor;
    }

    private static function dobrarLinha(string $linha): string
    {
        if (strlen($linha) <= 73) {
            return $linha;
        }

        $partes = [];
        $restante = $linha;
        $primeira = true;

        while ($restante !== "") {
            $limite = $primeira ? 73 : 72;

            if (function_exists("mb_strcut")) {
                $parte = mb_strcut(
                    $restante,
                    0,
                    $limite,
                    "UTF-8"
                );
            } else {
                $parte = substr($restante, 0, $limite);
            }

            if ($parte === "") {
                break;
            }

            $partes[] = $primeira
                ? $parte
                : " " . $parte;
            $restante = substr($restante, strlen($parte));
            $primeira = false;
        }

        return implode("\r\n", $partes);
    }

    private static function normalizarDataEvento(string $data): string
    {
        $data = trim($data);

        if (
            $data === ""
            || $data === "0000-00-00"
            || !self::dataValida($data)
        ) {
            return "";
        }

        return $data;
    }

    private static function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        if ($hora === "" || $hora === "00:00:00") {
            return "";
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora) !== 1) {
            return "";
        }

        return strlen($hora) === 5
            ? $hora . ":00"
            : substr($hora, 0, 8);
    }

    private static function dataValida(string $data): bool
    {
        $objeto = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $data
        );

        return $objeto instanceof DateTimeImmutable
            && $objeto->format("Y-m-d") === $data;
    }
}
