# VoteSystem 4Top Servers — DarkEden

Painel PHP de votação para DarkEden. O administrador cadastra **categoria, ID do item (ItemType) e quantidade**. A recompensa é gravada diretamente na **bag do personagem**, em `Storage = 0`; não usa GoodsID, loja ou retirada por NPC.

## Projetos

| GAME_PROJECT | Servidor de referência | Senha padrão |
|---|---|---|
| `darkeden_master` | `server-master` | Comparação direta (`plain`) |
| `darkeden_v2` | `darkeden-v2-serverfiles-main` | MySQL 3.23 (`mysql_old`), conforme `DB_VERSION : 4` |

O instalador permite sobrescrever o formato conforme a configuração do servidor: `plain`, `mysql_old` ou `mysql_password` (MySQL 4.1). Não há fallback entre formatos nem alteração das senhas existentes. Os hashes são verificados pelo PHP, sem depender das antigas funções do banco. Os testes usam vetores públicos da documentação [MariaDB OLD_PASSWORD](https://mariadb.com/docs/server/reference/plugins/authentication-plugins/authentication-plugin-mysql_old_password) e [PASSWORD](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/encryption-hashing-and-compression-functions/password).

Contas usam `Player.PlayerID`, `Password` e `Access = 'ALLOW'`. Administradores são definidos explicitamente em `DK_ADMIN_ACCOUNTS`; o campo Access do jogo não é nível administrativo. O cadastro principal `Slayer` informa a raça atual (`Race`), vinculada aos dados de Slayer, Vampire ou Ousters. A entrega revalida conta, nome, raça atual e personagem ativo.

## Cadastro e entrega de itens

DarkEden não possui um ID global de produto equivalente ao item_id do L2: o **ItemType é relativo à categoria**. Por exemplo, `Potion / ItemType 0` é diferente de `Sword / ItemType 0`. `ItemID` é o identificador de uma instância criada, e não o número que o administrador cadastra.

- Categorias de recompensa são limitadas a 40 loaders comuns analisados nos dois servidores, incluindo Potion, Sword, Blade, equipamentos Slayer/Vampire/Ousters e acessórios. Categorias especiais exigem integração específica.
- O ItemType precisa existir na tabela `CategoriaInfo` correspondente; zero é válido quando cadastrado no catálogo.
- A quantidade aceita 1 a 60 unidades por recompensa. Cada unidade ocupa seu próprio espaço; a entrega não altera pilhas existentes.
- A bag usa a grade 10×6 dos exemplos. O espaço ocupado considera as categorias já presentes, incluindo itens que não estão disponíveis como recompensa. Volume desconhecido, sobreposição e posição inválida fazem a entrega falhar sem gravar.
- Todo o lote é validado e posicionado antes da primeira inserção. Se não houver espaço, nenhuma unidade é entregue e a coleta pode ser tentada novamente.
- A conta precisa estar **desconectada do jogo** (`Player.LogOn = 'LOGOFF'`). O painel bloqueia a linha da conta e do personagem durante a transação. Ao entrar novamente, o jogo carrega a bag com os itens recebidos.
- São gravados `OwnerID = nome do personagem`, `Storage = 0`, `StorageID = 0` e coordenadas livres. A durabilidade vem do catálogo. Campos extras v2, como OptionType2 e HeroOption, são inicializados quando existem; campos desconhecidos obrigatórios sem default recusam a entrega.
- O ObjectID transitório é zero; o registro de objetos do servidor atribui o ID em memória ao carregar o personagem. Não se copiam IDs de objetos de personagens conectados.
- O schema auxiliar é versão 8. Recompensas da adaptação anterior por GoodsID recebem categoria vazia e **precisam ser removidas/recadastradas**; não são reinterpretadas como ItemType.

## Sequência de IDs — configuração obrigatória

Os servidores mantêm um contador ItemID em memória por categoria. Fazer apenas `MAX(ItemID)+1` pelo PHP conflita com itens que o jogo cria. Esta edição usa a mesma divisão por `ServerCount`/`ServerID` do código DK, reservando um slot exclusivamente ao painel.

Em um servidor único, o exemplo de configuração é:

- **Todos os gameservers:** `ServerCount : 2` e `ServerID : 0`.
- **Painel:** `DK_ITEM_ID_STEP = 2` e `DK_ITEM_ID_SLOT = 1`.

Depois de backup e validação em ambiente de teste, reinicie todos os gameservers após configurar o ServerCount. O jogo cria IDs pares e o painel cria IDs ímpares acima do maior existente. Em ambientes com múltiplos gameservers, use o mesmo ServerCount em todos e reserve um slot que nenhum deles utilize.

O instalador exige a confirmação dessa configuração. O painel valida os limites locais, mas **não consegue comprovar a configuração nem o reinício de servidores remotos**. Não ative a entrega até que todos estejam alinhados. Os exemplos de servidor em Downloads não são alterados pelo painel.

As travas por categoria permanecem até o commit/rollback, junto às travas de coleta por conta/fingerprint. IDs ficam limitados a 32 bits, conforme os tipos do servidor. Personagens conectados não recebem gravação direta do painel.

## Requisitos e instalação

- PHP 8.2 de 64 bits, `pdo_mysql`, `curl`, sessões e HTTPS.
- MySQL/MariaDB com utf8mb4, information_schema, InnoDB e GET_LOCK/RELEASE_LOCK. O MySQL 4.1 do exemplo v2 precisa de migração; a seleção do perfil v2 não torna esse banco legado compatível.
- Player, tabelas de personagens e as tabelas CategoriaObject usadas na entrega precisam ser InnoDB. O painel recusa tabelas não transacionais e não converte tabelas do jogo automaticamente.
- Banco do jogo e tabelas auxiliares acessíveis na mesma conexão. Forks que separam contas/personagens em bancos diferentes precisam de adaptação adicional.

1. Faça backup e teste o banco/servidor antes de migrar ou mudar ServerCount.
2. Alinhe a sequência reservada acima e reinicie os gameservers.
3. Publique os arquivos com as regras de proteção HTTP da hospedagem.
4. Abra `install.php`, selecione o perfil DK, banco, conta administradora existente, formato de senha, ServerCount e slot reservado.
5. Conclua a criação do schema. O assistente gera config.php e .installed e tenta remover install.php.
6. Cadastre 4TOP primeiro, configure os demais tops compatíveis com seu jogo e cadastre categoria, ItemType e quantidade.
7. Teste uma coleta com a conta desconectada e confira a bag ao entrar no jogo.

O fluxo mantém verificação dos tops ativos, CSRF, anticheat, logs e intervalo de 12 horas entre coletas. Adaptadores de votação existentes foram preservados; confirme a adequação dos rankings específicos de L2 antes de cadastrá-los. Proteja config.php e logs contra download e não publique credenciais ou dados de jogadores.

## Módulos e testes

- `includes/game_projects.php`: perfis DK e formatos de senha.
- `includes/core.php`, `includes/auth.php`: conta, personagem e permissões.
- `includes/inventory.php`: espaço na bag, IDs, schema dos itens e inserção direta.
- `includes/item_races.php`: raças aceitas pelos loaders dos dois projetos de referência.
- `includes/helpers.php`: votação, transação da coleta, schema 8 e logs.
- `install.php`, `config.sample.php`, `admin.php`, `vote.php`: configuração e formulários.
- `assets/js/i18n.js`, `assets/css/main.css`: mensagens e apresentação.

Execute `php tests/darkeden.php darkeden_master` e `php tests/darkeden.php darkeden_v2`. O terceiro argumento opcional seleciona plain, mysql_old ou mysql_password. São testes com banco simulado, cobrindo hashes, autorização, raça, bag cheia, ocupação, durabilidade, campos v2, IDs reservados e recusa de gravações parciais. A validação com MySQL/MariaDB e cliente DK reais continua obrigatória antes de produção.

Desenvolvido por [4Top Servers](https://top.4teambr.com).