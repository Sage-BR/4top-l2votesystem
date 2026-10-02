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
  var selectedLang = null;

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

  // Mensagens e instruções adicionadas ao fluxo de coleta e integração.
  var extra = {
    admin_top_hint: ['Preencha os dados do top. A URL de voto é gerada automaticamente.', 'Completa los datos del top. La URL de voto se genera automáticamente.', 'Enter the ranking site details. The vote URL is generated automatically.', 'Заполните данные сайта. URL голосования создаётся автоматически.'],
    admin_edit: ['✎ Editar', '✎ Editar', '✎ Edit', '✎ Изменить'],
    admin_cancel: ['Cancelar', 'Cancelar', 'Cancel', 'Отмена'],
    admin_save_changes: ['✓ Salvar alterações', '✓ Guardar cambios', '✓ Save changes', '✓ Сохранить изменения'],
    admin_keep_token: ['Deixe vazio para manter o atual', 'Deja vacío para conservar el actual', 'Leave empty to keep the current token', 'Оставьте пустым, чтобы сохранить текущий токен'],
    admin_remove: ['Remover', 'Eliminar', 'Remove', 'Удалить'],
    anticheat_desc: ['Bloqueia a tela de voto quando a conexão parecer VPN, proxy ou rede suspeita.', 'Bloquea la página de voto cuando la conexión parece una VPN, proxy o red sospechosa.', 'Blocks the voting page when the connection appears to use a VPN, proxy or suspicious network.', 'Блокирует страницу голосования при обнаружении VPN, прокси или подозрительной сети.'],
    anticheat_enable: ['Ativar anticheat', 'Activar anticheat', 'Enable anticheat', 'Включить античит'],
    anticheat_disable: ['Desativar anticheat', 'Desactivar anticheat', 'Disable anticheat', 'Отключить античит'],
    anticheat_status: ['Status atual:', 'Estado actual:', 'Current status:', 'Текущий статус:'],
    anticheat_active: ['Ativo', 'Activo', 'Enabled', 'Включён'],
    anticheat_inactive: ['Desativado', 'Desactivado', 'Disabled', 'Отключён'],
    anticheat_log_title: ['🛡 Detecções do Anticheat', '🛡 Detecciones del Anticheat', '🛡 Anticheat Detections', '🛡 Обнаружения античита'],
    anticheat_log_subtitle: ['Últimos 15 bloqueios', 'Últimos 15 bloqueos', 'Last 15 blocks', 'Последние 15 блокировок'],
    anticheat_no_log: ['Nenhuma detecção registrada ainda.', 'Aún no hay detecciones registradas.', 'No detections recorded yet.', 'Обнаружения ещё не зарегистрированы.'],
    col_risk: ['Risco', 'Riesgo', 'Risk', 'Риск'],
    col_reason: ['Motivo', 'Motivo', 'Reason', 'Причина'],
    col_source: ['Fonte', 'Origen', 'Source', 'Источник'],
    col_status: ['Status', 'Estado', 'Status', 'Статус'],
    anticheat_blocked: ['Bloqueado', 'Bloqueado', 'Blocked', 'Заблокирован'],
    anticheat_alert: ['Apenas alerta', 'Solo alerta', 'Warning only', 'Только предупреждение'],
    removed_top: ['Top removido', 'Top eliminado', 'Removed top', 'Удалённый сайт'],
    admin_security_error: ['Token de segurança inválido. Recarregue a página e tente novamente.', 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', 'Invalid security token. Reload the page and try again.', 'Недействительный токен безопасности. Обновите страницу и повторите попытку.'],
    admin_required_error: ['Nome, ID do Servidor e Top são obrigatórios.', 'El nombre, el ID del servidor y el top son obligatorios.', 'Name, Server ID and Top are required.', 'Название, ID сервера и сайт обязательны.'],
    admin_invalid_top: ['Top selecionado inválido.', 'Top seleccionado inválido.', 'Invalid ranking site selected.', 'Выбран недействительный сайт.'],
    admin_numeric_id: ['Este top exige o ID numérico do servidor.', 'Este top requiere el ID numérico del servidor.', 'This ranking site requires a numeric server ID.', 'Этот сайт требует числовой ID сервера.'],
    admin_hop_error: ['Hopzone.eu: informe o ID numérico do servidor e sua API Key.', 'Hopzone.eu: introduce el ID numérico del servidor y tu API Key.', 'Hopzone.eu: enter the numeric server ID and your API Key.', 'Hopzone.eu: укажите числовой ID сервера и API Key.'],
    admin_invalid_id: ['ID do Servidor contém caracteres inválidos.', 'El ID del servidor contiene caracteres inválidos.', 'Server ID contains invalid characters.', 'ID сервера содержит недопустимые символы.'],
    admin_4top_first: ['⚠ O 4TOP precisa ser adicionado primeiro antes de qualquer outro top.', '⚠ Añade 4TOP antes de cualquier otro top.', '⚠ Add 4TOP before any other ranking site.', '⚠ Добавьте 4TOP перед остальными сайтами.'],
    admin_duplicate_top: ['Este top já foi adicionado.', 'Este top ya fue añadido.', 'This ranking site has already been added.', 'Этот сайт уже добавлен.'],
    admin_missing_top: ['Top não encontrado ou inválido.', 'Top no encontrado o inválido.', 'Ranking site not found or invalid.', 'Сайт не найден или недействителен.'],
    admin_top_updated: ['Top atualizado com sucesso!', '¡Top actualizado correctamente!', 'Ranking site updated successfully!', 'Сайт успешно обновлён!'],
    admin_4top_remove: ['O 4TOP não pode ser removido.', '4TOP no se puede eliminar.', '4TOP cannot be removed.', '4TOP нельзя удалить.'],
    admin_top_removed: ['Top removido.', 'Top eliminado.', 'Ranking site removed.', 'Сайт удалён.'],
    admin_4top_disable: ['O 4TOP não pode ser desativado.', '4TOP no se puede desactivar.', '4TOP cannot be disabled.', '4TOP нельзя отключить.'],
    admin_status_updated: ['Status do top atualizado.', 'Estado del top actualizado.', 'Ranking site status updated.', 'Статус сайта обновлён.'],
    admin_item_invalid: ['Preencha pelo menos um Item ID válido.', 'Introduce al menos un Item ID válido.', 'Enter at least one valid Item ID.', 'Укажите хотя бы один корректный Item ID.'],
    admin_reward_removed: ['Reward removido.', 'Recompensa eliminada.', 'Reward removed.', 'Награда удалена.'],
    admin_rewards_cleared: ['Todos os rewards foram removidos.', 'Todas las recompensas fueron eliminadas.', 'All rewards were removed.', 'Все награды удалены.'],
    admin_anticheat_on: ['Anticheat ativado.', 'Anticheat activado.', 'Anticheat enabled.', 'Античит включён.'],
    admin_anticheat_off: ['Anticheat desativado.', 'Anticheat desactivado.', 'Anticheat disabled.', 'Античит отключён.'],
    admin_anticheat_error: ['Não foi possível alterar o anticheat. Verifique o banco de dados.', 'No se pudo cambiar el anticheat. Revisa la base de datos.', 'Could not change anticheat. Check the database.', 'Не удалось изменить античит. Проверьте базу данных.'],
    admin_top_added: ['Top "{name}" adicionado com sucesso!', '¡Top "{name}" añadido correctamente!', 'Ranking site "{name}" added successfully!', 'Сайт "{name}" успешно добавлен!'],
    admin_rewards_added: ['{count} item(s) de reward adicionado(s)!', '¡{count} objeto(s) de recompensa añadido(s)!', '{count} reward item(s) added!', 'Добавлено предметов награды: {count}!'],
    admin_integration_help: ['Instrução de integração', 'Instrucciones de integración', 'Integration instructions', 'Инструкции по интеграции'],
    msg_session_invalid: ['Sessão inválida. Faça login novamente.', 'Sesión inválida. Inicia sesión de nuevo.', 'Invalid session. Please sign in again.', 'Недействительная сессия. Войдите снова.'],
    msg_delivery_busy: ['Entrega em andamento. Tente novamente.', 'Entrega en curso. Inténtalo de nuevo.', 'Delivery in progress. Please try again.', 'Выдача выполняется. Попробуйте снова.'],
    msg_invalid_request: ['Requisição inválida. Recarregue a página.', 'Solicitud inválida. Recarga la página.', 'Invalid request. Reload the page.', 'Недействительный запрос. Обновите страницу.'],
    msg_internal_error: ['Erro interno ao processar a recompensa. Consulte o log do servidor.', 'Error interno al procesar la recompensa. Consulta el registro del servidor.', 'Internal error processing the reward. Check the server log.', 'Внутренняя ошибка выдачи награды. Проверьте журнал сервера.'],
    msg_service_unavailable: ['Serviço temporariamente indisponível. Tente novamente.', 'Servicio temporalmente no disponible. Inténtalo de nuevo.', 'Service temporarily unavailable. Please try again.', 'Сервис временно недоступен. Попробуйте снова.'],
    msg_access_blocked: ['Acesso bloqueado: desative a VPN/proxy e tente novamente.', 'Acceso bloqueado: desactiva la VPN/proxy e inténtalo de nuevo.', 'Access blocked: disable your VPN/proxy and try again.', 'Доступ заблокирован: отключите VPN/прокси и повторите попытку.'],
    access_blocked_title: ['Acesso temporariamente bloqueado', 'Acceso temporalmente bloqueado', 'Access temporarily blocked', 'Доступ временно заблокирован'],
    access_blocked_desc: ['Detectamos sinais de VPN/proxy ou conexão suspeita. Desative a VPN/proxy e recarregue a página para votar e receber recompensa.', 'Detectamos una VPN/proxy o una conexión sospechosa. Desactívala y recarga la página para votar y recibir la recompensa.', 'We detected a VPN/proxy or a suspicious connection. Disable it and reload the page to vote and claim your reward.', 'Обнаружены VPN/прокси или подозрительное соединение. Отключите VPN/прокси и обновите страницу для голосования и получения награды.'],
    top_ip_cooldown: ['⏳ IP em cooldown', '⏳ IP en espera', '⏳ IP on cooldown', '⏳ Для IP действует период ожидания'],
    top_vote_title: ['Votar neste top', 'Votar en este top', 'Vote on this top', 'Голосовать на этом сайте'],
    top_vote: ['⚔ Votar', '⚔ Votar', '⚔ Vote', '⚔ Голосовать'],
    top_voted: ['✓ Votado', '✓ Votado', '✓ Voted', '✓ Голос учтён'],
    integration_title: ['Como integrar', 'Cómo integrar', 'How to integrate', 'Как настроить интеграцию'],
    integration_close: ['Fechar instruções', 'Cerrar instrucciones', 'Close instructions', 'Закрыть инструкции'],
    integration_register: ['Cadastre seu servidor no', 'Registra tu servidor en', 'Register your server at', 'Зарегистрируйте сервер на'],
    integration_intro: ['No VoteSystem, informe o nome e o identificador do servidor no top.', 'En VoteSystem, introduce el nombre y el identificador del servidor en el top.', 'In VoteSystem, enter the server name and its identifier on the ranking site.', 'В VoteSystem укажите название сервера и его идентификатор на сайте рейтинга.'],
    integration_4top_config: ['O 4TOP é obrigatório e aparece primeiro. Informe o identificador do servidor no ranking e a API Key do painel do 4TOP no campo Token / API Key.', '4TOP es obligatorio y aparece primero. Introduce el identificador del servidor en el ranking y la API Key de su panel en Token / API Key.', '4TOP is required and appears first. Enter your server identifier from the ranking and the API Key from the 4TOP panel in Token / API Key.', '4TOP обязателен и отображается первым. Укажите идентификатор сервера в рейтинге и API Key из панели 4TOP в поле Token / API Key.'],
    integration_4top_check: ['O link e a consulta enviam a identificação da conta. A API deve confirmar um voto recente; abrir o link não comprova o voto. Não precisa configurar postback.', 'El enlace y la consulta envían el identificador de la cuenta. La API debe confirmar un voto reciente; abrir el enlace no lo confirma. No requiere postback.', 'The vote link and lookup send the account identifier. The API must confirm a recent vote; opening the link does not prove a vote. No postback setup is required.', 'Ссылка и запрос передают идентификатор аккаунта. API должен подтвердить недавний голос; открытие ссылки не подтверждает голосование. Настройка postback не требуется.'],
    integration_brasil_config: ['Informe o username do servidor no ranking (parâmetro <code>u</code>), não o login do jogador. O link envia <code>player_id</code> gerado pelo MD5 do login; a checagem usa o mesmo identificador.', 'Introduce el username del servidor en el ranking (parámetro <code>u</code>), no el login del jugador. El enlace envía <code>player_id</code> generado con MD5 del login; la consulta usa el mismo identificador.', 'Enter the server username on the ranking (parameter <code>u</code>), not the player login. The link sends <code>player_id</code> generated from the MD5 of the login; verification uses the same identifier.', 'Укажите username сервера в рейтинге (параметр <code>u</code>), а не логин игрока. Ссылка передаёт <code>player_id</code>, полученный через MD5 логина; проверка использует тот же идентификатор.'],
    integration_brasil_check: ['Conclua o voto e o captcha no top. A confirmação exige status válido e voto nas últimas 12 horas. Há consulta secundária por IP quando player_id não confirma.', 'Completa el voto y el captcha. La confirmación requiere un estado válido y un voto en las últimas 12 horas. Se consulta el IP si player_id no confirma.', 'Complete the vote and captcha. Confirmation requires a valid status and a vote within the last 12 hours. A secondary IP lookup is used when player_id does not confirm the vote.', 'Завершите голосование и капчу. Нужны корректный статус и голос за последние 12 часов. Если player_id не подтверждает голос, выполняется дополнительная проверка IP.'],
    integration_brasil_token: ['O campo Token não participa da consulta atual do L2JBrasil. Não precisa configurar postback.', 'El campo Token no se utiliza en la consulta actual de L2JBrasil. No requiere postback.', 'The Token field is not used by the current L2JBrasil lookup. No postback setup is required.', 'Поле Token не используется в текущем запросе L2JBrasil. Настройка postback не требуется.'],
    integration_l2top_config: ['Informe o ID do servidor e o token da API do painel do L2Top.org. O link inclui o login; a API consulta o mesmo login usando o token.', 'Introduce el ID del servidor y el token de la API de L2Top.org. El enlace incluye el login; la API consulta el mismo login con el token.', 'Enter the server ID and API token from the L2Top.org panel. The link includes the login; the API checks the same login using the token.', 'Укажите ID сервера и токен API из панели L2Top.org. Ссылка содержит логин; API проверяет тот же логин с помощью токена.'],
    integration_l2top_check: ['É necessário um voto confirmado dentro das últimas 12 horas. Não precisa configurar postback.', 'Se requiere un voto confirmado en las últimas 12 horas. No requiere postback.', 'A confirmed vote within the last 12 hours is required. No postback setup is needed.', 'Требуется подтверждённый голос за последние 12 часов. Настройка postback не нужна.'],
    integration_network_config: ['No campo ID do Servidor no Top, informe exatamente o valor de <code>u</code> do link oficial do L2Network. É o username do proprietário do cadastro e pode diferir do nome do servidor e do ID numérico da página de detalhes.', 'En ID del Servidor en el Top, introduce exactamente el valor de <code>u</code> del enlace oficial de L2Network. Es el username del propietario y puede diferir del nombre del servidor y del ID numérico de su página.', 'In Server ID on the Top, enter the exact <code>u</code> value from the official L2Network vote link. This is the listing owner username and may differ from the server name and the numeric details page ID.', 'В поле ID сервера на сайте укажите точное значение <code>u</code> из официальной ссылки L2Network. Это username владельца записи; он может отличаться от названия сервера и числового ID страницы.'],
    integration_network_api: ['Informe também sua API Key. O link envia o login em <code>id</code>; a consulta POST envia <code>apiKey</code>, <code>type=2</code> e <code>player=login</code>. Se abrir <code>details///</code>, confira o username em <code>u</code>.', 'Introduce también tu API Key. El enlace envía el login en <code>id</code>; la consulta POST envía <code>apiKey</code>, <code>type=2</code> y <code>player=login</code>. Si abre <code>details///</code>, revisa el username en <code>u</code>.', 'Also enter your API Key. The link sends the login in <code>id</code>; the POST lookup sends <code>apiKey</code>, <code>type=2</code> and <code>player=login</code>. If it opens <code>details///</code>, check the username in <code>u</code>.', 'Также укажите API Key. Ссылка передаёт логин в <code>id</code>; POST-запрос передаёт <code>apiKey</code>, <code>type=2</code> и <code>player=login</code>. Если открывается <code>details///</code>, проверьте username в <code>u</code>.'],
    integration_network_check: ['A resposta é um inteiro: <code>-1</code> não confirma voto, <code>0</code> não comprova voto recente e um timestamp positivo deve estar nas últimas 12 horas. Não precisa configurar postback.', 'La respuesta es un entero: <code>-1</code> no confirma el voto, <code>0</code> no demuestra un voto reciente y un timestamp positivo debe estar en las últimas 12 horas. No requiere postback.', 'The response is an integer: <code>-1</code> does not confirm a vote, <code>0</code> does not prove a recent vote, and a positive timestamp must fall within the last 12 hours. No postback setup is required.', 'Ответ — целое число: <code>-1</code> не подтверждает голос, <code>0</code> не доказывает недавний голос, а положительный timestamp должен попадать в последние 12 часов. Postback не требуется.'],
    integration_hop_config: ['Informe o ID numérico do servidor e sua API Key. A API gera uma URL com <code>vote_id</code> vinculado ao login; use sempre o botão do painel para votar.', 'Introduce el ID numérico del servidor y tu API Key. La API genera una URL con <code>vote_id</code> vinculado al login; usa siempre el botón del panel.', 'Enter the numeric server ID and your API Key. The API generates a URL with a <code>vote_id</code> linked to the login; always vote using the panel button.', 'Укажите числовой ID сервера и API Key. API создаёт URL с <code>vote_id</code>, связанным с логином; голосуйте через кнопку панели.'],
    integration_hop_check: ['A checagem exige <code>status=completed</code>, servidor e vote_id correspondentes, e voto recente. Voto pendente não libera recompensa. O IP só é consultado sem vínculo salvo para a conta. Não precisa configurar postback.', 'La consulta exige <code>status=completed</code>, servidor y vote_id correspondientes, y un voto reciente. Un voto pendiente no libera la recompensa. Solo se consulta el IP si no hay vínculo guardado para la cuenta. No requiere postback.', 'Verification requires <code>status=completed</code>, matching server and vote_id, and a recent vote. Pending votes do not unlock rewards. IP is checked only when there is no saved account link. No postback setup is required.', 'Проверка требует <code>status=completed</code>, совпадения сервера и vote_id и недавнего голоса. Ожидающий голос не открывает награду. IP проверяется только при отсутствии сохранённой связи с аккаунтом. Postback не требуется.'],
    integration_postback_config: ['Informe o ID numérico do servidor. Copie o endereço abaixo para o campo <strong>Postback URL</strong> no painel do top. Clique no campo para selecionar o endereço completo:', 'Introduce el ID numérico del servidor. Copia la dirección de abajo en <strong>Postback URL</strong> del panel del top. Haz clic en el campo para seleccionar la dirección completa:', 'Enter the numeric server ID. Copy the address below into <strong>Postback URL</strong> in the ranking panel. Click the field to select the full address:', 'Укажите числовой ID сервера. Скопируйте адрес ниже в поле <strong>Postback URL</strong> панели сайта. Нажмите на поле, чтобы выделить полный адрес:'],
    integration_mm_token: ['O campo Token recebe a API Key da página <strong>Vote Checker</strong> do MMTop200. Exemplo fictício (não válido): <code>0123456789abcdef0123456789abcdef</code>.', 'En Token introduce la API Key de <strong>Vote Checker</strong> de MMTop200. Ejemplo ficticio (no válido): <code>0123456789abcdef0123456789abcdef</code>.', 'The Token field takes the API Key from the MMTop200 <strong>Vote Checker</strong> page. Fictional example (not valid): <code>0123456789abcdef0123456789abcdef</code>.', 'В поле Token укажите API Key со страницы <strong>Vote Checker</strong> MMTop200. Вымышленный пример (недействителен): <code>0123456789abcdef0123456789abcdef</code>.'],
    integration_mm_check: ['Use o validador oficial sem senha adicional de postback: a integração valida a origem. O link envia o login e o callback deve informar o usuário e o voto contabilizado.', 'Usa el validador oficial sin contraseña adicional de postback: la integración valida el origen. El enlace envía el login y el callback debe informar el usuario y el voto contabilizado.', 'Use the official validator without an extra postback password: the integration validates the source. The link sends the login and the callback must report the user and the counted vote.', 'Используйте официальный валидатор без дополнительного пароля postback: интеграция проверяет источник. Ссылка передаёт логин, callback должен сообщить пользователя и учтённый голос.'],
    integration_gaming_config: ['Configure o callback na', 'Configura el callback en la', 'Configure the callback on the', 'Настройте callback на'],
    integration_gaming_page: ['página de edição do GamingTop100', 'página de edición de GamingTop100', 'GamingTop100 edit page', 'странице редактирования GamingTop100'],
    integration_no_token: ['Não precisa de token neste adaptador.', 'Este adaptador no requiere token.', 'This adapter does not require a token.', 'Этот адаптер не требует токена.'],
    integration_gaming_check: ['O link envia uma referência numérica vinculada ao login; o callback devolve essa referência para identificar a conta.', 'El enlace envía una referencia numérica vinculada al login; el callback devuelve esa referencia para identificar la cuenta.', 'The link sends a numeric reference linked to the login; the callback returns this reference to identify the account.', 'Ссылка передаёт числовую ссылку, связанную с логином; callback возвращает её для определения аккаунта.'],
    integration_arena_check: ['Mantenha <code>?postback=</code> no final do callback: o top acrescenta a referência do incentivo vinculada ao login. Não precisa de token neste adaptador.', 'Mantén <code>?postback=</code> al final del callback: el top añade la referencia del incentivo vinculada al login. Este adaptador no requiere token.', 'Keep <code>?postback=</code> at the end of the callback: the ranking appends the incentive reference linked to the login. This adapter does not require a token.', 'Оставьте <code>?postback=</code> в конце callback: сайт добавит ссылку поощрения, связанную с логином. Этот адаптер не требует токена.'],
    integration_postback_check: ['Após salvar, conclua um voto permitido pelo botão do VoteSystem. A confirmação exige postback autenticado da conta; IP sozinho não libera recompensa. Se ficar pendente, procure “POSTBACK RECEBIDO” e rejeições em <code>vote_api.log</code>.', 'Tras guardar, completa un voto permitido desde el botón de VoteSystem. Se requiere un postback autenticado de la cuenta; el IP solo no libera la recompensa. Si queda pendiente, busca “POSTBACK RECEBIDO” y rechazos en <code>vote_api.log</code>.', 'After saving, complete an allowed vote using the VoteSystem button. Confirmation requires an authenticated account postback; IP alone does not unlock rewards. If it stays pending, look for “POSTBACK RECEBIDO” and rejections in <code>vote_api.log</code>.', 'После сохранения выполните допустимое голосование через кнопку VoteSystem. Требуется аутентифицированный postback аккаунта; одного IP недостаточно для награды. Если голос ожидает подтверждения, ищите “POSTBACK RECEBIDO” и отказы в <code>vote_api.log</code>.'],
    integration_finish: ['URLs são geradas automaticamente. Vote em todos os tops ativos e volte para verificar e coletar. Cliques e falhas de consulta não liberam recompensa.', 'Las URLs se generan automáticamente. Vota en todos los tops activos y vuelve para verificar y recoger. Los clics y los errores de consulta no liberan la recompensa.', 'URLs are generated automatically. Vote on every active ranking site, then return to verify and claim. Clicks and failed lookups do not unlock rewards.', 'URL создаются автоматически. Проголосуйте на всех активных сайтах, затем вернитесь для проверки и получения награды. Клики и ошибки запросов не открывают награду.']
  };
  Object.keys(extra).forEach(function(key) {
    SUPPORTED.forEach(function(lang, index) { dict[lang][key] = extra[key][index]; });
  });

  /* ══════════════════════════════════════════════════════════════════════════
     FUNÇÕES CORE
  ══════════════════════════════════════════════════════════════════════════ */

  /** Retorna o idioma atual (leitura síncrona do localStorage, fallback cookie) */
  function getCurrentLang() {
    if (selectedLang) return selectedLang;
    var l;
    try { l = localStorage.getItem(STORAGE_KEY); } catch (e) {}
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
  function translateMsg(res, lang) {
    lang = lang || getCurrentLang();
    if (res.msg_key && dict[lang][res.msg_key] !== undefined) {
      // Para msg_not_voted, inclui a lista de tops faltantes
      if (res.msg_key === 'msg_not_voted' && res.missing && res.missing.length) {
        return t('msg_not_voted', lang) + ' ' + res.missing.join(', ');
      }
      return t(res.msg_key, lang);
    }
    var message = res.msg || '';
    // Compatibilidade com avisos administrativos antigos salvos na sessão.
    var normalized = message.replace('Nome, ID do Servidor e top são obrigatórios.', extra.admin_required_error[0]);
    var keys = Object.keys(extra);
    for (var i = 0; i < keys.length; i++) {
      if (extra[keys[i]][0] === normalized) return t(keys[i], lang);
    }
    var match = message.match(/^Top "(.*)" adicionado com sucesso!$/);
    if (match) return t('admin_top_added', lang).replace('{name}', match[1]);
    match = message.match(/^(\d+) item\(s\) de reward adicionado\(s\)!$/);
    if (match) return t('admin_rewards_added', lang).replace('{count}', match[1]);
    return message;
  }

  /** Aplica todas as traduções ao DOM */
  function applyTranslations(lang) {
    lang = lang || getCurrentLang();
    var i, key;

    var messages = document.querySelectorAll('[data-i18n-message]');
    for (i = 0; i < messages.length; i++) {
      messages[i].textContent = translateMsg({msg: messages[i].getAttribute('data-i18n-message')}, lang);
    }
    var ariaEls = document.querySelectorAll('[data-i18n-aria-label]');
    for (i = 0; i < ariaEls.length; i++) {
      ariaEls[i].setAttribute('aria-label', t(ariaEls[i].getAttribute('data-i18n-aria-label'), lang));
    }
    var removedTops = document.querySelectorAll('[data-i18n-top-name]');
    for (i = 0; i < removedTops.length; i++) {
      removedTops[i].textContent = removedTops[i].getAttribute('data-i18n-top-name').replace(/Top removido \(#(\d+)\)/g, function(match, id) {
        return t('removed_top', lang) + ' (#' + id + ')';
      });
    }

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
    selectedLang = lang;

    // Persistência síncrona imediata (localStorage)
    try { localStorage.setItem(STORAGE_KEY, lang); } catch (e) {}

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
        if (!selectedLang && stored && SUPPORTED.indexOf(stored) !== -1) {
          var previous = getCurrentLang();
          try { localStorage.setItem(STORAGE_KEY, stored); } catch (e) {}
          if (stored !== previous) {
            selectedLang = stored;
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
