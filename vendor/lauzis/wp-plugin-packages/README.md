# wp-plugin-packages

Shared components for the WordPress plugins in this account (mawiblah,
splecheh, rest-in-sync, and any that follow).

| Component | What it does |
| --- | --- |
| **Logs** | File-based logging, daily files, channels. |
| **Notices** | Dismissible admin notices, with the dismissal stored site-wide or per user. |
| **Toasts** | Transient floating messages raised from JavaScript. |
| **Settings** | A settings page composed from JSON schema fragments, rendered by Carbon Fields. |
| **Llm** | Provider-agnostic LLM calls: OpenAI, Claude, Gemini or a local command. |
| **Migrations** | Data migrations applied once each, in version order, when a plugin updates. |

They ship together, in one package behind one version gate, on purpose — see
[Version gating](#version-gating).

## Install

Not on Packagist. Consume it from GitHub:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/lauzis/wp-plugin-packages" }
    ],
    "require": {
        "lauzis/wp-plugin-packages": "^1.0"
    }
}
```

The consuming plugin must load `vendor/autoload.php` early — before anything
that might log or register a notice — **and require this package's bootstrap
explicitly**:

```php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/lauzis/wp-plugin-packages/bootstrap.php';
```

The second line is not optional and the package deliberately does not use
Composer's `files` autoload for it. Composer keys that mechanism on an
identifier derived from the package name, which is byte-identical in every
plugin's `vendor/` directory, so it runs exactly **one** copy's bootstrap per
request and every other copy silently never registers. The version gate then
cannot see them, and whichever plugin loaded first wins regardless of version.
`require_once` keys on the resolved path, which differs per plugin, so every
copy is seen.

Each plugin keeps its own thin facade class over these components, so its own
call sites and settings semantics stay where they belong. Every facade should
degrade to a no-op when the package is absent:

```php
if ( ! class_exists( 'WpPackages_Registry' ) ) {
    return null; // vendor/ missing — never fatal
}
```

Resolving a component through the registry is also what boots the library, so
nothing may reference `\Lauzis\WpPackages\*` before calling it.

## Logs

```php
WpPackages_Registry::logger(
    'my-plugin',
    array(
        'dir'     => MY_PLUGIN_LOG_PATH,
        'enabled' => array( __CLASS__, 'enabled' ),
    )
)->add( 'cron', 'Batch finished.', array( 'processed' => 12 ) );
```

`enabled` takes a callable so a settings change applies immediately rather than
being captured when the logger is first built.

| Method | Behaviour |
| --- | --- |
| `add($action, $message, $context, $channel)` | Appends a line when enabled. `false` if disabled or the write failed. |
| `error($action, $message, $context)` | Always writes to PHP's `error_log`; also to the plugin's file when enabled. |
| `count($channel)` | Number of **entries**, not files. |
| `files($channel)` | `['file', 'name', 'date', 'count']` per daily file, newest first. |
| `read($channel, $limit)` | Entries, newest first. |
| `clear($channel)` | Deletes one channel's files; `'*'` for all. |
| `dir()` | Absolute log directory path. |

Lines are `[2026-08-01 09:14:02] [action] message | {"json":"context"}`. An
empty `$action` omits that segment, for audit-style streams carrying only a
message.

A **channel** is a separate stream in the same directory, written as
`{channel}-YYYY-MM-DD.log`. The default channel is the plugin slug, which
reproduces the previous per-plugin filenames exactly. Channel names are reduced
to `[a-z0-9_-]`, so a caller-supplied channel cannot escape the directory.

Logs belong under `uploads/`, never inside the plugin directory — WordPress
deletes and re-extracts that folder on every update. The directory gets an
`index.php` and a deny-all `.htaccess` on creation.

## Notices

```php
$notices = WpPackages_Registry::notices(
    'my-plugin',
    array(
        'store'   => 'option',            // or 'user'
        'version' => MY_PLUGIN_VERSION,   // for VERSION-mode notices
        'screen'  => array( __CLASS__, 'is_plugin_screen' ),
    )
)->boot();

$notices->add(
    new \Lauzis\WpPackages\Notices\Notice(
        'missing-lib',
        __( 'The spell-check library is missing.', 'my-plugin' ),
        'error',
        \Lauzis\WpPackages\Notices\Notice::ONCE
    )
);
```

`boot()` registers the `admin_notices`, `admin_enqueue_scripts` and `wp_ajax_*`
hooks. It is idempotent.

| Mode | Dismissal lasts |
| --- | --- |
| `Notice::ONCE` | Forever. |
| `Notice::VERSION` | Until the configured `version` changes. |
| `Notice::SESSION` | Not persisted; reappears next page load. |

`store` is `option` (site-wide) or `user` (per user, in user meta); both write
to `{slug}_dismissed_notices`. `screen` is a callable deciding whether to
render — omit it and the default is any admin page whose `page` request
parameter starts with the slug. Dismissal requires a nonce *and* a capability
(`edit_posts` by default).

Messages pass through `wp_kses_post()`, so inline links and emphasis work and
scripts do not.

## Toasts

```php
WpPackages_Registry::toasts( 'my-plugin', array( 'timeout' => 5000 ) )->enqueue();
```

```js
window.wpNoticesToast.show( 'Pushed 3 posts.', 'success' );
```

Types are `success`, `error`, `warning`, `info`. Scripts calling it should
declare a dependency on the `wp-notices-toasts` handle. Messages are inserted
with `textContent`, not `innerHTML` — toast text routinely comes from server
responses and post titles.

## Settings

A settings page is composed from JSON fragments — the plugin's own, plus
whichever component schemas it registers:

```php
$settings = WpPackages_Registry::settings( 'my-plugin', array(
    'title'       => __( 'Settings', 'my-plugin' ),
    'mode'        => 'tabs',            // or 'flat'
    'page_parent' => 'my-plugin',
) );

$settings->register( MY_PLUGIN_DIR . 'config/settings.json', array(
    'prefix' => 'my_plugin_',
    'domain' => 'my-plugin',
) );

$settings->register( WpPackages_Registry::schema( 'logs' ), array(
    'prefix' => 'my_plugin_',
    'domain' => 'wp-plugin-packages',
) );

$settings->render();   // on carbon_fields_register_fields
```

Ids in a fragment are bare; the loader prefixes them so two plugins never share
an option. `map` keeps a legacy key, `defaults` overrides a component's default,
and `conditions` attaches conditional logic the component knows nothing about.

A field's `type` is passed straight to `Field::make()`, so any Carbon Fields
type works without the schema knowing about it. `settings` is passed on to the
field's own `set_settings()` where it has one — a `rich_text` field takes
`wp_editor` settings that way:

```json
{
  "id": "email_body",
  "type": "rich_text",
  "title": "Email body",
  "settings": { "media_buttons": false }
}
```

It is ignored on types that do not accept it, so a typo cannot fatal the
settings page.

Values that cannot be JSON literals — dynamic option lists, dynamic defaults,
generated html — use `"@callback:name"`, resolved against callbacks registered
by name. Nothing in a schema is ever eval'd.

Read a value back by its bare id, wherever it actually lives:

```php
$settings->get( 'logs_enabled', true );
```

`bin/schema-i18n` generates a PHP manifest of `__()` calls from the JSON so
`wp i18n make-pot` can see strings that live in a schema. `--check` makes it a
CI gate.

## Llm

```php
$llm = WpPackages_Registry::llm( 'my-plugin' );

$text = $llm->complete( 'You are a translator. Reply with JSON only.', $payload );

if ( is_wp_error( $text ) ) {
    return $text;
}

$rows = \Lauzis\WpPackages\Llm\Json::extract_array( $text );
```

The client covers provider selection, credentials, endpoints, models, timeouts
and unwrapping each provider's response envelope. The prompt, the expected
response shape and what to do with the result stay in the plugin.

Register `WpPackages_Registry::schema( 'llm' )` to get the configuration UI —
provider, access key, endpoint, command and timeout.

## Migrations

Work that has to happen when stored data no longer matches what the code
expects — renaming an option, moving meta, backfilling a column.

```php
$migrations = WpPackages_Registry::migrations( 'my-plugin', array(
    'version' => MY_PLUGIN_VERSION,
    'option'  => 'my_plugin_data_version',
) );

$migrations->add( '1.2.0', array( My_Plugin_Migrations::class, 'move_settings' ) );
$migrations->add( '1.3.0', function () { /* … */ } );

$migrations->run();   // safe on every request
```

Register each migration against the plugin version that *introduced the need for
it*. Only those newer than the recorded version run, in version order — and
never any newer than the code currently running, so a rolled-back plugin does
not apply migrations whose supporting code it no longer has.

The applied version is recorded **after each migration**, not at the end, so a
run that fails half way keeps the work it already did and resumes from there.

**Call `baseline()` on activation.** A fresh install has no old data, and
running the whole history against an empty site is wasted at best and wrong at
worst. It records the current version without running anything, and refuses to
overwrite existing state, so reactivation cannot erase migration history.

```php
register_activation_hook( __FILE__, function () {
    WpPackages_Registry::migrations( 'my-plugin', array( 'version' => MY_PLUGIN_VERSION ) )->baseline();
} );
```

A migration returning `false` means "not finished": the version is left
unrecorded, later migrations are held back so they never see half-migrated data,
and it resumes on the next request. That is how a migration too large for one
request works through a batch at a time.

Concurrent requests are handled with a short-lived lock, so two page loads
arriving together cannot both migrate.

## Language

Which language a site, a request, or a post is in. WordPress has no answer to
this, so every plugin that needs to know writes the same cascade.

```php
Language::current();            // 'lv'  — the request
Language::for_post( $id );      // 'fr'  — that post, wherever it is asked from
Language::default_language();   // 'en'
Language::available();          // ['en', 'lv']
Language::is_multilingual();
Language::source();             // 'wpml' | 'polylang' | 'filter' | 'none'
Language::locale();             // 'lv_LV' — when the region matters
```

WPML and Polylang are handled directly. Anything else answers through
`wp_packages_current_language` and `wp_packages_post_language` rather than by
being added here: the list of translation plugins is not something this package
can keep up with, and a site always knows better than a guess.

Codes come back as the active plugin reports them — a bare `lv`. With no plugin
the locale's language part is used, so `lv_LV` answers as `lv` and codes stay
comparable either way.

`for_post()` asks the plugin about the post rather than about the request. The
two agree on an ordinary page view, but a post looked up from cron, REST or a
sync has a language while the request has nothing meaningful to say.

A registered filter that returns nothing is not treated as an answer — that is
WPML before it has finished setting up, and it falls through to the locale
rather than reporting no language at all.

## Assets

The CSS and JS ship in `assets/` and are enqueued from
`vendor/lauzis/wp-plugin-packages/assets/`. Their URL is derived by mapping the
filesystem path onto the plugin directory URL, which works wherever the plugin
is installed. If your layout defeats that — a symlinked `vendor/`, or the
package installed outside a plugin — pass an explicit `assets_url`.

Assets are resolved against the *winning* copy's directory, so a newer template
can never load an older stylesheet.

## Version gating

Every plugin bundles its own copy in `vendor/`. Without arbitration PHP would
use whichever copy autoloaded first, so a plugin shipping a newer version could
silently run an older one and fatal on a method that version lacks.

`bootstrap.php` therefore registers only a version and a path.
`WpPackages_Registry` — global, `class_exists`-guarded, defined once by
whichever copy loads first — collects every registration and loads the highest
version.

**This is why the components live in one package.** The registry's own API can
essentially never change, since an *old* copy may be the one that defines it. A
package per component would mean a separate never-changeable registry for each,
duplicating the same arbitration logic — the exact duplication these packages
exist to remove.

Consequences to remember:

- Keep `WpPackages_Registry` minimal. New behaviour goes in the components.
- The version in `bootstrap.php`, the one in `Notices\Assets::VERSION` and the
  Git tag move together.

## Tests

```
composer test
```

Dependency-free by design: the package requires nothing, and pulling in PHPUnit
would push that resolution onto every consuming plugin. The suite stubs the few
WordPress functions the library touches.

## History

Supersedes `lauzis/wp-logs` and `lauzis/wp-notices`, which were split per
component before the shared-registry cost became clear. Both are archived.
