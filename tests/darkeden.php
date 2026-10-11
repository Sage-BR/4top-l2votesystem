<?php
/** Testes isolados: php tests/darkeden.php darkeden_master [plain|mysql_old|mysql_password] */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('INSTALLED', true); define('GAME_PROJECT', $argv[1] ?? 'darkeden_master');
if (isset($argv[2])) define('DK_PASSWORD_MODE', $argv[2]);
define('DK_ADMIN_ACCOUNTS', array('admin-test')); define('DK_ITEM_ID_STEP', 2); define('DK_ITEM_ID_SLOT', 1);
require_once __DIR__.'/../includes/core.php'; require_once __DIR__.'/../includes/auth.php';
$checks = 0;
function check($ok, $message) { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function rejects($fn, $message, $key = null) { try { $fn(); } catch (Throwable $e) { check($key === null || ($e instanceof DKInventoryException && $e->msgKey === $key), $message); return; } check(false, $message); }
check(gameOldPassword('mariadb') === '021bec665bf663f1', 'Vetor público MySQL 3.23');
check(gameOldPassword("m aria\tdb") === gameOldPassword('mariadb'), 'Espaços/tab legados');
check(gameOldPassword('') === '', 'Hash vazio');
$mode = gamePasswordMode(); $hash = $mode === 'plain' ? 'notagoodpwd' : ($mode === 'mysql_old' ? gameOldPassword('notagoodpwd') : '*3A70EE9FC6594F88CE9E959CD51C5A1C002DC937');
check(gameVerifyPassword('notagoodpwd', $hash), 'Senha correta'); check(!gameVerifyPassword('wrong', $hash), 'Senha incorreta'); check(!gameVerifyPassword('', ''), 'Senha vazia'); check(!gameVerifyPassword(array(), $hash), 'Entrada inválida');
check(count(gameProjects()) === 2 && !isset(gameProjects()['acis']), 'Somente perfis DK');
$token = gameCharToken('Slayer', 'Hero'); check(gameParseCharToken($token) === array('race'=>'Slayer', 'name'=>'Hero'), 'Token');
foreach (array('', 'abc', array(), base64_encode('Player:Hero'), base64_encode('Slayer:'), base64_encode('Slayer:'.str_repeat('x',41))) as $invalid) check(gameParseCharToken($invalid) === false, 'Token inválido');
$grid = array_fill(0,60,false); gameBagMark($grid,0,0,2,3); check(gameBagPlace($grid,1,3) === array(2,0), 'Volume de equipamento respeitado');
rejects(function() use(&$grid) { gameBagMark($grid,0,0,1,1); }, 'Sobreposição rejeitada');
rejects(function() use(&$grid) { gameBagMark($grid,9,5,2,1); }, 'Fora da grade');
$full = array_fill(0,60,true); rejects(function() use(&$full) { gameBagPlace($full,1,1); }, 'Bag cheia', 'msg_bag_full');
check(gameNextInventoryId(10,2,1) === 11 && gameNextInventoryId(11,2,1) === 13, 'IDs reservados sem repetir');
rejects(function(){ gameNextInventoryId(4294967295,2,1); }, 'Limite DWORD');
class TestDatabase {
    public $hash; public $access='ALLOW'; public $logon='LOGOFF'; public $race='SLAYER'; public $engine='InnoDB'; public $transaction=true; public $inserts=array(); public $locks=0; public $releases=0; public $unknownColumn=false; public $occupied=array(); public $schemaExtra=false;
    function prepare($sql) { return new TestStatement($this,$sql); }
    function query($sql) { $stmt=$this->prepare($sql); $stmt->execute(); return $stmt; }
    function inTransaction() { return $this->transaction; }
    function columns($class) {
        $columns=array();
        $names=array('ItemID','ObjectID','ItemType','OwnerID','Storage','StorageID','X','Y');
        if ($class==='Potion') $names[]='Num';
        else $names=array_merge($names,array('OptionType','Durability','EnchantLevel','Silver','ItemFlag','Grade'));
        if ($class==='Sword' && GAME_PROJECT==='darkeden_v2') $names=array_merge($names,array('OptionType2','HeroOption','HeroOptionAttr'));
        if ($this->unknownColumn) $names[]='CustomRequired';
        foreach($names as $name) $columns[]=array('Field'=>$name,'Null'=>'NO','Default'=>($name==='CustomRequired'||$name==='OptionType2'||$name==='HeroOption'||$name==='HeroOptionAttr')?null:($name==='Grade'?6:0),'Extra'=>'');
        return $columns;
    }
}
class TestStatement {
    private $db; private $sql; private $rows=array();
    function __construct($db,$sql) { $this->db=$db; $this->sql=$sql; }
    function execute($p=array()) {
        $s=$this->sql; $d=$this->db;
        if(strpos($s,'AS account_access')!==false) $this->rows=array(array('login'=>$p[0],'password'=>$d->hash,'account_access'=>$d->access));
        elseif(strpos($s,'SELECT Name, Race FROM Slayer')===0) $this->rows=$p[0]==='admin-test'?array(array('Name'=>'Hero','Race'=>$d->race)):array();
        elseif(strpos($s,'SELECT c.Name FROM')===0) $this->rows=$p[0]==='admin-test'&&$p[1]==='Hero'&&$p[2]===$d->race?array(array('Name'=>'Hero')):array();
        elseif(strpos($s,'SELECT LogOn')===0) $this->rows=array(array($d->logon));
        elseif(strpos($s,'SELECT ENGINE')===0) $this->rows=array(array($d->engine));
        elseif(preg_match('/SELECT \* FROM `(Potion|Sword)Info`/',$s,$m)) $this->rows=(int)$p[0]===0?array(array('ItemType'=>0,'Volume'=>$m[1]==='Potion'?1:3,'Durability'=>120)):array();
        elseif(strpos($s,'SHOW COLUMNS')===0) { preg_match('/`(Potion|Sword)Object`/',$s,$m); $this->rows=$d->columns($m[1]); }
        elseif(strpos($s,'SELECT COALESCE(MAX(ItemID)')===0) $this->rows=array(array(10));
        elseif(strpos($s,'SELECT GET_LOCK')===0) { $d->locks++; $this->rows=array(array(1)); }
        elseif(strpos($s,'SELECT RELEASE_LOCK')===0) { $d->releases++; $this->rows=array(array(1)); }
        elseif(strpos($s,'SELECT TABLE_NAME, COLUMN_NAME')===0) {
            $this->rows=array(); foreach(array('Potion','Sword') as $class) {
                foreach($d->columns($class) as $c) $this->rows[]=array('TABLE_NAME'=>$class.'Object','COLUMN_NAME'=>$c['Field']);
                foreach(array('ItemType','Volume') as $c) $this->rows[]=array('TABLE_NAME'=>$class.'Info','COLUMN_NAME'=>$c);
            }
            if($d->schemaExtra) foreach(array('OwnerID','Storage','ItemType','X','Y') as $c) $this->rows[]=array('TABLE_NAME'=>'UnknownObject','COLUMN_NAME'=>$c);
        }
        elseif(strpos($s,'SELECT o.X')===0) { preg_match('/FROM `(Potion|Sword)Object`/',$s,$m); $this->rows=$d->occupied[$m[1]]??array(); }
        elseif(strpos($s,'SELECT X, Y, NULL AS Volume')===0) $this->rows=array(array('X'=>0,'Y'=>0,'Volume'=>null));
        elseif(strpos($s,'INSERT INTO `')===0) {
            preg_match('/INSERT INTO `([^`]+)` \((.*?)\) VALUES/s',$s,$m); $fields=array_map(function($f){return trim($f," `");},explode(',',$m[2]));
            $d->inserts[]=array('table'=>$m[1],'data'=>array_combine($fields,$p)); $this->rows=array();
        } else throw new RuntimeException('Consulta não coberta pelo teste');
        return true;
    }
    function fetch($mode=null) { return array_shift($this->rows) ?: false; }
    function fetchAll($mode=null) { return $this->rows; }
    function fetchColumn() { $row=$this->fetch(); return $row?reset($row):false; }
    function closeCursor() { return true; }
}
$db=new TestDatabase(); $db->hash=$hash; function getDB(){global $db;return $db;}
check(gameLogin('admin-test','notagoodpwd')['access_level']===1,'Admin explícito'); check(gameLogin('player-test','notagoodpwd')['access_level']===0,'Jogador comum');
$db->access='DENY'; check(gameLogin('admin-test','notagoodpwd')===false,'Conta bloqueada'); $db->access='ALLOW';
check(gameCharBelongsTo('admin-test',$token),'Personagem próprio'); check(!gameCharBelongsTo('player-test',$token),'Outra conta');
check(!gameCharBelongsTo('admin-test',gameCharToken('Vampire','Hero')),'Raça antiga não autoriza entrega');
check(count(gameGetChars('admin-test'))===1,'Uma entrada para a raça atual');
$reward=array(array('item_class'=>'Potion','item_id'=>0,'quantity'=>2),array('item_class'=>'Sword','item_id'=>0,'quantity'=>1));
$db->occupied['Sword']=array(array('X'=>0,'Y'=>0,'Volume'=>6));
check(gameDeliverRewards('admin-test',$reward,$db,$token),'Entrega na bag'); check(count($db->inserts)===3,'Quantidade exata de unidades');
foreach($db->inserts as $row) check($row['data']['OwnerID']==='Hero'&&$row['data']['Storage']===0&&$row['data']['ItemID']%2===1&&$row['data']['ObjectID']===0,'Bag, dono e IDs reservados');
check($db->inserts[0]['data']['X']===2&&$db->inserts[1]['data']['X']===3,'Não sobrepõe ocupação existente');
check($db->inserts[2]['data']['Durability']===120,'Durabilidade do catálogo');
if(GAME_PROJECT==='darkeden_v2') check($db->inserts[2]['data']['OptionType2']===''&&$db->inserts[2]['data']['HeroOption']===0,'Campos exclusivos v2');
check($db->releases===0&&$db->locks===2,'Locks mantidos até commit'); $db->transaction=false; gameReleaseInventoryLocks($db); check($db->releases===$db->locks,'Locks liberados após commit'); $db->transaction=true;
function attempt($fn,$label,$key=null){global $db;$db->inserts=array();rejects($fn,$label,$key);check(!$db->inserts,'Sem entrega parcial: '.$label);gameReleaseInventoryLocks($db);}
$db->logon='GAME'; attempt(function()use($db,$reward,$token){gameDeliverRewards('admin-test',$reward,$db,$token);},'Conta online','msg_char_online'); $db->logon='LOGOFF';
$db->engine='MyISAM'; attempt(function()use($db,$reward,$token){gameDeliverRewards('admin-test',$reward,$db,$token);},'MyISAM','msg_bag_setup'); $db->engine='InnoDB';
attempt(function()use($db,$token){gameDeliverRewards('admin-test',array(array('item_class'=>'Potion','item_id'=>0,'quantity'=>1),array('item_class'=>'Sword','item_id'=>999,'quantity'=>1)),$db,$token);},'Item inexistente');
$db->unknownColumn=true; attempt(function()use($db,$reward,$token){gameDeliverRewards('admin-test',$reward,$db,$token);},'Schema customizado sem default'); $db->unknownColumn=false;
$db->occupied['Sword']=array();for($y=0;$y<6;$y++)for($x=0;$x<10;$x++)$db->occupied['Sword'][]=array('X'=>$x,'Y'=>$y,'Volume'=>1);
attempt(function()use($db,$reward,$token){gameDeliverRewards('admin-test',$reward,$db,$token);},'Bag cheia','msg_bag_full'); $db->occupied=array();
$db->schemaExtra=true; attempt(function()use($db,$reward,$token){gameDeliverRewards('admin-test',$reward,$db,$token);},'Categoria ocupada com volume desconhecido'); $db->schemaExtra=false;
$db->race='OUSTERS'; attempt(function()use($db){gameDeliverRewards('admin-test',array(array('item_class'=>'Potion','item_id'=>0,'quantity'=>1)),$db,gameCharToken('Ousters','Hero'));},'Raça não aceita pelo loader','msg_item_race'); $db->race='SLAYER';
foreach(array(0,61,-1) as $qty) rejects(function()use($db,$qty){gameValidateReward(0,$qty,$db,'Potion');},'Quantidade inválida');
rejects(function()use($db){gameValidateReward(0,1,$db,'Potion` WHERE 1=1');},'Categoria não vira SQL');
$db->transaction=false; rejects(function()use($db,$reward,$token){gameDeliverRewards('admin-test',$reward,$db,$token);},'Exige transação');
$source=file_get_contents(__DIR__.'/../install.php');$begin=strpos($source,'            $cfg  =');$end=strpos($source,'            if (file_put_contents',$begin);
$db_host='localhost';$db_user='test';$db_pass='';$db_name='DARKEDEN';$project=GAME_PROJECT;$password_mode=$mode;$admin_login='admin-test';$id_step=2;$id_slot=1;
eval(substr($source,$begin,$end-$begin));token_get_all($cfg,TOKEN_PARSE);check(strpos($cfg,'DK_ITEM_ID_STEP')!==false&&strpos($cfg,'\n')===false,'Config gerada válida');
startSession();$_SESSION['vs_login']='admin-test';check(isAdmin(),'Permissão admin');$_SESSION['vs_login']='player-test';$_SESSION['vs_access_level']=999;check(!isAdmin(),'Sem elevação por nível de sessão');session_write_close();
echo "OK: $checks verificações ($project / $mode)\n";