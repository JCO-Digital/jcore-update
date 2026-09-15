<?php
/**
 * php-scoper config used by .github/workflows/reusable-plugin-publish.yml
 * to prefix this library's namespace before it's bundled into a
 * consuming plugin's release zip.
 *
 * WHY THIS EXISTS
 * ----------------
 * Multiple WordPress plugins on the same site each bundle their own copy
 * of jcore-update under the identical `Jcore\Update` namespace. PHP only
 * autoloads a class once per request — whichever plugin's autoloader
 * wins the race for that namespace supplies the classes to every other
 * plugin too. If two plugins bundle different versions, the "wrong" one
 * silently wins for everyone (see: the missing $majorPayload property
 * incident on kehitys.jcore.fi, caused by exactly this).
 *
 * Renaming the namespace to something that embeds the exact version
 * being bundled means two plugins bundling different versions can never
 * collide — each gets its own, uniquely-named copy of the classes. This
 * was verified end-to-end against a real consuming plugin (jcore-dummy):
 * an old, unscoped v1.2.0 copy of this library loaded first in the same
 * request no longer contaminates a scoped v1.3.2 copy loaded afterwards.
 *
 * SCOPE OF THIS CONFIG
 * ---------------------
 * Two finders run: one renames this library's own source, the other
 * scans the *consuming plugin's* PHP files purely to rewrite their
 * `use Jcore\Update\...` / `\Jcore\Update\...` references (including
 * `class_exists()`-style string literals) to the new prefixed names —
 * confirmed working via testing, including the string-literal case.
 * `exclude-namespaces` below protects everything else (the plugin's own
 * namespace, WordPress core symbols) from being renamed itself.
 *
 * IMPORTANT — this alone is not sufficient. After scoping, you must also
 * patch the *cached* autoload metadata for this package before running
 * `composer dump-autoload`, or the renamed classes will not be found at
 * runtime: `composer dump-autoload` does NOT re-parse each installed
 * package's files or its composer.json — it trusts the autoload rules
 * already cached in vendor/composer/installed.json. That cached PSR-4
 * prefix (`Jcore\Update\`) must be remapped to the new prefixed one,
 * still pointing at the same src/ directory — scoping only rewrites the
 * leading namespace segments, it never moves files. See the workflow
 * step for the exact patch.
 *
 * Do NOT "fix" this instead by switching the package to a classmap
 * declaration plus `composer dump-autoload --classmap-authoritative`.
 * That was the original approach here and it caused a real production
 * fatal ("Class ... not found") the first time it shipped: authoritative
 * classmap mode removes PHP's normal autoload fallback — if a class is
 * ever missing from the pre-built map for any reason, it's a hard,
 * unrecoverable failure with no filesystem check at all, unlike plain
 * PSR-4 which computes the file path live on every lookup and simply
 * returns nothing if it's not there. Remapping the PSR-4 prefix avoids
 * that failure mode entirely.
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// Supplied by the workflow as JCORE_SCOPE_PREFIX, derived from the
// resolved jcore-update version (e.g. "JcoreUpdate_1_3_2"). Falls back to
// a fixed value so this config can still be run manually/locally.
$prefix = getenv( 'JCORE_SCOPE_PREFIX' ) ?: 'JcoreUpdateDev';

return array(
	'prefix'  => $prefix,

	'finders' => array(
		// This library's own source — gets renamed and relocated under
		// the new prefix.
		Finder::create()
			->files()
			->in( getcwd() . '/vendor/jcodigital/jcore-update/src' ),

		// The consuming plugin's own PHP files — not renamed themselves
		// (protected by exclude-namespaces below), but scanned so any
		// reference to Jcore\Update\* gets rewritten to match.
		Finder::create()
			->files()
			->name( '*.php' )
			->in( getcwd() )
			->exclude( array( 'vendor', 'tests', 'node_modules', '.git', 'release', 'build' ) ),
	),

	// Only rename namespaces under Jcore\Update — leave the consuming
	// plugin's own namespace, WordPress core symbols, and anything else
	// these finders might encounter, untouched.
	'exclude-namespaces' => array(
		'/^(?!Jcore\\\\Update).*/',
	),
);
