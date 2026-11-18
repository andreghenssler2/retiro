<?php
declare(strict_types=1);
/** Complementar CPF do responsável em inscrições anteriores, sem alterar consentimento. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$options = getopt('', ['inscricao:', 'gravar']);
$id = (int) ($options['inscricao'] ?? 0);
if ($id < 1) { fwrite(STDERR, "Uso: php tools/preencher_cpf_responsavel.php --inscricao=123 [--gravar]\n"); exit(1); }
$cfg = require dirname(__DIR__) . '/config/conn.php';
try {
    $db = new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['database'].';charset='.($cfg['charset']??'utf8mb4'), $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $q = $db->prepare("SELECT ida.responsavel_nome, ida.responsavel_cpf, i.nome AS participante FROM inscricao_dados_adicionais ida INNER JOIN inscricoes i ON i.idInscricao=ida.idInscricao WHERE ida.idInscricao=? LIMIT 1");
    $q->execute([$id]); $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r || trim((string)$r['responsavel_nome']) === '') { throw new RuntimeException('Inscrição sem responsável registrado.'); }
    echo "Inscrição #$id | Participante: ".$r['participante']." | Responsável: ".$r['responsavel_nome']."\n";
    echo 'CPF do responsável: ' . (trim((string)($r['responsavel_cpf']??'')) !== '' ? 'já preenchido' : 'a preencher') . "\n";
    if (!isset($options['gravar'])) { echo "Somente consulta. Execute com --gravar para preencher em modo interativo.\n"; exit(0); }
    if (trim((string)($r['responsavel_cpf']??'')) !== '') { throw new RuntimeException('CPF já preenchido; não haverá substituição automática.'); }
    echo 'Informe os 11 dígitos do CPF do responsável (não use parâmetros de linha de comando): ';
    $cpf = preg_replace('/\D+/', '', (string)fgets(STDIN));
    if (!is_string($cpf) || strlen($cpf)!==11 || preg_match('/^(\d)\1{10}$/',$cpf)) { throw new RuntimeException('CPF inválido.'); }
    for ($t=9;$t<=10;$t++) { $s=0; for($j=0;$j<$t;$j++) $s+=(int)$cpf[$j]*($t+1-$j); $d=(10* $s)%11; if ($d===10) $d=0; if((int)$cpf[$t]!==$d) throw new RuntimeException('Dígitos verificadores inválidos.'); }
    $u=$db->prepare("UPDATE inscricao_dados_adicionais SET responsavel_cpf=? WHERE idInscricao=? AND (responsavel_cpf IS NULL OR responsavel_cpf='')");
    $u->execute([$cpf,$id]); if($u->rowCount()!==1) throw new RuntimeException('Registro alterado por outra operação; revise.');
    echo "[OK] CPF do responsável preenchido. Refaça a exportação.\n";
} catch(Throwable $e) { fwrite(STDERR, '[ERRO] '.$e->getMessage()."\n"); exit(1); }
