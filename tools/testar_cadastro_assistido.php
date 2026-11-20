<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
// Testes de regras puras, sem inicializar config/settings.php, sem enviar mensagens.
$root = dirname(__DIR__);
require_once $root . '/mod/auth/Auth.php';
require_once $root . '/mod/auth/Usuario.php'; // TEST_REQUIRES_USUARIO_CPF_V1
require_once $root . '/mod/services/InscricaoPublicaService.php';
require_once $root . '/mod/services/CadastroAssistidoRetiro.php';
$_SESSION['user'] = ['id'=>999,'tipo'=>2]; // sessão fictícia, somente dentro deste processo CLI.
$u = ['data_nascimento'=>'2000-05-01','nacionalidade'=>'Brasileira','genero'=>'Feminino','cep'=>'95630-000','complemento'=>'','pais'=>'Brasil'];
$ev = ['data_inicio'=>'2026-11-21','data_fim'=>'2026-11-22','cidade'=>'Parobé','titulo'=>'Evento fictício','camiseta_ativa'=>1,'valor_inscricao'=>100,'valor_visitante'=>0,'idade_minima'=>0,'idade_maxima'=>0];
$termos = [['idTermo'=>1,'titulo'=>'Termo fictício','obrigatorio'=>1],['idTermo'=>2,'titulo'=>'Opcional','obrigatorio'=>0]];
$post = ['restricao_medicacao'=>'0','precisa_acessibilidade'=>'0','restricao_alimentar'=>'0','deficiencia'=>'Não','camiseta'=>'M','termos'=>['1']];
$tests = 0; $fail = 0;
function teste(string $nome, callable $cb): void {
    global $tests,$fail; $tests++;
    try { if ($cb() !== true) { throw new RuntimeException('resultado inesperado'); } echo "[OK] $nome\n"; }
    catch (Throwable $e) { $fail++; echo "[FALHA] $nome: " . $e->getMessage() . "\n"; }
}
function rejeita(callable $cb): bool { try { $cb(); return false; } catch (InvalidArgumentException) { return true; } }
$r = static fn(array $p, array $pessoa, array $evento = []) => CadastroAssistidoRetiro::respostas($p,$pessoa,$evento ?: $ev,$termos,['M','G']);
teste('Perfil e CEP normalizados', fn()=>CadastroAssistidoRetiro::perfil($u)['cep']==='95630000');
teste('Data inexistente bloqueada', fn()=>rejeita(fn()=>CadastroAssistidoRetiro::perfil(array_replace($u,['data_nascimento'=>'2000-02-30']))));
teste('Nascimento futuro bloqueado', fn()=>rejeita(fn()=>CadastroAssistidoRetiro::perfil(array_replace($u,['data_nascimento'=>'2099-01-01']))));
teste('Gênero fora das opções bloqueado', fn()=>rejeita(fn()=>CadastroAssistidoRetiro::perfil(array_replace($u,['genero'=>'X']))));
teste('CEP inválido bloqueado', fn()=>rejeita(fn()=>CadastroAssistidoRetiro::perfil(array_replace($u,['cep'=>'123']))));
teste('Idade no aniversário do evento', fn()=>CadastroAssistidoRetiro::idade(array_replace($u,['data_nascimento'=>'2008-11-21']),$ev)===18);
teste('Idade na véspera do aniversário', fn()=>CadastroAssistidoRetiro::idade(array_replace($u,['data_nascimento'=>'2008-11-22']),$ev)===17);
teste('Valor normal', fn()=>CadastroAssistidoRetiro::valor($ev,false)===100.0);
teste('Gratuidade visitante preservada', fn()=>CadastroAssistidoRetiro::valor($ev,true)===0.0);
teste('Identifica comunidade Visitante', fn()=>CadastroAssistidoRetiro::visitante(' visitante '));
teste('Respostas completas de adulto', fn()=>($r($post,$u)['dados']['responsavel_nome']===null && count($r($post,$u)['termos'])===1));
teste('Perguntas não habilitadas ignoradas', fn()=>($r(array_replace($post,['restricao_medicacao'=>'1','medicacao_detalhes'=>'Não deve ser salvo']),$u,array_replace($ev,['perguntar_restricao_medicacao'=>0]))['dados']['medicacao_detalhes']===null));
teste('Restrição de medicação exige detalhes', fn()=>rejeita(fn()=>$r(array_replace($post,['restricao_medicacao'=>'1']),$u)));
teste('Acessibilidade exige detalhes', fn()=>rejeita(fn()=>$r(array_replace($post,['precisa_acessibilidade'=>'1']),$u)));
teste('Restrição alimentar exige detalhes', fn()=>rejeita(fn()=>$r(array_replace($post,['restricao_alimentar'=>'1']),$u)));
teste('Deficiência fora da lista bloqueada', fn()=>rejeita(fn()=>$r(array_replace($post,['deficiencia'=>'X']),$u)));
teste('Termos obrigatórios exigem declaração', fn()=>rejeita(fn()=>$r(array_replace($post,['termos'=>[]]),$u)));
teste('Camiseta não configurada bloqueada', fn()=>rejeita(fn()=>$r(array_replace($post,['camiseta'=>'XGG']),$u)));
teste('Limite de idade respeitado', fn()=>rejeita(fn()=>$r($post,$u,array_replace($ev,['idade_maxima'=>20]))));
$menor = array_replace($u,['data_nascimento'=>'2010-05-01']);
$aut = ['responsavel_nome'=>'Pessoa fictícia','responsavel_cpf'=>'52998224725','responsavel_telefone_1'=>'(51) 99999-0000','responsavel_autorizacao'=>'1'];
teste('Menor sem autorização bloqueado', fn()=>rejeita(fn()=>$r($post,$menor)));
teste('Menor com autorização declarada e origem administrativa', fn()=>str_contains($r(array_replace($post,$aut),$menor)['dados']['responsavel_autorizacao_texto'],'usuário #999'));
teste('Segundo telefone inválido bloqueado', fn()=>rejeita(fn()=>$r(array_replace($post,$aut,['responsavel_telefone_2'=>'123']),$menor)));
teste('CPF do responsável válido é registrado', fn()=>($r(array_replace($post,$aut),$menor)['dados']['responsavel_cpf']==='52998224725'));
teste('CPF do responsável inválido é rejeitado', fn()=>rejeita(fn()=>$r(array_replace($post,$aut,['responsavel_cpf'=>'12345678900']),$menor)));
teste('Não persiste fora de transação', function(){
    $pdo = new class extends PDO { public function __construct() {} public function inTransaction():bool { return false; } };
    try { CadastroAssistidoRetiro::gravar($pdo,1,[],[],false); return false; } catch(LogicException) { return true; }
});
// Opcional: verificar estrutura do banco sem executar o bootstrap que agenda e-mails.
if (in_array('--verificar-banco',$argv,true)) {
    teste('Estrutura do banco configurado (somente leitura)',function() use($root) {
        $cfg = require $root . '/config/conn.php';
        try { $pdo = new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['database'].';charset='.($cfg['charset']??'utf8mb4'),$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
        catch (Throwable) { throw new RuntimeException('Não foi possível conectar ao banco configurado (confira o MySQL e config/conn.php).'); }
        $map = [
            'usuarios'=>['id','nacionalidade','data_nascimento','genero','pais','cep','complemento'],
            'inscricao_dados_adicionais'=>['idInscricao','nacionalidade','genero','pais','cep','logradouro','numero','complemento','bairro','cidade','estado','idComunidade','visitante','restricao_medicacao','medicacao_detalhes','deficiencia','deficiencia_detalhes','precisa_acessibilidade','acessibilidade_detalhes','restricao_alimentar','alimentar_detalhes','responsavel_nome','responsavel_cpf','responsavel_telefone_1','responsavel_telefone_2','responsavel_autorizacao_texto','responsavel_autorizacao_aceita_em'],
            'inscricao_termos_aceites'=>['idInscricao','idTermo','titulo_snapshot','descricao_snapshot','url_snapshot','obrigatorio','aceito_em','ip','user_agent'],
            'evento_termos'=>['idTermo','idEvento','titulo','descricao','url','obrigatorio','ordem'],
            'evento_camiseta_opcoes'=>['idEvento','idOpcao','tamanho','ordem'],
        ];
        foreach($map as $table=>$cols) {
            try { $found=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN); }
            catch (Throwable) { throw new RuntimeException('Tabela necessária ausente/inacessível: '.$table); }
            $missing=array_diff($cols,$found);
            if($missing) throw new RuntimeException('Colunas ausentes em '.$table.': '.implode(', ',$missing));
        }
        return true;
    });
}
echo "\n$tests teste(s); $fail falha(s). Nenhum e-mail enviado. Nenhum dado modificado.\n";
exit($fail ? 1 : 0);
