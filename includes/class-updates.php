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

	private const LOADER = 'gm-demo-guide-updates.php';

	/** @var object|null */
	private static $checker;

	public static function register(): void {
		self::boot_checker();
		self::ensure_network_loader();
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

		self::$checker = $checker;

		$file = plugin_basename(GM_DEMO_GUIDE_FILE);
		add_filter('plugin_action_links_' . $file, array(self::class, 'add_site_check_link'));
		add_filter('network_admin_plugin_action_links_' . $file, array(self::class, 'add_network_check_link'));
		// The library also adds this link under the description. Keep a single link in the action row.
		add_filter('puc_manual_check_link-' . self::SLUG, array(self::class, 'hide_library_link'));
		// Run before the library so Network Admin stays on /wp-admin/network/plugins.php.
		add_action('admin_init', array(self::class, 'check_from_network'), 1);
		add_action('all_admin_notices', array(self::class, 'show_network_result'));
	}

	/**
	 * @param mixed $links
	 * @return mixed
	 */
	public static function add_site_check_link($links) {
		return self::append_check_link($links, admin_url('plugins.php'));
	}

	/**
	 * @param mixed $links
	 * @return mixed
	 */
	public static function add_network_check_link($links) {
		return self::append_check_link($links, network_admin_url('plugins.php'));
	}

	/**
	 * @param mixed  $links
	 * @param string $plugins_url
	 * @return mixed
	 */
	private static function append_check_link($links, $plugins_url) {
		if (!is_array($links) || !is_string($plugins_url) || $plugins_url === '' || !self::user_can_check()) {
			return $links;
		}

		// wp_nonce_url() already escapes the href.
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'puc_check_for_updates' => '1',
					'puc_slug' => self::SLUG,
				),
				$plugins_url
			),
			'puc_check_for_updates'
		);

		$links[] = '<a href="' . $url . '">' . esc_html__('Check for updates', 'gm-demo-guide') . '</a>';
		return $links;
	}

	/**
	 * Network Admin does not load a plugin that is active only on a subsite.
	 * A must-use loader brings the updater onto /wp-admin/network/plugins.php.
	 */
	private static function ensure_network_loader(): void {
		if (!function_exists('is_multisite') || !is_multisite() || !defined('WPMU_PLUGIN_DIR')) {
			return;
		}

		$dir = WPMU_PLUGIN_DIR;
		$file = trailingslashit($dir) . self::LOADER;
		$source = self::network_loader_source();
		if (is_readable($file)) {
			$current = file_get_contents($file);
			if (is_string($current) && $current === $source) {
				return;
			}
		}

		if (!is_dir($dir) && !wp_mkdir_p($dir)) {
			return;
		}

		file_put_contents($file, $source);
	}

	public static function remove_network_loader(): void {
		if (!defined('WPMU_PLUGIN_DIR')) {
			return;
		}
		$file = trailingslashit(WPMU_PLUGIN_DIR) . self::LOADER;
		if (is_file($file)) {
			unlink($file);
		}
	}

	private static function network_loader_source(): string {
		$relative = var_export(plugin_basename(GM_DEMO_GUIDE_FILE), true);

		return <<<PHP
<?php
/**
 * GM Demo Guide network update loader.
 *
 * Loads the plugin in Network Admin so Check for updates works on
 * wp-admin/network/plugins.php when the plugin is active on a subsite only.
 * It does nothing on the front end or in a site dashboard.
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!defined('WP_NETWORK_ADMIN') || !WP_NETWORK_ADMIN || defined('GM_DEMO_GUIDE_FILE')) {
	return;
}

\$plugin = WP_PLUGIN_DIR . '/' . {$relative};
if (is_string(\$plugin) && is_readable(\$plugin)) {
	require_once \$plugin;
}

PHP;
	}

	/**
	 * Check from the network plugins screen and return there.
	 */
	public static function check_from_network(): void {
		if (!is_network_admin() || !self::user_can_check()) {
			return;
		}

		$slug = isset($_GET['puc_slug']) ? wp_unslash($_GET['puc_slug']) : '';
		if (!isset($_GET['puc_check_for_updates']) || !is_string($slug) || $slug !== self::SLUG) {
			return;
		}

		check_admin_referer('puc_check_for_updates');

		wp_safe_redirect(
			add_query_arg(
				array(
					'puc_update_check_result' => self::check_status(),
					'puc_slug' => self::SLUG,
				),
				network_admin_url('plugins.php')
			)
		);
		exit;
	}

	/**
	 * The library prints this result only for users who can update plugins.
	 * Network administrators who manage plugins still need the same message.
	 */
	public static function show_network_result(): void {
		if (!is_network_admin() || current_user_can('update_plugins') || !self::user_can_check()) {
			return;
		}

		$slug = isset($_GET['puc_slug']) ? wp_unslash($_GET['puc_slug']) : '';
		$result = isset($_GET['puc_update_check_result']) ? wp_unslash($_GET['puc_update_check_result']) : '';
		if (!is_string($slug) || $slug !== self::SLUG || !is_string($result)) {
			return;
		}

		$status = sanitize_key($result);
		if ($status === 'update_available') {
			$message = __('A new version of the GM Demo Guide plugin is available.', 'gm-demo-guide');
			$class = 'notice-success';
		} elseif ($status === 'no_update') {
			$message = __('The GM Demo Guide plugin is up to date.', 'gm-demo-guide');
			$class = 'notice-success';
		} elseif ($status === 'error') {
			$message = __('Could not determine if updates are available for GM Demo Guide.', 'gm-demo-guide');
			$class = 'notice-error';
		} else {
			return;
		}

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr($class),
			esc_html($message)
		);
	}

	private static function check_status(): string {
		if (!is_object(self::$checker) || !is_callable(array(self::$checker, 'checkForUpdates'))) {
			return 'error';
		}

		try {
			$update = self::$checker->checkForUpdates();
		} catch (\Throwable $e) {
			return 'error';
		}

		if ($update !== null) {
			return 'update_available';
		}

		if (!is_callable(array(self::$checker, 'getLastRequestApiErrors'))) {
			return 'no_update';
		}

		$errors = self::$checker->getLastRequestApiErrors();
		if (!is_array($errors)) {
			return 'no_update';
		}

		$ignored = array(
			'puc-github-http-error',
			'puc-gitlab-http-error',
			'puc-bitbucket-http-error',
		);
		foreach ($errors as $item) {
			if (!is_array($item) || !isset($item['error']) || !is_wp_error($item['error'])) {
				continue;
			}
			if (!in_array($item['error']->get_error_code(), $ignored, true)) {
				return 'error';
			}
		}

		return 'no_update';
	}

	private static function user_can_check(): bool {
		if (current_user_can('update_plugins')) {
			return true;
		}

		return is_multisite() && current_user_can('manage_network_plugins');
	}

	/**
	 * @return string
	 */
	public static function hide_library_link() {
		return '';
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
