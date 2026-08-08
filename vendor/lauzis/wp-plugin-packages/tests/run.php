<?php
/**
 * Test suite for lauzis/wp-plugin-packages.
 *
 * Dependency-free, like the package itself: pulling in PHPUnit would push that
 * resolution onto every consuming plugin. The WordPress functions the library
 * touches are stubbed below.
 */

use Lauzis\WpPackages\Notices\Notice;

define( 'WP_PLUGIN_DIR', '/srv/wp/wp-content/plugins' );
define( 'WP_CONTENT_DIR', '/srv/wp/wp-content' );

$base = sys_get_temp_dir() . '/wp-packages-tests-' . getmypid();
if ( is_dir( $base ) ) {
	exec( 'rm -rf ' . escapeshellarg( $base ) );
}

$GLOBALS['test_base'] = $base;
$GLOBALS['options']   = array();
$GLOBALS['user_meta'] = array();
$GLOBALS['hooks']     = array();
$GLOBALS['enqueued']  = array();
$GLOBALS['localized'] = array();
$GLOBALS['user_id']   = 1;
$GLOBALS['caps']      = true;

function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['test_base'] . '/uploads' ); }
function plugins_url() { return 'https://example.test/wp-content/plugins'; }
function content_url() { return 'https://example.test/wp-content'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function did_action( $hook ) { return 0; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_user_meta( $u, $k, $single = false ) { return $GLOBALS['user_meta'][ $u ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $u, $k, $v ) { $GLOBALS['user_meta'][ $u ][ $k ] = $v; return true; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function current_user_can( $c ) { return $GLOBALS['caps']; }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function __( $s, $d = 'default' ) { $GLOBALS['translated'][] = array( $s, $d ); return $s; }
// Approximates wp_kses_post()'s allow-list: block and inline markup through, scripts out.
function wp_kses_post( $s ) { return strip_tags( $s, '<a><strong><em><code><br><p><ul><ol><li><span><h2><h3>' ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_create_nonce( $a ) { return 'nonce-' . $a; }
function wp_enqueue_style( $h, $src = '', $d = array(), $v = null ) { $GLOBALS['enqueued'][ $h ] = $src; }
function wp_enqueue_script( $h, $src = '', $d = array(), $v = null, $f = false ) { $GLOBALS['enqueued'][ $h ] = $src; }
function wp_localize_script( $h, $name, $data ) { $GLOBALS['localized'][ $name ] = $data; }
function check_ajax_referer( $action, $field = false ) { return true; }
$GLOBALS['transients'] = array();
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ $h ] ?? ''; }

// Filters, enough of them for the components that use one.
$GLOBALS['filters'] = array();
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['filters'][ $hook ][] = $cb; }
function has_filter( $hook, $cb = false ) { return ! empty( $GLOBALS['filters'][ $hook ] ); }
function remove_all_filters( $hook ) { unset( $GLOBALS['filters'][ $hook ] ); }
function apply_filters( $hook, $value ) {
	$extra = array_slice( func_get_args(), 2 );
	foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $cb ) {
		$value = call_user_func_array( $cb, array_merge( array( $value ), $extra ) );
	}
	return $value;
}
$GLOBALS['locale']      = 'en_US';
$GLOBALS['site_locale'] = 'en_US';
function determine_locale() { return $GLOBALS['locale']; }
function get_locale() { return $GLOBALS['site_locale']; }

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function add_query_arg( $k, $v, $url ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $k . '=' . rawurlencode( $v ); }
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['http'][] = array( 'url' => $url, 'args' => $args );
	$next = array_shift( $GLOBALS['http_responses'] );
	return null === $next ? new WP_Error( 'http', 'no canned response' ) : $next;
}
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 200; }

/** Models wp_send_json_*(), which terminate the request. */
class WpJsonHalt extends Exception {}
function wp_send_json_error( $message = '', $code = null ) { throw new WpJsonHalt( is_string( $message ) ? $message : 'error' ); }
function wp_send_json_success( $data = null ) { throw new WpJsonHalt( 'success' ); }

require __DIR__ . '/fake-carbon-fields.php';
// Required explicitly, exactly as a consuming plugin must — see Registry.
require dirname( __DIR__ ) . '/bootstrap.php';

$pass = 0;
$fail = 0;

function check( $label, $got, $want ) {
	global $pass, $fail;

	if ( $got === $want ) {
		$pass++;
		echo "  ok   $label\n";

		return;
	}

	$fail++;
	echo "  FAIL $label\n";
	echo "         expected: " . var_export( $want, true ) . "\n";
	echo "         actual:   " . var_export( $got, true ) . "\n";
}

function section( $title ) {
	echo "\n$title\n";
}

function render( $notices ) {
	ob_start();
	$notices->render();

	return ob_get_clean();
}

/**
 * Invokes the dismissal handler the way admin-ajax would, returning whatever
 * the handler responded with instead of terminating.
 */
function dismiss( $notices, $id ) {
	$_POST['notification_id'] = $id;

	try {
		$notices->handle_dismiss();
	} catch ( WpJsonHalt $e ) {
		return $e->getMessage();
	}

	return null;
}

$today = gmdate( 'Y-m-d' );

// =========================================================== Logs component ==
$enabled = false;
$log     = WpPackages_Registry::logger( 'demo', array( 'enabled' => function () use ( &$enabled ) { return $enabled; } ) );

// ------------------------------------------------------------ enable gating --
echo "enable gating\n";
check( 'add() is a no-op while disabled', $log->add( 'boot', 'nope' ), false );
check( 'no directory created while disabled', is_dir( $log->dir() ), false );

$enabled = true;
check( 'enabling takes effect without rebuilding the logger', $log->add( 'cron', 'Batch finished.', array( 'processed' => 12 ) ), true );

// ------------------------------------------------------------------ format --
echo "format\n";
$file = $log->dir() . 'demo-' . $today . '.log';
check( 'file is {channel}-{date}.log', file_exists( $file ), true );
check(
	'line is [ts] [action] message | {json}',
	(bool) preg_match( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[cron\] Batch finished\. \| \{"processed":12\}$/', trim( file_get_contents( $file ) ) ),
	true
);

$log->add( '', 'no action label', array(), 'audit' );
$audit = file( $log->dir() . 'audit-' . $today . '.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
check(
	'empty action omits the action segment',
	(bool) preg_match( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] no action label$/', end( $audit ) ),
	true
);

$log->add( 'x', 'no context' );
$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
check( 'empty context omits the pipe', strpos( end( $lines ), '|' ), false );

// --------------------------------------------------------------- hardening --
echo "hardening\n";
check( 'index.php dropped in the log dir', file_exists( $log->dir() . 'index.php' ), true );
check( '.htaccess dropped in the log dir', file_exists( $log->dir() . '.htaccess' ), true );

$log->add( 'x', 'traversal', array(), '../../escaped' );
check( 'traversal channel is confined to the log dir', file_exists( $log->dir() . 'escaped-' . $today . '.log' ), true );
check( 'nothing was written above the log dir', glob( dirname( rtrim( $log->dir(), '/' ) ) . '/escaped-*.log' ), array() );

// ------------------------------------------------------ counting and listing --
echo "counting and listing\n";
check( 'count() counts entries, not files', $log->count(), 2 );
check( 'count() is per channel', $log->count( 'audit' ), 1 );

$meta = $log->files();
check( 'files() returns one file for the default channel', count( $meta ), 1 );
check( 'files() keys', array_keys( $meta[0] ), array( 'file', 'name', 'date', 'count' ) );
check( 'files() reports the date', $meta[0]['date'], $today );
check( 'files() reports the entry count', $meta[0]['count'], 2 );
check( 'files("*") spans every channel', count( $log->files( '*' ) ), 3 );

check( 'read() returns newest entry first', (bool) preg_match( '/no context$/', $log->read()[0] ), true );
check( 'read() honours the limit', count( $log->read( null, 1 ) ), 1 );

// ------------------------------------------------------------------- error --
echo "error()\n";
$enabled = false;
$log->error( 'send', 'unconditional' );
check( 'error() does not write to file while disabled', substr_count( file_get_contents( $file ), 'unconditional' ), 0 );

$enabled = true;
$log->error( 'send', 'unconditional' );
check( 'error() writes to file while enabled', substr_count( file_get_contents( $file ), 'unconditional' ), 1 );

// ------------------------------------------------------------------- clear --
echo "clear()\n";
$log->clear();
check( 'clear() empties the default channel', file_exists( $file ), false );
check( 'clear() leaves other channels alone', file_exists( $log->dir() . 'audit-' . $today . '.log' ), true );

$log->clear( '*' );
check( 'clear("*") empties every channel', count( $log->files( '*' ) ), 0 );

// ------------------------------------------------------------------ config --
echo "config\n";
$custom = WpPackages_Registry::logger( 'custom', array( 'dir' => $base . '/elsewhere', 'enabled' => true ) );
check( 'a trailing slash is added to dir', substr( $custom->dir(), -1 ), '/' );
$custom->add( 'a', 'b' );
check( 'writes to the configured dir', file_exists( $base . '/elsewhere/custom-' . $today . '.log' ), true );

$defaulted = WpPackages_Registry::logger( 'defaulted', array( 'enabled' => true ) );
check( 'dir defaults to uploads/{slug}-logs/', $defaulted->dir(), $base . '/uploads/defaulted-logs/' );


// ======================================================== Notices component ==
$always = function () { return true; };
$n      = WpPackages_Registry::notices( 'splecheh', array( 'screen' => $always ) );

echo "render\n";
$n->add( new Notice( 'missing-lib', 'The spell-check library is <strong>missing</strong>.', 'error', Notice::ONCE ) );
$html = render( $n );
check( 'renders a WordPress notice', (bool) strpos( $html, 'class="notice notice-error wp-notices-notice"' ), true );

$html = render( $n );
check( 'renders a WordPress notice', (bool) strpos( $html, 'class="notice notice-error wp-notices-notice"' ), true );
check( 'carries the id',   (bool) strpos( $html, 'data-wp-notices-id="missing-lib"' ), true );
check( 'carries the mode', (bool) strpos( $html, 'data-wp-notices-mode="once"' ), true );
check( 'keeps safe markup', (bool) strpos( $html, '<strong>missing</strong>' ), true );
check( 'renders a dismiss button', (bool) strpos( $html, 'notice-dismiss' ), true );

$evil = WpPackages_Registry::notices( 'evil', array( 'screen' => $always ) );
$evil->add( new Notice( 'xss', 'ok <script>alert(1)</script>', 'info' ) );
check( 'strips script tags from the message', false === strpos( render( $evil ), '<script>' ), true );

$bad = new Notice( 'x', 'y', 'not-a-type', 'not-a-mode' );
check( 'unknown type falls back to info', $bad->type, 'info' );
check( 'unknown mode falls back to once', $bad->mode, 'once' );

// ------------------------------------------------------------------ scoping --
echo "screen scoping\n";
$scoped = WpPackages_Registry::notices( 'scoped', array( 'screen' => function () { return false; } ) );
$scoped->add( new Notice( 'hidden', 'should not render' ) );
check( 'renders nothing off-screen', render( $scoped ), '' );

$GLOBALS['get'] = array();
$dflt = WpPackages_Registry::notices( 'mawiblah' );
$dflt->add( new Notice( 'setup', 'setup needed' ) );
check( 'default scoping hides notices with no page param', render( $dflt ), '' );
$_GET['page'] = 'mawiblah-settings';
check( 'default scoping shows on the plugin page', (bool) strpos( render( $dflt ), 'setup needed' ), true );
$_GET['page'] = 'some-other-plugin';
check( 'default scoping hides on other pages', render( $dflt ), '' );
unset( $_GET['page'] );

// ----------------------------------------------------------- dismissal: once --
echo "dismissal — option store, once\n";
check( 'dismissal succeeds', dismiss( $n, 'missing-lib' ), 'success' );
check( 'dismissal saved to an option', isset( $GLOBALS['options']['splecheh_dismissed_notices']['missing-lib'] ), true );
check( 'not saved to user meta', isset( $GLOBALS['user_meta'][1]['splecheh_dismissed_notices'] ), false );
check( 'dismissed notice no longer renders', render( $n ), '' );

$n->reset();
check( 'reset() brings it back', (bool) strpos( render( $n ), 'missing-lib' ), true );

// -------------------------------------------------------- dismissal: version --
echo "dismissal — user store, per version\n";
$v = WpPackages_Registry::notices( 'mawiblah_v', array( 'store' => 'user', 'version' => '1.0.28', 'screen' => $always ) );
$v->add( new Notice( 'setup', 'setup needed', 'warning', Notice::VERSION ) );
check( 'renders before dismissal', (bool) strpos( render( $v ), 'setup needed' ), true );

check( 'dismissal succeeds', dismiss( $v, 'setup' ), 'success' );
check( 'dismissal saved to user meta', $GLOBALS['user_meta'][1]['mawiblah_v_dismissed_notices']['setup'], '1.0.28' );
check( 'not saved to an option', isset( $GLOBALS['options']['mawiblah_v_dismissed_notices'] ), false );
check( 'hidden for the dismissed version', render( $v ), '' );

$v2 = new \Lauzis\WpPackages\Notices\Notices( 'mawiblah_v', array( 'store' => 'user', 'version' => '1.0.29', 'screen' => $always ) );
$v2->add( new Notice( 'setup', 'setup needed', 'warning', Notice::VERSION ) );
check( 'shows again after a version bump', (bool) strpos( render( $v2 ), 'setup needed' ), true );

$GLOBALS['user_id'] = 2;
check( 'user-store dismissal does not leak to another user', (bool) strpos( render( $v ), 'setup needed' ), true );
$GLOBALS['user_id'] = 1;

// -------------------------------------------------------- dismissal: session --
echo "dismissal — session\n";
$s = WpPackages_Registry::notices( 'sessiony', array( 'screen' => $always ) );
$s->add( new Notice( 'transient', 'just this once', 'info', Notice::SESSION ) );
$GLOBALS['options']['sessiony_dismissed_notices'] = array( 'transient' => true );
check( 'session notices ignore stored dismissals', (bool) strpos( render( $s ), 'just this once' ), true );

// ------------------------------------------------------------------ security --
echo "security\n";
$GLOBALS['caps'] = false;
check( 'dismissal requires the capability', dismiss( $n, 'missing-lib' ), 'Insufficient permissions' );
$GLOBALS['caps'] = true;

check( 'empty notification id is rejected', dismiss( $n, '' ), 'Invalid notification ID' );

// -------------------------------------------------------------------- assets --
echo "assets\n";
$a = new \Lauzis\WpPackages\Notices\Assets( '/srv/wp/wp-content/plugins/splecheh/vendor/lauzis/wp-plugin-packages' );
check(
	'vendor path maps onto a plugin URL',
	$a->url( 'notices.css' ),
	'https://example.test/wp-content/plugins/splecheh/vendor/lauzis/wp-plugin-packages/assets/notices.css'
);

$mu = new \Lauzis\WpPackages\Notices\Assets( '/srv/wp/wp-content/mu-plugins/thing/vendor/lauzis/wp-plugin-packages' );
check(
	'paths outside the plugin dir fall back to content_url',
	$mu->url( 'toasts.css' ),
	'https://example.test/wp-content/mu-plugins/thing/vendor/lauzis/wp-plugin-packages/assets/toasts.css'
);

$override = new \Lauzis\WpPackages\Notices\Assets( '/anywhere', 'https://cdn.test/a' );
check( 'explicit assets_url wins', $override->url( 'notices.css' ), 'https://cdn.test/a/notices.css' );

$n->enqueue();
check( 'enqueues the stylesheet', isset( $GLOBALS['enqueued']['wp-notices'] ), true );
check( 'localises per-plugin config', $GLOBALS['localized']['wpNoticessplecheh']['action'], 'splecheh_dismiss_notice' );
check( 'nonce matches the action', $GLOBALS['localized']['wpNoticessplecheh']['nonce'], 'nonce-splecheh_dismiss_notice' );

WpPackages_Registry::toasts( 'rest-in-sync', array( 'timeout' => 3000 ) )->enqueue();
check( 'toast assets enqueued', isset( $GLOBALS['enqueued']['wp-notices-toasts'] ), true );
check( 'toast timeout is configurable', $GLOBALS['localized']['wpNoticesToastConfig']['timeout'], 3000 );

// ---------------------------------------------------------------------- boot --
echo "boot\n";
$b = WpPackages_Registry::notices( 'booty', array( 'screen' => $always ) );
$b->boot();
$b->boot();
check( 'admin_notices hooked once', count( $GLOBALS['hooks']['admin_notices'] ), 1 );
check( 'ajax handler hooked', isset( $GLOBALS['hooks']['wp_ajax_booty_dismiss_notice'] ), true );
check( 'slug dashes become underscores in hook names', ( WpPackages_Registry::notices( 'rest-in-sync' ) )->action(), 'rest_in_sync_dismiss_notice' );


// ======================================================= Settings component ==
echo "settings — schema loading\n";

use Lauzis\WpPackages\Settings\Schema;

$fixture = __DIR__ . '/fixtures/plugin.json';

$plugin = Schema::load( $fixture, array( 'prefix' => 'demo_', 'domain' => 'demo' ) );
check( 'tabs become sections', count( $plugin ), 2 );
check( 'section id is prefixed', $plugin[0]['id'], 'demo_general' );
check( 'section keeps its domain', $plugin[0]['domain'], 'demo' );
check( 'field id is prefixed', $plugin[0]['fields'][0]['id'], 'demo_post_types' );
check( 'bare id is retained', $plugin[0]['fields'][0]['bare'], 'post_types' );

$advanced = $plugin[1]['fields'];
$command  = $advanced[1];
check( 'conditional logic field ref is prefixed', $command['conditional_logic'][0]['field'], 'demo_mode' );
check( 'conditional logic gets a default compare', $command['conditional_logic'][0]['compare'], '=' );

$keywords = $advanced[3];
check( 'complex sub-fields are NOT prefixed', $keywords['fields'][0]['id'], 'keyword' );
check( 'nested complex recurses', $keywords['fields'][1]['fields'][0]['id'], 'variation' );

// map is applied before the prefix, so a legacy key survives adoption.
$mapped = Schema::load( $fixture, array( 'prefix' => 'maw-', 'map' => array( 'post_types' => 'legacy-types' ) ) );
check( 'map replaces the id before prefixing', $mapped[0]['fields'][0]['id'], 'maw-legacy-types' );
check( 'unmapped ids are untouched', $mapped[0]['fields'][1]['id'], 'maw-language' );

$bad = false;
try { Schema::load( __DIR__ . '/fixtures/nope.json' ); } catch ( \RuntimeException $e ) { $bad = true; }
check( 'a missing schema throws', $bad, true );

echo "settings — string walking\n";
$found = array();
Schema::walk_strings( $plugin, function ( $text, $domain ) use ( &$found ) { $found[ $text ] = $domain; } );
check( 'section titles collected', isset( $found['General'] ), true );
check( 'section descriptions collected', isset( $found['Core behaviour.'] ), true );
check( 'field titles collected', isset( $found['Batch Size'] ), true );
check( 'help text collected', isset( $found['Which post types to process.'] ), true );
check( 'option labels collected', isset( $found['Hosted API'] ), true );
check( 'html collected', isset( $found['<p>Careful.</p>'] ), true );
check( 'nested field titles collected', isset( $found['Variation'] ), true );
check( 'default values NOT collected', isset( $found['50'] ), false );
check( 'callback refs NOT collected', isset( $found['@callback:default_language'] ), false );
check( 'strings carry the fragment domain', $found['General'], 'demo' );

echo "settings — composition and rendering\n";
$s = WpPackages_Registry::settings( 'demo', array( 'title' => 'Demo Settings', 'page_parent' => 'demo-root' ) );
$s->callback( 'public_post_types', function () { return array( 'post' => 'Posts', 'page' => 'Pages' ); } );
$s->callback( 'default_language', function () { return 'lv'; } );
$s->register( $fixture, array( 'prefix' => 'demo_', 'domain' => 'demo' ) );
$s->register( dirname( __DIR__ ) . '/settings/logs.json', array( 'prefix' => 'demo_', 'domain' => 'wp-plugin-packages' ) );

check( 'fragments merge in order', count( $s->sections() ), 3 );
check( 'component section is last', $s->sections()[2]['id'], 'demo_logging' );
check( 'component section keeps the package domain', $s->sections()[2]['domain'], 'wp-plugin-packages' );
check( 'component field lands on the plugin key', $s->key( 'logs_enabled' ), 'demo_logs_enabled' );

$s->render();
$c = \Carbon_Fields\Container::$last;

check( 'container is theme_options', $c->type, 'theme_options' );
check( 'container title', $c->title, 'Demo Settings' );
check( 'page parent passed through', $c->page_parent, 'demo-root' );
check( 'one tab per section', count( $c->tabs ), 3 );
check( 'tab titles translated', $c->tabs[0]['title'], 'General' );

check( 'set field built', $c->find( 'demo_post_types' )->type, 'set' );
check( 'callback options resolved', $c->find( 'demo_post_types' )->options, array( 'post' => 'Posts', 'page' => 'Pages' ) );
check( 'help text applied', $c->find( 'demo_post_types' )->help_text, 'Which post types to process.' );
check( 'callback default resolved', $c->find( 'demo_language' )->default_value, 'lv' );
check( 'literal default kept', $c->find( 'demo_batch_size' )->default_value, '50' );
check( 'attributes applied', $c->find( 'demo_batch_size' )->attributes, array( 'type' => 'number', 'min' => '1' ) );

// Field-type-specific config, e.g. the wp_editor settings a rich_text field
// takes. Passed straight through so the schema does not need to know about
// every field type Carbon Fields offers.
check( 'settings passed through', $c->find( 'demo_intro' )->settings, array( 'media_buttons' => false ) );
check( 'no settings leaves it unset', $c->find( 'demo_batch_size' )->settings, null );
check( 'a non-array settings value is ignored', $c->find( 'demo_language' )->settings, null );
check( 'static options kept', $c->find( 'demo_mode' )->options, array( 'commandline' => 'Commandline', 'api' => 'Hosted API' ) );
check( 'conditional logic applied', $c->find( 'demo_command' )->conditional_logic[0]['field'], 'demo_mode' );
check( 'html field carries markup', $c->find( 'demo_notice' )->html, '<p>Careful.</p>' );
check( 'complex nests one level', count( $c->find( 'demo_keywords' )->children ), 2 );
check( 'complex nests two levels', $c->find( 'variation' )->type, 'text' );
check( 'section description becomes an html field', $c->find( 'demo_general_description' )->html, '<p>Core behaviour.</p>' );
check( 'component field rendered', $c->find( 'demo_logs_enabled' )->type, 'checkbox' );

echo "settings — flat mode\n";
$flat = new \Lauzis\WpPackages\Settings\Settings( 'flatty', array( 'title' => 'Flat', 'mode' => 'flat' ) );
$flat->register( dirname( __DIR__ ) . '/settings/logs.json', array( 'prefix' => 'flatty_', 'domain' => 'wp-plugin-packages' ) );
$flat->render();
$fc = \Carbon_Fields\Container::$last;
check( 'flat mode uses no tabs', count( $fc->tabs ), 0 );
check( 'flat mode emits a separator per section', $fc->fields[0]->type, 'separator' );
check( 'separator carries the section title', $fc->fields[0]->label, 'Logging' );

echo "settings — reading values\n";
$GLOBALS['options']['_demo_logs_enabled'] = '1';
check( 'get() resolves prefix and reads storage', $s->get( 'logs_enabled' ), '1' );
check( 'get() falls back to the schema default first', $s->get( 'batch_size' ), '50' );
check( 'default_for() exposes the schema default', $s->default_for( 'batch_size' ), '50' );
check( 'then to the caller default when the schema has none', $s->get( 'language', 'fallback' ), 'lv' );
check( 'and to the caller default when neither exists', $s->get( 'command', 'none' ), 'none' );
check( 'get() returns default for unknown ids', $s->get( 'no_such_field', 'x' ), 'x' );
check( 'key() returns null for unknown ids', $s->key( 'no_such_field' ), null );

echo "settings — callback safety\n";
$orphan = new \Lauzis\WpPackages\Settings\Settings( 'orphan' );
check( 'unregistered callback resolves to null, not a fatal', $orphan->resolve( '@callback:missing' ), null );
check( 'literals pass through resolve()', $orphan->resolve( 'plain' ), 'plain' );
check( 'render() is idempotent', ( $s->render() === $s ), true );


echo "settings — defaults, sprintf args, html callbacks\n";
$ov = new \Lauzis\WpPackages\Settings\Settings( 'ov', array( 'title' => 'Ov' ) );
$ov->register( dirname( __DIR__ ) . '/settings/logs.json', array(
    'prefix'   => 'ov_',
    'domain'   => 'wp-plugin-packages',
    'defaults' => array( 'logs_enabled' => true ),
) );
$ov->render();
$oc = \Carbon_Fields\Container::$last;
check( 'defaults override the schema value', $oc->find( 'ov_logs_enabled' )->default_value, true );

$plain = new \Lauzis\WpPackages\Settings\Settings( 'plain', array( 'title' => 'Plain' ) );
$plain->register( dirname( __DIR__ ) . '/settings/logs.json', array( 'prefix' => 'plain_' ) );
$plain->render();
check( 'without an override the schema default stands', \Carbon_Fields\Container::$last->find( 'plain_logs_enabled' )->default_value, null );

$sp = new \Lauzis\WpPackages\Settings\Settings( 'sp', array( 'title' => 'Sp' ) );
$sp->callback( 'locale', function () { return 'lv'; } );
$sp->callback( 'test_field', function () { return '<p>rendered late</p>'; } );
$sp->register( __DIR__ . '/fixtures/dynamic.json', array( 'prefix' => 'sp_', 'domain' => 'sp' ) );
$sp->render();
$sc = \Carbon_Fields\Container::$last;
check( 'sprintf args fill the translated string', $sc->find( 'sp_language' )->help_text, 'Defaults to the site language (lv).' );
check( 'html callback is passed as a callable, not its result', is_callable( $sc->find( 'sp_widget' )->html ), true );
check( 'and it renders when invoked', call_user_func( $sc->find( 'sp_widget' )->html ), '<p>rendered late</p>' );
check( 'a missing html callback leaves the field empty', $sc->find( 'sp_orphan' )->html, null );


echo "logs — reading its own setting\n";
$auto = WpPackages_Registry::logger( 'autolog' );
check( 'no setting registered means off', $auto->isEnabled(), false );

WpPackages_Registry::settings( 'autolog' )->register(
    dirname( __DIR__ ) . '/settings/logs.json',
    array( 'prefix' => 'autolog_', 'domain' => 'wp-plugin-packages' )
);
$GLOBALS['options']['_autolog_logs_enabled'] = '1';
check( 'reads logs_enabled from its own schema', $auto->isEnabled(), true );
$GLOBALS['options']['_autolog_logs_enabled'] = '';
check( 'and follows it being switched off', $auto->isEnabled(), false );

// A plugin that mapped the field onto a legacy key still resolves by bare id.
WpPackages_Registry::settings( 'mapped' )->register(
    dirname( __DIR__ ) . '/settings/logs.json',
    array( 'prefix' => 'ris_', 'domain' => 'wp-plugin-packages', 'map' => array( 'logs_enabled' => 'enable_logging' ) )
);
$GLOBALS['options']['_ris_enable_logging'] = '1';
check( 'a mapped field is still found by its bare id', WpPackages_Registry::logger( 'mapped' )->isEnabled(), true );

check( 'an explicit enabled config still wins', WpPackages_Registry::logger( 'explicit', array( 'enabled' => true ) )->isEnabled(), true );

// Logging can happen before carbon_fields_register_fields has run, so a plugin
// whose logging defaults to on must not report off in that window.
check( 'enabled_default applies before the schema is registered', WpPackages_Registry::logger( 'early', array( 'enabled_default' => true ) )->isEnabled(), true );
WpPackages_Registry::settings( 'early' )->register(
    dirname( __DIR__ ) . '/settings/logs.json',
    array( 'prefix' => 'early_', 'domain' => 'wp-plugin-packages' )
);
// An unchecked checkbox stores an empty string; that is a real answer and must
// not be overridden by the default.
$GLOBALS['options']['_early_logs_enabled'] = '';
check( 'a stored empty value beats the default', WpPackages_Registry::logger( 'early' )->isEnabled(), false );
unset( $GLOBALS['options']['_early_logs_enabled'] );
check( 'an absent option still falls back', WpPackages_Registry::logger( 'early' )->isEnabled(), true );

// =========================================================== Llm component ==
echo "llm — JSON extraction\n";
use Lauzis\WpPackages\Llm\Json as LlmJson;

check( 'a bare array parses', LlmJson::extract_array( '[{"a":1}]' ), array( array( 'a' => 1 ) ) );
check( 'a fenced block is unwrapped', LlmJson::extract_array( "```json\n[1,2]\n```" ), array( 1, 2 ) );
check( 'a plain fence is unwrapped', LlmJson::extract_array( "```\n[3]\n```" ), array( 3 ) );
check( 'an object wrapping the array is unwrapped', LlmJson::extract_array( '{"results":[{"b":2}]}' ), array( array( 'b' => 2 ) ) );
check( 'prose around the array is tolerated', LlmJson::extract_array( 'Sure! [1,2,3] hope that helps' ), array( 1, 2, 3 ) );
check( 'an empty array is a valid result', LlmJson::extract_array( '[]' ), array() );
check( 'nothing usable returns null', LlmJson::extract_array( 'no json at all' ), null );
check( 'empty input returns null', LlmJson::extract_array( '' ), null );
check( 'describe() summarises', LlmJson::describe( 'short' ), 'short' );
check( 'describe() truncates and says how long', LlmJson::describe( str_repeat( 'x', 500 ), 10 ), str_repeat( 'x', 10 ) . '… (500 chars)' );
check( 'describe() names an empty response', LlmJson::describe( '' ), 'empty response' );

echo "llm — provider dispatch\n";
$GLOBALS['http'] = array(); $GLOBALS['http_responses'] = array();

$noKey = new \Lauzis\WpPackages\Llm\Client( 'x', array( 'settings' => array( 'llm_provider' => 'openai' ) ) );
$err = $noKey->complete( 'p', 'c' );
check( 'a missing access key is refused before any request', is_wp_error( $err ) && 'llm_no_access_key' === $err->get_error_code(), true );
check( 'and no HTTP call was made', count( $GLOBALS['http'] ), 0 );

$bad = new \Lauzis\WpPackages\Llm\Client( 'x', array( 'settings' => array( 'llm_provider' => 'nope', 'llm_access_key' => 'k' ) ) );
check( 'an unknown provider is refused', is_wp_error( $bad->complete( 'p', 'c' ) ), true );

function llm_client( array $settings, array $config = array() ) {
	return new \Lauzis\WpPackages\Llm\Client( 'x', array_merge( array( 'settings' => $settings ), $config ) );
}

$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"choices":[{"message":{"content":"[1]"}}]}' ) );
$out = llm_client( array( 'llm_provider' => 'openai', 'llm_access_key' => 'sk-test' ) )->complete( 'sys', array( 'a', 'b' ) );
check( 'openai returns the message content', $out, '[1]' );
$req = end( $GLOBALS['http'] );
check( 'openai endpoint', $req['url'], 'https://api.openai.com/v1/chat/completions' );
check( 'openai sends a bearer token', $req['args']['headers']['Authorization'], 'Bearer sk-test' );
$sent = json_decode( $req['args']['body'], true );
check( 'openai model default preserved', $sent['model'], 'gpt-4o-mini' );
check( 'prompt goes in the system message', $sent['messages'][0]['content'], 'sys' );
check( 'array content is JSON encoded', $sent['messages'][1]['content'], '["a","b"]' );

$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"content":[{"text":"ok"}]}' ) );
$out = llm_client( array( 'llm_provider' => 'claude', 'llm_access_key' => 'ak' ) )->complete( 'sys', 'body' );
check( 'claude returns the content text', $out, 'ok' );
$req = end( $GLOBALS['http'] );
check( 'claude endpoint', $req['url'], 'https://api.anthropic.com/v1/messages' );
check( 'claude api version header preserved', $req['args']['headers']['anthropic-version'], '2023-06-01' );
check( 'claude model default preserved', json_decode( $req['args']['body'], true )['model'], 'claude-3-5-haiku-latest' );
check( 'claude uses the system field', json_decode( $req['args']['body'], true )['system'], 'sys' );

$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"candidates":[{"content":{"parts":[{"text":"g"}]}}]}' ) );
$out = llm_client( array( 'llm_provider' => 'gemini', 'llm_access_key' => 'gk' ) )->complete( 'sys', 'body' );
check( 'gemini returns the part text', $out, 'g' );
check( 'gemini key goes in the query string', false !== strpos( end( $GLOBALS['http'] )['url'], 'key=gk' ), true );
check( 'gemini model is in the path', false !== strpos( end( $GLOBALS['http'] )['url'], 'gemini-1.5-flash:generateContent' ), true );

echo "llm — overrides and errors\n";
$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"choices":[{"message":{"content":"x"}}]}' ) );
llm_client( array( 'llm_provider' => 'openai', 'llm_access_key' => 'k', 'llm_endpoint' => 'https://proxy.test/v1' ) )->complete( 'p', 'c' );
check( 'endpoint override is used', end( $GLOBALS['http'] )['url'], 'https://proxy.test/v1' );

$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"choices":[{"message":{"content":"x"}}]}' ) );
llm_client( array( 'llm_provider' => 'openai', 'llm_access_key' => 'k' ), array( 'models' => array( 'openai' => 'gpt-4o' ) ) )->complete( 'p', 'c' );
check( 'model override is used', json_decode( end( $GLOBALS['http'] )['body'] ?? end( $GLOBALS['http'] )['args']['body'], true )['model'], 'gpt-4o' );

$GLOBALS['http_responses'] = array( array( 'code' => 401, 'body' => '{"error":"bad key"}' ) );
$err = llm_client( array( 'llm_provider' => 'openai', 'llm_access_key' => 'k' ) )->complete( 'p', 'c' );
check( 'an HTTP error becomes a WP_Error', is_wp_error( $err ) && 'llm_http_error' === $err->get_error_code(), true );
check( 'and reports the status code', false !== strpos( $err->get_error_message(), '401' ), true );

$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"unexpected":true}' ) );
$err = llm_client( array( 'llm_provider' => 'openai', 'llm_access_key' => 'k' ) )->complete( 'p', 'c' );
check( 'an unexpected shape becomes a WP_Error', is_wp_error( $err ) && 'llm_bad_response' === $err->get_error_code(), true );

$GLOBALS['http_responses'] = array( new WP_Error( 'http_request_failed', 'boom' ) );
$err = llm_client( array( 'llm_provider' => 'openai', 'llm_access_key' => 'k' ) )->complete( 'p', 'c' );
check( 'a transport error passes straight through', is_wp_error( $err ) && 'http_request_failed' === $err->get_error_code(), true );

echo "llm — commandline\n";
$cli = llm_client( array( 'llm_provider' => 'commandline', 'llm_command' => '' ) );
check( 'an empty command is refused', is_wp_error( $cli->complete( 'p', 'c' ) ), true );
check( 'commandline is the default provider', llm_client( array() )->provider(), 'commandline' );

$w = llm_client( array() );
check( 'wrapper gets a timeout with a margin', $w->with_wrapper_timeout( 'php llm-wrapper.php', 60 ), 'php llm-wrapper.php --timeout 55' );
check( 'an existing --timeout is left alone', $w->with_wrapper_timeout( 'php llm-wrapper.php --timeout 9', 60 ), 'php llm-wrapper.php --timeout 9' );
check( 'a non-wrapper command is left alone', $w->with_wrapper_timeout( 'claude -p', 60 ), 'claude -p' );
check( 'the margin never yields a non-positive timeout', $w->with_wrapper_timeout( 'php llm-wrapper.php', 1 ), 'php llm-wrapper.php --timeout 1' );

// The JSON argument is a contract with the user's own script, so a plugin with
// scripts already deployed can keep its key name.
$r = new ReflectionClass( '\Lauzis\WpPackages\Llm\Client' );
$prop = $r->getProperty( 'payload_key' ); $prop->setAccessible( true );
check( 'payload key defaults to content', $prop->getValue( llm_client( array() ) ), 'content' );
check( 'and is configurable', $prop->getValue( llm_client( array(), array( 'payload_key' => 'sentences' ) ) ), 'sentences' );

echo "llm — model labels\n";
check( 'http provider reports its model', llm_client( array( 'llm_provider' => 'claude' ) )->model_label(), 'claude-3-5-haiku-latest' );
check( 'commandline reports the wrapper model', llm_client( array( 'llm_provider' => 'commandline', 'llm_command' => 'php llm-wrapper.php --model qwen2.5:7b' ) )->model_label(), 'qwen2.5:7b' );
check( 'commandline falls back to a generic label', llm_client( array( 'llm_provider' => 'commandline', 'llm_command' => 'claude -p' ) )->model_label(), 'commandline' );

echo "llm — settings integration\n";
WpPackages_Registry::settings( 'llmplug' )->register(
	dirname( __DIR__ ) . '/settings/llm.json',
	array( 'prefix' => 'llmplug_', 'domain' => 'wp-plugin-packages' )
);
$GLOBALS['options']['_llmplug_llm_provider']   = 'claude';
$GLOBALS['options']['_llmplug_llm_access_key'] = 'from-settings';
$GLOBALS['http_responses'] = array( array( 'code' => 200, 'body' => '{"content":[{"text":"via settings"}]}' ) );
check( 'the client reads its settings from the schema', WpPackages_Registry::llm( 'llmplug' )->complete( 'p', 'c' ), 'via settings' );
check( 'using the stored key', end( $GLOBALS['http'] )['args']['headers']['x-api-key'], 'from-settings' );


echo "version gate — multi-plugin arbitration\n";
// Every plugin includes its own copy of Registry.php. PHP early-binds a
// top-level class with no parent, so a guard that merely returns early still
// fatals with "Cannot redeclare class" — this is that regression.
$second = shell_exec( 'php -r ' . escapeshellarg(
    'require ' . var_export( dirname( __DIR__ ) . '/src/Registry.php', true ) . ';'
    . 'require ' . var_export( dirname( __DIR__ ) . '/src/Registry.php', true ) . ';'
    . 'echo "ok";'
) . ' 2>&1' );
check( 'Registry.php can be included by several plugins', trim( (string) $second ), 'ok' );

// Reproduces the live failure: three plugins, one bundling an older copy. The
// older copy must not win just because its plugin loaded first.
$gate = new ReflectionClass( 'WpPackages_Registry' );
$copies = $gate->getProperty( 'copies' ); $copies->setAccessible( true );
$roots  = $gate->getProperty( 'roots' );  $roots->setAccessible( true );
$booted = $gate->getProperty( 'booted' ); $booted->setAccessible( true );

$saved_copies = $copies->getValue(); $saved_roots = $roots->getValue(); $saved_booted = $booted->getValue();

$copies->setValue( null, array() );
$roots->setValue( null, array() );
$booted->setValue( null, false );

// Registration order deliberately puts the oldest first, as alphabetical plugin
// load order did on the live site (rest-in-sync before splecheh and yolsa).
WpPackages_Registry::register( '1.5.2', '/rest-in-sync/load.php', '/rest-in-sync' );
WpPackages_Registry::register( '1.6.2', '/splecheh/load.php', '/splecheh' );
WpPackages_Registry::register( '1.6.2', '/yolsa/load.php', '/yolsa' );

check( 'the newest copy wins, not the first registered', WpPackages_Registry::active_version(), '1.6.2' );
check( 'and schemas resolve against it', WpPackages_Registry::active_root() !== '/rest-in-sync', true );

$copies->setValue( null, $saved_copies );
$roots->setValue( null, $saved_roots );
$booted->setValue( null, $saved_booted );

check( 'boot is deferred to plugins_loaded', isset( $GLOBALS['hooks']['plugins_loaded'] ), true );


// ==================================================== Migrations component ==
echo "migrations — ordering and recording\n";
$mig_ran = array();
$mig_a = WpPackages_Registry::migrations( 'mig', array( 'version' => '2.0.0', 'option' => 'mig_ver' ) );
// Registered out of order on purpose: they must apply by version, not by
// registration order.
$mig_a->add( '1.2.0', function () use ( &$mig_ran ) { $mig_ran[] = '1.2.0'; } );
$mig_a->add( '1.0.0', function () use ( &$mig_ran ) { $mig_ran[] = '1.0.0'; } );
$mig_a->add( '1.10.0', function () use ( &$mig_ran ) { $mig_ran[] = '1.10.0'; } );

$mig_r = $mig_a->run();
check( 'applied in version order, not registration order', $mig_ran, array( '1.0.0', '1.2.0', '1.10.0' ) );
check( 'version compare is semantic (1.10 after 1.2)', $mig_ran[2], '1.10.0' );
check( 'report lists what ran', $mig_r['applied'], array( '1.0.0', '1.2.0', '1.10.0' ) );
check( 'highest applied version recorded', $GLOBALS['options']['mig_ver'], '1.10.0' );

$mig_ran = array();
check( 'a second run does nothing', $mig_a->run()['applied'], array() );
check( 'and nothing re-ran', $mig_ran, array() );
check( 'has_pending() is false once done', $mig_a->has_pending(), false );

echo "migrations — only what is due\n";
$mig_ran = array();
$GLOBALS['options']['part_ver'] = '1.0.0';
$mig_p = WpPackages_Registry::migrations( 'part', array( 'version' => '2.0.0', 'option' => 'part_ver' ) );
$mig_p->add( '0.9.0', function () use ( &$mig_ran ) { $mig_ran[] = 'old'; } );
$mig_p->add( '1.0.0', function () use ( &$mig_ran ) { $mig_ran[] = 'equal'; } );
$mig_p->add( '1.5.0', function () use ( &$mig_ran ) { $mig_ran[] = 'new'; } );
$mig_p->run();
check( 'skips versions already applied', $mig_ran, array( 'new' ) );

echo "migrations — never runs ahead of the code\n";
$mig_ran = array();
$mig_d = WpPackages_Registry::migrations( 'down', array( 'version' => '1.0.0', 'option' => 'down_ver' ) );
$mig_d->add( '1.0.0', function () use ( &$mig_ran ) { $mig_ran[] = 'current'; } );
$mig_d->add( '2.0.0', function () use ( &$mig_ran ) { $mig_ran[] = 'future'; } );
$mig_d->run();
check( 'a rolled-back plugin skips newer migrations', $mig_ran, array( 'current' ) );
check( 'and records only what it applied', $GLOBALS['options']['down_ver'], '1.0.0' );

echo "migrations — unfinished work resumes\n";
$mig_calls = 0;
$mig_b = WpPackages_Registry::migrations( 'batch', array( 'version' => '1.0.0', 'option' => 'batch_ver' ) );
$mig_b->add( '0.5.0', function () use ( &$mig_calls ) { $mig_calls++; return $mig_calls >= 3; } );  // false twice
$mig_b->add( '0.6.0', function () use ( &$mig_ran ) { $mig_ran[] = 'later'; } );
$mig_ran = array();
$mig_b->run();
check( 'returning false leaves the version unrecorded', isset( $GLOBALS['options']['batch_ver'] ), false );
check( 'and stops later migrations running on half-migrated data', $mig_ran, array() );
$mig_b->run(); $mig_b->run();
check( 'it resumes until it reports done', $mig_calls, 3 );
check( 'then records and continues', $GLOBALS['options']['batch_ver'], '0.6.0' );

echo "migrations — failure\n";
$mig_f = WpPackages_Registry::migrations( 'fail', array( 'version' => '1.0.0', 'option' => 'fail_ver' ) );
$mig_f->add( '0.1.0', function () { return true; } );
$mig_f->add( '0.2.0', function () { throw new \RuntimeException( 'disk on fire' ); } );
$mig_f->add( '0.3.0', function () { throw new \RuntimeException( 'never reached' ); } );
$mig_rf = $mig_f->run();
check( 'the failure is reported', $mig_rf['failed'], 'disk on fire' );
check( 'work completed before it is kept', $GLOBALS['options']['fail_ver'], '0.1.0' );
check( 'and it does not carry on past the failure', $mig_rf['applied'], array( '0.1.0' ) );

echo "migrations — fresh installs\n";
$mig_ran = array();
$mig_n = WpPackages_Registry::migrations( 'newsite', array( 'version' => '3.0.0', 'option' => 'new_ver' ) );
$mig_n->add( '1.0.0', function () use ( &$mig_ran ) { $mig_ran[] = 'history'; } );
check( 'baseline() stamps the current version', $mig_n->baseline(), true );
check( 'at the running version', $GLOBALS['options']['new_ver'], '3.0.0' );
$mig_n->run();
check( 'so no historical migration runs on a new site', $mig_ran, array() );
check( 'baseline() will not overwrite existing state', $mig_n->baseline(), false );

echo "migrations — concurrency\n";
$mig_c = WpPackages_Registry::migrations( 'lockme', array( 'version' => '1.0.0', 'option' => 'lock_ver' ) );
$mig_c->add( '0.1.0', function () { return true; } );
$GLOBALS['transients'][ \Lauzis\WpPackages\Migrations\Runner::LOCK_PREFIX . 'lockme' ] = 1;  // another request holds it
$mig_rc = $mig_c->run();
check( 'a second concurrent request stands down', $mig_rc['skipped'], true );
check( 'and applies nothing', $mig_rc['applied'], array() );
unset( $GLOBALS['transients'][ \Lauzis\WpPackages\Migrations\Runner::LOCK_PREFIX . 'lockme' ] );
check( 'the lock is released after a run', $mig_c->run()['applied'], array( '0.1.0' ) );

check( 'no migrations registered is a no-op', WpPackages_Registry::migrations( 'empty' )->run()['applied'], array() );


// ============================================================ version gate ==
echo "version gate\n";
check( 'components share one registry', WpPackages_Registry::logger( 'demo' ) === $log, true );
check( 'a slug gets one logger', WpPackages_Registry::logger( 'demo' ) === WpPackages_Registry::logger( 'demo' ), true );
check( 'a slug gets one notice manager', WpPackages_Registry::notices( 'splecheh' ) === $n, true );
check( 'distinct slugs get distinct instances', WpPackages_Registry::logger( 'other' ) !== $log, true );
check( 'logger and notices are separate buckets', WpPackages_Registry::notices( 'demo' ) !== WpPackages_Registry::logger( 'demo' ), true );

// Registering other copies must not disturb anything already resolved, so
// these assertions come last.
$boot_version = WpPackages_Registry::active_version();
check( 'active root is this copy', WpPackages_Registry::active_root(), dirname( __DIR__ ) );

// The version registered in bootstrap.php and the asset cache-buster must move
// together, or a newer template could load an older stylesheet.
preg_match( "/register\\(\\s*'([^']+)'/", file_get_contents( dirname( __DIR__ ) . '/bootstrap.php' ), $m );
check( 'bootstrap registers its own declared version', $boot_version, $m[1] );
check( 'Assets::VERSION is in step with it', \Lauzis\WpPackages\Notices\Assets::VERSION, $boot_version );

WpPackages_Registry::register( '0.9.0', '/nonexistent/older.php', '/nonexistent' );
check( 'an older copy does not win', WpPackages_Registry::active_version(), $boot_version );

// Derived from the real version rather than written out, so a release does not
// quietly overtake the copy this test calls "newer".
$newer = preg_replace_callback(
	'/^(\d+)\.(\d+)/',
	static function ( $m ) {
		return $m[1] . '.' . ( (int) $m[2] + 1 );
	},
	$boot_version
);

WpPackages_Registry::register( $newer, dirname( __DIR__ ) . '/src/load.php', '/newer' );
check( 'version compare is semantic, not lexical', WpPackages_Registry::active_version(), $newer );
check( 'assets follow the winning copy', WpPackages_Registry::active_root(), '/newer' );

exec( 'rm -rf ' . escapeshellarg( $base ) );

// ---------------------------------------------------------------- Language --

use Lauzis\WpPackages\I18n\Language;

function lang_reset() {
	$GLOBALS['filters']     = array();
	$GLOBALS['locale']      = 'en_US';
	$GLOBALS['site_locale'] = 'en_US';
}

section( 'Language: a site with no translation plugin' );
lang_reset();
$GLOBALS['locale'] = 'lv_LV';
check( 'current comes from the locale', Language::current(), 'lv' );
check( 'the region is dropped', Language::normalize( 'pt_BR' ), 'pt' );
check( 'a hyphenated tag works too', Language::normalize( 'zh-Hant' ), 'zh' );
check( 'locale is available in full', Language::locale(), 'lv_LV' );
check( 'the current language resolves to it', Language::locale_for( 'lv' ), 'lv_LV' );
check( 'source says none', Language::source(), 'none' );
check( 'not multilingual', Language::is_multilingual(), false );
check( 'available is the one language', Language::available(), array( 'lv' ) );
check( 'a post falls back to the request', Language::for_post( 7 ), 'lv' );

section( 'Language: WPML' );
lang_reset();
add_filter( 'wpml_current_language', function () { return 'de'; } );
add_filter( 'wpml_default_language', function () { return 'en'; } );
add_filter( 'wpml_post_language_details', function ( $v, $post_id ) {
	return 42 === $post_id ? array( 'language_code' => 'fr' ) : null;
} );
add_filter( 'wpml_active_languages', function () {
	return array(
		'en' => array( 'language_code' => 'en', 'default_locale' => 'en_US' ),
		'de' => array( 'language_code' => 'de', 'default_locale' => 'de_DE' ),
	);
} );
check( "current is WPML's", Language::current(), 'de' );
check( 'a post answers for itself', Language::for_post( 42 ), 'fr' );
check( 'an untranslated post falls back', Language::for_post( 43 ), 'de' );
check( 'default language', Language::default_language(), 'en' );
check( 'available languages', Language::available(), array( 'en', 'de' ) );
check( 'multilingual', Language::is_multilingual(), true );
check( 'source says wpml', Language::source(), 'wpml' );
check( 'a code resolves to its locale', Language::locale_for( 'de' ), 'de_DE' );
check( 'and so does another', Language::locale_for( 'en' ), 'en_US' );
check( 'an unknown code is returned as given', Language::locale_for( 'xx' ), 'xx' );

section( 'Language: a plugin registered but not yet answering' );
lang_reset();
// What WPML does before it has finished setting up. A registered filter that
// returns nothing must not be mistaken for an answer.
add_filter( 'wpml_current_language', function () { return null; } );
$GLOBALS['locale'] = 'es_ES';
check( 'falls through to the locale', Language::current(), 'es' );

section( 'Language: anything else answers through the filter' );
lang_reset();
add_filter( 'wp_packages_current_language', function () { return 'sv'; } );
check( 'the filter decides', Language::current(), 'sv' );
check( 'source says filter', Language::source(), 'filter' );
lang_reset();
add_filter( 'wp_packages_post_language', function ( $c, $id ) { return 'no'; } );
check( 'a post can be overridden', Language::for_post( 5 ), 'no' );

// Declared inside a conditional so PHP does not hoist them: every section above
// this point has to run on a site where Polylang is genuinely absent.
if ( true ) {
	function pll_current_language( $f = '' ) { return 'it'; }
	function pll_default_language( $f = '' ) { return 'en'; }
	function pll_get_post_language( $id, $f = '' ) { return 99 === $id ? 'nl' : false; }
	function pll_languages_list( $a = array() ) {
		return ( isset( $a['fields'] ) && 'locale' === $a['fields'] )
			? array( 'en_US', 'it_IT', 'nl_NL' )
			: array( 'en', 'it', 'nl' );
	}
}

section( 'Language: Polylang' );
lang_reset();
check( "current is Polylang's", Language::current(), 'it' );
check( 'a post answers for itself', Language::for_post( 99 ), 'nl' );
check( 'an untranslated post falls back', Language::for_post( 100 ), 'it' );
check( 'default language', Language::default_language(), 'en' );
check( 'available languages', Language::available(), array( 'en', 'it', 'nl' ) );
check( 'source says polylang', Language::source(), 'polylang' );
check( 'polylang code resolves too', Language::locale_for( 'it' ), 'it_IT' );

section( 'Language: WPML wins when both are somehow present' );
lang_reset();
add_filter( 'wpml_current_language', function () { return 'de'; } );
check( 'wpml takes precedence', Language::current(), 'de' );
check( 'and is named as the source', Language::source(), 'wpml' );

// ------------------------------------------------------------------ Footer --

use Lauzis\WpPackages\Admin\Footer;

function footer_text( $text = 'Version 6.9' ) {
	return apply_filters( 'update_footer', $text );
}

section( 'Footer: on the plugin\'s own pages' );
$GLOBALS['filters'] = array();
$_GET = array( 'page' => 'demo-settings' );
Footer::show( 'demo', array( 'name' => 'Demo', 'version' => '2.1.0' ) );
check( 'the version is appended', false !== strpos( footer_text(), 'Demo 2.1.0' ), true );
check( "WordPress's own is kept", false !== strpos( footer_text(), 'Version 6.9' ), true );

section( 'Footer: elsewhere in the admin' );
$_GET = array( 'page' => 'some-other-plugin' );
check( 'left alone', footer_text(), 'Version 6.9' );
$_GET = array();
check( 'and on a page with no page parameter', footer_text(), 'Version 6.9' );

section( 'Footer: an empty footer' );
$_GET = array( 'page' => 'demo-settings' );
check( 'stands on its own without a separator', footer_text( '' ), '<span class="wp-packages-version">Demo 2.1.0</span>' );

section( 'Footer: a plugin with no version says nothing' );
$GLOBALS['filters'] = array();
$_GET = array( 'page' => 'quiet-page' );
Footer::show( 'quiet', array( 'name' => 'Quiet' ) );
check( 'nothing appended', footer_text(), 'Version 6.9' );

section( 'Footer: registering twice does not double up' );
$GLOBALS['filters'] = array();
$_GET = array( 'page' => 'twice-page' );
Footer::show( 'twice', array( 'name' => 'Twice', 'version' => '1.0.0' ) );
Footer::show( 'twice', array( 'name' => 'Twice', 'version' => '1.0.0' ) );
check( 'shown once', substr_count( footer_text(), 'Twice 1.0.0' ), 1 );

section( 'Footer: a screen callback overrides the page check' );
$GLOBALS['filters'] = array();
$_GET = array();
Footer::show( 'callback', array( 'name' => 'CB', 'version' => '3.0.0', 'screen' => function () { return true; } ) );
check( 'the callback decides', false !== strpos( footer_text(), 'CB 3.0.0' ), true );

$_GET = array();

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );

