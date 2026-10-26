<?php
/**
 * Plugin Name: Portfolio Core
 * Description: WordPress headless per il portfolio Astro: Progetti, Servizi, contenuti della Home, campi, REST API, redirect del front-end, rebuild su GitHub e form contatti.
 * Version:     1.11.0
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
		'funzionalita' => array( 'label' => 'Funzionalità chiave', 'type' => 'textarea', 'instructions' => 'Una per riga. Si vedono nella scheda a destra della pagina del progetto e quando il progetto è quello in evidenza nella pagina Progetti.' ),
		'repository'  => array( 'label' => 'Repository GitHub', 'type' => 'url', 'instructions' => 'Facoltativo, se il codice è pubblico.' ),
		'risultato'   => array( 'label' => 'Risultato', 'type' => 'textarea', 'instructions' => 'Il risultato ottenuto, in una o due frasi (meglio se con numeri).' ),
		'galleria'    => array( 'label' => 'Galleria', 'type' => 'gallery', 'instructions' => 'Screenshot o foto del progetto (pagine interne, versione mobile, dettagli). Si vedono in fondo alla pagina del progetto e si aprono a tutto schermo.' ),
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
		if ( 'gallery' === $c['type'] ) {
			$fields[ count( $fields ) - 1 ] += array( 'return_format' => 'id', 'preview_size' => 'medium', 'insert' => 'append', 'mime_types' => 'jpg, jpeg, png, webp' );
		}
	}
	acf_add_local_field_group( array(
		'key'      => 'group_portfolio_progetto',
		'title'    => 'Dettagli progetto',
		'fields'   => $fields,
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'progetti' ) ) ),
		'position' => 'acf_after_title',
	) );

	// Riquadro SEO del progetto, sotto il contenuto.
	acf_add_local_field_group( array(
		'key'      => 'group_portfolio_progetto_seo',
		'title'    => 'SEO',
		'fields'   => array(
			array( 'key' => 'field_portfolio_seo_titolo', 'name' => 'seo_titolo', 'label' => 'Titolo SEO', 'type' => 'text', 'maxlength' => 70, 'instructions' => 'Il titolo che compare su Google (circa 50-60 caratteri). Vuoto = titolo del progetto.' ),
			array( 'key' => 'field_portfolio_seo_descrizione', 'name' => 'seo_descrizione', 'label' => 'Meta descrizione', 'type' => 'textarea', 'rows' => 2, 'maxlength' => 170, 'instructions' => 'Il testo sotto il titolo su Google (circa 140-160 caratteri). Vuoto = estratto.' ),
			array( 'key' => 'field_portfolio_seo_immagine', 'name' => 'seo_immagine', 'label' => 'Immagine per i social', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'instructions' => 'Mostrata quando il link viene condiviso (WhatsApp, LinkedIn, Facebook). Vuoto = immagine in evidenza.' ),
			array( 'key' => 'field_portfolio_seo_noindex', 'name' => 'seo_noindex', 'label' => 'Nascondi dai motori di ricerca', 'type' => 'true_false', 'ui' => 1, 'instructions' => 'La pagina resta visibile sul sito ma Google non la indicizza.' ),
		),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'progetti' ) ) ),
		'position' => 'normal',
		'menu_order' => 20,
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
				} elseif ( 'gallery' === $c['type'] ) {
					// Originali con dimensioni e testo alternativo: Astro le scarica e le ottimizza.
					$immagini = array();
					foreach ( array_filter( array_map( 'intval', (array) $v ) ) as $id ) {
						$img = wp_get_attachment_image_src( $id, 'full' );
						if ( $img ) {
							$immagini[] = array(
								'src'    => $img[0],
								'width'  => (int) $img[1],
								'height' => (int) $img[2],
								'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
							);
						}
					}
					$v = $immagini;
				}
				$out[ $name ] = $v;
			}
			return $out;
		},
		'schema'       => array( 'type' => 'object' ),
	) );

	register_rest_field( 'progetti', 'seo', array(
		'get_callback' => function ( $post ) {
			$img_id = (int) get_post_meta( $post['id'], 'seo_immagine', true );
			$img    = $img_id ? wp_get_attachment_image_src( $img_id, 'full' ) : false;
			return array(
				'titolo'      => trim( (string) get_post_meta( $post['id'], 'seo_titolo', true ) ),
				'descrizione' => trim( (string) get_post_meta( $post['id'], 'seo_descrizione', true ) ),
				'immagine'    => $img ? array( 'src' => $img[0], 'width' => (int) $img[1], 'height' => (int) $img[2] ) : null,
				'noindex'     => (bool) get_post_meta( $post['id'], 'seo_noindex', true ),
			);
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
					'return_format' => 'id',
					'preview_size'  => 'medium',
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
					'nome_completo' => array( 'Nome e cognome', 'text', 'Gabriele Vilardo', 'Usato nei dati per Google (scheda persona e attività).' ),
					'ruolo'       => array( 'Ruolo', 'text', 'Sviluppatore web full-stack' ),
					'citta'       => array( 'Città', 'text', 'Palermo' ),
					'descrizione' => array( 'Descrizione del sito', 'textarea', 'Siti WordPress veloci e applicazioni web con Java, Spring Boot, Angular e PostgreSQL. Lavoro da Palermo, con clienti in tutta Italia.', 'Usata nei motori di ricerca e sotto il titolo della home.' ),
				),
				'Contatti' => array(
					'email'           => array( 'Email', 'email', 'gvilardo@hotmail.com' ),
					'telefono'        => array( 'Telefono', 'text', '340 4800981', 'Come vuoi che si legga, es. +39 333 1234567.' ),
					'whatsapp'        => array( 'Numero WhatsApp', 'text', '393404800981', 'Con prefisso internazionale, es. 393331234567. Vuoto = nessun pulsante WhatsApp.' ),
					'indirizzo'       => array( 'Indirizzo', 'text', 'Via Resuttana 367, 90146 Palermo (PA)', 'Mostrato nella pagina Chi sono e nei dati per Google Maps. Formato: via e civico, CAP città (provincia).' ),
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
					'menu_chisono'  => array( 'Voce Chi sono', 'text', 'Chi sono' ),
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
					'label_tecnologie' => array( 'Titolo Tecnologie', 'text', 'Tecnologie' ),
					'galleria_titolo'  => array( 'Titolo della galleria', 'text', 'Galleria' ),
					'cta_progetto'     => array( 'Pulsante finale', 'text', 'Hai un progetto simile? Richiedi un preventivo' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Progetti' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', 'Una selezione di lavori: il problema, la soluzione e il risultato.' ),
				),
			),
		),
		'pag_servizi' => array(
			'menu'   => 'Servizi',
			'gruppi' => array(
				'Intestazione' => array(
					'titolo'          => array( 'Titolo', 'text', 'Quello che so fare,' ),
					'titolo_evidenza' => array( 'Parte colorata del titolo', 'text', 'fatto bene' ),
					'intro'           => array( 'Introduzione', 'textarea', 'Ogni progetto è diverso, ma il mio approccio è sempre lo stesso: ascoltare, progettare e costruire qualcosa che funziona davvero.' ),
				),
				'Problemi' => array(
					'problemi_titolo'   => array( 'Titolo', 'text', 'Quali problemi' ),
					'problemi_evidenza' => array( 'Parte colorata del titolo', 'text', 'risolvo' ),
				),
				'Processo' => array(
					'processo_etichetta' => array( 'Etichetta', 'text', 'Processo' ),
					'processo_titolo'    => array( 'Titolo', 'text', 'Come' ),
					'processo_evidenza'  => array( 'Parte colorata del titolo', 'text', 'lavoro' ),
					'processo_intro'     => array( 'Introduzione', 'textarea', 'Dal primo contatto al supporto dopo il lancio, ogni fase è trasparente.' ),
					'step'               => array( 'Parola prima del numero', 'text', 'Step' ),
				),
				'FAQ' => array(
					'faq_etichetta' => array( 'Etichetta', 'text', 'FAQ' ),
					'faq_titolo'    => array( 'Titolo', 'text', 'Domande' ),
					'faq_evidenza'  => array( 'Parte colorata del titolo', 'text', 'frequenti' ),
				),
				'Chiusura' => array(
					'cta_titolo'   => array( 'Titolo', 'text', 'Hai trovato quello che cercavi?' ),
					'cta_testo'    => array( 'Testo', 'textarea', 'Raccontami il tuo progetto e troviamo insieme la soluzione giusta.' ),
					'cta_pulsante' => array( 'Pulsante', 'text', 'Parliamone' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Servizi' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', 'Siti web, applicazioni su misura e WordPress: i problemi che risolvo e come lavoro, passo dopo passo.' ),
				),
			),
		),
		'chisono' => array(
			'menu'   => 'Chi sono',
			'gruppi' => array(
				'Hero' => array(
					'hero_occhiello'   => array( 'Testo sopra il titolo', 'text', 'Gabriele Vilardo — Web Designer & Developer a Palermo' ),
					'hero_titolo'      => array( 'Titolo (H1)', 'textarea', 'Unisco precisione tecnica e cura estetica per dare forma al tuo business online.' ),
					'hero_sottotitolo' => array( 'Testo sotto il titolo', 'textarea', 'Con oltre 10 anni di esperienza nello sviluppo di siti web a Palermo, aiuto aziende e professionisti a distinguersi sul web attraverso progetti WordPress su misura, veloci e pensati per convertire.' ),
					'hero_cta'         => array( 'Pulsante principale', 'text', 'Richiedi una consulenza gratuita' ),
					'hero_cta_url'     => array( 'Link pulsante principale', 'text', '/contatti/' ),
					'hero_cta2'        => array( 'Pulsante secondario', 'text', 'Esplora il mio metodo ↓' ),
					'hero_cta2_url'    => array( 'Link pulsante secondario', 'text', '#metodo', 'Con # porta a una sezione della pagina che ha quell’ancora.' ),
					'hero_foto'        => array( 'Foto', 'image', '', 'Una tua foto vera, verticale (circa 4:5). Vuoto = si vede solo la luna.' ),
					'hero_foto_alt'    => array( 'Descrizione della foto', 'text', 'Gabriele Vilardo, web designer a Palermo, al lavoro' ),
					'hero_badge'       => array( 'Badge attorno alla foto', 'textarea', "WordPress\nElementor\nHTML5 / CSS3\nJava", 'Uno per riga.' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Gabriele Vilardo | Web Designer e Sviluppatore WordPress a Palermo', 'Usato così com’è, senza aggiungere il nome del sito. Meglio sotto i 60 caratteri.' ),
					'meta_descrizione' => array( 'Descrizione', 'textarea', 'Cerchi un Web Designer a Palermo? Sono Gabriele Vilardo: +10 anni di esperienza in siti WordPress, Elementor, codice custom e sviluppo Full-Stack. Scopri di più.', 'Meglio sotto i 160 caratteri.' ),
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

/** Icone a contorno del sito (src/lib/icone.ts), da scegliere nei campi "Icona". */
function portfolio_icone() {
	return array(
		'message' => 'Fumetto', 'search' => 'Lente', 'palette' => 'Tavolozza', 'layout' => 'Layout',
		'code' => 'Codice', 'rocket' => 'Razzo', 'wrench' => 'Chiave inglese', 'server' => 'Server',
		'chart' => 'Grafico', 'zap' => 'Fulmine', 'globe' => 'Globo', 'smartphone' => 'Smartphone',
		'snail' => 'Lumaca (lentezza)', 'compass' => 'Bussola', 'lock' => 'Lucchetto', 'settings' => 'Ingranaggio',
		'moto' => 'Moto', 'fish' => 'Pesce', 'chefHat' => 'Cappello da chef', 'torii' => 'Portale giapponese',
		'ball' => 'Pallone', 'book' => 'Libro', 'camera' => 'Macchina fotografica', 'music' => 'Musica', 'heart' => 'Cuore',
	);
}

/* Elenchi della pagina Servizi (repeater): problemi, fasi del processo, domande frequenti. */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$rep = function ( $nome, $label, $bottone, $sub ) {
		$fields = array();
		foreach ( $sub as $k => $c ) {
			$f = array( 'key' => "field_ps_{$nome}_{$k}", 'name' => $k, 'label' => $c[0], 'type' => $c[1], 'wrapper' => array( 'width' => $c[2] ) );
			if ( isset( $c[3] ) ) {
				$f['choices'] = $c[3];
			}
			if ( 'textarea' === $c[1] ) {
				$f['rows'] = 3;
			}
			$fields[] = $f;
		}
		return array(
			'key'          => "field_ps_{$nome}",
			'name'         => "pag_servizi_{$nome}",
			'label'        => $label,
			'type'         => 'repeater',
			'layout'       => 'block',
			'button_label' => $bottone,
			'collapsed'    => "field_ps_{$nome}_" . array_keys( $sub )[ 'faq' === $nome ? 0 : 1 ],
			'instructions' => 'Lasciando vuoto si vede l\'elenco predefinito del sito.',
			'sub_fields'   => $fields,
		);
	};
	$icone = portfolio_icone();
	acf_add_local_field_group( array(
		'key'        => 'group_portfolio_servizi_elenchi',
		'title'      => 'Elenchi della pagina',
		'menu_order' => 10,
		'fields'     => array(
			$rep( 'problemi', 'Problemi che risolvo', 'Aggiungi problema', array(
				'icona'  => array( 'Icona', 'select', 30, $icone ),
				'titolo' => array( 'Titolo', 'text', 85 ),
				'testo'  => array( 'Testo', 'textarea', 100 ),
			) ),
			$rep( 'processo', 'Fasi del processo', 'Aggiungi fase', array(
				'icona'  => array( 'Icona', 'select', 30, $icone ),
				'titolo' => array( 'Titolo', 'text', 70 ),
				'testo'  => array( 'Testo', 'textarea', 100 ),
			) ),
			$rep( 'faq', 'Domande frequenti', 'Aggiungi domanda', array(
				'domanda'  => array( 'Domanda', 'text', 100 ),
				'risposta' => array( 'Risposta', 'textarea', 100 ),
			) ),
		),
		'location'   => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'portfolio-pag_servizi' ) ) ),
	) );
} );

/* Sezioni della pagina Chi sono: contenuto flessibile, si aggiungono, tolgono e riordinano. */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$f = function ( $layout, $nome, $label, $tipo, $extra = array() ) {
		return array_merge( array( 'key' => "field_cs_{$layout}_{$nome}", 'name' => $nome, 'label' => $label, 'type' => $tipo ), $extra );
	};
	$etichetta = function ( $layout ) use ( $f ) {
		return $f( $layout, 'etichetta', 'Occhiello', 'text', array( 'instructions' => 'Il testo piccolo sopra il titolo, es. Competenze.', 'wrapper' => array( 'width' => 40 ) ) );
	};
	$rep = function ( $layout, $nome, $label, $bottone, $sub ) {
		$campi = array();
		foreach ( $sub as $k => $c ) {
			$campo = array( 'key' => "field_cs_{$layout}_{$nome}_{$k}", 'name' => $k, 'label' => $c[0], 'type' => $c[1], 'wrapper' => array( 'width' => $c[2] ) );
			if ( 'textarea' === $c[1] ) {
				$campo['rows'] = 3;
			}
			if ( isset( $c[3] ) ) {
				$campo = array_merge( $campo, $c[3] );
			}
			$campi[] = $campo;
		}
		return array( 'key' => "field_cs_{$layout}_{$nome}", 'name' => $nome, 'label' => $label, 'type' => 'repeater', 'layout' => 'block', 'button_label' => $bottone, 'sub_fields' => $campi );
	};
	$layouts = array(
		'statistiche' => array(
			'label'  => 'Numeri in evidenza',
			'fields' => array(
				$rep( 'statistiche', 'voci', 'Numeri', 'Aggiungi numero', array(
					'numero'    => array( 'Numero o testo breve', 'text', 25, array( 'instructions' => 'Se è un numero intero si anima contando, es. 11. Altrimenti resta fisso, es. 2014–2025.' ) ),
					'prefisso'  => array( 'Prima', 'text', 10, array( 'instructions' => 'Es. +' ) ),
					'suffisso'  => array( 'Dopo', 'text', 10 ),
					'etichetta' => array( 'Didascalia', 'text', 55 ),
				) ),
			),
		),
		'valori'      => array(
			'label'  => 'Testo con card (perché scegliermi)',
			'fields' => array(
				$etichetta( 'valori' ),
				$f( 'valori', 'titolo', 'Titolo', 'text', array( 'wrapper' => array( 'width' => 60 ) ) ),
				$f( 'valori', 'testo', 'Testo introduttivo', 'textarea', array( 'rows' => 3 ) ),
				$rep( 'valori', 'card', 'Card', 'Aggiungi card', array(
					'meta'   => array( 'Dettaglio sopra il titolo', 'text', 30, array( 'instructions' => 'Facoltativo, es. 2014 – 2025.' ) ),
					'titolo' => array( 'Titolo', 'text', 70 ),
					'testo'  => array( 'Testo', 'textarea', 100 ),
				) ),
			),
		),
		'bento'       => array(
			'label'  => 'Griglia competenze (bento)',
			'fields' => array(
				$etichetta( 'bento' ),
				$f( 'bento', 'titolo', 'Titolo', 'text', array( 'wrapper' => array( 'width' => 60 ) ) ),
				$rep( 'bento', 'box', 'Riquadri', 'Aggiungi riquadro', array(
					'dimensione' => array( 'Dimensione', 'select', 25, array( 'choices' => array( 'grande' => 'Grande', 'medio' => 'Medio', 'piccolo' => 'Piccolo' ), 'default_value' => 'medio' ) ),
					'titolo'     => array( 'Titolo', 'text', 75 ),
					'testo'      => array( 'Testo', 'textarea', 60 ),
					'tag'        => array( 'Badge', 'textarea', 40, array( 'instructions' => 'Uno per riga, si illuminano al passaggio del mouse.' ) ),
				) ),
			),
		),
		'personale'   => array(
			'label'  => 'Sezione personale (sfondo a contrasto)',
			'fields' => array(
				$etichetta( 'personale' ),
				$f( 'personale', 'titolo', 'Titolo', 'text', array( 'wrapper' => array( 'width' => 60 ) ) ),
				$f( 'personale', 'testo', 'Testo', 'textarea', array( 'rows' => 5, 'instructions' => 'Una riga vuota separa i paragrafi.' ) ),
				$rep( 'personale', 'passioni', 'Passioni', 'Aggiungi passione', array(
					'icona' => array( 'Icona', 'select', 40, array( 'choices' => portfolio_icone() ) ),
					'testo' => array( 'Testo', 'text', 60 ),
				) ),
			),
		),
		'contatti'    => array(
			'label'  => 'Chiusura con contatti',
			'fields' => array(
				$etichetta( 'contatti' ),
				$f( 'contatti', 'titolo', 'Titolo', 'text' ),
				$f( 'contatti', 'testo', 'Testo', 'textarea', array( 'rows' => 2 ) ),
				$f( 'contatti', 'pulsante', 'Pulsante', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				$f( 'contatti', 'url', 'Link pulsante', 'text', array( 'default_value' => '/contatti/', 'wrapper' => array( 'width' => 50 ), 'instructions' => 'Indirizzo, telefono, WhatsApp ed email arrivano da Testi del sito → Generale.' ) ),
			),
		),
		'testo'       => array(
			'label'  => 'Testo a colonne',
			'fields' => array(
				$etichetta( 'testo' ),
				$f( 'testo', 'colonne', 'Colonne', 'select', array( 'choices' => array( '1' => '1', '2' => '2', '3' => '3' ), 'default_value' => '2', 'wrapper' => array( 'width' => 20 ) ) ),
				$f( 'testo', 'titolo', 'Titolo (facoltativo)', 'text', array( 'wrapper' => array( 'width' => 40 ) ) ),
				$f( 'testo', 'blocchi', 'Blocchi', 'repeater', array(
					'layout'       => 'block',
					'button_label' => 'Aggiungi blocco',
					'sub_fields'   => array(
						array( 'key' => 'field_cs_testo_blocchi_titolo', 'name' => 'titolo', 'label' => 'Titolo', 'type' => 'text' ),
						array( 'key' => 'field_cs_testo_blocchi_testo', 'name' => 'testo', 'label' => 'Testo', 'type' => 'textarea', 'rows' => 3 ),
					),
				) ),
			),
		),
		'percorso'    => array(
			'label'  => 'Percorso (esperienze e formazione)',
			'fields' => array(
				$etichetta( 'percorso' ),
				$f( 'percorso', 'titolo', 'Titolo (facoltativo)', 'text', array( 'wrapper' => array( 'width' => 60 ) ) ),
				$f( 'percorso', 'voci', 'Voci', 'repeater', array(
					'layout'       => 'block',
					'button_label' => 'Aggiungi voce',
					'sub_fields'   => array(
						array( 'key' => 'field_cs_percorso_voci_periodo', 'name' => 'periodo', 'label' => 'Periodo', 'type' => 'text', 'wrapper' => array( 'width' => 25 ) ),
						array( 'key' => 'field_cs_percorso_voci_titolo', 'name' => 'titolo', 'label' => 'Titolo', 'type' => 'text', 'wrapper' => array( 'width' => 45 ) ),
						array( 'key' => 'field_cs_percorso_voci_luogo', 'name' => 'luogo', 'label' => 'Dove', 'type' => 'text', 'wrapper' => array( 'width' => 30 ) ),
						array( 'key' => 'field_cs_percorso_voci_testo', 'name' => 'testo', 'label' => 'Descrizione', 'type' => 'textarea', 'rows' => 2 ),
					),
				) ),
			),
		),
		'competenze'  => array(
			'label'  => 'Competenze',
			'fields' => array(
				$etichetta( 'competenze' ),
				$f( 'competenze', 'titolo', 'Titolo (facoltativo)', 'text', array( 'wrapper' => array( 'width' => 60 ) ) ),
				$f( 'competenze', 'elenco', 'Competenze', 'textarea', array( 'rows' => 6, 'instructions' => 'Una per riga.' ) ),
			),
		),
		'frase'       => array(
			'label'  => 'Frase grande',
			'fields' => array(
				$etichetta( 'frase' ),
				$f( 'frase', 'testo', 'Testo', 'textarea', array( 'rows' => 3 ) ),
			),
		),
		'cta'         => array(
			'label'  => 'Call to action',
			'fields' => array(
				$f( 'cta', 'titolo', 'Titolo', 'text' ),
				$f( 'cta', 'testo', 'Testo', 'textarea', array( 'rows' => 2 ) ),
				$f( 'cta', 'pulsante', 'Pulsante', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				$f( 'cta', 'url', 'Link pulsante', 'text', array( 'default_value' => '/contatti/', 'wrapper' => array( 'width' => 50 ) ) ),
				$f( 'cta', 'pulsante2', 'Secondo pulsante (facoltativo)', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
				$f( 'cta', 'url2', 'Link secondo pulsante', 'text', array( 'wrapper' => array( 'width' => 50 ) ) ),
			),
		),
	);
	$acf_layouts = array();
	foreach ( $layouts as $nome => $l ) {
		// Ogni sezione può avere un'ancora, per i link interni come "#metodo".
		$l['fields'][] = $f( $nome, 'ancora', 'Ancora (facoltativa)', 'text', array( 'instructions' => 'Una parola senza spazi, es. metodo: la sezione si raggiunge con il link #metodo.' ) );
		$acf_layouts[ "layout_cs_{$nome}" ] = array( 'key' => "layout_cs_{$nome}", 'name' => $nome, 'label' => $l['label'], 'display' => 'block', 'sub_fields' => $l['fields'] );
	}
	acf_add_local_field_group( array(
		'key'        => 'group_portfolio_chisono_sezioni',
		'title'      => 'Sezioni della pagina',
		'menu_order' => 10,
		'fields'     => array(
			array(
				'key'          => 'field_cs_sezioni',
				'name'         => 'chisono_sezioni',
				'label'        => 'Sezioni',
				'type'         => 'flexible_content',
				'button_label' => 'Aggiungi sezione',
				'instructions' => 'Aggiungi, togli e trascina le sezioni per riordinarle. Se non ce n’è nessuna si vedono quelle predefinite del sito.',
				'layouts'      => $acf_layouts,
			),
		),
		'location'   => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'portfolio-chisono' ) ) ),
	) );
} );

/** GET /wp-json/portfolio/v1/chi-sono: { sezioni: [ { tipo, ... } ] }. */
function portfolio_chisono_sezioni() {
	$righe   = function ( $v ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $v ) ) ) );
	};
	$str     = function ( $row, $k ) {
		return isset( $row[ $k ] ) ? trim( (string) $row[ $k ] ) : '';
	};
	$sezioni = array();
	$valore  = function_exists( 'get_field' ) ? get_field( 'chisono_sezioni', 'option' ) : array();
	foreach ( (array) $valore as $row ) {
		$tipo = isset( $row['acf_fc_layout'] ) ? $row['acf_fc_layout'] : '';
		$s    = array( 'tipo' => $tipo, 'etichetta' => $str( $row, 'etichetta' ), 'titolo' => $str( $row, 'titolo' ), 'ancora' => sanitize_title( $str( $row, 'ancora' ) ) );
		$sub  = function ( $nome, $chiavi ) use ( $row, $str ) {
			$out = array();
			foreach ( (array) ( isset( $row[ $nome ] ) ? $row[ $nome ] : array() ) as $r ) {
				$item = array();
				foreach ( $chiavi as $k ) {
					$item[ $k ] = $str( $r, $k );
				}
				$out[] = $item;
			}
			return $out;
		};
		if ( 'statistiche' === $tipo ) {
			$s['voci'] = $sub( 'voci', array( 'numero', 'prefisso', 'suffisso', 'etichetta' ) );
		} elseif ( 'valori' === $tipo ) {
			$s['testo'] = $str( $row, 'testo' );
			$s['card']  = $sub( 'card', array( 'meta', 'titolo', 'testo' ) );
		} elseif ( 'bento' === $tipo ) {
			$s['box'] = array();
			foreach ( $sub( 'box', array( 'dimensione', 'titolo', 'testo', 'tag' ) ) as $b ) {
				$b['dimensione'] = in_array( $b['dimensione'], array( 'grande', 'medio', 'piccolo' ), true ) ? $b['dimensione'] : 'medio';
				$b['tag']        = $righe( $b['tag'] );
				$s['box'][]      = $b;
			}
		} elseif ( 'personale' === $tipo ) {
			$s['testo']    = $str( $row, 'testo' );
			$s['passioni'] = $sub( 'passioni', array( 'icona', 'testo' ) );
		} elseif ( 'contatti' === $tipo ) {
			foreach ( array( 'testo', 'pulsante', 'url' ) as $k ) {
				$s[ $k ] = $str( $row, $k );
			}
		} elseif ( 'testo' === $tipo ) {
			$s['colonne'] = (int) ( $str( $row, 'colonne' ) ?: 2 );
			$s['blocchi'] = array();
			foreach ( (array) ( isset( $row['blocchi'] ) ? $row['blocchi'] : array() ) as $b ) {
				$s['blocchi'][] = array( 'titolo' => $str( $b, 'titolo' ), 'testo' => $str( $b, 'testo' ) );
			}
		} elseif ( 'percorso' === $tipo ) {
			$s['voci'] = array();
			foreach ( (array) ( isset( $row['voci'] ) ? $row['voci'] : array() ) as $v ) {
				$s['voci'][] = array( 'periodo' => $str( $v, 'periodo' ), 'titolo' => $str( $v, 'titolo' ), 'luogo' => $str( $v, 'luogo' ), 'testo' => $str( $v, 'testo' ) );
			}
		} elseif ( 'competenze' === $tipo ) {
			$s['elenco'] = $righe( $str( $row, 'elenco' ) );
		} elseif ( 'frase' === $tipo ) {
			$s['testo'] = $str( $row, 'testo' );
		} elseif ( 'cta' === $tipo ) {
			foreach ( array( 'testo', 'pulsante', 'url', 'pulsante2', 'url2' ) as $k ) {
				$s[ $k ] = $str( $row, $k );
			}
		} else {
			continue;
		}
		$sezioni[] = $s;
	}
	return array( 'sezioni' => $sezioni );
}

/** GET /wp-json/portfolio/v1/servizi: { problemi: [], processo: [], faq: [] }. Elenco vuoto = predefinito del sito. */
function portfolio_servizi_elenchi() {
	$righe = function ( $nome, $chiavi ) {
		$v   = function_exists( 'get_field' ) ? get_field( "pag_servizi_{$nome}", 'option' ) : array();
		$out = array();
		foreach ( (array) $v as $row ) {
			$item = array();
			foreach ( $chiavi as $k ) {
				$item[ $k ] = isset( $row[ $k ] ) ? trim( (string) $row[ $k ] ) : '';
			}
			if ( '' !== $item[ $chiavi[1] ] || '' !== $item[ $chiavi[0] ] ) {
				$out[] = $item;
			}
		}
		return $out;
	};
	return array(
		'problemi' => $righe( 'problemi', array( 'icona', 'titolo', 'testo' ) ),
		'processo' => $righe( 'processo', array( 'icona', 'titolo', 'testo' ) ),
		'faq'      => $righe( 'faq', array( 'domanda', 'risposta' ) ),
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
				if ( 'image' === $c[1] ) {
					// Le immagini sono salvate come ID: al sito serve l'indirizzo del file originale.
					$v = $v ? (string) wp_get_attachment_url( (int) $v ) : '';
				}
				$out[ $pagina ][ $nome ] = is_string( $v ) ? trim( $v ) : '';
			}
		}
	}
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'portfolio/v1', '/chi-sono', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_chisono_sezioni',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'portfolio/v1', '/servizi', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_servizi_elenchi',
		'permission_callback' => '__return_true',
	) );
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

/* -------------------------------------------------------------------------
 * Statistiche delle visite (senza cookie, senza servizi esterni)
 * Il sito invia POST /wp-json/portfolio/v1/visita a ogni pagina vista.
 * Non salviamo IP né dati personali: il visitatore unico è un'impronta
 * che cambia ogni giorno (sale giornaliero), come fanno Plausible e simili.
 * ---------------------------------------------------------------------- */
define( 'PORTFOLIO_STATS_DB', '1' );

function portfolio_stats_tabella() {
	global $wpdb;
	return $wpdb->prefix . 'portfolio_visite';
}

add_action( 'admin_init', function () {
	if ( get_option( 'portfolio_stats_db' ) === PORTFOLIO_STATS_DB ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = portfolio_stats_tabella();
	dbDelta( "CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		giorno date NOT NULL,
		creato datetime NOT NULL,
		pagina varchar(200) NOT NULL DEFAULT '',
		sorgente varchar(80) NOT NULL DEFAULT '',
		dispositivo varchar(10) NOT NULL DEFAULT '',
		browser varchar(20) NOT NULL DEFAULT '',
		visitatore char(16) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY giorno (giorno),
		KEY giorno_visitatore (giorno, visitatore)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'portfolio_stats_db', PORTFOLIO_STATS_DB );
} );

/** Nome leggibile della provenienza dal referrer (o da utm_source). */
function portfolio_stats_sorgente( $referrer, $utm ) {
	if ( '' !== $utm ) {
		return mb_substr( ucfirst( sanitize_text_field( $utm ) ), 0, 80 );
	}
	$host = strtolower( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
	$host = preg_replace( '/^(www|m|l|lm)\./', '', $host );
	if ( '' === $host ) {
		return 'Diretto';
	}
	$sito = strtolower( (string) wp_parse_url( portfolio_site_url(), PHP_URL_HOST ) );
	if ( $sito && preg_replace( '/^www\./', '', $sito ) === $host ) {
		return '';
	}
	$noti = array(
		'/(^|\.)google\./'                  => 'Google',
		'/(^|\.)bing\.com$/'                => 'Bing',
		'/(^|\.)duckduckgo\.com$/'          => 'DuckDuckGo',
		'/(^|\.)yahoo\./'                   => 'Yahoo',
		'/(^|\.)ecosia\.org$/'              => 'Ecosia',
		'/(^|\.)instagram\.com$/'           => 'Instagram',
		'/(^|\.)facebook\.com$|^fb\.me$/'   => 'Facebook',
		'/(^|\.)linkedin\.com$|^lnkd\.in$/' => 'LinkedIn',
		'/^t\.co$|(^|\.)x\.com$|twitter\.com$/' => 'X',
		'/(^|\.)tiktok\.com$/'              => 'TikTok',
		'/(^|\.)youtube\.com$|^youtu\.be$/' => 'YouTube',
		'/(^|\.)whatsapp\.com$|^wa\.me$/'   => 'WhatsApp',
		'/(^|\.)github\.com$/'              => 'GitHub',
		'/chatgpt\.com$|openai\.com$/'      => 'ChatGPT',
		'/perplexity\.ai$/'                 => 'Perplexity',
	);
	foreach ( $noti as $re => $nome ) {
		if ( preg_match( $re, $host ) ) {
			return $nome;
		}
	}
	return mb_substr( $host, 0, 80 );
}

function portfolio_stats_browser( $ua ) {
	$elenco = array( 'Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Firefox/' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari' );
	foreach ( $elenco as $chiave => $nome ) {
		if ( false !== strpos( $ua, $chiave ) ) {
			return $nome;
		}
	}
	return 'Altro';
}

function portfolio_visita( WP_REST_Request $req ) {
	$site   = portfolio_site_url();
	$origin = get_http_origin();
	if ( $site && $origin && untrailingslashit( $origin ) !== $site ) {
		return new WP_REST_Response( null, 204 );
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|preview|monitor|curl|wget|python/i', $ua ) ) {
		return new WP_REST_Response( null, 204 );
	}
	// Il sito invia testo semplice (sendBeacon), quindi leggiamo il corpo a mano.
	$d = json_decode( $req->get_body(), true );
	if ( ! is_array( $d ) || empty( $d['p'] ) ) {
		return new WP_REST_Response( null, 204 );
	}
	$pagina = '/' . ltrim( (string) wp_parse_url( (string) $d['p'], PHP_URL_PATH ), '/' );
	$pagina = mb_substr( sanitize_text_field( $pagina ), 0, 200 );
	$sorg   = portfolio_stats_sorgente( isset( $d['r'] ) ? (string) $d['r'] : '', isset( $d['u'] ) ? (string) $d['u'] : '' );
	$w      = isset( $d['w'] ) ? (int) $d['w'] : 0;
	$disp   = $w && $w < 768 ? 'Mobile' : ( $w && $w < 1100 ? 'Tablet' : 'Desktop' );

	// Impronta giornaliera: IP + browser + sale che cambia ogni giorno, troncata. L'IP non viene salvato.
	$oggi = current_time( 'Y-m-d' );
	$sale = get_option( 'portfolio_stats_sale' );
	if ( ! is_array( $sale ) || $sale['giorno'] !== $oggi ) {
		$sale = array( 'giorno' => $oggi, 'valore' => wp_generate_password( 32, true, true ) );
		update_option( 'portfolio_stats_sale', $sale, false );
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

	global $wpdb;
	$wpdb->insert( portfolio_stats_tabella(), array(
		'giorno'      => $oggi,
		'creato'      => current_time( 'mysql' ),
		'pagina'      => $pagina,
		'sorgente'    => $sorg,
		'dispositivo' => $disp,
		'browser'     => portfolio_stats_browser( $ua ),
		'visitatore'  => substr( hash( 'sha256', $sale['valore'] . $ip . $ua ), 0, 16 ),
	) );
	// Ogni tanto eliminiamo le visite più vecchie di 13 mesi.
	if ( 1 === wp_rand( 1, 200 ) ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . portfolio_stats_tabella() . ' WHERE giorno < %s', gmdate( 'Y-m-d', strtotime( '-400 days' ) ) ) );
	}
	return new WP_REST_Response( null, 204 );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'portfolio/v1', '/visita', array(
		'methods'             => 'POST',
		'callback'            => 'portfolio_visita',
		'permission_callback' => '__return_true',
	) );
} );

/** Dati aggregati per il periodo (ultimi $giorni giorni, oggi compreso). */
function portfolio_stats_dati( $giorni ) {
	global $wpdb;
	$t     = portfolio_stats_tabella();
	$da    = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( $giorni - 1 ) . ' days' ) );
	$prima = gmdate( 'Y-m-d', strtotime( $da . ' -' . $giorni . ' days' ) );
	$tot   = function ( $dal, $al ) use ( $wpdb, $t ) {
		return $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS viste, COUNT(DISTINCT giorno, visitatore) AS visitatori FROM {$t} WHERE giorno >= %s AND giorno < %s", $dal, $al ), ARRAY_A );
	};
	$top = function ( $col, $limite = 10 ) use ( $wpdb, $t, $da ) {
		return $wpdb->get_results( $wpdb->prepare( "SELECT {$col} AS nome, COUNT(DISTINCT giorno, visitatore) AS visitatori, COUNT(*) AS viste FROM {$t} WHERE giorno >= %s AND {$col} <> '' GROUP BY {$col} ORDER BY visitatori DESC, viste DESC LIMIT %d", $da, $limite ), ARRAY_A );
	};
	$per_giorno = $wpdb->get_results( $wpdb->prepare( "SELECT giorno, COUNT(*) AS viste, COUNT(DISTINCT visitatore) AS visitatori FROM {$t} WHERE giorno >= %s GROUP BY giorno", $da ), OBJECT_K );
	$serie      = array();
	for ( $i = 0; $i < $giorni; $i++ ) {
		$g       = gmdate( 'Y-m-d', strtotime( $da . " +{$i} days" ) );
		$serie[] = array( 'giorno' => $g, 'visitatori' => isset( $per_giorno[ $g ] ) ? (int) $per_giorno[ $g ]->visitatori : 0, 'viste' => isset( $per_giorno[ $g ] ) ? (int) $per_giorno[ $g ]->viste : 0 );
	}
	return array(
		'attuale'     => $tot( $da, gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) ) ),
		'precedente'  => $tot( $prima, $da ),
		'serie'       => $serie,
		'pagine'      => $top( 'pagina' ),
		'sorgenti'    => $top( 'sorgente' ),
		'dispositivi' => $top( 'dispositivo', 5 ),
		'browser'     => $top( 'browser', 6 ),
	);
}

function portfolio_stats_variazione( $ora, $prima ) {
	if ( ! $prima ) {
		return '';
	}
	$v = round( ( $ora - $prima ) / $prima * 100 );
	return sprintf( '<span class="pstat-var %s">%s%d%%</span>', $v >= 0 ? 'su' : 'giu', $v >= 0 ? '+' : '', $v );
}

function portfolio_stats_grafico( $serie ) {
	$max = max( 1, max( array_column( $serie, 'visitatori' ) ) );
	$n   = count( $serie );
	$out = '<div class="pstat-grafico" style="--n:' . $n . '">';
	foreach ( $serie as $p ) {
		$h    = round( $p['visitatori'] / $max * 100 );
		$data = date_i18n( 'j M', strtotime( $p['giorno'] ) );
		$out .= sprintf( '<div class="pstat-barra" title="%s: %d visitatori, %d visualizzazioni"><span style="height:%d%%"></span></div>', esc_attr( $data ), $p['visitatori'], $p['viste'], $h );
	}
	$out .= '</div><div class="pstat-asse"><span>' . esc_html( date_i18n( 'j M', strtotime( $serie[0]['giorno'] ) ) ) . '</span><span>' . esc_html( date_i18n( 'j M', strtotime( end( $serie )['giorno'] ) ) ) . '</span></div>';
	return $out;
}

function portfolio_stats_tabella_html( $titolo, $righe, $colonna ) {
	$max = $righe ? max( 1, (int) $righe[0]['visitatori'] ) : 1;
	$out = '<div class="pstat-box"><h3>' . esc_html( $titolo ) . '</h3>';
	if ( ! $righe ) {
		return $out . '<p class="pstat-vuoto">Nessun dato nel periodo.</p></div>';
	}
	$out .= '<table><thead><tr><th>' . esc_html( $colonna ) . '</th><th>Visitatori</th></tr></thead><tbody>';
	foreach ( $righe as $r ) {
		$out .= sprintf( '<tr><td><span class="pstat-fondo" style="width:%d%%"></span><span class="pstat-nome">%s</span></td><td>%d</td></tr>', round( $r['visitatori'] / $max * 100 ), esc_html( $r['nome'] ), $r['visitatori'] );
	}
	return $out . '</tbody></table></div>';
}

function portfolio_stats_css() {
	return '<style>
	.pstat-wrap{max-width:1200px}
	.pstat-periodi{display:flex;gap:6px;margin:12px 0 20px}
	.pstat-periodi a{padding:6px 14px;border:1px solid #c3c4c7;border-radius:999px;text-decoration:none;color:#1d2327;background:#fff}
	.pstat-periodi a.attivo{background:#0b8793;border-color:#0b8793;color:#fff}
	.pstat-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px}
	.pstat-kpi div,.pstat-box{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px}
	.pstat-kpi strong{display:block;font-size:28px;line-height:1.2;margin-top:4px}
	.pstat-kpi small{color:#646970;text-transform:uppercase;letter-spacing:.05em}
	.pstat-var{font-size:12px;font-weight:600;margin-left:6px}.pstat-var.su{color:#008a20}.pstat-var.giu{color:#d63638}
	.pstat-grafico{display:grid;grid-template-columns:repeat(var(--n),1fr);align-items:end;gap:3px;height:180px}
	.pstat-barra{height:100%;display:flex;align-items:flex-end}
	.pstat-barra span{display:block;width:100%;min-height:2px;background:#0b8793;border-radius:3px 3px 0 0}
	.pstat-barra:hover span{background:#1aa3ae}
	.pstat-asse{display:flex;justify-content:space-between;color:#646970;font-size:12px;margin-top:6px}
	.pstat-griglia{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;margin-top:12px}
	.pstat-box h3{margin:0 0 12px;font-size:14px}
	.pstat-box table{width:100%;border-collapse:collapse}
	.pstat-box th{text-align:left;color:#646970;font-weight:400;font-size:12px;padding:4px 8px}
	.pstat-box th:last-child,.pstat-box td:last-child{text-align:right;width:80px}
	.pstat-box td{position:relative;padding:6px 8px}
	.pstat-fondo{position:absolute;inset:3px auto 3px 0;background:#e6f4f5;border-radius:4px}
	.pstat-nome{position:relative;word-break:break-all}
	.pstat-vuoto{color:#646970}
	</style>';
}

// Pagina "Statistiche" nel menu.
add_action( 'admin_menu', function () {
	add_menu_page( 'Statistiche del sito', 'Statistiche', 'edit_posts', 'portfolio-statistiche', 'portfolio_stats_pagina', 'dashicons-chart-area', 3 );
} );

function portfolio_stats_pagina() {
	$periodi = array( 7 => '7 giorni', 30 => '30 giorni', 90 => '90 giorni', 365 => '12 mesi' );
	$giorni  = isset( $_GET['giorni'] ) && isset( $periodi[ (int) $_GET['giorni'] ] ) ? (int) $_GET['giorni'] : 30; // phpcs:ignore WordPress.Security.NonceVerification
	$d       = portfolio_stats_dati( $giorni );
	$viste   = (int) $d['attuale']['viste'];
	$vis     = (int) $d['attuale']['visitatori'];
	echo portfolio_stats_css(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="wrap pstat-wrap"><h1>Statistiche del sito</h1>';
	echo '<p>Visite al sito pubblico, raccolte senza cookie e senza dati personali.</p>';
	echo '<div class="pstat-periodi">';
	foreach ( $periodi as $n => $label ) {
		printf( '<a class="%s" href="%s">%s</a>', $n === $giorni ? 'attivo' : '', esc_url( add_query_arg( 'giorni', $n ) ), esc_html( $label ) );
	}
	echo '</div><div class="pstat-kpi">';
	printf( '<div><small>Visitatori unici</small><strong>%s%s</strong></div>', esc_html( number_format_i18n( $vis ) ), portfolio_stats_variazione( $vis, (int) $d['precedente']['visitatori'] ) ); // phpcs:ignore
	printf( '<div><small>Pagine viste</small><strong>%s%s</strong></div>', esc_html( number_format_i18n( $viste ) ), portfolio_stats_variazione( $viste, (int) $d['precedente']['viste'] ) ); // phpcs:ignore
	printf( '<div><small>Pagine per visitatore</small><strong>%s</strong></div>', esc_html( $vis ? number_format_i18n( $viste / $vis, 1 ) : '0' ) );
	$prima = $d['sorgenti'] ? $d['sorgenti'][0]['nome'] : '—';
	printf( '<div><small>Provenienza principale</small><strong>%s</strong></div>', esc_html( $prima ) );
	echo '</div><div class="pstat-box"><h3>Visitatori al giorno</h3>' . portfolio_stats_grafico( $d['serie'] ) . '</div>'; // phpcs:ignore
	echo '<div class="pstat-griglia">';
	echo portfolio_stats_tabella_html( 'Provenienza', $d['sorgenti'], 'Sorgente' ); // phpcs:ignore
	echo portfolio_stats_tabella_html( 'Pagine più viste', $d['pagine'], 'Pagina' ); // phpcs:ignore
	echo portfolio_stats_tabella_html( 'Dispositivi', $d['dispositivi'], 'Tipo' ); // phpcs:ignore
	echo portfolio_stats_tabella_html( 'Browser', $d['browser'], 'Browser' ); // phpcs:ignore
	echo '</div></div>';
}

// Riquadro riassuntivo nella Bacheca.
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'portfolio_statistiche', 'Visite al sito (ultimi 7 giorni)', function () {
		$d   = portfolio_stats_dati( 7 );
		$vis = (int) $d['attuale']['visitatori'];
		echo portfolio_stats_css(); // phpcs:ignore
		echo '<div class="pstat-kpi" style="grid-template-columns:1fr 1fr">';
		printf( '<div><small>Visitatori</small><strong>%s%s</strong></div>', esc_html( number_format_i18n( $vis ) ), portfolio_stats_variazione( $vis, (int) $d['precedente']['visitatori'] ) ); // phpcs:ignore
		printf( '<div><small>Pagine viste</small><strong>%s</strong></div>', esc_html( number_format_i18n( (int) $d['attuale']['viste'] ) ) );
		echo '</div>' . portfolio_stats_grafico( $d['serie'] ); // phpcs:ignore
		echo portfolio_stats_tabella_html( 'Provenienza', array_slice( $d['sorgenti'], 0, 5 ), 'Sorgente' ); // phpcs:ignore
		printf( '<p><a href="%s">Tutte le statistiche →</a></p>', esc_url( admin_url( 'admin.php?page=portfolio-statistiche' ) ) );
	} );
} );

/* -------------------------------------------------------------------------
 * Immagini più leggere già al caricamento
 *
 * Il sito Astro ottimizza comunque ogni immagine in build (WebP in più larghezze),
 * ma partire da file più piccoli rende la build più veloce e occupa meno spazio sul server.
 * ---------------------------------------------------------------------- */

// Le foto più grandi di 2400px vengono ridotte (WordPress di serie si ferma a 2560px).
add_filter( 'big_image_size_threshold', function () {
	return 2400;
} );

// Qualità JPEG/WebP delle versioni create da WordPress: 82 è indistinguibile a occhio e molto più leggera.
add_filter( 'wp_editor_set_quality', function () {
	return 82;
} );

// JPEG e PNG vengono salvati in WebP, se il server lo supporta (altrimenti restano nel formato originale).
add_filter( 'image_editor_output_format', function ( $formati ) {
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return $formati;
	}
	$formati['image/jpeg'] = 'image/webp';
	$formati['image/png']  = 'image/webp';
	return $formati;
} );

// Il sito non usa le versioni intermedie più grandi: niente file inutili per ogni caricamento.
add_filter( 'intermediate_image_sizes_advanced', function ( $formati ) {
	unset( $formati['medium_large'], $formati['1536x1536'], $formati['2048x2048'] );
	return $formati;
} );
