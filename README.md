# Migrate Polylang to WPML
Import multilingual data from Polylang to WPML

# Installation and usage
- Back up your database before you migrate.
- Deactivate Polylang.
- Activate WPML and the Migrate Polylang to WPML plugin. If you also want to migrate string translations, activate WPML String Translation.
- Finish the WPML setup wizard (you see it right after activation). Select the same languages that you used in Polylang.
- The plugin sets custom post types that have translations in Polylang to translatable. For other custom post types, go to WPML > Settings > Post Types Translation.
- Go to Tools > Migrate from Polylang to WPML
- Click "Migrate" and wait until the migration is complete. A large site takes longer.
- Done. You can uninstall the Migrate Polylang to WPML plugin.

# What this plugin does 
- Checks that every language you had in Polylang is active in WPML, and stops with a list of the missing ones. Select the same languages in the WPML wizard.
- Migrates all your posts, pages and custom post types and their translations.
- Migrates all your taxonomies (categories, tags and custom taxonomies) and their translations.
- Migrates your string translations, for the strings that WPML String Translation has registered.
- Migrates the language that you set for a widget in Polylang.

# What this plugin doesn't (yet)
- Migrate menus: Use the menu synchronization between languages option in WPML.
- Migrate other settings: This will be implemented progressively. For the time being, you are free to adjust them manually after the migration process is completed.

# Support and feedback
- please report any issue or feature request at [WPML forum ](https://wpml.org/forums/forum/english-support/)
- Read the [documentation](https://wpml.org/documentation/migrate-polylang-wpml/).

# Development

The development tools require PHP 7.4 or newer and Composer 2. Install the locked dependencies with:

```sh
composer install --no-plugins --no-scripts
```

Run all local checks with:

```sh
composer check
```

The individual commands are `composer test`, `composer phpcs`, and `composer phpstan`. PHPUnit uses WordPress function mocks, so it does not require a WordPress installation or database. PHPStan analyzes the production plugin at level 3 with WordPress and WPML stubs. PHPCS initially covers the new test and analysis support code; the legacy production files will be introduced separately after their existing violations are addressed.

CI runs the unit tests on the supported endpoints, PHP 7.4 and 8.5. Coding standards and static analysis run on PHP 8.5.

# Changelog

## Unreleased
- The widgets step runs with WPML alone. The separate WPML Widgets plugin is not needed anymore (#51, wpmldev-8892).
- A Polylang string translation goes to every WPML registration of the same text, not only to the first one (#52).
- Faster grouping of posts into translation groups on large sites (#50).
- The migration page no longer names the WPML Widgets plugin, and the documentation link points to the current page (wpmldev-8892).

## 5.0.0
- The .htaccess notice about the old default-language redirect shows only on sites where Polylang showed the default language in URLs, and one dismissal hides it for everyone (wpmlbridge-393).
- Removed two unused tooltip theme files; the tooltip stylesheet is versioned with the plugin.
- Map every Polylang language to its WPML code by locale instead of by slug, so custom slugs, Traditional Chinese, Norwegian and regional variants (es_MX, pt_BR) reach the right language. Unmatched languages are reported instead of written under an unknown code.
- The migration no longer activates WPML languages by itself. It checks that every Polylang language is active in WPML and stops with a list of the missing ones, so content only ever lands in languages selected in the WPML wizard.
- On WPML 5.0, where a locale can match a legacy code and a preset code (`en` and `en-us`), prefer the language the site activated in the WPML wizard, then the code WPML 5.0 offers for that locale. Content and string translations no longer land in a second, unchosen language (wpmlbridge-391).

## 0.5.2
- Fixed taxonomy migration for languages whose Polylang slugs differ from their WPML language codes, including Portuguese and Chinese.
- Fixed language-code handling for post translation groups, including groups without a translation in the site's default language.
- Prevented orphaned Polylang taxonomy translation groups from overwriting valid WPML relationships.
- Improved PHP 8 compatibility when migrating incomplete or corrupt Polylang data.

## 0.5.1
- Added capability and nonce checks to all migration and data-deletion AJAX actions.
- Escaped admin output and sanitized the `.htaccess` notice dismissal cookie.
- Prevented direct access to the plugin's PHP files.

## 0.5.0
- Automatically set the translation preference to "translate" for translated custom post types.
- Added the support for duplicated (synced) posts.
- Fixed a fatal error "Uncaught TypeError: Cannot access offset of type string on string".
- Fixed several PHP warnings.

## 0.4
- Fixed an issue with not terminating import when importing terms.

## 0.3
- Resolved an issue with migration stopping on nginx server.

## 0.2
- Fixed the migration of Chinese languages and Portuguese (pt-pt and pt-br) languages.
- Fixed a case when the user has set synchronisation options (comp-3271).

## 0.1
- Initial release.
