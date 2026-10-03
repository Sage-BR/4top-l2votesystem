# VoteSystem 4Top Servers

Painel de votação para servidores de **Lineage 2**. O jogador entra com a conta do jogo, verifica os votos e escolhe o personagem que receberá os itens. A entrega grava diretamente no banco do jogo, sem mod Java ou cron.

## Recursos

- Oito tops disponíveis, com **4TOP obrigatório**, cadastrado primeiro e mantido ativo.
- Verificação de todos os tops ativos antes da coleta.
- Escolha de personagem da conta e sem exclusão agendada.
- Recompensas configuráveis por ID de item, quantidade e descrição.
- Intervalo de **12 horas entre coletas**, por conta e identificação do navegador quando disponível.
- Admin para cadastro, edição, ativação e remoção de tops, recompensas e consulta de logs.
- Anticheat opcional para sinais de VPN, proxy e conexão suspeita.
- Português brasileiro, inglês, espanhol e russo, incluindo avisos e modais de integração.

## Tops suportados

O catálogo fica em `includes/helpers.php`. As instruções específicas aparecem nos modais do admin.

| Top | Identificador no cadastro | Token / API Key | Confirmação |
|---|---|---|---|
| [4TOP](https://top.4teambr.com) | Identificador do servidor no ranking | API Key do painel | API vinculada à conta; obrigatório |
| [L2JBrasil](https://top.l2jbrasil.com) | Username do servidor, parâmetro `u` | O adaptador atual não usa o token na consulta | API por `player_id` derivado do login; consulta secundária por IP |
| [L2Top.org](https://l2top.org) | ID do servidor | Token da API | API por login |
| [L2Network](https://l2network.eu) | Valor exato de `u` do link oficial | API Key | API por login |
| [Hopzone.eu](https://hopzone.eu) | ID numérico do servidor | API Key | API com `vote_id` vinculado à conta |
| [MMTop200](https://mmtop200.com) | ID numérico do servidor | API Key de Vote Checker | Postback autenticado por conta |
| [GamingTop100](https://www.gamingtop100.net) | ID numérico do servidor | Não utilizado neste adaptador | Postback com referência numérica vinculada à conta |
| [Top100Arena](https://www.top100arena.com) | ID numérico do servidor | Não utilizado neste adaptador | Postback com referência numérica vinculada à conta |

Os nomes `4top.php`, `l2network.php` etc. são identificadores dos adaptadores no cadastro. Não são arquivos de uma pasta `tops/`: os handlers são carregados localmente pelos módulos de `includes/`.

### L2Network

Informe o **username do proprietário do cadastro** presente em `u` no link oficial de votação. Ele pode ser diferente do nome exibido do servidor e do ID numérico da página de detalhes. Se o link abrir `details///`, confira esse valor.

O link envia o login do jogador em `id`; a API recebe `apiKey`, `type=2` e `player=login`. Respostas `-1` e `0` não comprovam voto recente. Um timestamp positivo precisa estar na janela de 12 horas.

### Hopzone.eu

O botão usa a ação `hopzone_vote` de `voteapi.php` para gerar a URL pela API e salvar o `vote_id` associado ao login em `4top_hopzone_votes`.

Reaberturas reutilizam votos pendentes por até 12 horas ou votos concluídos ainda válidos. A confirmação exige `status=completed`, servidor e identificador correspondentes e voto recente. A consulta por IP é usada quando não existe vínculo salvo para a conta; um voto vinculado não pode confirmar outra conta.

### Postbacks

| Top | Callback a configurar no painel do top |
|---|---|
| MMTop200 | `https://seu-dominio/voteapi.php` |
| GamingTop100 | `https://seu-dominio/voteapi.php` |
| Top100Arena | `https://seu-dominio/voteapi.php?postback=` |

Use o endereço completo exibido pelo admin, incluindo a subpasta da instalação quando houver. No Top100Arena, preserve `?postback=`: o top acrescenta a referência do incentivo.

MMTop200 envia o login. GamingTop100 e Top100Arena usam referências numéricas armazenadas em `4top_postback_refs`. A confirmação exige origem autenticada e vínculo com a conta; IP sozinho não substitui o postback.

As origens padrão ficam em `includes/postbacktops.php`. A constante opcional `VOTE_POSTBACK_ALLOWED_IPS` permite configurar listas de IPs por adaptador (`mmtop200.php`, `gamingtop100.php`, `top100arena.php`). Duplicatas na janela de 12 horas preservam o horário da primeira confirmação. Callbacks não entregam itens diretamente.

## Projetos e schemas do jogo

| `GAME_PROJECT` | Projeto | Coluna do personagem | Particularidade do INSERT em items |
|---|---|---|---|
| `acis` | aCis antigo e atual | `obj_Id` | `mana_left`; `time` omitido para usar o default quando existir |
| `l2jorion` | L2JOrion | `obj_Id` | `mana_left` |
| `l2jmobius` | L2JMobius | `charId` | `mana_left` e `time` |
| `l2jsunrise` | L2JSunrise | `charId` | `mana_left` e `time` |
| `l2mythras` | L2Mythras | `obj_Id` | Campos próprios de atributos, vida útil e aparência |
| `l2jlisvus` | L2JLisvus | `obj_Id` | Sem `mana_left` e `time` no INSERT |
| `l2jserver` | L2J4TeamC2 | `obj_Id` | `time_of_use` |

A verificação de senha detecta BCrypt pelo prefixo `$2` e comprimento de 60 caracteres; o outro formato é SHA-1 Base64. O perfil aCis aceita ambos sem alterar a configuração. O projeto selecionado define as colunas e o INSERT dos itens; forks personalizados precisam ser comparados com `includes/core.php`.

Personagens são filtrados por `account_name` e `COALESCE(deletetime, 0) = 0`, aceitando valores nulos de bases antigas, e ordenados por `lastAccess`.

## Requisitos

- **PHP 8.2** como referência usada nesta manutenção. O código atual não deve ser anunciado como compatível com PHP 5.6: usa recursos posteriores, como `??` e opções modernas de sessão.
- Extensões `pdo_mysql` e `curl`, sessões funcionando e diretório de sessão gravável.
- MySQL/MariaDB com transações e bloqueios nomeados `GET_LOCK`/`RELEASE_LOCK`.
- Tabelas transacionais, como InnoDB, para que o rollback proteja a entrega.
- Acesso às tabelas do jogo e saída de rede para as APIs dos tops e consultas do anticheat.
- Permissão de escrita na pasta da aplicação para os logs.

O usuário do banco precisa de leitura e gravação nas tabelas usadas. Instalação e atualização do schema também exigem `CREATE`, `ALTER` e criação de índices.

## Instalação

1. Envie os arquivos ao servidor web, incluindo `.htaccess` ou `web.config` conforme o servidor.
2. Abra `install.php` e selecione o projeto do jogo.
3. Informe a conexão com o banco que contém `accounts`, `characters` e `items`.
4. Crie as tabelas pelo assistente, que gera `config.php`. As páginas também exigem o arquivo marcador `.installed`: confirme sua existência na raiz. O instalador atual não o cria; em uma instalação nova, crie esse arquivo vazio após concluir as tabelas.
5. Entre com uma conta administrativa. O nível mínimo padrão é **1**, configurável por `VS_ADMIN_ACCESS_LEVEL`.
6. Adicione o 4TOP primeiro e os demais tops desejados.
7. Leia as instruções de integração e configure os callbacks necessários.
8. Cadastre os itens e ajuste o anticheat pelo admin.
9. Confirme a remoção de `install.php`: o assistente tenta removê-lo automaticamente após criar as tabelas. Se não conseguir, remova ou renomeie manualmente.

`config.sample.php` documenta as opções de banco, projeto, proxies e anticheat. Não publique credenciais reais nem envie `config.php` ao controle de versão.

## Votação e entrega

1. O jogador entra com a conta do jogo e abre os sites pelos banners.
2. Conclui os votos em todos os tops ativos.
3. Clica em **Verificar Votos**; o sistema consulta os registros locais e adaptadores apropriados.
4. A sessão recebe a autorização de coleta e o jogador escolhe o personagem.
5. Ao clicar em **Receber Recompensa**, o servidor valida o personagem, obtém as travas e confere o intervalo de coleta.
6. Os itens e os registros são gravados na transação. Após o commit, a autorização é removida e a confirmação aparece.

A autorização é preservada em caso de falha antes da conclusão para permitir nova tentativa. O botão bloqueia cliques repetidos; as travas e o cooldown protegem a coleta. A sessão é liberada durante consultas externas.

Há duas janelas distintas: a validade do voto usa seu horário de confirmação, quando fornecido pelo top; o intervalo entre coletas usa `claimed_at` em `4top_reward_claims`.

Os itens são inseridos em `items` com `loc = INVENTORY`. A atualização visual depende do servidor de jogo: a gravação no banco não envia um pacote de atualização ao cliente conectado.

## Estrutura de arquivos

```text
/
├── index.php                     # Login
├── logout.php                    # Encerramento da sessão
├── vote.php                      # Votação, verificação e coleta por AJAX
├── admin.php                     # Tops, recompensas, anticheat e logs
├── voteapi.php                   # APIs, postbacks e URL Hopzone
├── vote_register.php             # Endpoint legado de registro; não entrega itens
├── install.php                   # Assistente de instalação
├── config.sample.php             # Configuração de exemplo
├── config.php                    # Gerado na instalação; contém credenciais
├── .installed                    # Marcador exigido pelas páginas; verificar na instalação
├── .htaccess                     # Proteção Apache/LiteSpeed
├── web.config                    # Proteção IIS
├── README.md
├── assets/
│   ├── favicon.png
│   ├── css/main.css              # Estilos
│   ├── js/i18n.js                # Dicionários e aplicação das traduções
│   └── buttons/                  # Banners e imagem padrão
├── engine/
│   └── anticheat.php             # Análise de risco e cache
└── includes/
    ├── bootstrap.php             # Carregamento, schema e helpers de layout
    ├── layout.php                # Marca, navegação e rodapé
    ├── auth.php                  # Controle de acesso
    ├── db.php                    # Conexão PDO
    ├── core.php                  # Senhas, personagens, itens, IP e sessão
    ├── helpers.php               # Catálogo, schema, CRUD, votos e coleta
    ├── top_handlers.php          # Adaptadores das APIs
    ├── hopzoneeu.php             # Vínculo e consulta Hopzone
    ├── postbacktops.php          # Callbacks e referências
    ├── integration_help.php      # Modais de integração
    └── reward_debug.php          # Debug de verificação e entrega
```

Logs na raiz: `vote_api.log` e `reward_delivery.log`, conforme o fluxo executado. `vote_register.log` e `voteapi.log` aparecem nas regras de proteção legadas, mas não são os logs gravados pelos adaptadores atuais. Não existe `vote_callback.php`: os postbacks são recebidos por `voteapi.php`.

## Tabelas auxiliares

| Tabela | Finalidade |
|---|---|
| `4top_tops` | Cadastro, credenciais, ativação e ordenação |
| `4top_rewards` | Itens, quantidades e descrições |
| `4top_log` | Votos por top e marcação de recompensa |
| `4top_reward_claims` | Coletas e intervalo por conta/HWID |
| `4top_hopzone_votes` | Conta e identificador do voto Hopzone |
| `4top_postback_refs` | Referências numéricas dos postbacks |
| `4top_anticheat_log` | Detecções e bloqueios |
| `4top_settings` | Configurações persistidas pelo admin |

`ensureVoteSchema()` cria e atualiza as tabelas. O schema atual é **versão 7**; a conferência é guardada em sessão por até uma hora. A primeira atualização pode demorar em históricos grandes por causa dos índices.

No admin, entregas são agrupadas por conta e horário da entrega; pendentes, por conta, IP e dia. Apenas os tops confirmados naquela coleta são marcados como recompensados.

## IP e anticheat

`VS_TRUSTED_PROXY_CIDRS` configura os proxies confiáveis usados para resolver o IP real. O resolvedor prefere IPv4 entre candidatos válidos e mantém IPv6 quando não há IPv4 real disponível. Não converte IPv6 artificialmente nem troca o cliente por outro salto da cadeia de proxies.

O anticheat pode ser ligado ou desligado no admin. Seus padrões são configurados por `VS_ANTICHEAT_ENABLED`, `VS_ANTICHEAT_RISK_BLOCK`, `VS_ANTICHEAT_CACHE_SEC` e `VS_ANTICHEAT_IPAPI_TIMEOUT`. Falhas da consulta externa não bloqueiam o jogador por si só.

## Traduções e personalização

- Identidade visual, favicon e rodapé: `includes/layout.php`.
- Estilos: `assets/css/main.css`.
- Dicionários e instruções traduzidas: `assets/js/i18n.js`.
- Textos HTML usam `data-i18n`; avisos AJAX usam `msg_key`.
- O idioma é preservado em memória, cookie e armazenamento do navegador quando disponível.
- O script de tradução recebe uma versão baseada na data de modificação para atualizar o cache.
- Nomes de servidores, jogadores e descrições de itens cadastrados são conteúdo personalizado e não recebem tradução automática.

## Debug da entrega

`reward_delivery.log` registra linhas JSON com horário **UTC**, identificador aleatório da requisição, etapa, tipo da exceção, arquivo/linha e códigos SQL quando disponíveis. Não registra login, IP, senha, token ou conteúdo livre de consultas SQL.

| Registro ou sintoma | Investigação |
|---|---|
| `db_connection_exception` / HTTP 503 | Conexão com o banco; se o 503 vier da hospedagem, consulte os logs dela |
| `request_exception` / HTTP 500 | Tipo da exceção, arquivo e linha |
| `delivery_exception` | Etapa da entrega e códigos SQL |
| `transaction_committed` | Transação da coleta concluída |
| Verificação expirada | Sessão sem autorização; verificar votos novamente |
| Postback pendente | Origem do callback e vínculo da conta em `vote_api.log` |

Consultas de travas precisam consumir o resultado e fechar o cursor; resultados pendentes podem causar o erro MySQL **2014**. O código trata essas consultas em `core.php` e `helpers.php`.

Para reportar falhas, envie o status HTTP e as linhas relativas à mesma requisição. Não compartilhe cookies, credenciais ou tokens. O debug precisa de permissão de escrita e não tem rotação automática: acompanhe o tamanho e arquive os registros conforme necessário.

## Atualização e publicação

- Envie juntos os módulos PHP, assets e arquivos de proteção.
- Preserve `config.php`, `.installed` e os dados existentes; faça backup do banco antes de atualizar o schema.
- Confira que credenciais e logs não podem ser baixados por HTTP. `.htaccess` e `web.config` protegem `reward_delivery.log`; confira também a proteção de `vote_api.log` na hospedagem.
- Em Nginx, configure restrições equivalentes: ele não aplica `.htaccess` nem `web.config`.
- Os adaptadores consultam as APIs diretamente; o painel não faz HTTP para si mesmo.

## Créditos

Desenvolvido por **[4Top Servers](https://top.4teambr.com)**.

- [Site](https://top.4teambr.com)
- [Discord](https://discord.com/invite/rDBcgSH)
