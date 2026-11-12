<?php
/**
 * Plugin Name: Portfolio Core
 * Description: WordPress headless per il portfolio Astro: Progetti, Servizi, Pagine servizi, contenuti della Home, campi, REST API, redirect del front-end, rebuild su GitHub e form contatti.
 * Version:     1.22.0
 *
 * Va in wp-content/mu-plugins/. Configurazione in wp-config.php (nessun dominio scritto qui):
 *
 *   define( 'PORTFOLIO_SITE_URL', 'https://gabrielevilardo.it' );       // sito Astro pubblico
 *   define( 'PORTFOLIO_GITHUB_REPO', 'Gabriele1988/portfolio' );        // owner/repo
 *   define( 'PORTFOLIO_GITHUB_TOKEN', 'github_pat_...' );               // fine-grained token, solo questo repo, "Contents: Read and write"
 *   define( 'PORTFOLIO_CONTACT_EMAIL', 'tu@esempio.it' );               // facoltativo, altrimenti l'email admin
 *   define( 'PORTFOLIO_TURNSTILE_SECRET', '' );                         // facoltativo, Cloudflare Turnstile
 *   define( 'PORTFOLIO_GEMINI_API_KEY', '...' );                        // facoltativo, blog automatico (Google AI Studio)
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
		'introduzione' => array( 'label' => 'Introduzione', 'type' => 'textarea', 'instructions' => 'Il testo sotto il titolo nella pagina del progetto (2-4 frasi). Vuoto = estratto.' ),
		'stato'       => array(
			'label'         => 'Stato',
			'type'          => 'select',
			'choices'       => array( 'online' => 'Online', 'in_sviluppo' => 'In sviluppo', 'offline' => 'Offline (sito realizzato ma non più online)' ),
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
		'in_evidenza' => array( 'label' => 'In evidenza', 'type' => 'true_false', 'instructions' => 'Mostra in home page. Il primo in evidenza (nell\'ordine della lista Progetti) apre anche la pagina Progetti.' ),
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
	} elseif ( is_singular( 'pagina_servizio' ) ) {
		$path = '/servizi/' . get_post_field( 'post_name', get_queried_object_id() ) . '/';
	} elseif ( is_post_type_archive( 'progetti' ) || is_tax( 'servizi' ) ) {
		$path = '/progetti/';
	} elseif ( is_singular( 'post' ) ) {
		$path = '/blog/' . get_post_field( 'post_name', get_queried_object_id() ) . '/';
	} elseif ( is_home() || is_category() || is_tag() || is_author() || is_date() ) {
		$path = '/blog/';
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
	if ( ! in_array( $post->post_type, array( 'progetti', 'pagina_servizio', 'page', 'post' ), true ) ) {
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
					'email'           => array( 'Email', 'email', 'info@gabrielevilardo.it' ),
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
					'menu_blog'     => array( 'Voce Blog', 'text', 'Blog', 'Compare nel menu solo quando c\'è almeno un articolo pubblicato.' ),
					'menu_contatti' => array( 'Voce Contatti', 'text', 'Contatti', 'L\'ordine delle voci si cambia in Testi del sito → Ordine del menu.' ),
					'menu_cta'      => array( 'Pulsante', 'text', 'Richiedi un preventivo' ),
				),
				'Footer' => array(
					'footer_descrizione'     => array( 'Testo sotto il logo', 'textarea', "Sviluppatore web a Palermo.\nCreo siti che funzionano, per attività che contano." ),
					'footer_titolo_nav'      => array( 'Titolo colonna navigazione', 'text', 'Navigazione' ),
					'footer_titolo_contatti' => array( 'Titolo colonna contatti', 'text', 'Contatti' ),
					'footer_privacy'         => array( 'Link privacy', 'text', 'Privacy Policy' ),
					'footer_cookie'          => array( 'Link cookie policy', 'text', 'Cookie Policy' ),
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
					'stato_offline'  => array( 'Stato offline', 'text', 'Offline' ),
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
					'cta_progetto_titolo' => array( 'Titolo sopra il pulsante finale', 'text', 'Hai un progetto simile?' ),
					'cta_progetto'     => array( 'Pulsante finale', 'text', 'Richiedi un preventivo' ),
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
					'titolo'    => array( 'Titolo', 'text', 'Privacy Policy' ),
					'contenuto' => array( 'Testo (non più usato)', 'wysiwyg', '', 'Il testo della Privacy Policy e della Cookie Policy ora è scritto nel sito: questo campo viene ignorato.' ),
				),
				'SEO' => array(
					'meta_titolo'      => array( 'Titolo della pagina', 'text', 'Privacy Policy' ),
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
		'shield' => 'Scudo', 'trendingUp' => 'Grafico in crescita', 'sparkles' => 'Scintille', 'target' => 'Bersaglio', 'users' => 'Persone', 'refresh' => 'Frecce circolari',
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

/* -------------------------------------------------------------------------
 * Ordine dei progetti con il trascinamento
 *
 * In Progetti → Tutti i progetti si trascinano le righe per decidere l'ordine sul sito
 * (salvato in "Ordine" / menu_order, 1 = primo). Un progetto nuovo ha ordine 0, quindi
 * compare per primo finché non lo si sposta.
 * ---------------------------------------------------------------------- */

function portfolio_lista_progetti() {
	global $pagenow;
	return is_admin() && 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && 'progetti' === $_GET['post_type']; // phpcs:ignore WordPress.Security.NonceVerification
}

// La lista in bacheca segue lo stesso ordine del sito (se non si è scelto di ordinare per un'altra colonna).
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! portfolio_lista_progetti() || ! $q->is_main_query() || $q->get( 'orderby' ) ) {
		return;
	}
	$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
} );

// La prima volta l'ordine parte da quello attuale per data (dal più recente).
add_action( 'load-edit.php', function () {
	if ( ! portfolio_lista_progetti() || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	global $wpdb;
	$gia_ordinati = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'progetti' AND menu_order <> 0" );
	if ( $gia_ordinati ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'progetti', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) );
	foreach ( $ids as $i => $id ) {
		$wpdb->update( $wpdb->posts, array( 'menu_order' => $i + 1 ), array( 'ID' => $id ) );
		clean_post_cache( $id );
	}
} );

// Colonna con la maniglia per trascinare e colonna con la stella "In evidenza" (si clicca per cambiarla).
add_filter( 'manage_progetti_posts_columns', function ( $colonne ) {
	$colonne = array_slice( $colonne, 0, 1, true ) + array( 'portfolio_ordine' => '<span class="screen-reader-text">Ordine</span>' ) + array_slice( $colonne, 1, null, true );
	$pos     = array_search( 'title', array_keys( $colonne ), true );
	$pos     = false === $pos ? count( $colonne ) : $pos + 1;
	return array_slice( $colonne, 0, $pos, true ) + array( 'portfolio_evidenza' => 'In evidenza' ) + array_slice( $colonne, $pos, null, true );
} );
add_action( 'manage_progetti_posts_custom_column', function ( $colonna, $post_id ) {
	if ( 'portfolio_ordine' === $colonna ) {
		echo '<span class="portfolio-maniglia dashicons dashicons-menu" title="Trascina per cambiare l\'ordine" aria-hidden="true"></span>';
	} elseif ( 'portfolio_evidenza' === $colonna ) {
		$si = (bool) get_post_meta( $post_id, 'in_evidenza', true );
		printf(
			'<button type="button" class="portfolio-stella%s" data-id="%d" aria-pressed="%s" title="%s"><span class="dashicons dashicons-star-%s" aria-hidden="true"></span><span class="screen-reader-text">In evidenza</span></button>',
			$si ? ' attiva' : '',
			(int) $post_id,
			$si ? 'true' : 'false',
			esc_attr( $si ? 'In evidenza: clicca per toglierlo' : 'Clicca per metterlo in evidenza' ),
			$si ? 'filled' : 'empty'
		);
	}
}, 10, 2 );

add_action( 'wp_ajax_portfolio_evidenza', function () {
	check_ajax_referer( 'portfolio_evidenza', 'nonce' );
	$id = (int) ( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
	if ( 'progetti' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( null, 403 );
	}
	$si = ! empty( $_POST['valore'] ) && '0' !== $_POST['valore'];
	if ( function_exists( 'update_field' ) ) {
		update_field( 'field_portfolio_in_evidenza', $si ? 1 : 0, $id ); // così ACF/SCF resta allineato
	} else {
		update_post_meta( $id, 'in_evidenza', $si ? 1 : 0 );
	}
	if ( 'publish' === get_post_status( $id ) ) {
		portfolio_rebuild_later();
	}
	wp_send_json_success( array( 'valore' => $si ) );
} );

add_action( 'admin_enqueue_scripts', function () {
	if ( ! portfolio_lista_progetti() || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	// Con un ordinamento diverso (per titolo, data…) o una ricerca il trascinamento non avrebbe senso.
	$attivo = empty( $_GET['orderby'] ) && empty( $_GET['s'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$pagina = max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$per    = (int) get_user_option( 'edit_progetti_per_page' );
	$per    = $per > 0 ? $per : 20;

	wp_enqueue_script( 'jquery-ui-sortable' );
	// Stella "In evidenza": funziona sempre, anche con ricerca o altri ordinamenti.
	wp_add_inline_script( 'jquery-ui-sortable', 'jQuery(function ($) {
		$("#the-list").on("click", ".portfolio-stella", function () {
			var b = $(this), nuovo = !b.hasClass("attiva");
			b.prop("disabled", true);
			$.post(ajaxurl, { action: "portfolio_evidenza", nonce: "' . wp_create_nonce( 'portfolio_evidenza' ) . '", id: b.data("id"), valore: nuovo ? 1 : 0 })
				.done(function (r) {
					if (!r || !r.success) { return; }
					b.toggleClass("attiva", nuovo).attr("aria-pressed", nuovo).attr("title", nuovo ? "In evidenza: clicca per toglierlo" : "Clicca per metterlo in evidenza");
					b.find(".dashicons").toggleClass("dashicons-star-filled", nuovo).toggleClass("dashicons-star-empty", !nuovo);
				})
				.always(function () { b.prop("disabled", false); });
		});
	});' );
	wp_add_inline_style( 'common', '
		.column-portfolio_ordine { width: 2.2em; }
		.column-portfolio_evidenza { width: 7em; text-align: center !important; }
		.portfolio-stella { background: none; border: 0; padding: 2px; cursor: pointer; color: #8c8f94; }
		.portfolio-stella:hover, .portfolio-stella:focus { color: #2271b1; }
		.portfolio-stella.attiva { color: #dba617; }
		.portfolio-maniglia { color: #8c8f94; cursor: grab; }
		.portfolio-ordinabile #the-list tr:hover .portfolio-maniglia { color: #2271b1; }
		.portfolio-ordinabile #the-list tr.ui-sortable-helper { display: table; background: #fff; box-shadow: 0 6px 18px rgba(0,0,0,.15); }
		.portfolio-ordinabile #the-list tr.ui-sortable-placeholder { visibility: visible !important; background: #f0f6fc; outline: 2px dashed #72aee6; }
		.portfolio-ordine-avviso { margin: 8px 0 0; }
	' );
	wp_add_inline_script( 'jquery-ui-sortable', 'jQuery(function ($) {
		var attivo = ' . ( $attivo ? 'true' : 'false' ) . ', inizio = ' . ( ( $pagina - 1 ) * $per ) . ';
		var avviso = $("<p class=\"portfolio-ordine-avviso description\"></p>").insertAfter(".wp-header-end");
		if (!attivo) { avviso.text("Per cambiare l\u2019ordine trascinando, togli la ricerca o l\u2019ordinamento per colonna."); $(".portfolio-maniglia").hide(); return; }
		avviso.text("Trascina le righe dalla maniglia \u2630 per scegliere l\u2019ordine dei progetti sul sito. Il sito si aggiorna da solo.");
		$("body").addClass("portfolio-ordinabile");
		$("#the-list").sortable({
			handle: ".portfolio-maniglia",
			axis: "y",
			items: "> tr",
			placeholder: "ui-sortable-placeholder",
			helper: function (e, tr) { tr.children().each(function () { $(this).width($(this).width()); }); return tr; },
			update: function () {
				var ids = $("#the-list > tr").map(function () { return this.id.replace("post-", ""); }).get();
				avviso.text("Salvataggio dell\u2019ordine\u2026");
				$.post(ajaxurl, { action: "portfolio_ordina", nonce: "' . wp_create_nonce( 'portfolio_ordina' ) . '", inizio: inizio, ids: ids })
					.done(function (r) { avviso.text(r && r.success ? "Ordine salvato. Il sito si aggiorna tra qualche minuto." : "Non sono riuscito a salvare l\u2019ordine: ricarica la pagina e riprova."); })
					.fail(function () { avviso.text("Non sono riuscito a salvare l\u2019ordine: ricarica la pagina e riprova."); });
			}
		});
	});' );
} );

add_action( 'wp_ajax_portfolio_ordina', function () {
	check_ajax_referer( 'portfolio_ordina', 'nonce' );
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_send_json_error( null, 403 );
	}
	global $wpdb;
	$inizio = max( 0, (int) ( isset( $_POST['inizio'] ) ? $_POST['inizio'] : 0 ) );
	$ids    = array_map( 'intval', (array) ( isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array() ) );
	foreach ( array_values( array_filter( $ids ) ) as $i => $id ) {
		if ( 'progetti' !== get_post_type( $id ) ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'menu_order' => $inizio + $i + 1 ), array( 'ID' => $id ) );
		clean_post_cache( $id );
	}
	portfolio_rebuild_later();
	wp_send_json_success();
} );

/* -------------------------------------------------------------------------
 * Pagine dei singoli servizi (/servizi/<slug>/ sul sito)
 * Una pagina per servizio: introduzione, cosa include, percorso fase per fase e testo finale.
 * Lo slug (permalink) diventa l'indirizzo; il campo "Ordine" decide la sequenza.
 * Le card dei servizi in Home e nella pagina Servizi portano qui da sole quando il titolo coincide.
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type( 'pagina_servizio', array(
		'labels'             => array(
			'name'          => 'Pagine servizi',
			'singular_name' => 'Pagina servizio',
			'add_new'       => 'Aggiungi pagina',
			'add_new_item'  => 'Aggiungi pagina servizio',
			'edit_item'     => 'Modifica pagina servizio',
			'all_items'     => 'Pagine servizi',
		),
		'public'             => true,
		'publicly_queryable' => true,
		'exclude_from_search'=> true,
		'show_in_rest'       => true,
		'rest_base'          => 'pagine-servizi',
		'menu_icon'          => 'dashicons-admin-tools',
		'menu_position'      => 6,
		'has_archive'        => false,
		'rewrite'            => array( 'slug' => 'pagina-servizio' ),
		'supports'           => array( 'title', 'page-attributes', 'revisions' ),
	) );
} );

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group( array(
		'key'      => 'group_portfolio_pagina_servizio',
		'title'    => 'Contenuto della pagina',
		'fields'   => portfolio_pagina_servizio_campi(),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'pagina_servizio' ) ) ),
		'position' => 'acf_after_title',
	) );
} );

function portfolio_pagina_servizio_campi() {
	$icone = array_merge( portfolio_icone(), array(
		'cart' => 'Carrello (e-commerce)', 'layers' => 'Livelli (struttura)', 'shield' => 'Scudo (sicurezza)', 'trendingUp' => 'Grafico in crescita (vendite)',
		'sparkles' => 'Scintille (distintivo)', 'target' => 'Bersaglio (obiettivi)', 'users' => 'Persone (team)', 'refresh' => 'Frecce (integrazioni)',
		'clock' => 'Orologio (tempo)', 'mapPin' => 'Segnaposto (zona)',
	) );
	return array(
		array( 'key' => 'field_pps_icona', 'name' => 'icona', 'label' => 'Icona', 'type' => 'select', 'choices' => $icone, 'default_value' => 'globe', 'wrapper' => array( 'width' => 30 ) ),
		array( 'key' => 'field_pps_sottotitolo', 'name' => 'sottotitolo', 'label' => 'Sottotitolo', 'type' => 'text', 'instructions' => 'Riga breve colorata sotto il titolo.', 'wrapper' => array( 'width' => 70 ) ),
		array( 'key' => 'field_pps_intro', 'name' => 'intro', 'label' => 'Introduzione', 'type' => 'textarea', 'rows' => 3 ),
		array( 'key' => 'field_pps_problema_titolo', 'name' => 'problema_titolo', 'label' => 'Il problema: titolo', 'type' => 'text', 'instructions' => 'Il problema che il servizio risolve, es. "Un bel sito non basta, se non porta clienti".' ),
		array( 'key' => 'field_pps_problema_testo', 'name' => 'problema_testo', 'label' => 'Il problema: testo', 'type' => 'textarea', 'rows' => 3 ),
		array( 'key' => 'field_pps_vantaggi_titolo', 'name' => 'vantaggi_titolo', 'label' => 'Inizio della frase dei vantaggi', 'type' => 'text', 'instructions' => 'Es. "Un sito che": ogni vantaggio completa la frase ("vende", "non si blocca"…).' ),
		array(
			'key'          => 'field_pps_vantaggi',
			'name'         => 'vantaggi',
			'label'        => 'Vantaggi',
			'type'         => 'repeater',
			'layout'       => 'block',
			'button_label' => 'Aggiungi vantaggio',
			'collapsed'    => 'field_pps_vantaggio_titolo',
			'sub_fields'   => array(
				array( 'key' => 'field_pps_vantaggio_icona', 'name' => 'icona', 'label' => 'Icona', 'type' => 'select', 'choices' => $icone, 'default_value' => 'zap', 'wrapper' => array( 'width' => 25 ) ),
				array( 'key' => 'field_pps_vantaggio_titolo', 'name' => 'titolo', 'label' => 'Completa la frase', 'type' => 'text', 'required' => 1, 'instructions' => 'Es. "vende".', 'wrapper' => array( 'width' => 75 ) ),
				array( 'key' => 'field_pps_vantaggio_testo', 'name' => 'testo', 'label' => 'Testo', 'type' => 'textarea', 'rows' => 2 ),
			),
		),
		array( 'key' => 'field_pps_include', 'name' => 'include', 'label' => 'Cosa include', 'type' => 'textarea', 'rows' => 6, 'instructions' => 'Una voce per riga.' ),
		array(
			'key'          => 'field_pps_passi',
			'name'         => 'passi',
			'label'        => 'Percorso (fasi sulla linea con la navicella)',
			'type'         => 'repeater',
			'layout'       => 'block',
			'button_label' => 'Aggiungi fase',
			'collapsed'    => 'field_pps_passo_titolo',
			'sub_fields'   => array(
				array( 'key' => 'field_pps_passo_icona', 'name' => 'icona', 'label' => 'Icona', 'type' => 'select', 'choices' => $icone, 'default_value' => 'code', 'wrapper' => array( 'width' => 25 ) ),
				array( 'key' => 'field_pps_passo_etichetta', 'name' => 'etichetta', 'label' => 'Nome della fase', 'type' => 'text', 'instructions' => 'Es. "Analisi".', 'wrapper' => array( 'width' => 25 ) ),
				array( 'key' => 'field_pps_passo_domanda', 'name' => 'domanda', 'label' => 'Domanda', 'type' => 'text', 'instructions' => 'Es. "Chi deve trovarti?".', 'wrapper' => array( 'width' => 50 ) ),
				array( 'key' => 'field_pps_passo_titolo', 'name' => 'titolo', 'label' => 'Titolo', 'type' => 'text', 'required' => 1 ),
				array( 'key' => 'field_pps_passo_testo', 'name' => 'testo', 'label' => 'Testo', 'type' => 'textarea', 'rows' => 3 ),
			),
		),
		array( 'key' => 'field_pps_dopo', 'name' => 'dopo', 'label' => 'Dopo la consegna', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Sotto compaiono da soli i link agli altri servizi.' ),
		array( 'key' => 'field_pps_seo_titolo', 'name' => 'seo_titolo', 'label' => 'Titolo per Google', 'type' => 'text', 'instructions' => 'Vuoto = titolo della pagina.', 'wrapper' => array( 'width' => 40 ) ),
		array( 'key' => 'field_pps_seo_descrizione', 'name' => 'seo_descrizione', 'label' => 'Descrizione per Google', 'type' => 'textarea', 'rows' => 2, 'instructions' => 'Vuota = introduzione.', 'wrapper' => array( 'width' => 60 ) ),
	);
}

/** Pagine servizi pubblicate, nell'ordine scelto (campo "Ordine"), già pronte per il sito. */
function portfolio_pagine_servizi_data() {
	$righe = function ( $v ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $v ) ) ) );
	};
	$out   = array();
	$posts = get_posts( array( 'post_type' => 'pagina_servizio', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
	foreach ( $posts as $post ) {
		$m     = function ( $k ) use ( $post ) {
			return (string) get_post_meta( $post->ID, $k, true );
		};
		$passi = array();
		for ( $i = 0, $n = (int) get_post_meta( $post->ID, 'passi', true ); $i < $n; $i++ ) {
			$titolo = $m( "passi_{$i}_titolo" );
			if ( '' === $titolo ) {
				continue;
			}
			$passi[] = array(
				'icona'     => $m( "passi_{$i}_icona" ) ?: 'code',
				'etichetta' => $m( "passi_{$i}_etichetta" ),
				'domanda'   => $m( "passi_{$i}_domanda" ),
				'titolo'    => $titolo,
				'testo'     => $m( "passi_{$i}_testo" ),
			);
		}
		$vantaggi = array();
		for ( $i = 0, $n = (int) get_post_meta( $post->ID, 'vantaggi', true ); $i < $n; $i++ ) {
			$titolo = $m( "vantaggi_{$i}_titolo" );
			if ( '' !== $titolo ) {
				$vantaggi[] = array( 'icona' => $m( "vantaggi_{$i}_icona" ) ?: 'zap', 'titolo' => $titolo, 'testo' => $m( "vantaggi_{$i}_testo" ) );
			}
		}
		$out[] = array(
			'slug'            => $post->post_name,
			'titolo'          => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'icona'           => $m( 'icona' ) ?: 'globe',
			'sottotitolo'     => $m( 'sottotitolo' ),
			'intro'           => $m( 'intro' ),
			'problema_titolo' => $m( 'problema_titolo' ),
			'problema_testo'  => $m( 'problema_testo' ),
			'vantaggi_titolo' => $m( 'vantaggi_titolo' ),
			'vantaggi'        => $vantaggi,
			'include'         => $righe( $m( 'include' ) ),
			'passi'           => $passi,
			'dopo'            => $m( 'dopo' ),
			'seo_titolo'      => $m( 'seo_titolo' ),
			'seo_descrizione' => $m( 'seo_descrizione' ),
		);
	}
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'portfolio/v1', '/pagine-servizi', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_pagine_servizi_data',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Al primo accesso alla bacheca dopo l'aggiornamento crea le sei pagine con i testi del sito
 * (gli stessi di src/data/pagine-servizi.json), così si parte da pagine già scritte da modificare.
 * Succede una volta sola e solo se non esiste ancora nessuna pagina servizio.
 */
/** Chiavi ACF dei campi delle pagine servizio: "nome" e "repeater.sottocampo". */
function portfolio_pagina_servizio_chiavi() {
	$chiavi = array();
	foreach ( portfolio_pagina_servizio_campi() as $f ) {
		$chiavi[ $f['name'] ] = $f['key'];
		foreach ( isset( $f['sub_fields'] ) ? $f['sub_fields'] : array() as $sf ) {
			$chiavi[ $f['name'] . '.' . $sf['name'] ] = $sf['key'];
		}
	}
	return $chiavi;
}

/** Salva su una pagina servizio i campi indicati (valori semplici e repeater) presi dai testi predefiniti. */
function portfolio_pagina_servizio_salva( $id, $p, $campi ) {
	$chiavi = portfolio_pagina_servizio_chiavi();
	$salva  = function ( $nome, $chiave, $valore ) use ( $id ) {
		update_post_meta( $id, $nome, wp_slash( $valore ) );
		update_post_meta( $id, '_' . $nome, $chiave );
	};
	foreach ( $campi as $k ) {
		if ( ! isset( $p[ $k ] ) ) {
			continue;
		}
		if ( 'include' === $k ) {
			$salva( $k, $chiavi[ $k ], implode( "\n", $p[ $k ] ) );
		} elseif ( is_array( $p[ $k ] ) ) {
			$salva( $k, $chiavi[ $k ], count( $p[ $k ] ) );
			foreach ( $p[ $k ] as $j => $riga ) {
				foreach ( $riga as $sk => $v ) {
					$salva( "{$k}_{$j}_{$sk}", $chiavi[ "{$k}.{$sk}" ], (string) $v );
				}
			}
		} else {
			$salva( $k, $chiavi[ $k ], (string) $p[ $k ] );
		}
	}
}

/**
 * Al primo accesso alla bacheca dopo l'aggiornamento crea le sei pagine con i testi del sito
 * (gli stessi di src/data/pagine-servizi.json), così si parte da pagine già scritte da modificare.
 * Succede una volta sola e solo se non esiste ancora nessuna pagina servizio.
 * Le pagine create con una versione precedente ricevono una volta i nuovi campi (problema e vantaggi), se vuoti.
 */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'publish_posts' ) ) {
		return;
	}
	$predefinite = json_decode( portfolio_pagine_servizi_predefinite(), true );
	if ( ! get_option( 'portfolio_pagine_servizi_create' ) ) {
		update_option( 'portfolio_pagine_servizi_create', 1, false );
		update_option( 'portfolio_pagine_servizi_vantaggi', 1, false );
		if ( get_posts( array( 'post_type' => 'pagina_servizio', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
			return;
		}
		foreach ( $predefinite as $i => $p ) {
			$id = wp_insert_post( array(
				'post_type'   => 'pagina_servizio',
				'post_status' => 'publish',
				'post_title'  => $p['titolo'],
				'post_name'   => $p['slug'],
				'menu_order'  => $i + 1,
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				portfolio_pagina_servizio_salva( $id, $p, array_keys( array_diff_key( $p, array( 'slug' => 1, 'titolo' => 1 ) ) ) );
			}
		}
		return;
	}
	if ( ! get_option( 'portfolio_pagine_servizi_vantaggi' ) ) {
		update_option( 'portfolio_pagine_servizi_vantaggi', 1, false );
		foreach ( $predefinite as $p ) {
			$post = get_page_by_path( $p['slug'], OBJECT, 'pagina_servizio' );
			if ( ! $post || (int) get_post_meta( $post->ID, 'vantaggi', true ) > 0 || '' !== (string) get_post_meta( $post->ID, 'problema_titolo', true ) ) {
				continue;
			}
			portfolio_pagina_servizio_salva( $post->ID, $p, array( 'problema_titolo', 'problema_testo', 'vantaggi_titolo', 'vantaggi' ) );
		}
		portfolio_rebuild_later();
	}
} );

function portfolio_pagine_servizi_predefinite() {
	return <<<'JSON'
[
	{
		"slug": "sviluppo-web",
		"titolo": "Sviluppo Web",
		"icona": "globe",
		"sottotitolo": "Siti veloci, moderni e su misura",
		"intro": "Un sito non è una vetrina da riempire: è uno strumento che deve portarti contatti e clienti. Lo costruisco partendo da quello che vuoi ottenere, con codice pulito che carica in un attimo anche da telefono.",
		"problema_titolo": "Un bel sito non basta, se non porta clienti",
		"problema_testo": "Pagine lente, testi generici e pulsanti nascosti: il visitatore se ne va in pochi secondi e chiama un concorrente. Il sito deve lavorare per te anche quando non ci sei.",
		"vantaggi_titolo": "Un sito che",
		"vantaggi": [
			{
				"icona": "trendingUp",
				"titolo": "vende",
				"testo": "Ogni pagina ha un obiettivo e un invito all'azione chiaro: chiamare, scrivere, prenotare o chiedere un preventivo."
			},
			{
				"icona": "zap",
				"titolo": "carica in un attimo",
				"testo": "Codice leggero e immagini ottimizzate: punteggi alti su PageSpeed anche da telefono, da dove arriva la maggior parte delle visite."
			},
			{
				"icona": "search",
				"titolo": "si fa trovare su Google",
				"testo": "Struttura, titoli e dati strutturati pensati per la SEO fin dal primo giorno, non aggiunti alla fine."
			},
			{
				"icona": "shield",
				"titolo": "non si blocca",
				"testo": "Niente plugin pesanti o temi da rattoppare: meno cose che si rompono, meno sorprese."
			},
			{
				"icona": "sparkles",
				"titolo": "ti distingue",
				"testo": "Un design su misura per il tuo brand, non un template uguale a quello di altri mille siti."
			},
			{
				"icona": "smartphone",
				"titolo": "funziona ovunque",
				"testo": "Perfetto su telefono, tablet e computer, e accessibile anche a chi naviga con la tastiera o uno screen reader."
			}
		],
		"include": [
			"Siti vetrina e istituzionali",
			"Landing page ad alta conversione",
			"Performance ottimizzate (Core Web Vitals)",
			"Design responsive su ogni dispositivo",
			"SEO tecnica di base",
			"Formazione per gestire i contenuti"
		],
		"passi": [
			{
				"icona": "message",
				"etichetta": "Obiettivo",
				"domanda": "A cosa ti serve il sito?",
				"titolo": "Partiamo da cosa vuoi ottenere",
				"testo": "Prima del design parliamo di numeri: più richieste di preventivo, più prenotazioni, più credibilità. Un obiettivo chiaro guida ogni scelta successiva, dai testi ai pulsanti."
			},
			{
				"icona": "search",
				"etichetta": "Analisi",
				"domanda": "Chi deve trovarti e cosa cerca?",
				"titolo": "Studio il tuo pubblico e la concorrenza",
				"testo": "Guardo come si muovono i tuoi clienti, quali parole usano su Google e cosa fanno i concorrenti. Così il sito risponde alle domande giuste, nel modo giusto."
			},
			{
				"icona": "layers",
				"etichetta": "Struttura e preventivo",
				"domanda": "Quali pagine servono davvero?",
				"titolo": "Mappa del sito e preventivo chiaro",
				"testo": "Definisco pagine, sezioni e percorso dell'utente. Ricevi un preventivo dettagliato con tempi e costi, senza voci nascoste."
			},
			{
				"icona": "palette",
				"etichetta": "Materiali e design",
				"domanda": "Che immagine vuoi dare?",
				"titolo": "Testi, foto e prototipo",
				"testo": "Raccogliamo i materiali e preparo il design delle pagine principali. Lo rivediamo insieme finché non ti rappresenta, prima di scrivere codice."
			},
			{
				"icona": "code",
				"etichetta": "Sviluppo",
				"domanda": "Come lo rendiamo veloce?",
				"titolo": "Costruzione su un'anteprima online",
				"testo": "Sviluppo il sito su un indirizzo di prova che puoi visitare quando vuoi. Ottimizzo immagini, caricamento e accessibilità mentre procedo, non alla fine."
			},
			{
				"icona": "rocket",
				"etichetta": "Online",
				"domanda": "Siamo pronti al decollo?",
				"titolo": "Lancio e consegna",
				"testo": "Test su browser e telefoni, collegamento del dominio, Search Console e messa online. Ti mostro come aggiornare i contenuti in autonomia."
			}
		],
		"dopo": "Dopo la pubblicazione resto a disposizione per aggiornamenti, nuove pagine e controlli periodici su velocità e sicurezza.",
		"seo_titolo": "Sviluppo siti web su misura a Palermo",
		"seo_descrizione": "Siti web veloci, moderni e su misura: dall'obiettivo alla messa online, con codice pulito e performance ottimizzate. Gabriele Vilardo, web developer a Palermo."
	},
	{
		"slug": "applicazioni-web",
		"titolo": "Applicazioni web",
		"icona": "server",
		"sottotitolo": "Gestionali e piattaforme su misura",
		"intro": "Quando fogli Excel, email e programmi scollegati non bastano più, serve uno strumento costruito sul tuo modo di lavorare. Progetto applicazioni web solide, dal database all'interfaccia.",
		"problema_titolo": "Fogli Excel, email e programmi che non si parlano?",
		"problema_testo": "Ogni giorno si perde tempo a copiare dati, cercare file e correggere errori. Più l'attività cresce, più il problema peggiora, e gli strumenti generici non si adattano al tuo modo di lavorare.",
		"vantaggi_titolo": "Un gestionale che",
		"vantaggi": [
			{
				"icona": "clock",
				"titolo": "ti fa risparmiare ore",
				"testo": "Automatizza i passaggi ripetitivi: preventivi, scadenze, report e notifiche partono da soli."
			},
			{
				"icona": "layers",
				"titolo": "mette tutto in un posto",
				"testo": "Clienti, ordini, documenti e storico in un unico archivio, sempre aggiornato e consultabile da ovunque."
			},
			{
				"icona": "users",
				"titolo": "lavora come il tuo team",
				"testo": "Ruoli e permessi su misura: ognuno vede e modifica solo quello che gli serve."
			},
			{
				"icona": "shield",
				"titolo": "protegge i tuoi dati",
				"testo": "Accessi sicuri, backup automatici e dati su un database solido come PostgreSQL."
			},
			{
				"icona": "trendingUp",
				"titolo": "cresce con te",
				"testo": "Architettura modulare: le nuove funzioni si aggiungono senza rifare tutto da capo."
			},
			{
				"icona": "refresh",
				"titolo": "si collega al resto",
				"testo": "API per dialogare con fatturazione, e-commerce, CRM e gli strumenti che usi già."
			}
		],
		"include": [
			"Analisi dei processi",
			"API REST con Spring Boot",
			"Interfacce in Angular",
			"Database PostgreSQL",
			"Accessi e permessi per ruolo",
			"Rilascio e manutenzione"
		],
		"passi": [
			{
				"icona": "message",
				"etichetta": "Obiettivo",
				"domanda": "Quale problema vuoi risolvere?",
				"titolo": "Capire dove si perde tempo",
				"testo": "Partiamo dal lavoro di tutti i giorni: quali passaggi sono ripetitivi, dove nascono gli errori, chi deve vedere cosa. L'applicazione nasce da lì."
			},
			{
				"icona": "search",
				"etichetta": "Analisi",
				"domanda": "Come funziona oggi il processo?",
				"titolo": "Mappatura dei flussi e dei dati",
				"testo": "Disegno i flussi attuali e quelli futuri, i dati da gestire e le integrazioni con i sistemi che usi già. Niente funzioni superflue."
			},
			{
				"icona": "layers",
				"etichetta": "Architettura e preventivo",
				"domanda": "Su quali basi la costruiamo?",
				"titolo": "Architettura e piano di lavoro",
				"testo": "Scelgo le tecnologie adatte e divido il progetto in fasi rilasciabili. Ricevi un preventivo per fase, così vedi risultati presto e mantieni il controllo del budget."
			},
			{
				"icona": "palette",
				"etichetta": "Prototipo",
				"domanda": "È comodo da usare?",
				"titolo": "Prototipo cliccabile delle schermate",
				"testo": "Prima del codice prepariamo le schermate principali e le proviamo con chi userà davvero lo strumento. Correggere qui costa pochissimo."
			},
			{
				"icona": "code",
				"etichetta": "Sviluppo",
				"domanda": "Come procede il lavoro?",
				"titolo": "Sviluppo a rilasci successivi",
				"testo": "Backend con Java e Spring Boot, interfaccia in Angular, dati su PostgreSQL. A ogni rilascio provi le nuove funzioni su un ambiente di test."
			},
			{
				"icona": "rocket",
				"etichetta": "Rilascio",
				"domanda": "Come passiamo al nuovo sistema?",
				"titolo": "Messa in produzione e formazione",
				"testo": "Migro i dati esistenti, metto online l'applicazione e formo il tuo team. Il passaggio avviene senza fermare il lavoro."
			}
		],
		"dopo": "Un'applicazione cresce con la tua attività: dopo il rilascio mi occupo di manutenzione, backup e nuove funzioni quando servono.",
		"seo_titolo": "Sviluppo applicazioni web e gestionali su misura",
		"seo_descrizione": "Gestionali e piattaforme web su misura con Java, Spring Boot, Angular e PostgreSQL: analisi, prototipo, sviluppo a rilasci e formazione."
	},
	{
		"slug": "wordpress",
		"titolo": "WordPress",
		"icona": "layout",
		"sottotitolo": "Flessibilità e autonomia nella gestione",
		"intro": "Oltre dieci anni di WordPress: temi scritti da zero, niente page builder pesanti e un pannello semplice per aggiornare i contenuti da solo. Anche in versione headless, collegato a un frontend velocissimo.",
		"problema_titolo": "WordPress lento, pieno di plugin e difficile da aggiornare?",
		"problema_testo": "Temi pesanti e page builder rendono il sito lento e fragile: ogni aggiornamento è un rischio e modificare una pagina diventa un'impresa.",
		"vantaggi_titolo": "Un WordPress che",
		"vantaggi": [
			{
				"icona": "settings",
				"titolo": "aggiorni da solo",
				"testo": "Un pannello pulito con campi chiari: cambi testi, foto e pagine senza toccare il codice e senza rompere il layout."
			},
			{
				"icona": "zap",
				"titolo": "è leggero e veloce",
				"testo": "Tema scritto da zero, senza page builder: carica in un attimo anche su hosting condiviso."
			},
			{
				"icona": "shield",
				"titolo": "non si rompe agli aggiornamenti",
				"testo": "Pochi plugin scelti con cura, aggiornamenti e backup sotto controllo."
			},
			{
				"icona": "search",
				"titolo": "piace a Google",
				"testo": "HTML pulito, struttura corretta e prestazioni alte: le basi per posizionarsi bene."
			},
			{
				"icona": "layers",
				"titolo": "si adatta ai tuoi contenuti",
				"testo": "Tipi di contenuto e campi su misura per progetti, eventi, prodotti o qualsiasi cosa tu debba pubblicare."
			},
			{
				"icona": "rocket",
				"titolo": "può diventare headless",
				"testo": "Lo usi come pannello di gestione e il sito pubblico vola, come quello che stai visitando."
			}
		],
		"include": [
			"Temi custom da zero",
			"WordPress headless",
			"ACF e custom post type",
			"Migrazione da altri CMS",
			"Sicurezza e aggiornamenti",
			"Formazione per la gestione autonoma"
		],
		"passi": [
			{
				"icona": "message",
				"etichetta": "Obiettivo",
				"domanda": "Cosa vuoi poter gestire da solo?",
				"titolo": "Contenuti e autonomia",
				"testo": "Capiamo quali contenuti cambieranno spesso e chi li aggiornerà. WordPress va modellato su questo, non il contrario."
			},
			{
				"icona": "search",
				"etichetta": "Analisi",
				"domanda": "Classico o headless?",
				"titolo": "La soluzione tecnica giusta",
				"testo": "Valuto se ti serve un sito WordPress classico o headless, quali plugin sono davvero necessari e se c'è un sito esistente da migrare."
			},
			{
				"icona": "layers",
				"etichetta": "Struttura e preventivo",
				"domanda": "Come organizziamo i contenuti?",
				"titolo": "Tipi di contenuto e campi su misura",
				"testo": "Progetto custom post type e campi personalizzati, così ogni contenuto ha il suo modulo ordinato. Ricevi un preventivo dettagliato."
			},
			{
				"icona": "palette",
				"etichetta": "Design",
				"domanda": "Che aspetto avrà?",
				"titolo": "Design e prototipo delle pagine",
				"testo": "Disegno le pagine principali sul tuo brand e le approviamo insieme prima di sviluppare il tema."
			},
			{
				"icona": "code",
				"etichetta": "Sviluppo",
				"domanda": "Come resta leggero?",
				"titolo": "Tema custom senza zavorra",
				"testo": "Scrivo il tema da zero con solo il codice che serve. Il pannello resta pulito e il sito veloce, anche su hosting condiviso."
			},
			{
				"icona": "rocket",
				"etichetta": "Online",
				"domanda": "Sai già come usarlo?",
				"titolo": "Pubblicazione e formazione",
				"testo": "Metto online il sito, configuro backup e sicurezza e ti mostro passo passo come aggiungere e modificare i contenuti."
			}
		],
		"dopo": "Dopo la pubblicazione posso occuparmi di aggiornamenti di WordPress e plugin, backup e piccole modifiche, così non devi pensarci.",
		"seo_titolo": "Sviluppo siti WordPress su misura e headless",
		"seo_descrizione": "Temi WordPress custom da zero, WordPress headless, ACF e migrazioni: siti leggeri e facili da gestire in autonomia. Gabriele Vilardo, Palermo."
	},
	{
		"slug": "e-commerce",
		"titolo": "E-Commerce",
		"icona": "cart",
		"sottotitolo": "Vendi online senza complicazioni",
		"intro": "Un negozio online funziona quando comprare è facile e gestire gli ordini non ti ruba la giornata. Costruisco e-commerce chiari per i clienti e semplici per te.",
		"problema_titolo": "Tante visite al negozio, ma pochi ordini?",
		"problema_testo": "Schede prodotto confuse, checkout lungo, spese di spedizione a sorpresa: basta un ostacolo e il carrello resta abbandonato.",
		"vantaggi_titolo": "Un negozio che",
		"vantaggi": [
			{
				"icona": "trendingUp",
				"titolo": "trasforma le visite in ordini",
				"testo": "Schede prodotto chiare, foto curate e un checkout breve, pensato per comprare da telefono."
			},
			{
				"icona": "zap",
				"titolo": "è veloce anche con tanti prodotti",
				"testo": "Catalogo ottimizzato e immagini leggere: niente attese tra una pagina e l'altra."
			},
			{
				"icona": "lock",
				"titolo": "ispira fiducia",
				"testo": "Pagamenti sicuri, condizioni chiare e recensioni ben visibili: il cliente compra tranquillo."
			},
			{
				"icona": "search",
				"titolo": "si fa trovare",
				"testo": "Categorie e prodotti strutturati per Google, con prezzi e disponibilità nei risultati di ricerca."
			},
			{
				"icona": "settings",
				"titolo": "si gestisce facilmente",
				"testo": "Ordini, magazzino e spedizioni da un unico pannello, con email automatiche ai clienti."
			},
			{
				"icona": "refresh",
				"titolo": "parla con il tuo gestionale",
				"testo": "Collegamento con magazzino, fatturazione e corrieri, per non inserire due volte gli stessi dati."
			}
		],
		"include": [
			"WooCommerce personalizzato",
			"Pagamenti e spedizioni",
			"Catalogo prodotti ottimizzato",
			"Integrazione con gestionali",
			"Email e notifiche d'ordine",
			"Pagine prodotto pensate per vendere"
		],
		"passi": [
			{
				"icona": "message",
				"etichetta": "Obiettivo",
				"domanda": "Cosa vendi e a chi?",
				"titolo": "Prodotti, clienti e margini",
				"testo": "Parliamo di catalogo, prezzi, zone di spedizione e di come gestisci oggi gli ordini. Da qui capiamo che negozio ti serve."
			},
			{
				"icona": "search",
				"etichetta": "Analisi",
				"domanda": "Come compra il tuo cliente?",
				"titolo": "Percorso d'acquisto e concorrenza",
				"testo": "Studio come i tuoi clienti cercano e scelgono i prodotti e cosa offrono i concorrenti, per togliere ogni ostacolo tra il prodotto e il pagamento."
			},
			{
				"icona": "layers",
				"etichetta": "Struttura e preventivo",
				"domanda": "Quali funzioni servono?",
				"titolo": "Catalogo, pagamenti e spedizioni",
				"testo": "Definisco categorie, varianti, metodi di pagamento, spedizioni e integrazioni. Ricevi un preventivo chiaro con tempi e costi."
			},
			{
				"icona": "palette",
				"etichetta": "Design",
				"domanda": "Il prodotto si vede bene?",
				"titolo": "Pagine prodotto e carrello",
				"testo": "Progetto scheda prodotto, carrello e checkout perché siano chiari da telefono. Foto, prezzi e pulsanti al posto giusto."
			},
			{
				"icona": "code",
				"etichetta": "Sviluppo",
				"domanda": "Tutto funziona davvero?",
				"titolo": "Configurazione e test degli ordini",
				"testo": "Sviluppo il negozio, importo il catalogo e faccio ordini di prova con ogni metodo di pagamento e spedizione."
			},
			{
				"icona": "rocket",
				"etichetta": "Online",
				"domanda": "Pronti a vendere?",
				"titolo": "Apertura del negozio",
				"testo": "Metto online lo shop, collego statistiche e Search Console e ti mostro come gestire prodotti, ordini e resi."
			}
		],
		"dopo": "Dopo l'apertura ti aiuto a migliorare le vendite: velocità, schede prodotto, promozioni e aggiornamenti di sicurezza.",
		"seo_titolo": "Realizzazione e-commerce con WooCommerce",
		"seo_descrizione": "Negozi online con WooCommerce chiari per i clienti e semplici da gestire: catalogo, pagamenti, spedizioni e integrazioni. Gabriele Vilardo, Palermo."
	},
	{
		"slug": "seo-performance",
		"titolo": "SEO & Performance",
		"icona": "zap",
		"sottotitolo": "Farsi trovare e caricare in un attimo",
		"intro": "Un sito lento perde visitatori prima ancora che leggano una riga, e uno che Google non capisce non viene trovato. Lavoro sulla parte tecnica per renderlo rapido e ben posizionato.",
		"problema_titolo": "Il tuo sito c'è, ma su Google non ti trova nessuno?",
		"problema_testo": "Se il sito è lento o Google non lo capisce, i clienti finiscono dai concorrenti. Spesso il problema è tecnico, e si può risolvere.",
		"vantaggi_titolo": "Un sito che",
		"vantaggi": [
			{
				"icona": "search",
				"titolo": "compare nelle ricerche giuste",
				"testo": "Lavoro sulle parole che usano davvero i tuoi clienti, anche nelle ricerche locali."
			},
			{
				"icona": "zap",
				"titolo": "supera i Core Web Vitals",
				"testo": "Ottimizzo caricamento, stabilità e reattività: punteggi alti e una navigazione più piacevole."
			},
			{
				"icona": "smartphone",
				"titolo": "è perfetto da telefono",
				"testo": "Google valuta prima la versione mobile: la rendo veloce e comoda da usare."
			},
			{
				"icona": "target",
				"titolo": "Google capisce al volo",
				"testo": "Titoli, struttura, link interni e dati strutturati in ordine: ogni pagina dice chiaramente di cosa parla."
			},
			{
				"icona": "mapPin",
				"titolo": "ti fa trovare in zona",
				"testo": "Scheda Google Business e SEO locale per chi cerca un servizio vicino a sé."
			},
			{
				"icona": "chart",
				"titolo": "misura i risultati",
				"testo": "Report chiari prima e dopo: posizioni, traffico e velocità, senza gergo tecnico."
			}
		],
		"include": [
			"Audit tecnico",
			"Core Web Vitals",
			"Ottimizzazione immagini",
			"SEO on-page e locale",
			"Dati strutturati",
			"Report con priorità di intervento"
		],
		"passi": [
			{
				"icona": "message",
				"etichetta": "Obiettivo",
				"domanda": "Per cosa vuoi farti trovare?",
				"titolo": "Ricerche e risultati attesi",
				"testo": "Partiamo dalle ricerche che contano per la tua attività e da dove sei oggi su Google, così misuriamo i progressi su dati reali."
			},
			{
				"icona": "search",
				"etichetta": "Audit",
				"domanda": "Cosa rallenta il sito?",
				"titolo": "Analisi tecnica completa",
				"testo": "Controllo velocità, Core Web Vitals, indicizzazione, struttura dei contenuti, dati strutturati ed errori in Search Console."
			},
			{
				"icona": "layers",
				"etichetta": "Priorità e preventivo",
				"domanda": "Da dove conviene partire?",
				"titolo": "Piano di interventi in ordine di impatto",
				"testo": "Ricevi un report chiaro con i problemi trovati, ordinati per impatto e difficoltà, e un preventivo per risolverli."
			},
			{
				"icona": "zap",
				"etichetta": "Ottimizzazione",
				"domanda": "Quanto possiamo guadagnare?",
				"titolo": "Velocità e Core Web Vitals",
				"testo": "Ottimizzo immagini, font, script e cache. L'obiettivo è un sito che carica in un attimo anche da telefono e su hosting condiviso."
			},
			{
				"icona": "code",
				"etichetta": "SEO on-page",
				"domanda": "Google capisce le tue pagine?",
				"titolo": "Titoli, contenuti e dati strutturati",
				"testo": "Sistemo titoli, descrizioni, intestazioni, link interni e dati strutturati, e curo la presenza locale se lavori sul territorio."
			},
			{
				"icona": "chart",
				"etichetta": "Misura",
				"domanda": "Ha funzionato?",
				"titolo": "Verifica dei risultati",
				"testo": "Confrontiamo i dati prima e dopo: punteggi di velocità, posizioni e traffico. Ti lascio indicazioni per mantenere i risultati."
			}
		],
		"dopo": "La SEO non finisce con un intervento: posso seguire il sito nel tempo con controlli periodici e nuovi contenuti ottimizzati.",
		"seo_titolo": "Ottimizzazione SEO e velocità del sito",
		"seo_descrizione": "Audit tecnico, Core Web Vitals, ottimizzazione immagini e SEO on-page e locale per un sito veloce e ben posizionato su Google."
	},
	{
		"slug": "consulenza-digitale",
		"titolo": "Consulenza Digitale",
		"icona": "chart",
		"sottotitolo": "Strategia prima della tecnologia",
		"intro": "Non sempre serve un sito nuovo. A volte basta capire cosa non funziona e dove conviene investire. Ti aiuto a prendere decisioni con dati e priorità chiare.",
		"problema_titolo": "Investi nel digitale ma non sai cosa funziona?",
		"problema_testo": "Abbonamenti inutili, fornitori che non si parlano, un sito che non porta risultati: senza una strategia si spende tanto e si raccoglie poco.",
		"vantaggi_titolo": "Scelte che",
		"vantaggi": [
			{
				"icona": "target",
				"titolo": "partono dagli obiettivi",
				"testo": "Prima capiamo cosa vuoi ottenere, poi scegliamo gli strumenti. Mai il contrario."
			},
			{
				"icona": "chart",
				"titolo": "si basano sui dati",
				"testo": "Analizzo sito, statistiche e concorrenza per capire dove conviene davvero intervenire."
			},
			{
				"icona": "wrench",
				"titolo": "evitano gli sprechi",
				"testo": "Ti consiglio solo gli strumenti e i servizi che ti servono, senza abbonamenti superflui."
			},
			{
				"icona": "compass",
				"titolo": "danno una direzione chiara",
				"testo": "Un piano con priorità, tempi e budget: sai cosa fare adesso e cosa può aspettare."
			},
			{
				"icona": "users",
				"titolo": "coinvolgono il tuo team",
				"testo": "Spiego le scelte in modo semplice e affianco chi lavora con te."
			},
			{
				"icona": "shield",
				"titolo": "ti lasciano libero",
				"testo": "Nessuna dipendenza da un solo fornitore: le decisioni restano tue."
			}
		],
		"include": [
			"Audit del sito esistente",
			"Scelta degli strumenti giusti",
			"Pianificazione budget e priorità",
			"Supporto nella scelta di fornitori",
			"Affiancamento del tuo team"
		],
		"passi": [
			{
				"icona": "message",
				"etichetta": "Ascolto",
				"domanda": "Cosa non ti convince oggi?",
				"titolo": "Il punto di partenza",
				"testo": "Mi racconti obiettivi, dubbi e cosa hai già provato. Spesso il problema vero emerge proprio in questa prima chiacchierata."
			},
			{
				"icona": "search",
				"etichetta": "Analisi",
				"domanda": "Dove sono i colli di bottiglia?",
				"titolo": "Sito, strumenti e numeri",
				"testo": "Analizzo sito, statistiche, strumenti che usi e concorrenza per capire cosa funziona, cosa no e perché."
			},
			{
				"icona": "layers",
				"etichetta": "Strategia",
				"domanda": "Su cosa conviene investire?",
				"titolo": "Priorità e budget",
				"testo": "Ti propongo un piano con interventi ordinati per impatto e costo. Decidi tu cosa fare, quando e con chi."
			},
			{
				"icona": "wrench",
				"etichetta": "Strumenti",
				"domanda": "Quali strumenti ti servono?",
				"titolo": "Scelte tecniche senza sprechi",
				"testo": "Ti consiglio piattaforme, servizi e fornitori adatti alle tue esigenze reali, evitando abbonamenti e funzioni che non userai."
			},
			{
				"icona": "chart",
				"etichetta": "Affiancamento",
				"domanda": "Come lo mettiamo in pratica?",
				"titolo": "Accompagnamento nelle scelte",
				"testo": "Posso seguire l'attuazione, affiancare il tuo team o valutare i preventivi di altri fornitori, finché i risultati non arrivano."
			}
		],
		"dopo": "Una consulenza può diventare un appuntamento periodico: rivediamo i numeri e aggiorniamo le priorità quando la tua attività cambia.",
		"seo_titolo": "Consulenza digitale per piccole imprese",
		"seo_descrizione": "Audit del sito, scelta degli strumenti e pianificazione di budget e priorità: una consulenza per investire nel digitale con dati chiari."
	}
]
JSON;
}

/* -------------------------------------------------------------------------
 * Blog automatico con Gemini (Articoli → Blog automatico)
 * Scrive l'articolo (titolo, testo, riassunto, meta description, tag) e crea l'immagine in evidenza,
 * a orari fissi con WP-Cron oppure con il pulsante "Genera ora". Gli articoli restano in bozza
 * per essere riletti, a meno di scegliere la pubblicazione diretta.
 * Serve la chiave in wp-config.php: define( 'PORTFOLIO_GEMINI_API_KEY', '...' );
 * ---------------------------------------------------------------------- */
define( 'PORTFOLIO_BLOG_HOOK', 'portfolio_blog_genera' );

function portfolio_blog_opzioni() {
	return wp_parse_args( get_option( 'portfolio_blog', array() ), array(
		'attivo'          => 0,
		'frequenza'       => 'portfolio_settimanale',
		'stato'           => 'draft',
		'argomenti'       => '',
		'pubblico'        => 'Piccole imprese, professionisti e attività locali che vogliono un sito che porti clienti.',
		'tono'            => 'Chiaro, concreto e amichevole, in prima persona come web designer freelance. Niente gergo tecnico non spiegato.',
		'parole'          => 1000,
		'stile_immagine'  => 'Illustrazione digitale moderna e pulita, palette viola, blu notte e lilla, atmosfera spaziale leggera, nessun testo né logo nell\'immagine.',
		'modello_testo'   => 'gemini-3.6-flash',
		'modello_immagine'=> 'gemini-3.1-flash-image',
		'avvisa'          => 1,
		'immagine'        => 'grafica',
		'immagine_base'   => 0,
	) );
}

add_filter( 'cron_schedules', function ( $s ) {
	$s['portfolio_3giorni']      = array( 'interval' => 3 * DAY_IN_SECONDS, 'display' => 'Ogni 3 giorni' );
	$s['portfolio_settimanale']  = array( 'interval' => WEEK_IN_SECONDS, 'display' => 'Una volta a settimana' );
	$s['portfolio_quindicinale'] = array( 'interval' => 2 * WEEK_IN_SECONDS, 'display' => 'Ogni due settimane' );
	return $s;
} );

function portfolio_blog_frequenze() {
	return array(
		'daily'                  => 'Ogni giorno',
		'portfolio_3giorni'      => 'Ogni 3 giorni',
		'portfolio_settimanale'  => 'Una volta a settimana',
		'portfolio_quindicinale' => 'Ogni due settimane',
	);
}

// Tiene il calendario allineato alle impostazioni (anche dopo l'aggiornamento del plugin).
function portfolio_blog_pianifica() {
	$o    = portfolio_blog_opzioni();
	$next = wp_next_scheduled( PORTFOLIO_BLOG_HOOK );
	$ora  = wp_get_schedule( PORTFOLIO_BLOG_HOOK );
	if ( ! $o['attivo'] ) {
		if ( $next ) {
			wp_clear_scheduled_hook( PORTFOLIO_BLOG_HOOK );
		}
		return;
	}
	if ( $next && $ora === $o['frequenza'] ) {
		return;
	}
	wp_clear_scheduled_hook( PORTFOLIO_BLOG_HOOK );
	// Primo articolo domani alle 9 (ora del sito), poi con la frequenza scelta.
	$domani = new DateTime( 'tomorrow 09:00', wp_timezone() );
	wp_schedule_event( $domani->getTimestamp(), $o['frequenza'], PORTFOLIO_BLOG_HOOK );
}
add_action( 'init', function () {
	if ( is_admin() || wp_doing_cron() ) {
		portfolio_blog_pianifica();
	}
} );

add_action( PORTFOLIO_BLOG_HOOK, function () {
	portfolio_blog_genera();
} );

function portfolio_blog_log( $esito, $messaggio, $post_id = 0 ) {
	$log = get_option( 'portfolio_blog_log', array() );
	array_unshift( $log, array( 'quando' => time(), 'esito' => $esito, 'messaggio' => $messaggio, 'post' => (int) $post_id ) );
	update_option( 'portfolio_blog_log', array_slice( $log, 0, 20 ), false );
}

/** Chiamata a Gemini (generateContent). Restituisce le "parts" della risposta o WP_Error. */
function portfolio_gemini( $modello, $corpo, $timeout = 120 ) {
	if ( ! defined( 'PORTFOLIO_GEMINI_API_KEY' ) || ! PORTFOLIO_GEMINI_API_KEY ) {
		return new WP_Error( 'portfolio_gemini', 'Manca PORTFOLIO_GEMINI_API_KEY in wp-config.php.' );
	}
	$res = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $modello ) . ':generateContent', array(
		'timeout' => $timeout,
		'headers' => array(
			'Content-Type'   => 'application/json',
			'x-goog-api-key' => PORTFOLIO_GEMINI_API_KEY,
		),
		'body'    => wp_json_encode( $corpo ),
	) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$codice = wp_remote_retrieve_response_code( $res );
	$dati   = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $codice ) {
		$msg = $dati['error']['message'] ?? wp_remote_retrieve_body( $res );
		return new WP_Error( 'portfolio_gemini', 'Gemini (' . $modello . ') ha risposto ' . $codice . ': ' . wp_trim_words( wp_strip_all_tags( (string) $msg ), 40 ) );
	}
	$parti = $dati['candidates'][0]['content']['parts'] ?? array();
	// Le parti "thought" sono ragionamenti o bozze intermedie, non il risultato.
	$parti = array_values( array_filter( $parti, function ( $p ) {
		return empty( $p['thought'] );
	} ) );
	if ( ! $parti ) {
		$motivo = $dati['candidates'][0]['finishReason'] ?? ( $dati['promptFeedback']['blockReason'] ?? 'risposta vuota' );
		return new WP_Error( 'portfolio_gemini', 'Gemini (' . $modello . ') non ha restituito contenuti: ' . $motivo );
	}
	return $parti;
}

/** Pagine servizio pubblicate (titolo e indirizzo), per i link interni negli articoli. */
function portfolio_blog_servizi() {
	$sito  = portfolio_site_url();
	$lista = array();
	foreach ( get_posts( array( 'post_type' => 'pagina_servizio', 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $p ) {
		$lista[] = array( 'titolo' => get_the_title( $p ), 'url' => $sito . '/servizi/' . $p->post_name . '/' );
	}
	return $lista;
}

function portfolio_blog_prossimo_argomento() {
	$righe = array_values( array_filter( array_map( 'trim', explode( "\n", portfolio_blog_opzioni()['argomenti'] ) ) ) );
	return $righe[0] ?? '';
}

// Toglie dall'elenco l'argomento appena usato, così il prossimo articolo parla d'altro.
function portfolio_blog_consuma_argomento( $argomento ) {
	$o     = portfolio_blog_opzioni();
	$righe = array_values( array_filter( array_map( 'trim', explode( "\n", $o['argomenti'] ) ) ) );
	$k     = array_search( $argomento, $righe, true );
	if ( false !== $k ) {
		unset( $righe[ $k ] );
		$o['argomenti'] = implode( "\n", $righe );
		update_option( 'portfolio_blog', $o );
	}
}

function portfolio_blog_testo( $argomento ) {
	$o        = portfolio_blog_opzioni();
	$esistenti = wp_list_pluck( get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'numberposts' => 60 ) ), 'post_title' );
	$servizi  = portfolio_blog_servizi();
	$sito     = portfolio_site_url();

	$prompt  = "Sei il copywriter SEO del blog di un web designer e sviluppatore freelance italiano.\n";
	$prompt .= 'Pubblico: ' . $o['pubblico'] . "\n";
	$prompt .= 'Tono: ' . $o['tono'] . "\n";
	$prompt .= $argomento
		? "Argomento dell'articolo: " . $argomento . "\n"
		: "Scegli tu un argomento utile e cercato su Google dal pubblico indicato, legato ai servizi qui sotto. Deve essere diverso dagli articoli già scritti.\n";
	if ( $servizi ) {
		$prompt .= "Servizi offerti (puoi collegarne uno o due nel testo, solo se pertinenti, con link HTML a questi indirizzi esatti):\n";
		foreach ( $servizi as $s ) {
			$prompt .= '- ' . $s['titolo'] . ': ' . $s['url'] . "\n";
		}
	}
	if ( $esistenti ) {
		$prompt .= "Articoli già presenti, da non ripetere:\n- " . implode( "\n- ", array_map( 'wp_strip_all_tags', $esistenti ) ) . "\n";
	}
	$prompt .= "\nRegole:\n";
	$prompt .= '- Circa ' . (int) $o['parole'] . " parole, in italiano corretto.\n";
	$prompt .= "- contenuto_html: solo <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <blockquote>. Niente <h1>, niente titolo all'inizio, niente stili o classi.\n";
	$prompt .= "- Apri con un paragrafo che dica subito il problema del lettore. Usa sottotitoli <h2> chiari, esempi pratici e consigli applicabili.\n";
	$prompt .= "- Chiudi con un breve paragrafo che inviti a chiedere un parere o un preventivo, con un link a " . $sito . "/contatti/.\n";
	$prompt .= "- Non inventare dati, statistiche, clienti o citazioni. Se citi numeri, devono essere prudenti e generali.\n";
	$prompt .= "- titolo: massimo 65 caratteri, con la parola chiave principale. meta_descrizione: 140-155 caratteri. estratto: 1-2 frasi.\n";
	$prompt .= "- slug: breve, minuscolo, parole separate da trattini, senza articoli inutili.\n";
	$prompt .= "- tag: da 2 a 5, brevi.\n";
	$prompt .= "- prompt_immagine: descrizione in inglese di un'immagine di copertina che rappresenti il tema in modo visivo e metaforico, senza testo, scritte, loghi o persone riconoscibili.\n";
	$prompt .= "- alt_immagine: testo alternativo in italiano, massimo 120 caratteri.\n";

	$schema = array(
		'type'       => 'OBJECT',
		'properties' => array(
			'titolo'           => array( 'type' => 'STRING' ),
			'slug'             => array( 'type' => 'STRING' ),
			'estratto'         => array( 'type' => 'STRING' ),
			'meta_descrizione' => array( 'type' => 'STRING' ),
			'contenuto_html'   => array( 'type' => 'STRING' ),
			'tag'              => array( 'type' => 'ARRAY', 'items' => array( 'type' => 'STRING' ) ),
			'prompt_immagine'  => array( 'type' => 'STRING' ),
			'alt_immagine'     => array( 'type' => 'STRING' ),
		),
		'required'   => array( 'titolo', 'slug', 'estratto', 'meta_descrizione', 'contenuto_html', 'tag', 'prompt_immagine', 'alt_immagine' ),
	);

	$corpo = array(
		'contents'         => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $prompt ) ) ) ),
		'generationConfig' => array(
			'responseMimeType' => 'application/json',
			'responseSchema'   => $schema,
		),
	);
	$parti = portfolio_gemini( $o['modello_testo'], $corpo );
	// Modello ritirato o nome sbagliato: si riprova con l'ultimo Flash disponibile.
	if ( is_wp_error( $parti ) && false !== strpos( $parti->get_error_message(), ' 404' ) && 'gemini-flash-latest' !== $o['modello_testo'] ) {
		$parti = portfolio_gemini( 'gemini-flash-latest', $corpo );
	}
	if ( is_wp_error( $parti ) ) {
		return $parti;
	}
	$json = '';
	foreach ( $parti as $p ) {
		$json .= $p['text'] ?? '';
	}
	$json = trim( preg_replace( '/^```(?:json)?|```$/m', '', $json ) );
	$a    = json_decode( $json, true );
	if ( ! is_array( $a ) || empty( $a['titolo'] ) || empty( $a['contenuto_html'] ) ) {
		return new WP_Error( 'portfolio_gemini', 'La risposta di Gemini non è un articolo valido.' );
	}
	return $a;
}

function portfolio_copertina_png( $titolo, $etichetta, $bold, $medium, $seme, $base = '' ) {
	$w = 1600; $h = 900;
	mt_srand( crc32( $seme ) );
	// Immagine di base scelta in Blog automatico: ritagliata a 16:9 (dal centro), con un velo scuro dove va il testo.
	$foto = $base && is_readable( $base ) ? @imagecreatefromstring( (string) file_get_contents( $base ) ) : false; // phpcs:ignore
	if ( $foto ) {
		$fw = imagesx( $foto ); $fh = imagesy( $foto );
		$scala = max( $w / $fw, $h / $fh );
		$sw = (int) round( $w / $scala ); $sh = (int) round( $h / $scala );
		$im = imagecreatetruecolor( $w, $h );
		imagecopyresampled( $im, $foto, 0, 0, (int) ( ( $fw - $sw ) / 2 ), (int) ( ( $fh - $sh ) / 2 ), $w, $h, $sw, $sh );
		imagedestroy( $foto );
		imagealphablending( $im, true );
		for ( $x = 0; $x < $w; $x += 4 ) {
			$a = (int) max( 0, min( 127, 127 - ( 1 - $x / ( $w * 0.85 ) ) * 115 ) );
			imagefilledrectangle( $im, $x, 0, $x + 3, $h, imagecolorallocatealpha( $im, 8, 8, 12, $a ) );
		}
		imagefilledrectangle( $im, 0, 0, $w, $h, imagecolorallocatealpha( $im, 8, 8, 12, 95 ) );
		return portfolio_copertina_testo( $im, $titolo, $etichetta, $bold, $medium );
	}
	// Sfondo: macchie di luce viola e fucsia disegnate in piccolo e ingrandite (sfumature morbide).
	$pw = 160; $ph = 90;
	$p = imagecreatetruecolor( $pw, $ph );
	$macchie = array(
		array( mt_rand( 95, 150 ), mt_rand( -10, 40 ), mt_rand( 55, 80 ), array( 97, 9, 250 ) ),
		array( mt_rand( 110, 165 ), mt_rand( 50, 100 ), mt_rand( 40, 65 ), array( 229, 0, 255 ) ),
		array( mt_rand( -20, 30 ), mt_rand( 60, 100 ), mt_rand( 35, 55 ), array( 80, 0, 244 ) ),
	);
	for ( $y = 0; $y < $ph; $y++ ) {
		for ( $x = 0; $x < $pw; $x++ ) {
			$c = array( 10, 10, 14 );
			foreach ( $macchie as $m ) {
				$d = sqrt( ( $x - $m[0] ) ** 2 + ( ( $y - $m[1] ) * 1.2 ) ** 2 ) / $m[2];
				$k = max( 0, 1 - $d ); $k = $k * $k * 0.75;
				for ( $i = 0; $i < 3; $i++ ) { $c[ $i ] += ( $m[3][ $i ] - $c[ $i ] ) * $k; }
			}
			imagesetpixel( $p, $x, $y, imagecolorallocate( $p, (int) $c[0], (int) $c[1], (int) $c[2] ) );
		}
	}
	$im = imagecreatetruecolor( $w, $h );
	imagecopyresampled( $im, $p, 0, 0, 0, 0, $w, $h, $pw, $ph );
	imagedestroy( $p );
	imagealphablending( $im, true );
	// Velo scuro a sinistra, dove sta il testo.
	for ( $x = 0; $x < $w; $x += 4 ) {
		$a = (int) max( 0, min( 127, 127 - ( 1 - $x / ( $w * 0.75 ) ) * 90 ) );
		imagefilledrectangle( $im, $x, 0, $x + 3, $h, imagecolorallocatealpha( $im, 8, 8, 12, $a ) );
	}
	// Stelle.
	for ( $i = 0; $i < 140; $i++ ) {
		$r = mt_rand( 1, 100 ) > 92 ? 2 : 1;
		imagefilledellipse( $im, mt_rand( 0, $w ), mt_rand( 0, $h ), $r * 2, $r * 2, imagecolorallocatealpha( $im, 255, 255, 255, mt_rand( 30, 100 ) ) );
	}
	// Anelli di un pianeta in basso a destra.
	$cx = 1330 + mt_rand( -60, 60 ); $cy = 640 + mt_rand( -60, 60 );
	imagesetthickness( $im, 3 );
	imagefilledellipse( $im, $cx, $cy, 230, 230, imagecolorallocatealpha( $im, 244, 240, 255, 112 ) );
	imageellipse( $im, $cx, $cy, 230, 230, imagecolorallocatealpha( $im, 244, 240, 255, 60 ) );
	imagearc( $im, $cx, $cy, 420, 110, 160, 380, imagecolorallocatealpha( $im, 240, 138, 255, 40 ) );
	return portfolio_copertina_testo( $im, $titolo, $etichetta, $bold, $medium );
}

/** Scrive etichetta, titolo e indirizzo del sito sulla copertina e la restituisce in PNG. */
function portfolio_copertina_testo( $im, $titolo, $etichetta, $bold, $medium ) {
	$h      = imagesy( $im );
	$bianco = imagecolorallocate( $im, 255, 255, 255 );
	$viola  = imagecolorallocate( $im, 196, 160, 255 );
	$x0 = 110;
	// Titolo: la dimensione si riduce finché sta in 4 righe.
	$maxw = 1000;
	foreach ( array( 84, 76, 68, 60, 54 ) as $size ) {
		$righe = array(); $riga = '';
		foreach ( preg_split( '/\s+/', trim( $titolo ) ) as $parola ) {
			$prova = $riga === '' ? $parola : $riga . ' ' . $parola;
			$b = imagettfbbox( $size, 0, $bold, $prova );
			if ( $b[2] - $b[0] > $maxw && $riga !== '' ) { $righe[] = $riga; $riga = $parola; } else { $riga = $prova; }
		}
		$righe[] = $riga;
		if ( count( $righe ) <= 4 ) break;
	}
	$alto = $size * 1.22;
	$y = (int) ( $h / 2 - ( count( $righe ) * $alto ) / 2 + $size * 0.9 + 30 );
	if ( $etichetta ) {
		imagettftext( $im, 24, 0, $x0, (int) ( $y - $size - 40 ), $viola, $medium, mb_strtoupper( $etichetta ) );
	}
	foreach ( $righe as $r ) {
		imagettftext( $im, $size, 0, $x0, (int) $y, $bianco, $bold, $r );
		$y += $alto;
	}
	imagefilledrectangle( $im, $x0, 780, $x0 + 90, 785, imagecolorallocate( $im, 229, 0, 255 ) );
	imagettftext( $im, 22, 0, $x0, 830, imagecolorallocatealpha( $im, 255, 255, 255, 40 ), $medium, 'gabrielevilardo.it' );
	ob_start(); imagepng( $im, null, 6 ); imagedestroy( $im );
	return ob_get_clean();
}

/** Scarica una volta i font della copertina (Space Grotesk, licenza OFL) nella cartella uploads. */
function portfolio_copertina_font( $peso ) {
	$dir  = trailingslashit( wp_upload_dir()['basedir'] ) . 'portfolio-font/';
	$file = $dir . 'SpaceGrotesk-' . $peso . '.ttf';
	if ( file_exists( $file ) && filesize( $file ) > 50000 ) {
		return $file;
	}
	wp_mkdir_p( $dir );
	$res = wp_remote_get( 'https://raw.githubusercontent.com/floriankarsten/space-grotesk/master/fonts/ttf/static/SpaceGrotesk-' . $peso . '.ttf', array( 'timeout' => 30 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return '';
	}
	file_put_contents( $file, wp_remote_retrieve_body( $res ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $file;
}

/** Copertina grafica gratuita (senza AI): sfondo spaziale nei colori del sito con il titolo dell'articolo. */
function portfolio_blog_copertina_grafica( $articolo, $post_id ) {
	if ( ! function_exists( 'imagettftext' ) ) {
		return new WP_Error( 'portfolio_copertina', 'Sul server manca la libreria GD con FreeType per creare la copertina.' );
	}
	$bold   = portfolio_copertina_font( 'Bold' );
	$medium = portfolio_copertina_font( 'Medium' );
	if ( ! $bold || ! $medium ) {
		return new WP_Error( 'portfolio_copertina', 'Font della copertina non scaricato.' );
	}
	$tag  = (array) ( $articolo['tag'] ?? array() );
	$base = (int) portfolio_blog_opzioni()['immagine_base'];
	$png  = portfolio_copertina_png( $articolo['titolo'], 'Blog' . ( $tag ? ' · ' . $tag[0] : '' ), $bold, $medium, $articolo['titolo'], $base ? (string) get_attached_file( $base ) : '' );
	return portfolio_blog_allega( base64_encode( $png ), 'image/png', $articolo, $post_id, sanitize_text_field( $articolo['titolo'] ) );
}

/** Crea l'immagine con Gemini e la carica nella libreria media. Restituisce l'ID allegato o WP_Error. */
function portfolio_blog_immagine( $articolo, $post_id ) {
	$o      = portfolio_blog_opzioni();
	$prompt = trim( $articolo['prompt_immagine'] ) . "\nStyle: " . $o['stile_immagine'] . "\nWide 16:9 blog cover. No text, no letters, no watermark, no logos.";
	$parti  = portfolio_gemini( $o['modello_immagine'], array(
		'contents'         => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $prompt ) ) ) ),
		'generationConfig' => array(
			'responseModalities' => array( 'TEXT', 'IMAGE' ),
			'imageConfig'        => array( 'aspectRatio' => '16:9' ),
		),
	), 180 );
	if ( is_wp_error( $parti ) ) {
		return $parti;
	}
	$img = null;
	foreach ( $parti as $p ) {
		$d = $p['inlineData'] ?? ( $p['inline_data'] ?? null );
		if ( $d && ! empty( $d['data'] ) ) {
			$img = $d;
		}
	}
	if ( ! $img ) {
		return new WP_Error( 'portfolio_gemini', 'Gemini non ha restituito un\'immagine.' );
	}
	$mime = $img['mimeType'] ?? ( $img['mime_type'] ?? 'image/png' );
	return portfolio_blog_allega( $img['data'], $mime, $articolo, $post_id, sanitize_text_field( $articolo['alt_immagine'] ?? $articolo['titolo'] ) );
}

/** Carica un'immagine (base64) nella libreria media, collegata all'articolo. */
function portfolio_blog_allega( $base64, $mime, $articolo, $post_id, $alt ) {
	$est  = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp' )[ $mime ] ?? 'png';
	$nome = sanitize_file_name( ( $articolo['slug'] ?: 'articolo' ) . '.' . $est );
	$file = wp_upload_bits( $nome, null, base64_decode( $base64 ) );
	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'portfolio_upload', $file['error'] );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = wp_insert_attachment( array(
		'post_mime_type' => $mime,
		'post_title'     => sanitize_text_field( $articolo['titolo'] ),
		'post_status'    => 'inherit',
	), $file['file'], $post_id );
	if ( is_wp_error( $id ) || ! $id ) {
		return is_wp_error( $id ) ? $id : new WP_Error( 'portfolio_upload', 'Allegato non creato.' );
	}
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return $id;
}

/** Genera un articolo completo. Restituisce l'ID dell'articolo o WP_Error. */
function portfolio_blog_genera() {
	if ( get_transient( 'portfolio_blog_in_corso' ) ) {
		return new WP_Error( 'portfolio_blog', 'Un articolo è già in preparazione.' );
	}
	set_transient( 'portfolio_blog_in_corso', 1, 10 * MINUTE_IN_SECONDS );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$o         = portfolio_blog_opzioni();
	$argomento = portfolio_blog_prossimo_argomento();
	$a         = portfolio_blog_testo( $argomento );
	if ( is_wp_error( $a ) ) {
		delete_transient( 'portfolio_blog_in_corso' );
		portfolio_blog_log( 'errore', $a->get_error_message() );
		return $a;
	}

	$id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_author'  => get_current_user_id() ?: (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 ),
		'post_title'   => sanitize_text_field( $a['titolo'] ),
		'post_name'    => sanitize_title( $a['slug'] ?: $a['titolo'] ),
		'post_excerpt' => sanitize_textarea_field( $a['estratto'] ),
		'post_content' => wp_kses_post( $a['contenuto_html'] ),
		'tags_input'   => array_map( 'sanitize_text_field', array_slice( (array) ( $a['tag'] ?? array() ), 0, 5 ) ),
	), true );
	if ( is_wp_error( $id ) ) {
		delete_transient( 'portfolio_blog_in_corso' );
		portfolio_blog_log( 'errore', $id->get_error_message() );
		return $id;
	}
	update_post_meta( $id, 'portfolio_meta_descrizione', sanitize_text_field( $a['meta_descrizione'] ) );
	update_post_meta( $id, 'portfolio_generato', $o['modello_testo'] );
	if ( $argomento ) {
		portfolio_blog_consuma_argomento( $argomento );
	}

	// Immagine: con Gemini (a pagamento) o copertina grafica gratuita; se Gemini non riesce si usa la grafica.
	$nota = '';
	$img  = 'gemini' === $o['immagine'] ? portfolio_blog_immagine( $a, $id ) : new WP_Error( 'portfolio_skip', '' );
	if ( is_wp_error( $img ) ) {
		if ( 'gemini' === $o['immagine'] ) {
			$nota = ' Immagine AI non creata (' . $img->get_error_message() . '): uso la copertina grafica.';
		}
		$img = portfolio_blog_copertina_grafica( $a, $id );
	}
	if ( is_wp_error( $img ) ) {
		$nota .= ' Immagine non creata: ' . $img->get_error_message();
	} else {
		set_post_thumbnail( $id, $img );
	}

	// Pubblicazione diretta solo con l'immagine pronta; altrimenti resta in bozza da completare.
	$pubblica = 'publish' === $o['stato'] && ! is_wp_error( $img );
	if ( $pubblica ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	}
	delete_transient( 'portfolio_blog_in_corso' );
	portfolio_blog_log( $nota ? 'attenzione' : 'ok', ( $pubblica ? 'Pubblicato: ' : 'Bozza pronta: ' ) . $a['titolo'] . '.' . $nota, $id );

	if ( $o['avvisa'] ) {
		$to = defined( 'PORTFOLIO_CONTACT_EMAIL' ) ? PORTFOLIO_CONTACT_EMAIL : get_option( 'admin_email' );
		wp_mail(
			$to,
			( $pubblica ? 'Nuovo articolo pubblicato: ' : 'Nuova bozza da rileggere: ' ) . $a['titolo'],
			( $pubblica ? "L'articolo è online:\n" . portfolio_site_url() . '/blog/' . get_post_field( 'post_name', $id ) . "/\n\n" : "L'articolo è in bozza: rileggilo e pubblicalo quando sei pronto.\n\n" )
			. 'Modifica: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" . $nota
		);
	}
	return $id;
}

// Meta description degli articoli: visibile e modificabile nell'editor (riquadro "Campi personalizzati") e nella REST API.
add_action( 'init', function () {
	register_post_meta( 'post', 'portfolio_meta_descrizione', array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'portfolio_seo_post', 'Meta description (Google)', function ( $post ) {
		wp_nonce_field( 'portfolio_seo_post', 'portfolio_seo_post_nonce' );
		$v = get_post_meta( $post->ID, 'portfolio_meta_descrizione', true );
		echo '<textarea name="portfolio_meta_descrizione" rows="3" style="width:100%" maxlength="170">' . esc_textarea( $v ) . '</textarea>';
		echo '<p class="description">Circa 150 caratteri. Se vuota si usa il riassunto.</p>';
	}, 'post', 'side' );
} );

add_action( 'save_post_post', function ( $id ) {
	if ( ! isset( $_POST['portfolio_seo_post_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['portfolio_seo_post_nonce'] ), 'portfolio_seo_post' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	update_post_meta( $id, 'portfolio_meta_descrizione', sanitize_text_field( wp_unslash( $_POST['portfolio_meta_descrizione'] ?? '' ) ) );
} );

add_action( 'admin_menu', function () {
	$hook = add_submenu_page( 'edit.php', 'Blog automatico', 'Blog automatico', 'manage_options', 'portfolio-blog', 'portfolio_blog_pagina' );
	add_action( 'load-' . $hook, function () {
		wp_enqueue_media();
	} );
} );

add_action( 'admin_post_portfolio_blog_salva', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permesso negato.' );
	}
	check_admin_referer( 'portfolio_blog_salva' );
	$in  = wp_unslash( $_POST );
	$def = portfolio_blog_opzioni();
	$o   = array(
		'attivo'           => empty( $in['attivo'] ) ? 0 : 1,
		'frequenza'        => isset( portfolio_blog_frequenze()[ $in['frequenza'] ?? '' ] ) ? $in['frequenza'] : $def['frequenza'],
		'stato'            => ( $in['stato'] ?? '' ) === 'publish' ? 'publish' : 'draft',
		'argomenti'        => sanitize_textarea_field( $in['argomenti'] ?? '' ),
		'pubblico'         => sanitize_textarea_field( $in['pubblico'] ?? '' ),
		'tono'             => sanitize_textarea_field( $in['tono'] ?? '' ),
		'parole'           => max( 400, min( 2500, (int) ( $in['parole'] ?? 1000 ) ) ),
		'stile_immagine'   => sanitize_textarea_field( $in['stile_immagine'] ?? '' ),
		'modello_testo'    => sanitize_text_field( $in['modello_testo'] ?? '' ) ?: $def['modello_testo'],
		'modello_immagine' => sanitize_text_field( $in['modello_immagine'] ?? '' ) ?: $def['modello_immagine'],
		'avvisa'           => empty( $in['avvisa'] ) ? 0 : 1,
		'immagine'         => ( $in['immagine'] ?? '' ) === 'gemini' ? 'gemini' : 'grafica',
		'immagine_base'    => wp_attachment_is_image( (int) ( $in['immagine_base'] ?? 0 ) ) ? (int) $in['immagine_base'] : 0,
	);
	update_option( 'portfolio_blog', $o );
	portfolio_blog_pianifica();
	$msg = 'salvato';
	if ( ! empty( $in['genera_ora'] ) ) {
		$r   = portfolio_blog_genera();
		$msg = is_wp_error( $r ) ? 'errore' : 'generato';
	}
	wp_safe_redirect( admin_url( 'edit.php?page=portfolio-blog&esito=' . $msg ) );
	exit;
} );

function portfolio_blog_pagina() {
	$o      = portfolio_blog_opzioni();
	$chiave = defined( 'PORTFOLIO_GEMINI_API_KEY' ) && PORTFOLIO_GEMINI_API_KEY;
	$next   = wp_next_scheduled( PORTFOLIO_BLOG_HOOK );
	$log    = get_option( 'portfolio_blog_log', array() );
	$esito  = sanitize_key( $_GET['esito'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$fmt    = get_option( 'date_format' ) . ' H:i';
	?>
	<div class="wrap">
		<h1>Blog automatico</h1>
		<?php if ( 'salvato' === $esito ) : ?><div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div><?php endif; ?>
		<?php if ( 'generato' === $esito ) : ?><div class="notice notice-success is-dismissible"><p>Articolo creato. Lo trovi in cima al registro qui sotto.</p></div><?php endif; ?>
		<?php if ( 'copertine' === $esito ) : ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html( $log[0]['messaggio'] ?? '' ); ?></p></div><?php endif; ?>
		<?php if ( 'errore' === $esito ) : ?><div class="notice notice-error is-dismissible"><p>Articolo non creato: <?php echo esc_html( $log[0]['messaggio'] ?? '' ); ?></p></div><?php endif; ?>
		<?php if ( ! $chiave ) : ?>
			<div class="notice notice-warning"><p>Per usare il blog automatico aggiungi in <code>wp-config.php</code> la riga <code>define( 'PORTFOLIO_GEMINI_API_KEY', 'la-tua-chiave' );</code> con la chiave creata su Google AI Studio.</p></div>
		<?php endif; ?>
		<p>Gemini scrive l'articolo e crea l'immagine in evidenza. Con la pubblicazione il sito si aggiorna da solo.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="portfolio_blog_salva">
			<?php wp_nonce_field( 'portfolio_blog_salva' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Automatico</th><td><label><input type="checkbox" name="attivo" value="1" <?php checked( $o['attivo'] ); ?>> Crea articoli da solo</label>
					<p class="description"><?php echo $next ? 'Prossimo articolo: ' . esc_html( wp_date( $fmt, $next ) ) : 'Nessun articolo in programma.'; ?></p></td></tr>
				<tr><th scope="row"><label for="pb-frequenza">Frequenza</label></th><td><select id="pb-frequenza" name="frequenza">
					<?php foreach ( portfolio_blog_frequenze() as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['frequenza'], $k ); ?>><?php echo esc_html( $v ); ?></option><?php endforeach; ?>
				</select></td></tr>
				<tr><th scope="row">Quando è pronto</th><td>
					<label><input type="radio" name="stato" value="draft" <?php checked( $o['stato'], 'draft' ); ?>> Salva in bozza, lo rileggo e pubblico io (consigliato)</label><br>
					<label><input type="radio" name="stato" value="publish" <?php checked( $o['stato'], 'publish' ); ?>> Pubblica subito</label><br>
					<label><input type="checkbox" name="avvisa" value="1" <?php checked( $o['avvisa'] ); ?>> Avvisami via email</label></td></tr>
				<tr><th scope="row"><label for="pb-argomenti">Argomenti</label></th><td><textarea id="pb-argomenti" name="argomenti" rows="6" class="large-text"><?php echo esc_textarea( $o['argomenti'] ); ?></textarea>
					<p class="description">Uno per riga, usati dall'alto verso il basso e tolti dall'elenco dopo l'uso. Se l'elenco è vuoto l'argomento lo sceglie Gemini, legato ai tuoi servizi e diverso dagli articoli già scritti.</p></td></tr>
				<tr><th scope="row"><label for="pb-pubblico">A chi scrivi</label></th><td><textarea id="pb-pubblico" name="pubblico" rows="2" class="large-text"><?php echo esc_textarea( $o['pubblico'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="pb-tono">Tono</label></th><td><textarea id="pb-tono" name="tono" rows="2" class="large-text"><?php echo esc_textarea( $o['tono'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="pb-parole">Lunghezza</label></th><td><input id="pb-parole" type="number" name="parole" min="400" max="2500" step="100" value="<?php echo esc_attr( $o['parole'] ); ?>"> parole circa</td></tr>
				<tr><th scope="row">Immagine in evidenza</th><td>
					<label><input type="radio" name="immagine" value="grafica" <?php checked( $o['immagine'], 'grafica' ); ?>> Copertina grafica con il titolo, nei colori del sito (gratuita)</label><br>
					<label><input type="radio" name="immagine" value="gemini" <?php checked( $o['immagine'], 'gemini' ); ?>> Illustrazione creata da Gemini (serve la fatturazione attiva o i crediti Google Cloud)</label>
					<p class="description">Se Gemini non riesce a creare l'immagine si usa la copertina grafica.</p></td></tr>
				<tr><th scope="row">Immagine di base</th><td>
					<input type="hidden" name="immagine_base" id="pb-base" value="<?php echo esc_attr( $o['immagine_base'] ); ?>">
					<div id="pb-base-anteprima" style="margin-bottom:8px"><?php echo $o['immagine_base'] ? wp_get_attachment_image( $o['immagine_base'], 'medium', false, array( 'style' => 'max-width:320px;height:auto;border-radius:6px' ) ) : ''; ?></div>
					<button type="button" class="button" id="pb-base-scegli">Scegli immagine</button>
					<button type="button" class="button-link" id="pb-base-togli" <?php echo $o['immagine_base'] ? '' : 'hidden'; ?>>Togli</button>
					<p class="description">Facoltativa. La copertina grafica usa questa immagine come sfondo, con un velo scuro e il titolo sopra. Va bene una 1600×900 o più grande: viene ritagliata a 16:9 dal centro. Senza immagine si usa lo sfondo spaziale.</p>
					<script>
						jQuery( function ( $ ) {
							var cornice;
							$( '#pb-base-scegli' ).on( 'click', function () {
								cornice = cornice || wp.media( { title: 'Immagine di base delle copertine', library: { type: 'image' }, multiple: false, button: { text: 'Usa questa immagine' } } );
								cornice.off( 'select' ).on( 'select', function () {
									var a = cornice.state().get( 'selection' ).first().toJSON();
									$( '#pb-base' ).val( a.id );
									var u = ( a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url );
									$( '#pb-base-anteprima' ).html( $( '<img>' ).attr( 'src', u ).css( { maxWidth: '320px', height: 'auto', borderRadius: '6px' } ) );
									$( '#pb-base-togli' ).prop( 'hidden', false );
								} );
								cornice.open();
							} );
							$( '#pb-base-togli' ).on( 'click', function () {
								$( '#pb-base' ).val( 0 );
								$( '#pb-base-anteprima' ).empty();
								$( this ).prop( 'hidden', true );
							} );
						} );
					</script></td></tr>
				<tr><th scope="row"><label for="pb-stile">Stile immagine AI</label></th><td><textarea id="pb-stile" name="stile_immagine" rows="2" class="large-text"><?php echo esc_textarea( $o['stile_immagine'] ); ?></textarea></td></tr>
				<tr><th scope="row">Modelli Gemini</th><td>
					<label>Testo <input type="text" name="modello_testo" value="<?php echo esc_attr( $o['modello_testo'] ); ?>" class="regular-text"></label><br>
					<label>Immagine <input type="text" name="modello_immagine" value="<?php echo esc_attr( $o['modello_immagine'] ); ?>" class="regular-text"></label>
					<p class="description">Da cambiare solo se Google ritira un modello.</p></td></tr>
			</table>
			<p class="submit">
				<?php submit_button( 'Salva', 'primary', 'salva', false ); ?>
				<?php submit_button( 'Salva e genera un articolo ora', 'secondary', 'genera_ora', false, $chiave ? array( 'onclick' => "this.value='Sto scrivendo… (1-2 minuti)'" ) : array( 'disabled' => 'disabled' ) ); ?>
			</p>
		</form>
		<h2>Copertine mancanti</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="portfolio_blog_copertine">
			<?php wp_nonce_field( 'portfolio_blog_copertine' ); ?>
			<p>Gli articoli senza immagine in evidenza ricevono la copertina grafica quando li pubblichi. Con questo pulsante la crei subito per tutti, bozze comprese.</p>
			<?php submit_button( 'Crea le copertine mancanti', 'secondary', 'copertine', false ); ?>
		</form>
		<?php if ( $log ) : ?>
			<h2>Ultime esecuzioni</h2>
			<table class="widefat striped"><thead><tr><th>Quando</th><th>Esito</th><th>Dettagli</th></tr></thead><tbody>
			<?php foreach ( $log as $r ) : ?>
				<tr><td><?php echo esc_html( wp_date( $fmt, $r['quando'] ) ); ?></td><td><?php echo esc_html( array( 'ok' => 'Fatto', 'attenzione' => 'Da completare', 'errore' => 'Errore' )[ $r['esito'] ] ?? $r['esito'] ); ?></td>
				<td><?php echo esc_html( $r['messaggio'] ); ?><?php if ( $r['post'] && get_post( $r['post'] ) ) : ?> <a href="<?php echo esc_url( get_edit_post_link( $r['post'] ) ); ?>">Apri</a><?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}

/* Articoli senza immagine in evidenza (scritti a mano o creati prima della copertina grafica):
 * la copertina si crea da sola alla pubblicazione, oppure per tutti con il pulsante in Blog automatico. */
function portfolio_blog_copertina_per_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || has_post_thumbnail( $post ) ) {
		return false;
	}
	$tag = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
	$img = portfolio_blog_copertina_grafica( array(
		'titolo' => get_the_title( $post ),
		'slug'   => $post->post_name ?: sanitize_title( $post->post_title ),
		'tag'    => is_array( $tag ) ? $tag : array(),
	), $post->ID );
	if ( is_wp_error( $img ) ) {
		return $img;
	}
	set_post_thumbnail( $post->ID, $img );
	return true;
}

// wp_after_insert_post: dopo che l'editor ha salvato anche l'immagine in evidenza scelta (o nessuna).
add_action( 'wp_after_insert_post', function ( $post_id, $post ) {
	if ( 'publish' === $post->post_status && 'post' === $post->post_type && ! wp_is_post_revision( $post ) && trim( $post->post_title ) !== '' && ! has_post_thumbnail( $post ) ) {
		if ( true === portfolio_blog_copertina_per_post( $post_id ) ) {
			portfolio_rebuild_later();
		}
	}
}, 10, 2 );

add_action( 'admin_post_portfolio_blog_copertine', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permesso negato.' );
	}
	check_admin_referer( 'portfolio_blog_copertine' );
	$fatti = 0;
	$errore = '';
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'numberposts' => 100, 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) ) as $p ) {
		$r = portfolio_blog_copertina_per_post( $p->ID );
		if ( true === $r ) {
			$fatti++;
		} elseif ( is_wp_error( $r ) ) {
			$errore = $r->get_error_message();
			break;
		}
	}
	portfolio_blog_log( $errore ? 'errore' : 'ok', 'Copertine create: ' . $fatti . '.' . ( $errore ? ' ' . $errore : '' ) );
	if ( $fatti ) {
		portfolio_rebuild_later();
	}
	wp_safe_redirect( admin_url( 'edit.php?page=portfolio-blog&esito=copertine' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * Ordine del menu (Testi del sito → Ordine del menu)
 * Le voci si trascinano nell'ordine voluto e si possono nascondere. Vale per header e footer.
 * GET /wp-json/portfolio/v1/menu: { ordine: [chiavi], nascoste: [chiavi] }
 * ---------------------------------------------------------------------- */
function portfolio_menu_voci() {
	return array(
		'home'     => 'Home',
		'servizi'  => 'Servizi',
		'progetti' => 'Progetti',
		'chisono'  => 'Chi sono',
		'blog'     => 'Blog',
		'contatti' => 'Contatti',
	);
}

function portfolio_menu_data() {
	$voci     = array_keys( portfolio_menu_voci() );
	$salvato  = get_option( 'portfolio_menu', array() );
	$ordine   = array_values( array_intersect( (array) ( $salvato['ordine'] ?? array() ), $voci ) );
	// Voci nuove (o mai ordinate) in fondo, nell'ordine predefinito.
	$ordine   = array_merge( $ordine, array_values( array_diff( $voci, $ordine ) ) );
	$nascoste = array_values( array_intersect( (array) ( $salvato['nascoste'] ?? array() ), $voci ) );
	return array( 'ordine' => $ordine, 'nascoste' => $nascoste );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'portfolio/v1', '/menu', array(
		'methods'             => 'GET',
		'callback'            => 'portfolio_menu_data',
		'permission_callback' => '__return_true',
	) );
} );

add_action( 'admin_menu', function () {
	$hook = add_submenu_page( 'portfolio-testi', 'Ordine del menu', 'Ordine del menu', 'edit_posts', 'portfolio-menu-ordine', 'portfolio_menu_pagina', 99 );
	add_action( 'load-' . $hook, function () {
		wp_enqueue_script( 'jquery-ui-sortable' );
	} );
}, 100 ); // dopo ACF, che crea "Testi del sito"

add_action( 'admin_post_portfolio_menu_salva', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Permesso negato.' );
	}
	check_admin_referer( 'portfolio_menu_salva' );
	$voci     = array_keys( portfolio_menu_voci() );
	$ordine   = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['ordine'] ?? array() ) ), $voci ) );
	$visibili = array_map( 'sanitize_key', (array) wp_unslash( $_POST['visibile'] ?? array() ) );
	update_option( 'portfolio_menu', array( 'ordine' => $ordine, 'nascoste' => array_values( array_diff( $voci, $visibili ) ) ) );
	portfolio_rebuild_later();
	wp_safe_redirect( admin_url( 'admin.php?page=portfolio-menu-ordine&salvato=1' ) );
	exit;
} );

function portfolio_menu_pagina() {
	$d     = portfolio_menu_data();
	$nomi  = portfolio_menu_voci();
	$label = function ( $k ) use ( $nomi ) {
		$v = get_option( 'options_generale_menu_' . $k, '' );
		return is_string( $v ) && trim( $v ) !== '' ? trim( $v ) : $nomi[ $k ];
	};
	?>
	<div class="wrap">
		<h1>Ordine del menu</h1>
		<?php if ( ! empty( $_GET['salvato'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Ordine salvato: il sito si aggiorna tra un paio di minuti.</p></div>
		<?php endif; ?>
		<p>Trascina le voci nell'ordine che vuoi. Togli la spunta per nascondere una voce. Vale per il menu in alto e per il footer. I nomi delle voci si cambiano in Testi del sito → Generale → Menu.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="portfolio_menu_salva">
			<?php wp_nonce_field( 'portfolio_menu_salva' ); ?>
			<ul id="portfolio-menu-voci">
				<?php foreach ( $d['ordine'] as $k ) : ?>
					<li>
						<span class="dashicons dashicons-menu" aria-hidden="true"></span>
						<input type="hidden" name="ordine[]" value="<?php echo esc_attr( $k ); ?>">
						<label><input type="checkbox" name="visibile[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( ! in_array( $k, $d['nascoste'], true ) ); ?>> <?php echo esc_html( $label( $k ) ); ?></label>
						<?php if ( 'blog' === $k ) : ?><span class="description">(solo con almeno un articolo pubblicato)</span><?php endif; ?>
						<span class="sposta">
							<button type="button" class="button-link su" aria-label="Sposta su">▲</button>
							<button type="button" class="button-link giu" aria-label="Sposta giù">▼</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php submit_button( 'Salva l\'ordine' ); ?>
		</form>
	</div>
	<style>
		#portfolio-menu-voci { max-width: 32rem; margin: 1.5rem 0; }
		#portfolio-menu-voci li { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; margin: 0 0 .5rem; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; cursor: move; }
		#portfolio-menu-voci li.ui-sortable-placeholder { visibility: visible !important; background: #f0f0f1; border-style: dashed; }
		#portfolio-menu-voci label { font-weight: 600; }
		#portfolio-menu-voci .sposta { margin-left: auto; display: flex; gap: .25rem; }
		#portfolio-menu-voci .sposta button { padding: 2px 6px; color: #50575e; cursor: pointer; }
	</style>
	<script>
		jQuery( function ( $ ) {
			var lista = $( '#portfolio-menu-voci' );
			lista.sortable( { axis: 'y', placeholder: 'ui-sortable-placeholder', forcePlaceholderSize: true } );
			lista.on( 'click', '.su', function () { var li = $( this ).closest( 'li' ); li.prev().before( li ); } );
			lista.on( 'click', '.giu', function () { var li = $( this ).closest( 'li' ); li.next().after( li ); } );
		} );
	</script>
	<?php
}
