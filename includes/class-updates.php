<?php
/**
 * GitHub updates for the public repository.
 *
 * WordPress is pointed at the gm-demo-guide.zip release asset, whose top
 * folder is gm-demo-guide/. A rename step covers GitHub's generated source
 * archive, which unpacks under a different folder name.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (!defined('ABSPATH')) {
	exit;
}

final class Updates {
	private const SLUG = 'gm-demo-guide';

	private const REPOSITORY = 'https://github.com/scotthill04210/gm-demo-guide/';

	public static function register(): void {
		self::boot_checker();
		add_filter('upgrader_source_selection', array(self::class, 'select_source'), 1, 4);
	}

	/**
	 * setBranch('main') checks the latest GitHub release first.
	 * enableReleaseAssets() uses the uploaded zip instead of the source archive.
	 */
	private static function boot_checker(): void {
		$library = GM_DEMO_GUIDE_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if (!is_readable($library)) {
			return;
		}

		require_once $library;

		if (!class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
			return;
		}

		try {
			$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				self::REPOSITORY,
				GM_DEMO_GUIDE_FILE,
				self::SLUG
			);
		} catch (\Throwable $e) {
			return;
		}

		if (!is_object($checker) || !is_callable(array($checker, 'setBranch'))) {
			return;
		}

		$checker->setBranch('main');

		if (property_exists($checker, 'debugMode')) {
			$checker->debugMode = false;
		}

		if (!is_callable(array($checker, 'getVcsApi'))) {
			return;
		}

		$api = $checker->getVcsApi();
		if (is_object($api) && is_callable(array($api, 'enableReleaseAssets'))) {
			$api->enableReleaseAssets('/^gm-demo-guide\.zip$/');
		}
	}

	/**
	 * @param mixed $source        Unpacked package directory.
	 * @param mixed $remote_source Directory that contains the unpacked package.
	 * @param mixed $upgrader      Upgrader instance.
	 * @param mixed $hook_extra    Extra upgrade arguments.
	 * @return mixed
	 */
	public static function select_source($source = null, $remote_source = null, $upgrader = null, $hook_extra = null) {
		try {
			return self::rename_source($source, $remote_source, $upgrader, $hook_extra);
		} catch (\Throwable $e) {
			return $source;
		}
	}

	/**
	 * @param mixed $source
	 * @param mixed $remote_source
	 * @param mixed $upgrader
	 * @param mixed $hook_extra
	 * @return mixed
	 */
	private static function rename_source($source, $remote_source, $upgrader, $hook_extra) {
		if (is_wp_error($source) || !is_string($source) || $source === '') {
			return $source;
		}

		if (!is_array($hook_extra)) {
			$hook_extra = array();
		}

		$plugin_file = self::plugin_file($hook_extra, $upgrader);
		if ($plugin_file !== '' && !self::is_this_plugin($plugin_file)) {
			return $source;
		}

		if (isset($hook_extra['theme']) && is_string($hook_extra['theme']) && $hook_extra['theme'] !== '') {
			return $source;
		}

		if (isset($hook_extra['type']) && is_string($hook_extra['type']) && $hook_extra['type'] !== 'plugin') {
			return $source;
		}

		$is_ours = $plugin_file !== '' || self::source_is_this_plugin($source);
		if (!$is_ours) {
			return $source;
		}

		$from = untrailingslashit($source);
		if ($from === '') {
			return $source;
		}

		if (basename(untrailingslashit(wp_normalize_path($from))) === self::SLUG) {
			return $source;
		}

		$parent = '';
		if (is_string($remote_source) && $remote_source !== '') {
			$parent = untrailingslashit($remote_source);
		}
		if ($parent === '') {
			$parent = dirname($from);
		}

		$to = untrailingslashit(trailingslashit($parent) . self::SLUG);
		$normalized_from = untrailingslashit(wp_normalize_path($from));
		$normalized_parent = untrailingslashit(wp_normalize_path($parent));
		$normalized_to = untrailingslashit(wp_normalize_path($to));

		// A flat archive has no inner folder. Renaming the working directory
		// would break WordPress cleanup, so leave it alone.
		if ($normalized_from === $normalized_parent || $normalized_from === $normalized_to) {
			return $source;
		}

		global $wp_filesystem;
		if (!is_object($wp_filesystem) || !is_callable(array($wp_filesystem, 'move'))) {
			return $source;
		}

		$moved = $wp_filesystem->move($from, $to, true);
		if (is_wp_error($moved) || !$moved) {
			return $source;
		}

		return trailingslashit($to);
	}

	/**
	 * @param array<string, mixed> $hook_extra
	 * @param mixed                $upgrader
	 */
	private static function plugin_file(array $hook_extra, $upgrader): string {
		if (isset($hook_extra['plugin']) && is_string($hook_extra['plugin']) && $hook_extra['plugin'] !== '') {
			return $hook_extra['plugin'];
		}

		if (is_object($upgrader) && isset($upgrader->skin) && is_object($upgrader->skin) && isset($upgrader->skin->plugin) && is_string($upgrader->skin->plugin)) {
			return $upgrader->skin->plugin;
		}

		return '';
	}

	private static function is_this_plugin(string $plugin): bool {
		if ($plugin === '') {
			return false;
		}

		return wp_normalize_path($plugin) === wp_normalize_path(plugin_basename(GM_DEMO_GUIDE_FILE));
	}

	private static function source_is_this_plugin(string $source): bool {
		if ($source === '' || !function_exists('get_file_data')) {
			return false;
		}

		$main = trailingslashit($source) . 'gm-demo-guide.php';
		if (!is_readable($main)) {
			return false;
		}

		$headers = get_file_data(
			$main,
			array(
				'Name' => 'Plugin Name',
				'TextDomain' => 'Text Domain',
			),
			'plugin'
		);

		$name = isset($headers['Name']) && is_string($headers['Name']) ? $headers['Name'] : '';
		$domain = isset($headers['TextDomain']) && is_string($headers['TextDomain']) ? $headers['TextDomain'] : '';

		return $name === 'GM Demo Guide' && $domain === self::SLUG;
	}
}
