<?php
/**
 * Plugin Name: Portfolio Core
 * Description: WordPress headless per il portfolio Astro: Progetti, Servizi, contenuti della Home, campi, REST API, redirect del front-end, rebuild su GitHub e form contatti.
 * Version:     1.5.0
 *
 * Va in wp-content/mu-plugins/. Configurazione in wp-config.php (nessun dominio scritto qui):
 *
 *   define( 'PORTFOLIO_SITE_URL', 'https://visonuovomdb.com' );        // sito Astro pubblico
 *   define( 'PORTFOLIO_GITHUB_REPO', 'Gabriele1988/portfolio' );        // owner/repo
 *   define( 'PORTFOLIO_GITHUB_TOKEN', 'github_pat_...' );               // fine-grained token, solo questo repo, "Contents: Read and write"
 *   define( 'PORTFOLIO_CONTACT_EMAIL', 'tu@esempio.it' );               // facoltativo, altrimenti l'email admin
 *   define( 'PORTFOLIO_TURNSTILE_SECRET', '' );                         // facoltativo, Cloudflare Turnstile
 *
 *   // SMTP facoltativo (in alternativa usa un plugin come FluentSMTP)
 *   define( 'PORTFOLIO_SMTP_HOST', 'smtp.esempio.it' );
 *   define( 'PORTFOLIO_SMTP_PORT', 587 );
 *   define( 'PORTFOLIO_SMTP_USER', 'tu@esempio.it' );
 *   define( 'PORTFOLIO_SMTP_PASS', '...' );
 *   define( 'PORTFOLIO_SMTP_SECURE', 'tls' );                          // tls | ssl
 */

defined( 'ABSPATH' ) || exit;

function portfolio_site_url() {
	return defined( 'PORTFOLIO_SITE_URL' ) ? untrailingslashit( PORTFOLIO_SITE_URL ) : '';
}

/* -------------------------------------------------------------------------
 * 1. Tipo di contenuto "Progetti" e tassonomia "Servizi"
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type( 'progetti', array(
		'labels'        => array(
			'name'          => 'Progetti',
			'singular_name' => 'Progetto',
			'add_new_item'  => 'Aggiungi progetto',
			'edit_item'     => 'Modifica progetto',
			'all_items'     => 'Tutti i progetti',
		),
		'public'        => true,
		'show_in_rest'  => true,
		'rest_base'     => 'progetti',
		'menu_icon'     => 'dashicons-portfolio',
		'menu_position' => 5,
		'has_archive'   => false,
		'rewrite'       => array( 'slug' => 'progetti' ),
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes' ),
	) );

	register_taxonomy( 'servizi', array( 'progetti' ), array(
		'labels'            => array( 'name' => 'Servizi', 'singular_name' => 'Servizio' ),
		'public'            => true,
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
	) );
} );

/* -------------------------------------------------------------------------
 * 2. Campi del progetto (definiti nel codice, quindi sotto Git)
 * ---------------------------------------------------------------------- */
function portfolio_campi() {
	return array(
		'cliente'     => array( 'label' => 'Cliente', 'type' => 'text' ),
		'sottotitolo' => array( 'label' => 'Sottotitolo', 'type' => 'text', 'instructions' => 'Riga in evidenza sotto il titolo, es. Azienda agricola — Gaiole in Chianti' ),
		'stato'       => array(
			'label'         => 'Stato',
			'type'          => 'select',
			'choices'       => array( 'online' => 'Online', 'in_sviluppo' => 'In sviluppo' ),
			'default_value' => 'online',
		),
		'anno'        => array( 'label' => 'Anno', 'type' => 'number' ),
		'url'         => array( 'label' => 'URL del progetto', 'type' => 'url' ),
		'ruolo'       => array( 'label' => 'Ruolo', 'type' => 'text' ),
		'tecnologie'  => array( 'label' => 'Tecnologie', 'type' => 'text', 'instructions' => 'Separate da virgola, es. Angular, Spring Boot, PostgreSQL' ),
		'funzionalita' => array( 'label' => 'Funzionalità chiave', 'type' => 'textarea', 'instructions' => 'Una per riga. Si vedono quando il progetto è quello in evidenza nella pagina Progetti.' ),
		'repository'  => array( 'label' => 'Repository GitHub', 'type' => 'url', 'instructions' => 'Facoltativo, se il codice è pubblico.' ),
		'risultato'   => array( 'label' => 'Risultato', 'type' => 'textarea', 'instructions' => 'Il risultato ottenuto, in una o due frasi (meglio se con numeri).' ),
		'in_evidenza' => array( 'label' => 'In evidenza', 'type' => 'true_false', 'instructions' => 'Mostra in home page. Il più recente in evidenza apre anche la pagina Progetti.' ),
	);
}

// Interfaccia dei campi con Secure Custom Fields / ACF, se attivo.
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$fields = array();
	foreach ( portfolio_campi() as $name => $c ) {
		$fields[] = array(
			'key'           => 'field_portfolio_' . $name,
			'name'          => $name,
			'label'         => $c['label'],
			'type'          => $c['type'],
			'instructions'  => isset( $c['instructions'] ) ? $c['instructions'] : '',
			'ui'            => 'true_false' === $c['type'] ? 1 : 0,
			'choices'       => isset( $c['choices'] ) ? $c['choices'] : array(),
			'default_value' => isset( $c['default_value'] ) ? $c['default_value'] : '',
		);
	}
	acf_add_local_field_group( array(
		'key'      => 'group_portfolio_progetto',
		'title'    => 'Dettagli progetto',
		'fields'   => $fields,
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'progetti' ) ) ),
		'position' => 'acf_after_title',
	) );
} );

/* -------------------------------------------------------------------------
 * 3. REST API: campi e copertina già pronti
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_field( 'progetti', 'campi', array(
		'get_callback' => function ( $post ) {
			$out = array();
			foreach ( portfolio_campi() as $name => $c ) {
				$v = get_post_meta( $post['id'], $name, true );
				if ( 'true_false' === $c['type'] ) {
					$v = (bool) $v;
				} elseif ( 'number' === $c['type'] ) {
					$v = '' === $v ? null : (int) $v;
				} elseif ( 'tecnologie' === $name ) {
					$v = array_values( array_filter( array_map( 'trim', explode( ',', (string) $v ) ) ) );
				} elseif ( 'funzionalita' === $name ) {
					$v = array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $v ) ) ) );
				} elseif ( 'stato' === $name && '' === $v ) {
					$v = 'online';
				}
				$out[ $name ] = $v;
			}
			return $out;
		},
		'schema'       => array( 'type' => 'object' ),
	) );

	register_rest_field( 'progetti', 'copertina', array(
		'get_callback' => function ( $post ) {
			$id = get_post_thumbnail_id( $post['id'] );
			if ( ! $id ) {
				return null;
			}
			// L'originale: Astro scarica e ottimizza da qui.
			$img = wp_get_attachment_image_src( $id, 'full' );
			if ( ! $img ) {
				return null;
			}
			return array(
				'src'    => $img[0],
				'width'  => (int) $img[1],
				'height' => (int) $img[2],
				'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			);
		},
		'schema'       => array( 'type' => array( 'object', 'null' ) ),
	) );

	register_rest_route( 'portfolio/v1', '/contatto', array(
		'methods'             => 'POST',
		'callback'            => 'portfolio_contatto',
		'permission_callback' => '__return_true',
	) );
} );

/* -------------------------------------------------------------------------
 * 4. Il front-end di WordPress reindirizza al sito Astro (CMS non indicizzato)
 * ---------------------------------------------------------------------- */
add_action( 'send_headers', function () {
	header( 'X-Robots-Tag: noindex, nofollow', true );
} );

add_filter( 'robots_txt', function () {
	return "User-agent: *\nDisallow: /\n";
}, 99 );

add_action( 'template_redirect', function () {
	$site = portfolio_site_url();
	if ( ! $site || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_robots() ) {
		return;
	}
	$path = '/';
	if ( is_singular( 'progetti' ) ) {
		$path = '/progetti/' . get_post_field( 'post_name', get_queried_object_id() ) . '/';
	} elseif ( is_post_type_archive( 'progetti' ) || is_tax( 'servizi' ) ) {
		$path = '/progetti/';
	}
	wp_redirect( $site . $path, 301 );
	exit;
} );

/* -------------------------------------------------------------------------
 * 5. Rebuild su GitHub quando cambia un contenuto
 * ---------------------------------------------------------------------- */
function portfolio_rebuild() {
	if ( ! defined( 'PORTFOLIO_GITHUB_REPO' ) || ! defined( 'PORTFOLIO_GITHUB_TOKEN' ) ) {
		return new WP_Error( 'portfolio_config', 'PORTFOLIO_GITHUB_REPO o PORTFOLIO_GITHUB_TOKEN mancanti in wp-config.php.' );
	}
	$res = wp_remote_post( 'https://api.github.com/repos/' . PORTFOLIO_GITHUB_REPO . '/dispatches', array(
		'timeout' => 10,
		'headers' => array(
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . PORTFOLIO_GITHUB_TOKEN,
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'portfolio-core',
		),
		'body'    => wp_json_encode( array( 'event_type' => 'cms-update' ) ),
	) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( 204 !== $code ) {
		return new WP_Error( 'portfolio_github', 'GitHub ha risposto ' . $code . ': ' . wp_remote_retrieve_body( $res ) );
	}
	set_transient( 'portfolio_last_rebuild', time(), DAY_IN_SECONDS );
	return true;
}

// Più salvataggi nella stessa richiesta = un solo rebuild, inviato a fine richiesta.
function portfolio_rebuild_later() {
	static $scheduled = false;
	if ( $scheduled ) {
		return;
	}
	$scheduled = true;
	add_action( 'shutdown', 'portfolio_rebuild' );
}

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'progetti', 'page', 'post' ), true ) ) {
		return;
	}
	// Solo se il contenuto è o era pubblico.
	if ( 'publish' === $new || 'publish' === $old ) {
		portfolio_rebuild_later();
	}
}, 10, 3 );

foreach ( array( 'created_servizi', 'edited_servizi', 'delete_servizi' ) as $hook ) {
	add_action( $hook, 'portfolio_rebuild_later' );
}

/* -------------------------------------------------------------------------
 * 6. Pulsante "Ripubblica sito" nella barra di amministrazione
 * ---------------------------------------------------------------------- */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$bar->add_node( array(
		'id'    => 'portfolio-rebuild',
		'title' => 'Ripubblica sito',
		'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=portfolio_rebuild' ), 'portfolio_rebuild' ),
	) );
	if ( portfolio_site_url() ) {
		$bar->add_node( array(
			'id'     => 'portfolio-view',
			'parent' => 'portfolio-rebuild',
			'title'  => 'Apri il sito pubblico',
			'href'   => portfolio_site_url() . '/',
			'meta'   => array( 'target' => '_blank' ),
		) );
	}
}, 100 );

add_action( 'admin_post_portfolio_rebuild', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Permesso negato.', 403 );
	}
	check_admin_referer( 'portfolio_rebuild' );
	$r    = portfolio_rebuild();
	$back = wp_get_referer() ? wp_get_referer() : admin_url();
	wp_safe_redirect( add_query_arg( 'portfolio_rebuild', is_wp_error( $r ) ? 'errore' : 'ok', $back ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( empty( $_GET['portfolio_rebuild'] ) ) {
		return;
	}
	$ok = 'ok' === $_GET['portfolio_rebuild'];
	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		$ok ? 'success' : 'error',
		$ok ? 'Ripubblicazione avviata: il sito sarà aggiornato tra un paio di minuti.' : 'Ripubblicazione non riuscita: controlla le costanti PORTFOLIO_GITHUB_* in wp-config.php.'
	);
} );

/* -------------------------------------------------------------------------
 * Contenuti della Home (richiede Secure Custom Fields o ACF PRO per il repeater)
 * GET /wp-json/portfolio/v1/home
 * ---------------------------------------------------------------------- */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	// Menu "Testi del sito": una pagina per ogni pagina del sito (vedi portfolio_testi_schema()).
	acf_add_options_page( array(
		'page_title' => 'Testi del sito',
		'menu_title' => 'Testi del sito',
		'menu_slug'  => 'portfolio-testi',
		'capability' => 'edit_posts',
		'icon_url'   => 'dashicons-edit-page',
		'position'   => 4,
		'redirect'   => true,
	) );
	foreach ( portfolio_testi_schema() as $pagina => $def ) {
		acf_add_options_sub_page( array(
			'page_title'  => 'Testi: ' . $def['menu'],
			'menu_title'  => $def['menu'],
			'menu_slug'   => 'portfolio-' . $pagina,
			'parent_slug' => 'portfolio-testi',
			'capability'  => 'edit_posts',
		) );
		$fields = array();
		foreach ( $def['gruppi'] as $gruppo => $campi ) {
			$fields[] = array( 'key' => "field_pt_{$pagina}_tab_" . sanitize_key( $gruppo ), 'label' => $gruppo, 'type' => 'tab' );
			foreach ( $campi as $nome => $c ) {
				$fields[] = array(
					'key'           => "field_pt_{$pagina}_{$nome}",
					'name'          => "{$pagina}_{$nome}",
					'label'         => $c[0],
					'type'          => $c[1],
					'default_value' => isset( $c[2] ) ? $c[2] : '',
					'instructions'  => isset( $c[3] ) ? $c[3] : '',
					'rows'          => 'textarea' === $c[1] ? 3 : '',
					'media_upload'  => 0,
				);
			}
		}
		acf_add_local_field_group( array(
			'key'        => "group_portfolio_testi_{$pagina}",
			'title'      => 'Testi',
			'fields'     => $fields,
			'menu_order' => -1,
			'location'   => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'portfolio-' . $pagina ) ) ),
		) );
	}

	acf_add_local_field_group( array(
		'key'      => 'group_portfolio_home_servizi',
		'title'    => 'Sezione Servizi',
		'fields'   => array(
			array( 'key' => 'field_ph_serv_etichetta', 'name' => 'servizi_etichetta', 'label' => 'Etichetta', 'type' => 'text', 'default_value' => '', 'instructions' => 'Facoltativa, es. "01. Servizi".', 'wrapper' => array( 'width' => 25 ) ),
			array( 'key' => 'field_ph_serv_titolo', 'name' => 'servizi_titolo', 'label' => 'Titolo', 'type' => 'text', 'default_value' => 'Servizi nel', 'wrapper' => array( 'width' => 40 ) ),
			array( 'key' => 'field_ph_serv_titolo_evid', 'name' => 'servizi_titolo_evidenza', 'label' => 'Parte evidenziata del titolo', 'type' => 'text', 'default_value' => 'dettaglio', 'instructions' => 'Mostrata in verde dopo il titolo.', 'wrapper' => array( 'width' => 35 ) ),
			array( 'key' => 'field_ph_serv_intro', 'name' => 'servizi_intro', 'label' => 'Introduzione', 'type' => 'textarea', 'rows' => 2 ),
			array(
				'key'          => 'field_ph_servizi',
				'name'         => 'servizi',
				'label'        => 'Servizi',
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => 'Aggiungi servizio',
				'collapsed'    => 'field_ph_servizio_titolo',
				'sub_fields'   => array(
					array(
						'key'     => 'field_ph_servizio_icona',
						'name'    => 'icona',
						'label'   => 'Icona',
						'type'    => 'select',
						'choices' => array(
							'globe'      => 'Globo (siti web)',
							'layout'     => 'Layout (WordPress, template)',
							'cart'       => 'Carrello (e-commerce)',
							'palette'    => 'Tavolozza (design)',
							'chart'      => 'Grafico (consulenza, analytics)',
							'code'       => 'Codice',
							'search'     => 'Lente (SEO)',
							'server'     => 'Server (backend, hosting)',
							'smartphone' => 'Smartphone (app)',
							'zap'        => 'Fulmine (performance)',
							'rocket'     => 'Razzo (lancio, startup)',
							'wrench'     => 'Chiave inglese (manutenzione)',
							'layers'     => 'Livelli (architettura)',
						),
						'default_value' => 'globe',
						'wrapper'       => array( 'width' => 25 ),
					),
					array( 'key' => 'field_ph_servizio_titolo', 'name' => 'titolo', 'label' => 'Titolo', 'type' => 'text', 'required' => 1, 'wrapper' => array( 'width' => 35 ) ),
					array( 'key' => 'field_ph_servizio_sottotitolo', 'name' => 'sottotitolo', 'label' => 'Sottotitolo', 'type' => 'text', 'instructions' => 'Riga breve in verde, es. "Siti veloci, moderni e su misura".', 'wrapper' => array( 'width' => 40 ) ),
					array( 'key' => 'field_ph_servizio_descrizione', 'name' => 'descrizione', 'label' => 'Descrizione', 'type' => 'textarea', 'rows' => 3 ),
					array( 'key' => 'field_ph_servizio_include', 'name' => 'include', 'label' => 'Cosa include', 'type' => 'textarea', 'rows' => 5, 'instructions' => 'Una voce per riga.' ),
					array(
						'key'        => 'field_ph_servizio_link',
						'name'       => 'link',
						'label'      => 'Link (facoltativo)',
						'type'       => 'group',
						'layout'     => 'row',
						'sub_fields' => array(
							array( 'key' => 'field_ph_servizio_link_testo', 'name' => 'testo', 'label' => 'Testo', 'type' => 'text', 'default_value' => 'Scopri di più' ),
							array( 'key' => 'field_ph_servizio_link_url', 'name' => 'url', 'label' => 'URL', 'type' => 'text' ),
						),
					),
				),
			),
		),
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'portfolio-home' ) ) ),
	) );
} );

function portfolio_home_data() {
	$opt = function ( $name, $default = '' ) {
		$v = function_exists( 'get_field' ) ? get_field( $name, 'option' ) : get_option( 'options_' . $name );
		return ( null === $v || false === $v || '' === $v ) ? $default : $v;
	};
	$righe   = function ( $v ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $v ) ) ) );
	};
	$servizi = array();
	foreach ( (array) $opt( 'servizi', array() ) as $row ) {
		if ( empty( $row['titolo'] ) ) {
			continue;
		}
		$link      = isset( $row['link'] ) && is_array( $row['link'] ) ? $row['link'] : array();
		$servizi[] = array(
			'icona'       => ! empty( $row['icona'] ) ? (string) $row['icona'] : 'globe',
			'titolo'      => (string) $row['titolo'],
			'sottotitolo' => isset( $row['sottotitolo'] ) ? (string) $row['sottotitolo'] : '',
			'descrizione' => isset( $row['descrizione'] ) ? (string) $row['descrizione'] : '',
			'include'     => $righe( isset( $row['include'] ) ? $row['include'] : '' ),
			'link'        => array(
				'testo' => ! empty( $link['testo'] ) ? (string) $link['testo'] : 'Scopri di più',
				'url'   => ! empty( $link['url'] ) ? (string) $link['url'] : '',
			),
		);
	}
	return array(
		'servizi' => array(
			'etichetta'       => (string) $opt( 'servizi_etichetta', '' ),
			'titolo'          => (string) $opt( 'servizi_titolo', 'Servizi nel' ),
			'titoloEvidenza'  => (string) $opt( 'servizi_titolo_evidenza', 'dettaglio' ),
			'intro'           => (string) $opt( 'servizi_intro', '' ),
			'elenco'          => $servizi,
		),
	);
}

/**
 * Testi modificabili di ogni pagina: pagina => menu, gruppi (schede) => campo => [etichetta, tipo, predefinito, istruzioni].
 * I predefiniti sono gli stessi di src/lib/testi.ts: un campo lasciato vuoto usa quello.
 * Nel database ogni campo è un'opzione "{pagina}_{campo}".
 */
function portfolio_testi_schema() {
	$vuoto_auto = 'Lasciando vuoto si usa il testo predefinito del sito.';
	return array(
		'generale' => array(
			'menu'   => 'Generale',
			'gruppi' => array(
				'Identità' => array(
					'nome'        => array( 'Nome (logo)', 'text', 'Gabriele' ),
					'ruolo'       => array( 'Ruolo', 'text', 'Sviluppatore web full-stack' ),
					'citta'       => array( 'Città', 'text', 'Palermo' ),
					'descrizione' => array( 'Descrizione del sito', 'textarea', 'Siti WordPress veloci e applicazioni web con Java, Spring Boot, Angular e PostgreSQL. Lavoro da Palermo, con clienti in tutta Italia.', 'Usata nei motori di ricerca e sotto il titolo della home.' ),
				),
				'Contatti' => array(
					'email'           => array( 'Email', 'email', '' ),
					'telefono'        => array( 'Telefono', 'text', '', 'Come vuoi che si legga, es. +39 333 1234567.' ),
					'whatsapp'        => array( 'Numero WhatsApp', 'text', '', 'Con prefisso internazionale, es. 393331234567. Vuoto = nessun pulsante WhatsApp.' ),
					'link_call'       => array( 'Link per prenotare una call', 'url', '', 'Es. Calendly o Google Calendar. Vuoto = riquadro nascosto.' ),
					'link_recensione' => array( 'Link per lasciare una recensione', 'url', '', 'Es. il link di Google per le recensioni. Il QR code viene creato da questo link. Vuoto = riquadro nascosto.' ),
				),
				'Social' => array(
					'instagram' => array( 'Instagram', 'url', '' ),
					'facebook'  => array( 'Facebook', 'url', '' ),
					'linkedin'  => array( 'LinkedIn', 'url', '' ),
					'github'    => array( 'GitHub', 'url', 'https://github.com/Gabriele1988' ),
					'youtube'   => array( 'YouTube', 'url', '' ),
				),
				'Menu' => array(
					'menu_home'     => array( 'Voce Home', 'text', 'Home' ),
					'menu_servizi'  => array( 'Voce Servizi', 'text', 'Servizi' ),
					'menu_progetti' => array( 'Voce Progetti', 'text', 'Progetti' ),
					'menu_contatti' => array( 'Voce Contatti', 'text', 'Contatti' ),
					'menu_cta'      => array( 'Pulsante', 'text', 'Richiedi un preventivo' ),
				),
				'Footer' => array(
					'footer_descrizione'     => array( 'Testo sotto il logo', 'textarea', "Sviluppatore web a Palermo.\nCreo siti che funzionano, per attività che contano." ),
					'footer_titolo_nav'      => array( 'Titolo colonna navigazione', 'text', 'Navigazione' ),
					'footer_titolo_contatti' => array( 'Titolo colonna contatti', 'text', 'Contatti' ),
					'footer_privacy'         => array( 'Link privacy', 'text', 'Privacy Policy' ),
					'dati_legali'            => array( 'Dati legali', 'textarea', '', 'Es. P.IVA, regime fiscale, sede. Una riga per riga.' ),
					'copyright'              => array( 'Copyright', 'text', 'Tutti i diritti riservati.', 'Preceduto da © anno e nome.' ),
					'credito'                => array( 'Testo in basso a destra', 'text', 'Sviluppato con Astro & ♥' ),
				),
			),
		),
		'home' => array(
			'menu'   => 'Home',
			'gruppi' => array(
				'Hero' => array(
					'hero_occhiello' => array( 'Testo sopra il titolo', 'text', '', 'Lasciando vuoto: ruolo · città.' ),
					'hero_titolo'    => array( 'Titolo', 'text', 'Siti veloci e applicazioni web su misura.' ),
					'hero_testo'     => array( 'Testo', 'textarea', '', 'Lasciando vuoto si usa la descrizione del sito (Generale).' ),
					'hero_cta'       => array( 'Pulsante principale', 'text', 'Richiedi un preventivo' ),
					'hero_cta2'      => array( 'Pulsante secondario', 'text', 'Guarda i progetti' ),
				),
				'Progetti in evidenza' => array(
					'progetti_titolo' => array( 'Titolo', 'text', 'Progetti in evidenza' ),
					'progetti_vuoto'  => array( 'Testo se non ci sono progetti', 'text', 'I progetti arriveranno presto.' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', '', 'Lasciando vuoto: nome · ruolo.' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', '', 'Lasciando vuoto si usa la descrizione del sito.' ),
				),
			),
		),
		'progetti' => array(
			'menu'   => 'Progetti',
			'gruppi' => array(
				'Intestazione' => array(
					'titolo'          => array( 'Titolo', 'text', 'Lavori' ),
					'titolo_evidenza' => array( 'Parte colorata del titolo', 'text', 'recenti' ),
					'intro'           => array( 'Introduzione', 'textarea', 'Ogni progetto è una storia diversa. Ecco quelli già online e quelli a cui sto lavorando.' ),
				),
				'Progetto in evidenza' => array(
					'etichetta_evidenza'  => array( 'Etichetta', 'text', 'Progetto in evidenza' ),
					'funzionalita_titolo' => array( 'Titolo elenco funzionalità', 'text', 'Funzionalità chiave' ),
				),
				'Altri progetti' => array(
					'altri_titolo'          => array( 'Titolo', 'text', 'Altri' ),
					'altri_titolo_evidenza' => array( 'Parte colorata del titolo', 'text', 'progetti' ),
					'vuoto'                 => array( 'Testo se non ci sono progetti', 'text', 'I progetti arriveranno presto.' ),
					'precedente'            => array( 'Paginazione: precedente', 'text', 'Precedente' ),
					'successiva'            => array( 'Paginazione: successiva', 'text', 'Successiva' ),
				),
				'Card' => array(
					'stato_online'   => array( 'Stato online', 'text', 'Online' ),
					'stato_sviluppo' => array( 'Stato in sviluppo', 'text', 'In sviluppo' ),
					'presto'         => array( 'Testo senza immagine', 'text', 'Presto online' ),
					'visita'         => array( 'Link al sito', 'text', 'Visita il sito' ),
					'dettagli'       => array( 'Link al progetto', 'text', 'Scopri il progetto' ),
				),
				'Singolo progetto' => array(
					'torna'            => array( 'Link di ritorno', 'text', '← Tutti i progetti' ),
					'label_cliente'    => array( 'Etichetta Cliente', 'text', 'Cliente' ),
					'label_anno'       => array( 'Etichetta Anno', 'text', 'Anno' ),
					'label_ruolo'      => array( 'Etichetta Ruolo', 'text', 'Ruolo' ),
					'label_servizi'    => array( 'Etichetta Servizi', 'text', 'Servizi' ),
					'label_online'     => array( 'Etichetta Online', 'text', 'Online' ),
					'label_risultato'  => array( 'Titolo Risultato', 'text', 'Risultato' ),
					'cta_progetto'     => array( 'Pulsante finale', 'text', 'Hai un progetto simile? Richiedi un preventivo' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Progetti' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', 'Una selezione di lavori: il problema, la soluzione e il risultato.' ),
				),
			),
		),
		'contatti' => array(
			'menu'   => 'Contatti',
			'gruppi' => array(
				'Intestazione' => array(
					'titolo_evidenza' => array( 'Prima riga del titolo (colorata)', 'text', 'Inizia' ),
					'titolo'          => array( 'Seconda riga del titolo', 'text', 'tutto da qui.' ),
					'intro'           => array( 'Sottotitolo', 'text', 'Pronto a creare qualcosa che ancora non esiste?' ),
				),
				'Colonna sinistra' => array(
					'label_email'        => array( 'Etichetta email', 'text', 'Email' ),
					'label_telefono'     => array( 'Etichetta telefono', 'text', 'Telefono' ),
					'label_whatsapp'     => array( 'Etichetta WhatsApp', 'text', 'WhatsApp' ),
					'testo_whatsapp'     => array( 'Testo WhatsApp', 'text', 'Scrivimi su WhatsApp' ),
					'call_titolo'        => array( 'Call: titolo', 'text', 'Preferisci una call?' ),
					'call_testo'         => array( 'Call: testo', 'text', 'Prenota 30 minuti per parlare del tuo progetto' ),
					'call_pulsante'      => array( 'Call: pulsante', 'text', 'Prenota una call' ),
					'seguimi'            => array( 'Titolo social', 'text', 'Seguimi' ),
					'recensione_titolo'  => array( 'Recensione: titolo', 'text', 'Hai già lavorato con me?' ),
					'recensione_testo'   => array( 'Recensione: testo', 'textarea', 'La tua opinione mi aiuta a crescere e a farmi trovare da nuovi clienti.' ),
					'recensione_nota'    => array( 'Recensione: nota', 'text', 'Scansiona il QR code o clicca il bottone' ),
					'recensione_pulsante' => array( 'Recensione: pulsante', 'text', 'Recensione' ),
				),
				'Modulo' => array(
					'form_titolo'           => array( 'Titolo', 'text', 'Hai qualcosa da' ),
					'form_titolo_evidenza'  => array( 'Parte colorata del titolo', 'text', 'chiedermi' ),
					'form_titolo_fine'      => array( 'Fine del titolo', 'text', '?' ),
					'campo_nome'            => array( 'Nome', 'text', 'Nome' ),
					'ph_nome'               => array( 'Nome: suggerimento', 'text', 'Il tuo nome' ),
					'campo_cognome'         => array( 'Cognome', 'text', 'Cognome' ),
					'ph_cognome'            => array( 'Cognome: suggerimento', 'text', 'Il tuo cognome' ),
					'campo_azienda'         => array( 'Azienda', 'text', 'Azienda' ),
					'ph_azienda'            => array( 'Azienda: suggerimento', 'text', 'Nome azienda (opzionale)' ),
					'campo_email'           => array( 'Email', 'text', 'Email' ),
					'ph_email'              => array( 'Email: suggerimento', 'text', 'la-tua@email.it' ),
					'campo_contatto'        => array( 'Domanda: dove contattarti', 'text', 'Dove posso contattarti?' ),
					'opz_email'             => array( 'Opzione Email', 'text', 'Email' ),
					'opz_telefono'          => array( 'Opzione Telefono', 'text', 'Telefono' ),
					'opz_whatsapp'          => array( 'Opzione WhatsApp', 'text', 'WhatsApp' ),
					'campo_telefono'        => array( 'Telefono (compare con Telefono o WhatsApp)', 'text', 'Numero di telefono' ),
					'ph_telefono'           => array( 'Telefono: suggerimento', 'text', '+39 333 1234567' ),
					'campo_sito'            => array( 'Domanda: hai già un sito', 'text', 'Hai già un sito?' ),
					'opz_sito_no'           => array( 'Opzione No', 'text', 'No' ),
					'opz_sito_si'           => array( 'Opzione Sì', 'text', 'Sì' ),
					'opz_sito_rifare'       => array( 'Opzione Sì, da rifare', 'text', 'Sì, ma va rifatto' ),
					'campo_budget'          => array( 'Domanda: budget', 'text', 'Hai un budget?' ),
					'opz_budget_si'         => array( 'Opzione Sì', 'text', 'Sì' ),
					'opz_budget_no'         => array( 'Opzione No', 'text', 'No' ),
					'campo_messaggio'       => array( 'Messaggio', 'text', 'Messaggio' ),
					'ph_messaggio'          => array( 'Messaggio: suggerimento', 'text', 'Raccontami il tuo progetto…' ),
					'privacy'               => array( 'Consenso privacy', 'textarea', 'Ho letto e accetto la [Privacy Policy]', 'Il testo tra parentesi quadre diventa il link alla pagina Privacy.' ),
					'invia'                 => array( 'Pulsante', 'text', 'Invia messaggio' ),
					'reset'                 => array( 'Link per svuotare il modulo', 'text', 'Resetta il form' ),
					'invio'                 => array( 'Messaggio durante l\'invio', 'text', 'Invio in corso…' ),
					'grazie'                => array( 'Messaggio inviato', 'text', 'Grazie! Ho ricevuto il tuo messaggio e ti risponderò a breve.' ),
					'errore'                => array( 'Aggiunta in caso di errore', 'text', 'Riprova oppure scrivimi via email.' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Contatti' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', 'Raccontami il tuo progetto: rispondo entro un giorno lavorativo.' ),
				),
			),
		),
		'privacy' => array(
			'menu'   => 'Privacy',
			'gruppi' => array(
				'Contenuto' => array(
					'titolo'    => array( 'Titolo', 'text', 'Privacy e cookie' ),
					'contenuto' => array( 'Testo', 'wysiwyg', '', 'Lasciando vuoto si vede la bozza provvisoria.' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Privacy e cookie' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', 'Informativa sul trattamento dei dati personali.' ),
				),
			),
		),
		'notfound' => array(
			'menu'   => 'Pagina non trovata',
			'gruppi' => array(
				'Contenuto' => array(
					'titolo'   => array( 'Titolo', 'text', 'Pagina non trovata' ),
					'testo'    => array( 'Testo', 'textarea', 'La pagina che cerchi non esiste o è stata spostata.' ),
					'pulsante' => array( 'Pulsante', 'text', 'Torna alla home' ),
				),
			),
		),
	);
}

/** GET /wp-json/portfolio/v1/testi: { pagina: { campo: valore } }. I campi mai salvati arrivano vuoti. */
function portfolio_testi_data() {
	$out = array();
	foreach ( portfolio_testi_schema() as $pagina => $def ) {
		$out[ $pagina ] = array();
		foreach ( $def['gruppi'] as $campi ) {
			foreach ( $campi as $nome => $c ) {
				$v = get_option( "options_{$pagina}_{$nome}", '' );
				$out[ $pagina ][ $nome ] = is_string( $v ) ? trim( $v ) : '';
			}
		}
	}
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'portfolio/v1', '/testi', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_testi_data',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'portfolio/v1', '/home', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_home_data',
		'permission_callback' => '__return_true',
	) );
} );

// Salvando una pagina di "Testi del sito" parte il rebuild del sito.
add_action( 'acf/save_post', function ( $post_id ) {
	if ( 'options' === $post_id ) {
		portfolio_rebuild_later();
	}
}, 20 );

/* -------------------------------------------------------------------------
 * Form contatti: POST /wp-json/portfolio/v1/contatto
 * ---------------------------------------------------------------------- */
function portfolio_contatto( WP_REST_Request $req ) {
	$site   = portfolio_site_url();
	$origin = get_http_origin();
	if ( $site && $origin && untrailingslashit( $origin ) !== $site ) {
		return new WP_Error( 'origine', 'Origine non consentita.', array( 'status' => 403 ) );
	}

	// Bot: campo trappola compilato o invio troppo rapido. Rispondiamo "ok" senza inviare nulla.
	if ( '' !== trim( (string) $req->get_param( 'sito_web' ) ) || (int) $req->get_param( 'tempo' ) < 3 ) {
		return array( 'ok' => true );
	}

	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'portfolio_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return new WP_Error( 'troppi', 'Troppi messaggi in poco tempo.', array( 'status' => 429 ) );
	}

	$testo     = function ( $k ) use ( $req ) {
		return sanitize_text_field( (string) $req->get_param( $k ) );
	};
	$scelta    = function ( $k, $ammessi ) use ( $testo ) {
		$v = $testo( $k );
		return isset( $ammessi[ $v ] ) ? $ammessi[ $v ] : '';
	};
	$nome      = $testo( 'nome' );
	$cognome   = $testo( 'cognome' );
	$azienda   = $testo( 'azienda' );
	$telefono  = $testo( 'telefono' );
	$email     = sanitize_email( (string) $req->get_param( 'email' ) );
	$messaggio = sanitize_textarea_field( (string) $req->get_param( 'messaggio' ) );
	$contatto  = $scelta( 'contatto', array( 'email' => 'Email', 'telefono' => 'Telefono', 'whatsapp' => 'WhatsApp' ) );
	$sito      = $scelta( 'sito', array( 'no' => 'No', 'si' => 'Sì', 'rifare' => 'Sì, ma va rifatto' ) );
	$budget    = $scelta( 'budget', array( 'si' => 'Sì', 'no' => 'No' ) );

	if ( '' === $nome || ! is_email( $email ) || mb_strlen( $messaggio ) < 10 ) {
		return new WP_Error( 'dati', 'Controlla nome, email e messaggio.', array( 'status' => 400 ) );
	}
	if ( in_array( $contatto, array( 'Telefono', 'WhatsApp' ), true ) && '' === $telefono ) {
		return new WP_Error( 'telefono', 'Inserisci il numero di telefono.', array( 'status' => 400 ) );
	}
	if ( true !== $req->get_param( 'privacy' ) ) {
		return new WP_Error( 'privacy', 'Serve il consenso al trattamento dei dati.', array( 'status' => 400 ) );
	}

	if ( defined( 'PORTFOLIO_TURNSTILE_SECRET' ) && PORTFOLIO_TURNSTILE_SECRET ) {
		$v = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => PORTFOLIO_TURNSTILE_SECRET,
				'response' => (string) $req->get_param( 'turnstile' ),
				'remoteip' => $ip,
			),
		) );
		$body = json_decode( wp_remote_retrieve_body( $v ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'captcha', 'Verifica anti-spam non riuscita.', array( 'status' => 400 ) );
		}
	}

	$to   = defined( 'PORTFOLIO_CONTACT_EMAIL' ) ? PORTFOLIO_CONTACT_EMAIL : get_option( 'admin_email' );
	$sent = wp_mail(
		$to,
		'[Portfolio] Nuova richiesta da ' . trim( "{$nome} {$cognome}" ),
		implode( "\n", array_filter( array(
			'Nome: ' . trim( "{$nome} {$cognome}" ),
			$azienda ? "Azienda: {$azienda}" : '',
			"Email: {$email}",
			$telefono ? "Telefono: {$telefono}" : '',
			$contatto ? "Preferisce essere contattato via: {$contatto}" : '',
			$sito ? "Ha già un sito: {$sito}" : '',
			$budget ? "Ha un budget: {$budget}" : '',
		) ) ) . "\n\n{$messaggio}\n",
		array( 'Reply-To: ' . trim( "{$nome} {$cognome}" ) . ' <' . $email . '>' )
	);
	if ( ! $sent ) {
		return new WP_Error( 'invio', 'Non sono riuscito a inviare il messaggio.', array( 'status' => 500 ) );
	}

	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return array( 'ok' => true );
}

add_action( 'phpmailer_init', function ( $mailer ) {
	if ( ! defined( 'PORTFOLIO_SMTP_HOST' ) ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host       = PORTFOLIO_SMTP_HOST;
	$mailer->Port       = defined( 'PORTFOLIO_SMTP_PORT' ) ? PORTFOLIO_SMTP_PORT : 587;
	$mailer->SMTPAuth   = defined( 'PORTFOLIO_SMTP_USER' );
	$mailer->Username   = defined( 'PORTFOLIO_SMTP_USER' ) ? PORTFOLIO_SMTP_USER : '';
	$mailer->Password   = defined( 'PORTFOLIO_SMTP_PASS' ) ? PORTFOLIO_SMTP_PASS : '';
	$mailer->SMTPSecure = defined( 'PORTFOLIO_SMTP_SECURE' ) ? PORTFOLIO_SMTP_SECURE : 'tls';
	if ( defined( 'PORTFOLIO_SMTP_USER' ) ) {
		$mailer->setFrom( PORTFOLIO_SMTP_USER, get_bloginfo( 'name' ) );
	}
} );
