#!/usr/bin/env bash
# Retiro - Agendador central do cron.
# Chamado UMA VEZ por minuto pelo cPanel.
set -u
umask 077
export TZ=America/Sao_Paulo

RAIZ="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)" || exit 1
PHP_BIN="${PHP_BIN:-/usr/local/bin/ea-php83}"
ESTADO="$RAIZ/storage/cron-unificado"

rotinas=(
    "processar-boletos-vencidos.php"
    "finalizar-credenciamentos.php"
    "sincronizar-notificacoes.php"
    "notificar-logs.php"
    "limpar-logs.php"
)

verificar() {
    local falha=0
    echo "Retiro - Verificacao do agendador"
    echo "Projeto: $RAIZ"
    echo "PHP CLI: $PHP_BIN"
    if [[ ! -x "$PHP_BIN" ]]; then
        echo "ERRO: o executavel PHP nao existe ou nao tem permissao: $PHP_BIN" >&2
        falha=1
    fi
    if ! command -v flock >/dev/null 2>&1; then
        echo "ERRO: o comando flock nao esta disponivel no servidor." >&2
        falha=1
    fi
    local arquivo
    for arquivo in "${rotinas[@]}"; do
        if [[ -f "$RAIZ/cron/$arquivo" ]]; then
            echo "OK: cron/$arquivo"
        else
            echo "ERRO: arquivo ausente cron/$arquivo" >&2
            falha=1
        fi
    done
    echo "Programacao: boletos, credenciamentos e notificacoes a cada minuto;"
    echo "             notificacao de logs a cada 5 min; limpeza entre 03:00 e 03:59 uma vez por dia."
    return "$falha"
}

case "${1:-}" in
    --verificar)
        verificar
        exit $?
        ;;
    "")
        ;;
    *)
        echo "Uso: bash cron/geral.sh [--verificar]" >&2
        exit 2
        ;;
esac

# Evita que duas execucoes simultaneas concorram pelo mesmo banco.
if [[ ! -x "$PHP_BIN" ]]; then
    echo "PHP CLI nao encontrado: $PHP_BIN" >&2
    exit 1
fi
if ! command -v flock >/dev/null 2>&1; then
    echo "Comando flock indisponivel. Cron interrompido por seguranca." >&2
    exit 1
fi
if ! mkdir -p "$ESTADO"; then
    echo "Nao foi possivel criar $ESTADO" >&2
    exit 1
fi

exec 9>"$ESTADO/geral.lock" || exit 1
if ! flock -n 9; then
    # O ciclo anterior ainda esta executando.
    exit 0
fi

HOJE="$(date +%Y-%m-%d)"
HORA="$(date +%H)"
MINUTO="$(date +%M)"
LOG="$ESTADO/$HOJE.log"
FALHOU=0

executar() {
    local nome="$1"
    local arquivo="$2"
    local inicio retorno

    inicio="$(date '+%Y-%m-%d %H:%M:%S')"
    if [[ ! -f "$RAIZ/cron/$arquivo" ]]; then
        echo "[$inicio] ERRO $nome: arquivo nao encontrado cron/$arquivo" >> "$LOG"
        FALHOU=1
        return 1
    fi

    echo "[$inicio] INICIO $nome" >> "$LOG"
    "$PHP_BIN" -q "$RAIZ/cron/$arquivo" >> "$LOG" 2>&1
    retorno=$?

    if [[ "$retorno" -eq 0 ]]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] OK $nome" >> "$LOG"
        return 0
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERRO $nome: codigo $retorno" >> "$LOG"
    FALHOU=1
    return 1
}

# Rotinas prioritarias, iguais ou mais frequentes que as atuais.
executar "Boletos vencidos" "processar-boletos-vencidos.php" || :
executar "Credenciamentos" "finalizar-credenciamentos.php" || :
executar "Notificacoes do portal" "sincronizar-notificacoes.php" || :

# Os emails de logs nao precisam ser verificados a cada minuto.
if (( 10#$MINUTO % 5 == 0 )); then
    executar "Alertas de logs" "notificar-logs.php" || :
fi

# Limpeza de logs somente uma vez por dia, com nova tentativa
# se uma execucao falhar durante a faixa das 03 horas.
MARCADOR="$ESTADO/limpeza-$HOJE.ok"
if [[ "$HORA" == "03" && ! -f "$MARCADOR" ]]; then
    if executar "Limpeza de logs" "limpar-logs.php"; then
        touch "$MARCADOR"
        # Rotacao dos logs gerados apenas por este agendador.
        find "$ESTADO" -maxdepth 1 -type f \
            \( -name '*.log' -o -name 'limpeza-*.ok' \) \
            -mtime +30 -delete 2>> "$LOG" || :
    fi
fi

exit "$FALHOU"
