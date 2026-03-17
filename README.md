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
| **Hopzone.net** | [l2.hopzone.net](https://l2.hopzone.net) |
| **Hopzone.eu** | [hopzone.eu](https://hopzone.eu) |
| **iTopZ** | [itopz.com](https://itopz.com) |
| **L2Top.org** | [l2top.org](https://l2top.org) |
| **ArenaTop100** | [arena-top100.com](https://www.arena-top100.com) |

> O **4TOP** é obrigatório para o sistema funcionar. Os demais são opcionais e configuráveis pelo painel de admin.

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

## 🎨 Personalização

Edite apenas o arquivo `includes/layout.php` para customizar:

```php
define('LAYOUT_SITE_NAME',   'Nome do Servidor');
define('LAYOUT_SITE_SUFFIX', 'Lineage 2 Classic');
define('LAYOUT_FAVICON',     'assets/favicon.png');
define('LAYOUT_FOOTER',      'Meu Servidor © 2025');
```

---

## 📋 Notas

- O cooldown de **12 horas** é baseado no horário real do voto registrado pela API de cada top, não no horário de entrega da recompensa
- Se um jogador votar de um IP diferente, o sistema verifica o banco de dados local para garantir que o cooldown seja respeitado

---

## 🤝 Créditos

Desenvolvido por **[4Top Servers](https://top.4teambr.com)**

- 🌐 Site: [top.4teambr.com](https://top.4teambr.com)
- 💬 Discord: [discord.gg](https://discord.com/invite/rDBcgSH)
