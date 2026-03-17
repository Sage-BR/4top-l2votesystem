/**
 * VoteSystem — Sistema de Internacionalização (i18n)
 * ====================================================
 * Idiomas: pt (Português/BR), es (Español), en (English/US), ru (Русский)
 * Persistência: localForage (IndexedDB/WebSQL/localStorage) + localStorage sync
 *
 * Atributos HTML suportados:
 *   data-i18n="chave"             → define textContent
 *   data-i18n-html="chave"        → define innerHTML (permite tags HTML)
 *   data-i18n-placeholder="chave" → define atributo placeholder
 *   data-i18n-title="chave"       → define atributo title
 *   data-i18n-confirm="chave"     → define atributo onsubmit confirm
 */

(function (w) {
  'use strict';

  var STORAGE_KEY  = 'vs_lang';
  var DEFAULT_LANG = 'pt';
  var SUPPORTED    = ['pt', 'es', 'en', 'ru'];

  /* ══════════════════════════════════════════════════════════════════════════
     DICIONÁRIO DE TRADUÇÕES
  ══════════════════════════════════════════════════════════════════════════ */
  var dict = {

    /* ─── pt — Português (Brasil) ──────────────────────────────────────── */
    pt: {
      // Navbar
      nav_vote:   '⚜ Votar',
      nav_admin:  '⚙ Admin',
      nav_logout: 'Sair',
      nav_login:  'Login',

      // Login
      login_nologin_warn:   '⚠ Você precisa estar logado para acessar essa página.',
      login_card_title:     '🔐 Acesso do Jogador',
      login_account_label:  'Login da Conta',
      login_password_label: 'Senha',
      login_ph_login:       'seu_login',
      login_show_pwd:       'Mostrar senha',
      login_submit:         '⚜ Entrar',
      login_hint_main:      'Use a mesma conta e senha do servidor de jogo.',
      login_hint_sub:       'Vote diariamente para ganhar recompensas!',
      login_submitting:     'Entrando...',
      login_error_fields:   '✗ Preencha o login e a senha.',
      login_error_creds:    '✗ Login ou senha incorretos.',

      // Vote — hero
      vote_eyebrow:  '⚜ Vote & Ganhe',
      vote_title:    'Painel de Votação',
      vote_subtitle: 'Vote nos tops para apoiar o servidor e ganhar recompensas exclusivas!',

      // Vote — stats
      stat_total_votes:  'Votos Totais',
      stat_available:    'Tops Disponíveis',
      stat_reward_items: 'Itens de Reward',
      stat_active_tops:  'Tops Ativos',

      // Vote — alertas
      warn_vote_disabled:   '⚠ <strong>Votação indisponível.</strong>',
      warn_4top_required:   'O administrador ainda não configurou o 4TOP, que é obrigatório para a votação funcionar.',
      warn_no_tops:         '⚠ Nenhum TOP configurado ainda.',
      admin_configure_link: 'Configure no painel de admin →',

      // Vote — tops
      section_vote_sites:   '🗳 Sites de Votação',
      top_available_status: '● Disponível',
      top_cooldown_status:  '⏳ Em cooldown',
      top_next_vote:        '⏱ Próximo voto em:',
      top_voted_title:      'Já votado',

      // Vote — caixa de coleta
      claim_daily_reward:       'Recompensa Diária',
      claim_vote_all:           'Vote em todos os tops e clique abaixo para verificar.',
      btn_check_votes:          '⚔ Verificar Votos',
      claim_choose_char:        'Escolha o personagem que vai receber:',
      btn_claim_reward:         '🎁 Receber Recompensa',
      reward_delivered_title:   'Recompensa entregue!',
      reward_delivered_sub:     'Volte em 12h para votar novamente.',

      // Vote — card de rewards
      card_rewards_title:   '🎁 Recompensas por Voto',
      no_reward_configured: 'Nenhum reward configurado.',
      reward_auto_delivery: 'Os itens serão entregues ao seu personagem automaticamente após o voto ser confirmado.',

      // Vote — card como votar
      card_how_to_vote: '📖 Como Votar',
      vote_step1: 'Clique na imagem do top para abrir o site de votação',
      vote_step2: 'Vote de verdade no site que abrir na nova aba',
      vote_step3: 'Repita para todos os tops disponíveis',
      vote_step4: 'Volte para esta página — o sistema detecta seu voto automaticamente',
      vote_step5: 'Clique em <strong style="color:var(--gold)">Entregar Recompensa</strong> para receber os itens',
      vote_step6: 'Você poderá votar novamente após <strong style="color:var(--gold)">12 horas</strong>',

      // Admin — hero
      admin_eyebrow:        '⚙ Painel Administrativo',
      admin_hero_title:     'Gerenciar VoteSystem',
      admin_hero_subtitle:  'Configure tops de votação, rewards e monitore a atividade dos jogadores.',

      // Admin — stats
      admin_stat_total: 'Votos Total',
      admin_stat_today: 'Votos Hoje',
      admin_stat_tops:  'Tops Cadastrados',

      // Admin — form de top
      admin_add_top_title:  '➕ Adicionar Site de TOP',
      admin_4top_req_warn:  '⚠ <strong>O 4TOP é obrigatório.</strong> Adicione o 4TOP antes de qualquer outro site de votação.',
      admin_top_sel_label:  'Site de Votação',
      admin_top_sel_ph:     '— Selecione o site —',
      admin_top_name_label: 'Nome do Top',
      admin_top_name_ph:    'ex: L2JBrasil',
      admin_top_id_label:   'ID do Servidor no Top',
      admin_top_id_ph:      'ex: 12345 (veja no painel do site de votação)',
      admin_site_hint:      'ℹ Encontre seu ID em:',
      admin_token_label:    'Token / API Key',
      admin_token_req:      '(obrigatório para este top)',
      admin_token_ph:       'Cole o token gerado no painel do site de votação',
      admin_url_auto_info:  'ℹ As URLs de votação são geradas automaticamente. A ordem é definida automaticamente (4TOP sempre em 1º).',
      admin_btn_add_top:    '✓ Adicionar Top',

      // Admin — lista de tops
      admin_tops_list_title: '🏆 Tops Cadastrados',
      admin_no_tops:         'Nenhum top cadastrado ainda.',
      col_name:              'Nome',
      col_id:                'ID',
      col_status:            'Status',
      col_actions:           'Ações',
      badge_active:          'Ativo',
      badge_inactive:        'Inativo',
      confirm_remove_top:    'Remover este top?',
      title_disable:         'Desativar',
      title_enable:          'Ativar',
      title_4top_no_disable: 'O 4TOP não pode ser desativado',

      // Admin — form de rewards
      admin_reward_cfg_title: '🎁 Configurar Rewards por Voto',
      admin_reward_cfg_desc:  'Adicione os itens que serão entregues ao jogador a cada voto. Você pode adicionar múltiplos itens de uma vez.',
      admin_reward_item_id:   'Item ID',
      admin_reward_qty:       'Quantidade',
      admin_reward_name_lbl:  'Nome (Ex: "Adena")',
      admin_reward_name_ph:   'Nome do item para exibição',
      admin_btn_save_rewards: '✓ Salvar Rewards',
      admin_btn_add_more:     'Adicionar mais',
      admin_btn_remove_row:   'Remover',

      // Admin — lista de rewards
      admin_rewards_list_title: '📦 Rewards Configurados',
      admin_no_rewards:         'Nenhum reward configurado.',
      col_qty:                  'Qtd',
      col_item_name:            'Nome',
      confirm_remove_reward:    'Remover este reward?',
      confirm_clear_rewards:    'Remover TODOS os rewards?',
      admin_btn_clear_rewards:  '🗑 Limpar Todos',

      // Admin — log de votos
      admin_log_title:    '📊 Log de Votos Recentes',
      admin_log_subtitle: 'Últimas 15 sessões',
      admin_no_log:       'Nenhum voto registrado ainda.',
      col_login:          'Login',
      col_tops_voted:     'Tops Votados',
      col_ip:             'IP',
      col_datetime:       'Data/Hora',
      col_reward:         'Reward',
      badge_delivered:    'Entregue',
      badge_pending:      'Pendente',

      // Mensagens AJAX (msg_key do servidor)
      msg_cooldown:       '⏳ Você já coletou sua recompensa nas últimas 12 horas.',
      msg_not_voted:      '⚠ Vote em todos os tops antes de coletar.',
      msg_all_confirmed:  '✅ Todos os votos confirmados! Escolha o personagem.',
      msg_no_chars:       '⚠ Nenhum personagem encontrado. Crie um personagem no jogo primeiro.',
      msg_reward_ok:      '🎁 Recompensa entregue com sucesso!',
      msg_expired:        '❌ Verificação expirada. Clique em Verificar Votos novamente.',
      msg_invalid_char:   '❌ Personagem inválido.',
      msg_reward_error:   '❌ Erro ao entregar recompensa. Tente novamente.',
      msg_select_char:    'Selecione um personagem.',
      msg_connect_error:  'Erro ao conectar. Tente novamente.',
      msg_checking_votes: '⏳ Verificando votos...',
      msg_confirming:     '⏳ Confirmando...',
      msg_delivering:     '⏳ Entregando...',
      msg_entering:       'Entrando...',

      // Install
      install_title:          'VoteSystem 4Top — Instalação',
      install_subtitle:       '4Top Servers — Assistente de Instalação',
      install_footer:         'VoteSystem 4Top Servers — by 4TeamBR',
      install_step1:          'Projeto',
      install_step2:          'Banco',
      install_step3:          'Tabelas',
      install_step4:          'Pronto',
      install_s1_title:       '⚙ Selecione o Projeto L2J',
      install_s1_desc:        'Escolha o emulador do servidor. Isso define como as senhas são verificadas e como os rewards são entregues aos jogadores.',
      install_s1_info:        'ℹ Tops e rewards são configurados depois, no painel de admin.',
      install_s1_btn:         'Próximo — Configurar Banco de Dados ›',
      install_s2_title:       '🗄 Configuração MySQL',
      install_s2_warn:        '⚠ Use o banco de dados do servidor onde ficam as contas dos jogadores.',
      install_s2_host:        'Host',
      install_s2_user:        'Usuário',
      install_s2_pass:        'Senha',
      install_s2_dbname:      'Nome do Banco (Database)',
      install_s2_back:        '‹ Voltar',
      install_s2_btn:         'Testar Conexão & Continuar ›',
      install_s3_title:       '📋 Criar Tabelas',
      install_s3_ok:          '✓ Conexão com o banco de dados estabelecida com sucesso!',
      install_s3_desc:        'As tabelas abaixo serão criadas. Tabelas existentes não serão afetadas:',
      install_s3_info:        '✅ Rewards são inseridos diretamente na tabela items do jogo — sem mod Java ou cron necessário.',
      install_s3_btn:         '✓ Criar Tabelas e Finalizar ›',
      install_s4_title:       'Instalação Concluída!',
      install_s4_desc:        'O VoteSystem está pronto. Faça login com uma conta que tenha access_level ≥ 1 para configurar tops e rewards.',
      install_s4_reward_title:'✅ Entrega de Reward:',
      install_s4_reward_desc: 'Os itens são inseridos diretamente em items no personagem escolhido.',
      install_s4_reward_sub:  'Nenhum mod Java ou cron necessário.',
      install_s4_sec_title:   '🔒 Segurança:',
      install_s4_sec_desc:    'Exclua ou renomeie install.php após configurar o sistema!',
      install_s4_btn:         '⚜ Ir para o VoteSystem',
      install_tbl_tops:       'Sites de TOP configurados',
      install_tbl_rewards:    'Itens de recompensa por voto',
      install_tbl_log:        'Histórico de votos',
      install_tbl_claims:     'Registro de recompensas coletadas',
    },

    /* ─── es — Español ─────────────────────────────────────────────── */
    es: {
      nav_vote:   '⚜ Votar',
      nav_admin:  '⚙ Admin',
      nav_logout: 'Salir',
      nav_login:  'Iniciar Sesión',

      login_nologin_warn:   '⚠ Necesitas estar conectado para acceder a esta página.',
      login_card_title:     '🔐 Acceso del Jugador',
      login_account_label:  'Usuario de la Cuenta',
      login_password_label: 'Contraseña',
      login_ph_login:       'tu_usuario',
      login_show_pwd:       'Mostrar contraseña',
      login_submit:         '⚜ Entrar',
      login_hint_main:      'Usa la misma cuenta y contraseña del servidor de juego.',
      login_hint_sub:       '¡Vota diariamente para ganar recompensas!',
      login_submitting:     'Entrando...',
      login_error_fields:   '✗ Completa el usuario y la contraseña.',
      login_error_creds:    '✗ Usuario o contraseña incorrectos.',

      vote_eyebrow:  '⚜ Vota & Gana',
      vote_title:    'Panel de Votación',
      vote_subtitle: '¡Vota en los tops para apoyar el servidor y ganar recompensas exclusivas!',

      stat_total_votes:  'Votos Totales',
      stat_available:    'Tops Disponibles',
      stat_reward_items: 'Ítems de Reward',
      stat_active_tops:  'Tops Activos',

      warn_vote_disabled:   '⚠ <strong>Votación no disponible.</strong>',
      warn_4top_required:   'El administrador aún no ha configurado el 4TOP, que es obligatorio para que la votación funcione.',
      warn_no_tops:         '⚠ Ningún TOP configurado aún.',
      admin_configure_link: 'Configurar en el panel de admin →',

      section_vote_sites:   '🗳 Sitios de Votación',
      top_available_status: '● Disponible',
      top_cooldown_status:  '⏳ En cooldown',
      top_next_vote:        '⏱ Próximo voto en:',
      top_voted_title:      'Ya votado',

      claim_daily_reward:     'Recompensa Diaria',
      claim_vote_all:         'Vota en todos los tops y haz clic abajo para verificar.',
      btn_check_votes:        '⚔ Verificar Votos',
      claim_choose_char:      'Elige el personaje que recibirá la recompensa:',
      btn_claim_reward:       '🎁 Recibir Recompensa',
      reward_delivered_title: '¡Recompensa entregada!',
      reward_delivered_sub:   'Vuelve en 12h para votar de nuevo.',

      card_rewards_title:   '🎁 Recompensas por Voto',
      no_reward_configured: 'Ninguna recompensa configurada.',
      reward_auto_delivery: 'Los ítems serán entregados a tu personaje automáticamente tras confirmar el voto.',

      card_how_to_vote: '📖 Cómo Votar',
      vote_step1: 'Haz clic en la imagen del top para abrir el sitio de votación',
      vote_step2: 'Vota de verdad en el sitio que se abrirá en la nueva pestaña',
      vote_step3: 'Repite para todos los tops disponibles',
      vote_step4: 'Vuelve a esta página — el sistema detecta tu voto automáticamente',
      vote_step5: 'Haz clic en <strong style="color:var(--gold)">Recibir Recompensa</strong> para obtener los ítems',
      vote_step6: 'Podrás votar nuevamente después de <strong style="color:var(--gold)">12 horas</strong>',

      admin_eyebrow:       '⚙ Panel Administrativo',
      admin_hero_title:    'Gestionar VoteSystem',
      admin_hero_subtitle: 'Configura tops de votación, recompensas y monitorea la actividad de los jugadores.',

      admin_stat_total: 'Total de Votos',
      admin_stat_today: 'Votos Hoy',
      admin_stat_tops:  'Tops Registrados',

      admin_add_top_title:  '➕ Agregar Sitio de TOP',
      admin_4top_req_warn:  '⚠ <strong>El 4TOP es obligatorio.</strong> Agrega el 4TOP antes que cualquier otro sitio de votación.',
      admin_top_sel_label:  'Sitio de Votación',
      admin_top_sel_ph:     '— Selecciona el sitio —',
      admin_top_name_label: 'Nombre del Top',
      admin_top_name_ph:    'ej: L2JBrasil',
      admin_top_id_label:   'ID del Servidor en el Top',
      admin_top_id_ph:      'ej: 12345 (ver en el panel del sitio de votación)',
      admin_site_hint:      'ℹ Encuentra tu ID en:',
      admin_token_label:    'Token / API Key',
      admin_token_req:      '(requerido para este top)',
      admin_token_ph:       'Pega el token generado en el panel del sitio de votación',
      admin_url_auto_info:  'ℹ Las URLs de votación se generan automáticamente. El orden se define automáticamente (4TOP siempre en 1°).',
      admin_btn_add_top:    '✓ Agregar Top',

      admin_tops_list_title: '🏆 Tops Registrados',
      admin_no_tops:         'Ningún top registrado aún.',
      col_name:              'Nombre',
      col_id:                'ID',
      col_status:            'Estado',
      col_actions:           'Acciones',
      badge_active:          'Activo',
      badge_inactive:        'Inactivo',
      confirm_remove_top:    '¿Eliminar este top?',
      title_disable:         'Desactivar',
      title_enable:          'Activar',
      title_4top_no_disable: 'El 4TOP no puede desactivarse',

      admin_reward_cfg_title: '🎁 Configurar Recompensas por Voto',
      admin_reward_cfg_desc:  'Agrega los ítems que se entregarán al jugador por cada voto. Puedes agregar múltiples ítems a la vez.',
      admin_reward_item_id:   'ID de Ítem',
      admin_reward_qty:       'Cantidad',
      admin_reward_name_lbl:  'Nombre (Ej: "Adena")',
      admin_reward_name_ph:   'Nombre del ítem para mostrar',
      admin_btn_save_rewards: '✓ Guardar Recompensas',
      admin_btn_add_more:     'Agregar más',
      admin_btn_remove_row:   'Eliminar',

      admin_rewards_list_title: '📦 Recompensas Configuradas',
      admin_no_rewards:         'Ninguna recompensa configurada.',
      col_qty:                  'Cant',
      col_item_name:            'Nombre',
      confirm_remove_reward:    '¿Eliminar esta recompensa?',
      confirm_clear_rewards:    '¿Eliminar TODAS las recompensas?',
      admin_btn_clear_rewards:  '🗑 Limpiar Todo',

      admin_log_title:    '📊 Log de Votos Recientes',
      admin_log_subtitle: 'Últimas 15 sesiones',
      admin_no_log:       'Ningún voto registrado aún.',
      col_login:          'Usuario',
      col_tops_voted:     'Tops Votados',
      col_ip:             'IP',
      col_datetime:       'Fecha/Hora',
      col_reward:         'Recompensa',
      badge_delivered:    'Entregado',
      badge_pending:      'Pendiente',

      msg_cooldown:       '⏳ Ya recogiste tu recompensa en las últimas 12 horas.',
      msg_not_voted:      '⚠ Vota en todos los tops antes de recoger.',
      msg_all_confirmed:  '✅ ¡Todos los votos confirmados! Elige el personaje.',
      msg_no_chars:       '⚠ No se encontraron personajes. Crea un personaje en el juego primero.',
      msg_reward_ok:      '🎁 ¡Recompensa entregada con éxito!',
      msg_expired:        '❌ Verificación expirada. Haz clic en Verificar Votos de nuevo.',
      msg_invalid_char:   '❌ Personaje inválido.',
      msg_reward_error:   '❌ Error al entregar la recompensa. Inténtalo de nuevo.',
      msg_select_char:    'Selecciona un personaje.',
      msg_connect_error:  'Error de conexión. Inténtalo de nuevo.',
      msg_checking_votes: '⏳ Verificando votos...',
      msg_confirming:     '⏳ Confirmando...',
      msg_delivering:     '⏳ Entregando...',
      msg_entering:       'Entrando...',

      // Install
      install_title:          'VoteSystem 4Top — Instalación',
      install_subtitle:       '4Top Servers — Asistente de Instalación',
      install_footer:         'VoteSystem 4Top Servers — by 4TeamBR',
      install_step1:          'Proyecto',
      install_step2:          'BD',
      install_step3:          'Tablas',
      install_step4:          'Listo',
      install_s1_title:       '⚙ Selecciona el Proyecto L2J',
      install_s1_desc:        'Elige el emulador del servidor. Esto define cómo se verifican las contraseñas y cómo se entregan las recompensas.',
      install_s1_info:        'ℹ Los tops y recompensas se configuran después en el panel de admin.',
      install_s1_btn:         'Siguiente — Configurar Base de Datos ›',
      install_s2_title:       '🗄 Configuración MySQL',
      install_s2_warn:        '⚠ Usa la base de datos del servidor donde están las cuentas de los jugadores.',
      install_s2_host:        'Host',
      install_s2_user:        'Usuario',
      install_s2_pass:        'Contraseña',
      install_s2_dbname:      'Nombre de la Base de Datos',
      install_s2_back:        '‹ Volver',
      install_s2_btn:         'Probar Conexión & Continuar ›',
      install_s3_title:       '📋 Crear Tablas',
      install_s3_ok:          '✓ ¡Conexión con la base de datos establecida con éxito!',
      install_s3_desc:        'Las tablas abajo serán creadas. Las tablas existentes no se verán afectadas:',
      install_s3_info:        '✅ Los rewards se insertan directamente en la tabla items del juego — sin mod Java ni cron.',
      install_s3_btn:         '✓ Crear Tablas y Finalizar ›',
      install_s4_title:       '¡Instalación Completada!',
      install_s4_desc:        'El VoteSystem está listo. Inicia sesión con una cuenta con access_level ≥ 1 para configurar tops y recompensas.',
      install_s4_reward_title:'✅ Entrega de Recompensa:',
      install_s4_reward_desc: 'Los ítems se insertan directamente en items en el personaje elegido.',
      install_s4_reward_sub:  'No se necesita mod Java ni cron.',
      install_s4_sec_title:   '🔒 Seguridad:',
      install_s4_sec_desc:    '¡Elimina o renombra install.php después de configurar el sistema!',
      install_s4_btn:         '⚜ Ir al VoteSystem',
      install_tbl_tops:       'Sitios de TOP configurados',
      install_tbl_rewards:    'Ítems de recompensa por voto',
      install_tbl_log:        'Historial de votos',
      install_tbl_claims:     'Registro de recompensas reclamadas',
    },

    /* ─── en — English (US) ────────────────────────────────────────── */
    en: {
      nav_vote:   '⚜ Vote',
      nav_admin:  '⚙ Admin',
      nav_logout: 'Logout',
      nav_login:  'Login',

      login_nologin_warn:   '⚠ You need to be logged in to access this page.',
      login_card_title:     '🔐 Player Access',
      login_account_label:  'Account Login',
      login_password_label: 'Password',
      login_ph_login:       'your_login',
      login_show_pwd:       'Show password',
      login_submit:         '⚜ Enter',
      login_hint_main:      'Use the same account and password as the game server.',
      login_hint_sub:       'Vote daily to earn exclusive rewards!',
      login_submitting:     'Logging in...',
      login_error_fields:   '✗ Please fill in your login and password.',
      login_error_creds:    '✗ Incorrect login or password.',

      vote_eyebrow:  '⚜ Vote & Win',
      vote_title:    'Voting Panel',
      vote_subtitle: 'Vote on the tops to support the server and earn exclusive rewards!',

      stat_total_votes:  'Total Votes',
      stat_available:    'Available Tops',
      stat_reward_items: 'Reward Items',
      stat_active_tops:  'Active Tops',

      warn_vote_disabled:   '⚠ <strong>Voting unavailable.</strong>',
      warn_4top_required:   'The administrator has not yet configured 4TOP, which is required for voting to work.',
      warn_no_tops:         '⚠ No TOPs configured yet.',
      admin_configure_link: 'Configure in the admin panel →',

      section_vote_sites:   '🗳 Voting Sites',
      top_available_status: '● Available',
      top_cooldown_status:  '⏳ On cooldown',
      top_next_vote:        '⏱ Next vote in:',
      top_voted_title:      'Already voted',

      claim_daily_reward:     'Daily Reward',
      claim_vote_all:         'Vote on all tops and click below to verify.',
      btn_check_votes:        '⚔ Check Votes',
      claim_choose_char:      'Choose the character that will receive it:',
      btn_claim_reward:       '🎁 Claim Reward',
      reward_delivered_title: 'Reward delivered!',
      reward_delivered_sub:   'Come back in 12h to vote again.',

      card_rewards_title:   '🎁 Vote Rewards',
      no_reward_configured: 'No rewards configured.',
      reward_auto_delivery: 'Items will be delivered to your character automatically after your vote is confirmed.',

      card_how_to_vote: '📖 How to Vote',
      vote_step1: 'Click the top image to open the voting site',
      vote_step2: 'Actually vote on the site that opens in the new tab',
      vote_step3: 'Repeat for all available tops',
      vote_step4: 'Return to this page — the system detects your vote automatically',
      vote_step5: 'Click <strong style="color:var(--gold)">Claim Reward</strong> to receive your items',
      vote_step6: 'You can vote again after <strong style="color:var(--gold)">12 hours</strong>',

      admin_eyebrow:       '⚙ Admin Panel',
      admin_hero_title:    'Manage VoteSystem',
      admin_hero_subtitle: 'Configure voting tops, rewards and monitor player activity.',

      admin_stat_total: 'Total Votes',
      admin_stat_today: 'Votes Today',
      admin_stat_tops:  'Registered Tops',

      admin_add_top_title:  '➕ Add TOP Site',
      admin_4top_req_warn:  '⚠ <strong>4TOP is required.</strong> Add 4TOP before any other voting site.',
      admin_top_sel_label:  'Voting Site',
      admin_top_sel_ph:     '— Select the site —',
      admin_top_name_label: 'Top Name',
      admin_top_name_ph:    'e.g.: L2JBrasil',
      admin_top_id_label:   'Server ID on the Top',
      admin_top_id_ph:      'e.g.: 12345 (see in the voting site panel)',
      admin_site_hint:      'ℹ Find your ID at:',
      admin_token_label:    'Token / API Key',
      admin_token_req:      '(required for this top)',
      admin_token_ph:       'Paste the token generated in the voting site panel',
      admin_url_auto_info:  'ℹ Vote URLs are generated automatically. Order is set automatically (4TOP always 1st).',
      admin_btn_add_top:    '✓ Add Top',

      admin_tops_list_title: '🏆 Registered Tops',
      admin_no_tops:         'No tops registered yet.',
      col_name:              'Name',
      col_id:                'ID',
      col_status:            'Status',
      col_actions:           'Actions',
      badge_active:          'Active',
      badge_inactive:        'Inactive',
      confirm_remove_top:    'Remove this top?',
      title_disable:         'Disable',
      title_enable:          'Enable',
      title_4top_no_disable: '4TOP cannot be disabled',

      admin_reward_cfg_title: '🎁 Configure Vote Rewards',
      admin_reward_cfg_desc:  'Add the items that will be delivered to the player per vote. You can add multiple items at once.',
      admin_reward_item_id:   'Item ID',
      admin_reward_qty:       'Quantity',
      admin_reward_name_lbl:  'Name (e.g.: "Adena")',
      admin_reward_name_ph:   'Item display name',
      admin_btn_save_rewards: '✓ Save Rewards',
      admin_btn_add_more:     'Add more',
      admin_btn_remove_row:   'Remove',

      admin_rewards_list_title: '📦 Configured Rewards',
      admin_no_rewards:         'No rewards configured.',
      col_qty:                  'Qty',
      col_item_name:            'Name',
      confirm_remove_reward:    'Remove this reward?',
      confirm_clear_rewards:    'Remove ALL rewards?',
      admin_btn_clear_rewards:  '🗑 Clear All',

      admin_log_title:    '📊 Recent Vote Log',
      admin_log_subtitle: 'Last 15 sessions',
      admin_no_log:       'No votes recorded yet.',
      col_login:          'Login',
      col_tops_voted:     'Voted Tops',
      col_ip:             'IP',
      col_datetime:       'Date/Time',
      col_reward:         'Reward',
      badge_delivered:    'Delivered',
      badge_pending:      'Pending',

      msg_cooldown:       '⏳ You already claimed your reward in the last 12 hours.',
      msg_not_voted:      '⚠ Vote on all tops before claiming.',
      msg_all_confirmed:  '✅ All votes confirmed! Choose your character.',
      msg_no_chars:       '⚠ No characters found. Create a character in the game first.',
      msg_reward_ok:      '🎁 Reward successfully delivered!',
      msg_expired:        '❌ Verification expired. Click Check Votes again.',
      msg_invalid_char:   '❌ Invalid character.',
      msg_reward_error:   '❌ Error delivering reward. Please try again.',
      msg_select_char:    'Please select a character.',
      msg_connect_error:  'Connection error. Please try again.',
      msg_checking_votes: '⏳ Checking votes...',
      msg_confirming:     '⏳ Confirming...',
      msg_delivering:     '⏳ Delivering...',
      msg_entering:       'Logging in...',

      // Install
      install_title:          'VoteSystem 4Top — Installation',
      install_subtitle:       '4Top Servers — Installation Wizard',
      install_footer:         'VoteSystem 4Top Servers — by 4TeamBR',
      install_step1:          'Project',
      install_step2:          'Database',
      install_step3:          'Tables',
      install_step4:          'Done',
      install_s1_title:       '⚙ Select L2J Project',
      install_s1_desc:        'Choose the server emulator. This defines how passwords are verified and how rewards are delivered to players.',
      install_s1_info:        'ℹ Tops and rewards are configured later in the admin panel.',
      install_s1_btn:         'Next — Configure Database ›',
      install_s2_title:       '🗄 MySQL Configuration',
      install_s2_warn:        '⚠ Use the server database where player accounts are stored.',
      install_s2_host:        'Host',
      install_s2_user:        'User',
      install_s2_pass:        'Password',
      install_s2_dbname:      'Database Name',
      install_s2_back:        '‹ Back',
      install_s2_btn:         'Test Connection & Continue ›',
      install_s3_title:       '📋 Create Tables',
      install_s3_ok:          '✓ Database connection established successfully!',
      install_s3_desc:        'The tables below will be created. Existing tables will not be affected:',
      install_s3_info:        '✅ Rewards are inserted directly into the game items table — no Java mod or cron needed.',
      install_s3_btn:         '✓ Create Tables & Finish ›',
      install_s4_title:       'Installation Complete!',
      install_s4_desc:        'VoteSystem is ready. Log in with an account with access_level ≥ 1 to configure tops and rewards.',
      install_s4_reward_title:'✅ Reward Delivery:',
      install_s4_reward_desc: 'Items are inserted directly into items on the chosen character.',
      install_s4_reward_sub:  'No Java mod or cron required.',
      install_s4_sec_title:   '🔒 Security:',
      install_s4_sec_desc:    'Delete or rename install.php after setting up the system!',
      install_s4_btn:         '⚜ Go to VoteSystem',
      install_tbl_tops:       'Configured TOP sites',
      install_tbl_rewards:    'Vote reward items',
      install_tbl_log:        'Vote history',
      install_tbl_claims:     'Claimed rewards log',
    },

    /* ─── ru — Русский ──────────────────────────────────────────────── */
    ru: {
      nav_vote:   '⚜ Голосовать',
      nav_admin:  '⚙ Админ',
      nav_logout: 'Выйти',
      nav_login:  'Войти',

      login_nologin_warn:   '⚠ Необходимо войти, чтобы получить доступ к этой странице.',
      login_card_title:     '🔐 Вход для игрока',
      login_account_label:  'Логин аккаунта',
      login_password_label: 'Пароль',
      login_ph_login:       'ваш_логин',
      login_show_pwd:       'Показать пароль',
      login_submit:         '⚜ Войти',
      login_hint_main:      'Используйте тот же аккаунт и пароль, что и на игровом сервере.',
      login_hint_sub:       'Голосуйте ежедневно, чтобы получать эксклюзивные награды!',
      login_submitting:     'Входим...',
      login_error_fields:   '✗ Введите логин и пароль.',
      login_error_creds:    '✗ Неверный логин или пароль.',

      vote_eyebrow:  '⚜ Голосуй & Побеждай',
      vote_title:    'Панель голосования',
      vote_subtitle: 'Голосуйте в топах, чтобы поддержать сервер и получать эксклюзивные награды!',

      stat_total_votes:  'Всего голосов',
      stat_available:    'Доступно топов',
      stat_reward_items: 'Предметы награды',
      stat_active_tops:  'Активные топы',

      warn_vote_disabled:   '⚠ <strong>Голосование недоступно.</strong>',
      warn_4top_required:   'Администратор ещё не настроил 4TOP, который обязателен для работы голосования.',
      warn_no_tops:         '⚠ Топы ещё не настроены.',
      admin_configure_link: 'Настройте в панели администратора →',

      section_vote_sites:   '🗳 Сайты голосования',
      top_available_status: '● Доступно',
      top_cooldown_status:  '⏳ В ожидании',
      top_next_vote:        '⏱ Следующий голос через:',
      top_voted_title:      'Уже проголосовано',

      claim_daily_reward:     'Ежедневная награда',
      claim_vote_all:         'Проголосуйте во всех топах и нажмите ниже для проверки.',
      btn_check_votes:        '⚔ Проверить голоса',
      claim_choose_char:      'Выберите персонажа, который получит награду:',
      btn_claim_reward:       '🎁 Получить награду',
      reward_delivered_title: 'Награда доставлена!',
      reward_delivered_sub:   'Возвращайтесь через 12ч, чтобы проголосовать снова.',

      card_rewards_title:   '🎁 Награды за голосование',
      no_reward_configured: 'Награды не настроены.',
      reward_auto_delivery: 'Предметы будут автоматически отправлены вашему персонажу после подтверждения голоса.',

      card_how_to_vote: '📖 Как голосовать',
      vote_step1: 'Нажмите на изображение топа, чтобы открыть сайт голосования',
      vote_step2: 'Проголосуйте на сайте, который откроется в новой вкладке',
      vote_step3: 'Повторите для всех доступных топов',
      vote_step4: 'Вернитесь на эту страницу — система автоматически определит ваш голос',
      vote_step5: 'Нажмите <strong style="color:var(--gold)">Получить награду</strong>, чтобы получить предметы',
      vote_step6: 'Вы сможете проголосовать снова через <strong style="color:var(--gold)">12 часов</strong>',

      admin_eyebrow:       '⚙ Панель администратора',
      admin_hero_title:    'Управление VoteSystem',
      admin_hero_subtitle: 'Настройте топы голосования, награды и отслеживайте активность игроков.',

      admin_stat_total: 'Всего голосов',
      admin_stat_today: 'Голосов сегодня',
      admin_stat_tops:  'Зарегистрированных топов',

      admin_add_top_title:  '➕ Добавить TOP сайт',
      admin_4top_req_warn:  '⚠ <strong>4TOP обязателен.</strong> Добавьте 4TOP перед любым другим сайтом голосования.',
      admin_top_sel_label:  'Сайт голосования',
      admin_top_sel_ph:     '— Выберите сайт —',
      admin_top_name_label: 'Название топа',
      admin_top_name_ph:    'пример: L2JBrasil',
      admin_top_id_label:   'ID сервера в топе',
      admin_top_id_ph:      'пример: 12345 (смотрите в панели сайта голосования)',
      admin_site_hint:      'ℹ Найдите ваш ID на:',
      admin_token_label:    'Токен / API Key',
      admin_token_req:      '(обязателен для этого топа)',
      admin_token_ph:       'Вставьте токен из панели сайта голосования',
      admin_url_auto_info:  'ℹ URL голосований генерируются автоматически. Порядок устанавливается автоматически (4TOP всегда 1-й).',
      admin_btn_add_top:    '✓ Добавить топ',

      admin_tops_list_title: '🏆 Зарегистрированные топы',
      admin_no_tops:         'Топы ещё не зарегистрированы.',
      col_name:              'Название',
      col_id:                'ID',
      col_status:            'Статус',
      col_actions:           'Действия',
      badge_active:          'Активен',
      badge_inactive:        'Неактивен',
      confirm_remove_top:    'Удалить этот топ?',
      title_disable:         'Отключить',
      title_enable:          'Включить',
      title_4top_no_disable: '4TOP нельзя отключить',

      admin_reward_cfg_title: '🎁 Настройка наград за голосование',
      admin_reward_cfg_desc:  'Добавьте предметы, которые будут выданы игроку за каждый голос. Можно добавить несколько предметов сразу.',
      admin_reward_item_id:   'ID предмета',
      admin_reward_qty:       'Количество',
      admin_reward_name_lbl:  'Название (пример: "Adena")',
      admin_reward_name_ph:   'Отображаемое имя предмета',
      admin_btn_save_rewards: '✓ Сохранить награды',
      admin_btn_add_more:     'Добавить ещё',
      admin_btn_remove_row:   'Удалить',

      admin_rewards_list_title: '📦 Настроенные награды',
      admin_no_rewards:         'Награды не настроены.',
      col_qty:                  'Кол-во',
      col_item_name:            'Название',
      confirm_remove_reward:    'Удалить эту награду?',
      confirm_clear_rewards:    'Удалить ВСЕ награды?',
      admin_btn_clear_rewards:  '🗑 Очистить всё',

      admin_log_title:    '📊 Журнал последних голосований',
      admin_log_subtitle: 'Последние 15 сессий',
      admin_no_log:       'Голосований ещё не зарегистрировано.',
      col_login:          'Логин',
      col_tops_voted:     'Проголосованные топы',
      col_ip:             'IP',
      col_datetime:       'Дата/Время',
      col_reward:         'Награда',
      badge_delivered:    'Выдано',
      badge_pending:      'Ожидание',

      msg_cooldown:       '⏳ Вы уже получили награду в последние 12 часов.',
      msg_not_voted:      '⚠ Проголосуйте во всех топах перед получением.',
      msg_all_confirmed:  '✅ Все голоса подтверждены! Выберите персонажа.',
      msg_no_chars:       '⚠ Персонажи не найдены. Сначала создайте персонажа в игре.',
      msg_reward_ok:      '🎁 Награда успешно доставлена!',
      msg_expired:        '❌ Проверка устарела. Нажмите «Проверить голоса» ещё раз.',
      msg_invalid_char:   '❌ Неверный персонаж.',
      msg_reward_error:   '❌ Ошибка при выдаче награды. Попробуйте снова.',
      msg_select_char:    'Выберите персонажа.',
      msg_connect_error:  'Ошибка соединения. Попробуйте снова.',
      msg_checking_votes: '⏳ Проверяем голоса...',
      msg_confirming:     '⏳ Подтверждаем...',
      msg_delivering:     '⏳ Доставляем...',
      msg_entering:       'Выполняем вход...',

      // Install
      install_title:          'VoteSystem 4Top — Установка',
      install_subtitle:       '4Top Servers — Мастер установки',
      install_footer:         'VoteSystem 4Top Servers — by 4TeamBR',
      install_step1:          'Проект',
      install_step2:          'БД',
      install_step3:          'Таблицы',
      install_step4:          'Готово',
      install_s1_title:       '⚙ Выберите проект L2J',
      install_s1_desc:        'Выберите эмулятор сервера. Это определяет, как проверяются пароли и как доставляются награды.',
      install_s1_info:        'ℹ Топы и награды настраиваются позже в панели администратора.',
      install_s1_btn:         'Далее — Настройка базы данных ›',
      install_s2_title:       '🗄 Настройка MySQL',
      install_s2_warn:        '⚠ Используйте базу данных сервера, где хранятся аккаунты игроков.',
      install_s2_host:        'Хост',
      install_s2_user:        'Пользователь',
      install_s2_pass:        'Пароль',
      install_s2_dbname:      'Имя базы данных',
      install_s2_back:        '‹ Назад',
      install_s2_btn:         'Проверить подключение & Продолжить ›',
      install_s3_title:       '📋 Создать таблицы',
      install_s3_ok:          '✓ Подключение к базе данных установлено успешно!',
      install_s3_desc:        'Приведённые ниже таблицы будут созданы. Существующие таблицы затронуты не будут:',
      install_s3_info:        '✅ Награды вставляются напрямую в таблицу items игры — без Java-мода и cron.',
      install_s3_btn:         '✓ Создать таблицы и завершить ›',
      install_s4_title:       'Установка завершена!',
      install_s4_desc:        'VoteSystem готов. Войдите с аккаунтом с access_level ≥ 1 для настройки топов и наград.',
      install_s4_reward_title:'✅ Доставка наград:',
      install_s4_reward_desc: 'Предметы вставляются напрямую в items выбранного персонажа.',
      install_s4_reward_sub:  'Java-мод и cron не требуются.',
      install_s4_sec_title:   '🔒 Безопасность:',
      install_s4_sec_desc:    'Удалите или переименуйте install.php после настройки системы!',
      install_s4_btn:         '⚜ Перейти в VoteSystem',
      install_tbl_tops:       'Настроенные TOP-сайты',
      install_tbl_rewards:    'Предметы наград за голосование',
      install_tbl_log:        'История голосований',
      install_tbl_claims:     'Журнал полученных наград',
    },
  };

  /* ══════════════════════════════════════════════════════════════════════════
     FUNÇÕES CORE
  ══════════════════════════════════════════════════════════════════════════ */

  /** Retorna o idioma atual (leitura síncrona do localStorage, fallback cookie) */
  function getCurrentLang() {
    var l = localStorage.getItem(STORAGE_KEY);
    if (!l || SUPPORTED.indexOf(l) === -1) {
      // fallback: lê do cookie
      var m = document.cookie.match('(?:^|;)\\s*' + STORAGE_KEY + '=([^;]+)');
      l = m ? decodeURIComponent(m[1]) : DEFAULT_LANG;
    }
    return (SUPPORTED.indexOf(l) !== -1) ? l : DEFAULT_LANG;
  }

  /** Retorna tradução de uma chave; fallback para PT ou a própria chave */
  function t(key, lang) {
    lang = lang || getCurrentLang();
    var d = dict[lang] || dict[DEFAULT_LANG];
    if (d[key] !== undefined) return d[key];
    if (dict[DEFAULT_LANG][key] !== undefined) return dict[DEFAULT_LANG][key];
    return key;
  }

  /** Traduz mensagem do servidor usando msg_key (se disponível) */
  function translateMsg(res) {
    if (res.msg_key && dict[getCurrentLang()][res.msg_key] !== undefined) {
      // Para msg_not_voted, inclui a lista de tops faltantes
      if (res.msg_key === 'msg_not_voted' && res.missing && res.missing.length) {
        return t('msg_not_voted') + ' ' + res.missing.join(', ');
      }
      return t(res.msg_key);
    }
    return res.msg || '';
  }

  /** Aplica todas as traduções ao DOM */
  function applyTranslations(lang) {
    lang = lang || getCurrentLang();
    var i, key;

    // data-i18n → textContent
    var els = document.querySelectorAll('[data-i18n]');
    for (i = 0; i < els.length; i++) {
      key = els[i].getAttribute('data-i18n');
      var val = dict[lang] ? dict[lang][key] : undefined;
      if (val === undefined) val = dict[DEFAULT_LANG][key];
      if (val !== undefined) els[i].textContent = val;
    }

    // data-i18n-html → innerHTML
    var htmlEls = document.querySelectorAll('[data-i18n-html]');
    for (i = 0; i < htmlEls.length; i++) {
      key = htmlEls[i].getAttribute('data-i18n-html');
      var hval = dict[lang] ? dict[lang][key] : undefined;
      if (hval === undefined) hval = dict[DEFAULT_LANG][key];
      if (hval !== undefined) htmlEls[i].innerHTML = hval;
    }

    // data-i18n-placeholder → placeholder
    var phEls = document.querySelectorAll('[data-i18n-placeholder]');
    for (i = 0; i < phEls.length; i++) {
      key = phEls[i].getAttribute('data-i18n-placeholder');
      var pval = dict[lang] ? dict[lang][key] : undefined;
      if (pval === undefined) pval = dict[DEFAULT_LANG][key];
      if (pval !== undefined) phEls[i].placeholder = pval;
    }

    // data-i18n-title → title
    var titleEls = document.querySelectorAll('[data-i18n-title]');
    for (i = 0; i < titleEls.length; i++) {
      key = titleEls[i].getAttribute('data-i18n-title');
      var tval = dict[lang] ? dict[lang][key] : undefined;
      if (tval === undefined) tval = dict[DEFAULT_LANG][key];
      if (tval !== undefined) titleEls[i].title = tval;
    }

    // Marca botão de flag ativo
    var langBtns = document.querySelectorAll('.lang-btn[data-lang]');
    for (i = 0; i < langBtns.length; i++) {
      langBtns[i].classList.toggle('active', langBtns[i].getAttribute('data-lang') === lang);
    }

    // Atualiza o lang do HTML
    var langMap = { pt: 'pt-BR', es: 'es', en: 'en-US', ru: 'ru' };
    document.documentElement.lang = langMap[lang] || lang;
  }

  /** Define o idioma e persiste */
  function setLang(lang) {
    if (SUPPORTED.indexOf(lang) === -1) return;

    // Persistência síncrona imediata (localStorage)
    localStorage.setItem(STORAGE_KEY, lang);

    // Cookie para PHP poder ler (step labels server-side)
    document.cookie = STORAGE_KEY + '=' + lang + ';path=/;max-age=31536000;SameSite=Lax';

    // Persistência assíncrona robusta (localForage — IndexedDB / WebSQL / localStorage)
    if (w.localforage) {
      w.localforage.setItem(STORAGE_KEY, lang).catch(function() {});
    }

    applyTranslations(lang);
  }

  /** Inicialização: aplica traduções e vincula eventos nos botões */
  function init() {
    // Aplica imediatamente via localStorage (sem flash)
    applyTranslations(getCurrentLang());

    // Confirmação assíncrona via localForage
    if (w.localforage) {
      w.localforage.getItem(STORAGE_KEY).then(function(stored) {
        if (stored && SUPPORTED.indexOf(stored) !== -1) {
          localStorage.setItem(STORAGE_KEY, stored); // sincroniza
          if (stored !== getCurrentLang()) {
            applyTranslations(stored);
          }
        }
      }).catch(function() {});
    }

    // Vincula cliques nas bandeiras
    var btns = document.querySelectorAll('.lang-btn[data-lang]');
    for (var i = 0; i < btns.length; i++) {
      (function(btn) {
        btn.addEventListener('click', function() {
          setLang(btn.getAttribute('data-lang'));
        });
      })(btns[i]);
    }
  }

  /* ══════════════════════════════════════════════════════════════════════════
     API GLOBAL
  ══════════════════════════════════════════════════════════════════════════ */
  w.vsI18n = {
    t:                  t,
    setLang:            setLang,
    getCurrentLang:     getCurrentLang,
    applyTranslations:  applyTranslations,
    translateMsg:       translateMsg,
  };

  // Auto-inicializa quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window);