<?php
/**
 * Plugin Name: Portfolio Core
 * Description: WordPress headless per il portfolio Astro: Progetti, Servizi, contenuti della Home, campi, REST API, redirect del front-end, rebuild su GitHub e form contatti.
 * Version:     1.2.0
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
		'anno'        => array( 'label' => 'Anno', 'type' => 'number' ),
		'url'         => array( 'label' => 'URL del progetto', 'type' => 'url' ),
		'ruolo'       => array( 'label' => 'Ruolo', 'type' => 'text' ),
		'tecnologie'  => array( 'label' => 'Tecnologie', 'type' => 'text', 'instructions' => 'Separate da virgola, es. Angular, Spring Boot, PostgreSQL' ),
		'risultato'   => array( 'label' => 'Risultato', 'type' => 'textarea', 'instructions' => 'Il risultato ottenuto, in una o due frasi (meglio se con numeri).' ),
		'in_evidenza' => array( 'label' => 'In evidenza', 'type' => 'true_false', 'instructions' => 'Mostra in home page.' ),
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
			'key'          => 'field_portfolio_' . $name,
			'name'         => $name,
			'label'        => $c['label'],
			'type'         => $c['type'],
			'instructions' => isset( $c['instructions'] ) ? $c['instructions'] : '',
			'ui'           => 'true_false' === $c['type'] ? 1 : 0,
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
	acf_add_options_page( array(
		'page_title' => 'Contenuti della Home',
		'menu_title' => 'Home',
		'menu_slug'  => 'portfolio-home',
		'capability' => 'edit_posts',
		'icon_url'   => 'dashicons-admin-home',
		'position'   => 4,
		'redirect'   => false,
	) );

	acf_add_local_field_group( array(
		'key'      => 'group_portfolio_home_servizi',
		'title'    => 'Sezione Servizi',
		'fields'   => array(
			array( 'key' => 'field_ph_serv_etichetta', 'name' => 'servizi_etichetta', 'label' => 'Etichetta', 'type' => 'text', 'default_value' => '01. Servizi', 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_ph_serv_titolo', 'name' => 'servizi_titolo', 'label' => 'Titolo', 'type' => 'text', 'default_value' => 'Cosa faccio', 'wrapper' => array( 'width' => 67 ) ),
			array( 'key' => 'field_ph_serv_intro', 'name' => 'servizi_intro', 'label' => 'Introduzione', 'type' => 'textarea', 'rows' => 2 ),
			array( 'key' => 'field_ph_serv_link_testo', 'name' => 'servizi_link_testo', 'label' => 'Testo del link', 'type' => 'text', 'default_value' => 'Tutti i servizi', 'wrapper' => array( 'width' => 33 ) ),
			array( 'key' => 'field_ph_serv_link_url', 'name' => 'servizi_link_url', 'label' => 'URL del link', 'type' => 'text', 'instructions' => 'Percorso sul sito, es. /servizi/ oppure /contatti/', 'wrapper' => array( 'width' => 67 ) ),
			array(
				'key'          => 'field_ph_servizi',
				'name'         => 'servizi',
				'label'        => 'Servizi',
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => 'Aggiungi servizio',
				'collapsed'    => 'field_ph_servizio_titolo',
				'sub_fields'   => array(
					array( 'key' => 'field_ph_servizio_titolo', 'name' => 'titolo', 'label' => 'Titolo', 'type' => 'text', 'required' => 1 ),
					array( 'key' => 'field_ph_servizio_descrizione', 'name' => 'descrizione', 'label' => 'Descrizione', 'type' => 'textarea', 'rows' => 3 ),
					array( 'key' => 'field_ph_servizio_voci', 'name' => 'voci', 'label' => 'Voci / tecnologie', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Facoltativo: una per riga (es. React, Next.js, WordPress).' ),
					array(
						'key'        => 'field_ph_servizio_link',
						'name'       => 'link',
						'label'      => 'Link',
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
	$servizi = array();
	foreach ( (array) $opt( 'servizi', array() ) as $row ) {
		if ( empty( $row['titolo'] ) ) {
			continue;
		}
		$link      = isset( $row['link'] ) && is_array( $row['link'] ) ? $row['link'] : array();
		$servizi[] = array(
			'titolo'      => (string) $row['titolo'],
			'descrizione' => isset( $row['descrizione'] ) ? (string) $row['descrizione'] : '',
			'voci'        => array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', isset( $row['voci'] ) ? (string) $row['voci'] : '' ) ) ) ),
			'link'        => array(
				'testo' => ! empty( $link['testo'] ) ? (string) $link['testo'] : 'Scopri di più',
				'url'   => ! empty( $link['url'] ) ? (string) $link['url'] : '',
			),
		);
	}
	return array(
		'servizi' => array(
			'etichetta' => (string) $opt( 'servizi_etichetta', '01. Servizi' ),
			'titolo'    => (string) $opt( 'servizi_titolo', 'Cosa faccio' ),
			'intro'     => (string) $opt( 'servizi_intro', '' ),
			'link'      => array(
				'testo' => (string) $opt( 'servizi_link_testo', 'Tutti i servizi' ),
				'url'   => (string) $opt( 'servizi_link_url', '' ),
			),
			'elenco'    => $servizi,
		),
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'portfolio/v1', '/home', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_home_data',
		'permission_callback' => '__return_true',
	) );
} );

// Salvando la pagina "Home" parte il rebuild del sito.
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

	$nome      = sanitize_text_field( (string) $req->get_param( 'nome' ) );
	$email     = sanitize_email( (string) $req->get_param( 'email' ) );
	$messaggio = sanitize_textarea_field( (string) $req->get_param( 'messaggio' ) );

	if ( '' === $nome || ! is_email( $email ) || mb_strlen( $messaggio ) < 10 ) {
		return new WP_Error( 'dati', 'Controlla nome, email e messaggio.', array( 'status' => 400 ) );
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
		'[Portfolio] Nuova richiesta da ' . $nome,
		"Nome: {$nome}\nEmail: {$email}\n\n{$messaggio}\n",
		array( 'Reply-To: ' . $nome . ' <' . $email . '>' )
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
