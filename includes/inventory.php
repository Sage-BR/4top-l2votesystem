<?php
/** Entrega direta no inventário DarkEden (Storage = 0). */
require_once __DIR__ . '/item_races.php';
class DKInventoryException extends RuntimeException {
    public $msgKey;
    function __construct($key, $message) { parent::__construct($message); $this->msgKey = $key; }
}
function gameItemClasses() {
    return array('Potion', 'Sword', 'Blade', 'Ring', 'Bracelet', 'Necklace', 'Coat', 'Trouser', 'Shoes', 'Shield', 'Cross', 'Glove', 'Helm', 'SG', 'SMG', 'AR', 'SR', 'VampireRing', 'VampireBracelet', 'VampireNecklace', 'VampireCoat', 'Mace', 'VampireEarring', 'VampireWeapon', 'VampireAmulet', 'OustersArmsband', 'OustersBoots', 'OustersChakram', 'OustersCirclet', 'OustersCoat', 'OustersPendent', 'OustersRing', 'OustersStone', 'OustersWristlet', 'CarryingReceiver', 'ShoulderArmor', 'Dermis', 'Persona', 'Fascia', 'Mitten');
}
function gameItemDefinition($itemClass, $itemId, $db) {
    if (!is_string($itemClass) || !in_array($itemClass, gameItemClasses(), true)) throw new InvalidArgumentException('Selecione uma categoria de item suportada. Recompensas Goods antigas precisam ser recadastradas.');
    $stmt = $db->prepare("SELECT * FROM `{$itemClass}Info` WHERE ItemType = ? LIMIT 1");
    $stmt->execute(array($itemId));
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$info) throw new InvalidArgumentException('ID de item inexistente na categoria selecionada.');
    gameItemVolume($info['Volume'] ?? -1);
    return $info;
}
function gameValidateReward($itemId, $quantity, $db, $itemClass = '') {
    if (!is_int($itemId) || !is_int($quantity) || $itemId < 0 || $itemId > 65535 || $quantity < 1 || $quantity > 60) throw new InvalidArgumentException('ID ou quantidade inválidos (1 a 60 unidades).');
    return gameItemDefinition($itemClass, $itemId, $db);
}
function gameItemVolume($volume) {
    $sizes = array(1 => array(1, 1), 2 => array(1, 2), 3 => array(1, 3), 4 => array(2, 1), 5 => array(2, 2), 6 => array(2, 3));
    if (!isset($sizes[(int)$volume])) throw new RuntimeException('Volume do item não suportado.');
    return $sizes[(int)$volume];
}
function gameBagMark(&$grid, $x, $y, $width, $height) {
    if ($x < 0 || $y < 0 || $x + $width > 10 || $y + $height > 6) throw new RuntimeException('Posição de inventário inválida.');
    for ($dy = 0; $dy < $height; $dy++) for ($dx = 0; $dx < $width; $dx++) {
        $cell = ($y + $dy) * 10 + $x + $dx;
        if (!empty($grid[$cell])) throw new RuntimeException('Inventário contém itens sobrepostos.');
        $grid[$cell] = true;
    }
}
function gameBagPlace(&$grid, $width, $height) {
    for ($y = 0; $y <= 6 - $height; $y++) for ($x = 0; $x <= 10 - $width; $x++) {
        $free = true;
        for ($dy = 0; $dy < $height; $dy++) for ($dx = 0; $dx < $width; $dx++) if (!empty($grid[($y + $dy) * 10 + $x + $dx])) $free = false;
        if ($free) { gameBagMark($grid, $x, $y, $width, $height); return array($x, $y); }
    }
    throw new DKInventoryException('msg_bag_full', 'A bag não possui espaço para todas as recompensas. Libere espaço e tente novamente.');
}
function gameRequireInnoDB($table, $db) {
    $stmt = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute(array($table));
    if (strcasecmp((string)$stmt->fetchColumn(), 'InnoDB') !== 0) throw new DKInventoryException('msg_bag_setup', 'A entrega na bag exige tabelas InnoDB e uma sequência de IDs reservada ao painel. Consulte o administrador.');
}
function gameInventoryPartition() {
    $step = defined('DK_ITEM_ID_STEP') ? DK_ITEM_ID_STEP : 0;
    $slot = defined('DK_ITEM_ID_SLOT') ? DK_ITEM_ID_SLOT : -1;
    if (!is_int($step) || !is_int($slot) || $step < 2 || $step > 256 || $slot < 0 || $slot >= $step) throw new DKInventoryException('msg_bag_setup', 'Configure uma sequência de IDs reservada ao painel, alinhada ao ServerCount do jogo.');
    return array($step, $slot);
}
function gameNextInventoryId($maxId, $step, $slot) {
    $delta = ($slot - ($maxId % $step) + $step) % $step;
    $id = $maxId + ($delta === 0 ? $step : $delta);
    if ($id < 1 || $id > 4294967295) throw new RuntimeException('Limite de IDs do servidor atingido.');
    return $id;
}
function gameInventoryColumns($table, $db) {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $table)) throw new RuntimeException('Tabela inválida.');
    $stmt = $db->query("SHOW COLUMNS FROM `$table`");
    $columns = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) $columns[$column['Field']] = $column;
    return $columns;
}
function gameLoadBag($name, $db) {
    // Inclui todas as categorias existentes na bag, mesmo as não oferecidas como reward.
    $stmt = $db->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (TABLE_NAME LIKE '%Object' OR TABLE_NAME LIKE '%Info')");
    $schema = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:Object|Info)$/D', $row['TABLE_NAME'])) $schema[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
    }
    $grid = array_fill(0, 60, false);
    foreach ($schema as $table => $columns) {
        if (substr($table, -6) !== 'Object' || count(array_intersect(array('OwnerID','Storage','X','Y','ItemType'), array_keys($columns))) !== 5) continue;
        $infoTable = substr($table, 0, -6) . 'Info';
        if (isset($schema[$infoTable]['Volume'], $schema[$infoTable]['ItemType'])) {
            $query = $db->prepare("SELECT o.X, o.Y, i.Volume FROM `$table` o LEFT JOIN `$infoTable` i ON i.ItemType = o.ItemType WHERE o.OwnerID = ? AND o.Storage = 0");
        } else {
            // Não presume tamanho de categorias desconhecidas.
            $query = $db->prepare("SELECT X, Y, NULL AS Volume FROM `$table` WHERE OwnerID = ? AND Storage = 0");
        }
        $query->execute(array($name));
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            list($width, $height) = gameItemVolume($row['Volume'] ?? -1);
            gameBagMark($grid, (int)$row['X'], (int)$row['Y'], $width, $height);
        }
    }
    return $grid;
}
function gameInventoryInsertData($columns, $info, $id, $name, $x, $y) {
    $data = array('ItemID' => $id, 'ObjectID' => 0, 'ItemType' => (int)$info['ItemType'], 'OwnerID' => $name, 'Storage' => 0, 'StorageID' => 0, 'X' => $x, 'Y' => $y);
    foreach (array('Num' => 1, 'OptionType' => '', 'OptionType2' => '', 'EnchantLevel' => 0, 'Silver' => 0, 'ItemFlag' => 0, 'HeroOption' => 0, 'HeroOptionAttr' => 0) as $field => $value) if (isset($columns[$field])) $data[$field] = $value;
    if (isset($columns['Durability'])) {
        if (!isset($info['Durability']) || !is_numeric($info['Durability']) || $info['Durability'] < 0) throw new RuntimeException('Durabilidade do item não encontrada no catálogo.');
        $data['Durability'] = (int)$info['Durability'];
    }
    foreach ($columns as $field => $column) {
        if (!isset($data[$field]) && ($column['Null'] ?? 'NO') === 'NO' && ($column['Default'] ?? null) === null && strpos($column['Extra'] ?? '', 'auto_increment') === false) throw new RuntimeException('Schema personalizado exige integração adicional.');
    }
    return $data;
}
function gameReleaseInventoryLocks($db) {
    foreach ($GLOBALS['dkInventoryLocks'] ?? array() as $name) {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute(array($name)); $stmt->fetchColumn(); $stmt->closeCursor();
    }
    $GLOBALS['dkInventoryLocks'] = array();
}
function gameDeliverRewards($login, array $rewards, $db, $token = null) {
    if (!$db->inTransaction()) throw new RuntimeException('A entrega exige uma transação.');
    if (!$rewards || !gameCharBelongsTo($login, $token)) throw new RuntimeException('Personagem ou recompensas inválidos.');
    list($step, $slot) = gameInventoryPartition();
    $char = gameParseCharToken($token);
    gameRequireInnoDB('Player', $db);
    gameRequireInnoDB('Slayer', $db);
    gameRequireInnoDB($char['race'], $db);
    // Mantém a linha de login bloqueada até o commit/rollback da coleta.
    $account = $db->prepare('SELECT LogOn FROM Player WHERE PlayerID = ? FOR UPDATE');
    $account->execute(array($login));
    if ($account->fetchColumn() !== 'LOGOFF') throw new DKInventoryException('msg_char_online', 'Saia do jogo antes de receber a recompensa na bag.');
    $owned = $db->prepare("SELECT c.Name FROM `{$char['race']}` c JOIN Slayer s ON s.Name = c.Name AND s.PlayerID = c.PlayerID WHERE c.PlayerID = ? AND c.Name = ? AND c.Active = 'ACTIVE' AND s.Active = 'ACTIVE' AND s.Race = ? FOR UPDATE");
    $owned->execute(array($login, $char['name'], strtoupper($char['race'])));
    if (!$owned->fetchColumn()) throw new RuntimeException('Personagem alterado durante a coleta.');
    $definitions = array(); $categories = array(); $units = 0;
    foreach ($rewards as $reward) {
        $class = $reward['item_class'] ?? '';
        $info = gameValidateReward((int)$reward['item_id'], (int)$reward['quantity'], $db, $class);
        if (!in_array($char['race'], gameItemRaces($class), true)) throw new DKInventoryException('msg_item_race', 'Uma recompensa não pode ser carregada pela raça escolhida. Selecione um personagem compatível.');
        $definitions[] = array($class, $info, (int)$reward['quantity']); $categories[$class] = true; $units += (int)$reward['quantity'];
    }
    if ($units > 60) throw new DKInventoryException('msg_bag_full', 'A bag não possui espaço para todas as recompensas.');
    $categories = array_keys($categories); sort($categories, SORT_STRING);
    $columns = array(); $ids = array();
    foreach ($categories as $class) {
        $table = $class . 'Object'; gameRequireInnoDB($table, $db);
        $name = 'dk_bag_ids_' . hash('sha256', $table); $name = substr($name, 0, 64);
        $lock = $db->prepare('SELECT GET_LOCK(?, 5)'); $lock->execute(array($name)); $acquired = (int)$lock->fetchColumn() === 1; $lock->closeCursor();
        if (!$acquired) throw new RuntimeException('Entrega em andamento.');
        $GLOBALS['dkInventoryLocks'][] = $name;
        $columns[$class] = gameInventoryColumns($table, $db);
        $max = $db->query("SELECT COALESCE(MAX(ItemID), 0) FROM `$table` FOR UPDATE"); $ids[$class] = (int)$max->fetchColumn(); $max->closeCursor();
    }
    $grid = gameLoadBag($char['name'], $db); $plan = array();
    foreach ($definitions as $definition) {
        list($class, $info, $quantity) = $definition; list($width, $height) = gameItemVolume($info['Volume']);
        for ($i = 0; $i < $quantity; $i++) {
            list($x, $y) = gameBagPlace($grid, $width, $height);
            $ids[$class] = gameNextInventoryId($ids[$class], $step, $slot);
            $plan[] = array($class, gameInventoryInsertData($columns[$class], $info, $ids[$class], $char['name'], $x, $y));
        }
    }
    // Todo o lote foi validado e posicionado antes da primeira gravação.
    foreach ($plan as $item) {
        list($class, $data) = $item; $fields = '`' . implode('`, `', array_keys($data)) . '`'; $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $insert = $db->prepare("INSERT INTO `{$class}Object` ($fields) VALUES ($placeholders)"); $insert->execute(array_values($data));
    }
    return true; // As travas de IDs são liberadas pelo chamador após commit/rollback.
}