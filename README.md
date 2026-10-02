# 🗳️ VoteSystem 4Top Servers

> Sistema de votação para servidores de **Lineage 2** — vote nos principais sites de ranking, ganhe recompensas in-game automaticamente.

---

## ✨ O que é

O VoteSystem é um painel web que permite aos jogadores do seu servidor votarem nos principais sites de ranking de Lineage 2 e receberem recompensas automaticamente no personagem. Tudo sem mod Java, sem cron job — a entrega é feita diretamente no banco de dados do jogo.

---

## 🗳️ Sites de Votação Suportados

| Site | Link |
|---|---|
| **4TOP** *(obrigatório)* | [top.4teambr.com](https://top.4teambr.com) |
| **L2JBrasil** | [top.l2jbrasil.com](https://top.l2jbrasil.com) |
| **Hopzone.eu** | [hopzone.eu](https://hopzone.eu) |
| **MMTop200** | [mmtop200.com](https://mmtop200.com) |
| **GamingTop100** | [gamingtop100.net](https://www.gamingtop100.net) |
| **Top100Arena** | [top100arena.com](https://www.top100arena.com) |
| **L2Top.org** | [l2top.org](https://l2top.org) |
| **L2Network** | [l2network.eu](https://l2network.eu) |

> O **4TOP** é obrigatório para o sistema funcionar. Os demais são opcionais e configuráveis pelo painel de admin.

### Hopzone.eu

Cadastre o ID numérico do servidor e a API Key no admin. Ao abrir o botão de voto,
A ação `hopzone_vote` em `voteapi.php` gera uma URL pela API e salva o `vote_id` associado ao login.
Reaberturas reutilizam o voto pendente por até 12 horas ou o voto concluído ainda válido.
A verificação exige `status=completed`, servidor/identificador correspondentes e voto
com menos de 12 horas. O IP só é consultado quando não existe vínculo salvo para a conta;
um voto encontrado por IP é vinculado à conta e não pode confirmar outra conta.
Cliques, votos pendentes e falhas da API não confirmam votos nem entregam recompensas.
Publique também `includes/hopzoneeu.php` e `assets/buttons/hopzoneu.png`.
A tabela `4top_hopzone_votes` é criada automaticamente na atualização do schema.
Contrato: https://hopzone.eu/docs.html/

### MMTop200, GamingTop100 e Top100Arena

Integrações adaptadas do painel Loong: confirmação por postback autenticado e vinculado
ao login. Checker por IP não comprova a conta e não libera a recompensa nesses tops.
O horário da primeira confirmação por postback é preservado durante a janela de 12h.
Registros antigos confirmados somente por IP não substituem um postback da conta.
MMTop200 envia o login no link; GamingTop100 e Top100Arena exigem uma referência numérica,
associada ao login na tabela automática `4top_postback_refs`.
Cadastre o ID numérico no admin e, para MMTop200, o Vote Checker Token no campo Token.
Configure o endereço HTTPS completo de `voteapi.php` como callback de MMTop200/GamingTop100;
no Top100Arena, use `voteapi.php?postback=` (o top acrescenta a referência do incentivo).
O admin exibe o link de configuração de cada cadastro. MMTop200 valida a origem DNS
`validator.mmtop200.com` sem senha adicional; GamingTop100 usa `gamingtop100.net`.
Top100Arena usa a origem `3.86.48.116` do código de referência. Para origens oficiais
atualizadas, `VOTE_POSTBACK_ALLOWED_IPS` pode ser definido em config.php como um array
de nomes de botão (`mmtop200.php`, `gamingtop100.php`, `top100arena.php`) para listas de IPs.
Callbacks rejeitam origens não autorizadas, não entregam recompensas diretamente e
duplicatas na janela de 12h não renovam o horário. Cliques não confirmam votos.
GamingTop100: https://www.gamingtop100.net/vote-check

---

## ⚙️ Projetos Compatíveis

| Projeto | Hash de Senha | Entrega de Reward |
|---|---|---|
| **aCis** (362 ~ 408) | SHA-1 Base64 | Direto no `items` |
| **aCis** (409+) | BCrypt | Direto no `items` |
| **L2JOrion** | SHA-1 Base64 | Direto no `items` |
| **L2JMobius** (all Chronicles) | SHA-1 Base64 | Direto no `items` |
| **L2JSunrise** | SHA-1 Base64 | Direto no `items` |
| **L2Mythras** | SHA-1 Base64 | Direto no `items` |
| **L2JLisvus** | SHA-1 Base64 | Direto no `items` |

> O sistema detecta automaticamente o tipo de hash armazenado (BCrypt vs SHA-1) — nenhuma configuração extra necessária ao migrar versões do aCis.

---

## 🖥️ Requisitos

| Requisito | Versão |
|---|---|
| **PHP** | 5.6 ~ 8.2 |
| **MySQL / MariaDB** | 5.7+ |
| **Extensão PHP** | `pdo_mysql`, `curl` |

---

## 🚀 Como Funciona

### Para o Jogador

1. Acessa o painel e faz login com a conta do servidor de jogo
2. Clica na imagem de cada site de votação — uma nova aba abre com o site
3. Vota de verdade no site que abriu
4. Repete para todos os tops disponíveis
5. Volta ao painel e clica em **Verificar Votos**
6. Escolhe o personagem que vai receber a recompensa
7. Clica em **Receber Recompensa** — os itens aparecem na bag automaticamente
8. Pode votar novamente após **12 horas**

### Para o Admin

- Acessa `admin.php` com uma conta com `access_level >= 1`
- Adiciona os sites de votação desejados com o ID/token de cada um
- Configura os itens de recompensa (ID do item + quantidade)
- Acompanha o log de votos agrupado por sessão

---

## 📁 Estrutura de Arquivos

```
/
├── index.php               # Página de login
├── vote.php                # Painel de votação do jogador
├── admin.php               # Painel de administração
├── vote_callback.php       # Callback para ArenaTop100 (postback)
├── vote_register.php       # Registro de votos via callback
├── install.php             # Assistente de instalação
├── config.php              # Gerado pelo install (não compartilhar)
├── assets/
│   ├── css/main.css
│   └── buttons/            # Imagens dos botões dos tops
└── includes/
    ├── bootstrap.php       # Carregamento e layout
    ├── layout.php          # ⭐ Edite aqui: título, favicon, logo, rodapé
    ├── core.php            # Autenticação e entrega de reward
    ├── helpers.php         # Lógica de votação e cooldown
    ├── auth.php            # Controle de sessão
    └── db.php              # Conexão PDO
```

---

## 🛠️ Instalação

1. Faça upload dos arquivos para o seu servidor web
2. Acesse `install.php` no navegador
3. Selecione o projeto do seu servidor (aCis, L2JMobius, etc.)
4. Preencha os dados de conexão com o banco do jogo
5. Clique em **Criar Tabelas**
6. Faça login com uma conta com `access_level >= 1` para acessar o admin
7. Adicione os tops e configure as recompensas
8. **Delete ou renomeie o `install.php`** após a instalação

---


## 📋 Notas

- O cooldown de **12 horas** é baseado no horário real do voto registrado pela API de cada top, não no horário de entrega da recompensa
- Se um jogador votar de um IP diferente, o sistema verifica o banco de dados local para garantir que o cooldown seja respeitado

---

## 🤝 Créditos

Desenvolvido por **[4Top Servers](https://top.4teambr.com)**

- 🌐 Site: [top.4teambr.com](https://top.4teambr.com)
- 💬 Discord: [discord.gg](https://discord.com/invite/rDBcgSH)
