<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$raiz=dirname(__DIR__);
try {
    require_once $raiz.'/mod/services/PagamentosManuaisPrazoService.php';
    $cfg=require $raiz.'/config/conn.php';
    $db=new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['database'].';charset='.($cfg['charset']??'utf8mb4'),$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $linhas=(new PagamentosManuaisPrazoService($db))->consultar();
    foreach($linhas as $l) echo '#'.$l['idInscricao'].' | '.$l['evento'].' | '.$l['forma'].' | R$ '.number_format((float)$l['valor'],2,',','.').' | Prazo: '.$l['prazo_registro'].' | '.$l['situacao_registro'].($l['conflito_asaas']?' | Conferir vínculo Asaas':'').PHP_EOL;
    echo 'Pendências de registro: '.count($linhas).PHP_EOL.'Consulta somente leitura; nenhum e-mail enviado.'.PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,"[ERRO] Não foi possível consultar. Confira a conexão e as migrações.\n");exit(1);}
