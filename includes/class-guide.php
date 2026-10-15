<?php
/**
 * Front-end guide: tour, help panel, and glossary.
 *
 * Progress is stored in the browser. The plugin does not write to the database.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (!defined('ABSPATH')) {
	exit;
}

final class Guide {
	public static function register(): void {
		add_action('init', array(self::class, 'load_textdomain'));
		add_action('admin_notices', array(self::class, 'admin_notice'));
		add_action('wp_enqueue_scripts', array(self::class, 'enqueue'));
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain('gm-demo-guide', false, dirname(plugin_basename(GM_DEMO_GUIDE_FILE)) . '/languages');
	}

	public static function admin_notice(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		try {
			Loader::load(GM_DEMO_GUIDE_CONTENT);
		} catch (\Throwable $e) {
			return;
		}
		$reason = Loader::error();
		if ($reason === '') {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: short reason the content file was rejected */
				__('GM Demo Guide is off because content/default.json could not be read (%s). The rest of the site is unchanged.', 'gm-demo-guide'),
				$reason
			)
		);
		echo '</p></div>';
	}

	public static function enqueue(): void {
		try {
			self::enqueue_assets();
		} catch (\Throwable $e) {
			return;
		}
	}

	private static function enqueue_assets(): void {
		$show = self::should_display();
		$embed_css = self::page_has_embed_shortcode();
		if (!$show && !$embed_css) {
			return;
		}

		wp_enqueue_style(
			'gm-demo-guide',
			GM_DEMO_GUIDE_URL . 'assets/guide.css',
			array(),
			GM_DEMO_GUIDE_VERSION
		);
		if (!$show) {
			return;
		}

		$payload = self::payload();
		if ($payload === null) {
			return;
		}
		$json = wp_json_encode($payload);
		if (!is_string($json)) {
			return;
		}

		wp_register_script(
			'gm-demo-guide',
			GM_DEMO_GUIDE_URL . 'assets/guide.js',
			array(),
			GM_DEMO_GUIDE_VERSION,
			true
		);
		wp_add_inline_script('gm-demo-guide', 'window.gmDemoGuide = ' . $json . ';', 'before');
		wp_enqueue_script('gm-demo-guide');
	}

	private static function should_display(): bool {
		$show = !is_admin() && is_user_logged_in() && !is_feed() && !self::is_embed_request();
		if (function_exists('wp_is_json_request') && wp_is_json_request()) {
			$show = false;
		}
		return (bool) apply_filters('gm_demo_guide_should_display', $show);
	}

	private static function is_embed_request(): bool {
		if (!isset($_GET['gm_demo_embed'])) {
			return false;
		}
		$value = wp_unslash($_GET['gm_demo_embed']);
		return is_string($value) && $value === '1';
	}

	private static function page_has_embed_shortcode(): bool {
		$post = get_post();
		if (!$post instanceof \WP_Post) {
			return false;
		}
		return has_shortcode((string) $post->post_content, 'gm_demo_screen');
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function payload(): ?array {
		$content = Loader::load(GM_DEMO_GUIDE_CONTENT);
		if (Loader::error() !== '') {
			return null;
		}
		$filtered = apply_filters('gm_demo_guide_content', $content);
		if (!is_array($filtered)) {
			$filtered = Loader::empty_content();
		}
		$content = Loader::sanitize($filtered);
		if ($content['tours'] === array() && $content['help_panels'] === array() && $content['glossary'] === array()) {
			return null;
		}

		$blocked = apply_filters('gm_demo_guide_blocked_paths', Blocked::defaults());
		if (!is_array($blocked)) {
			$blocked = Blocked::defaults();
		}

		$context = self::context();
		$tour = null;
		if (isset($content['tours'][0]) && is_array($content['tours'][0])) {
			$tour = self::tour_payload($content['tours'][0], $context, $blocked);
		}

		return array(
			'currentPath' => $context['path'],
			'tour' => $tour,
			'help' => self::help_payload($content['help_panels'], $context),
			'glossary' => self::glossary_payload($content['glossary']),
			'strings' => self::strings(),
		);
	}

	/**
	 * @return array{path:string,post_type:string,post_content:string}
	 */
	private static function context(): array {
		$uri = '/';
		if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
			$uri = wp_unslash($_SERVER['REQUEST_URI']);
		}
		$path = parse_url($uri, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			$path = '/';
		}

		$post_type = '';
		$post_content = '';
		$queried = get_queried_object();
		if ($queried instanceof \WP_Post) {
			$post_type = (string) $queried->post_type;
			$post_content = (string) $queried->post_content;
		} elseif (is_singular()) {
			$singular = get_post_type();
			if (is_string($singular)) {
				$post_type = $singular;
			}
		} elseif (is_post_type_archive()) {
			$archive = get_query_var('post_type');
			if (is_string($archive)) {
				$post_type = $archive;
			} elseif (is_array($archive) && isset($archive[0]) && is_string($archive[0])) {
				$post_type = $archive[0];
			}
		}

		return array(
			'path' => PageMatch::to_site_path($path, home_url('/')),
			'post_type' => $post_type,
			'post_content' => $post_content,
		);
	}

	/**
	 * @param array<string, mixed>              $tour
	 * @param array{path:string,post_type:string,post_content:string} $context
	 * @param array<int, mixed>                 $blocked
	 * @return array<string, mixed>|null
	 */
	private static function tour_payload(array $tour, array $context, array $blocked): ?array {
		if (!isset($tour['steps']) || !is_array($tour['steps'])) {
			return null;
		}
		$steps = array();
		foreach ($tour['steps'] as $step) {
			if (!is_array($step) || !isset($step['page_match']) || !is_array($step['page_match'])) {
				continue;
			}
			$nav = PageMatch::navigation_path($step['page_match'], $blocked);
			$href = null;
			if ($nav !== null) {
				$raw = esc_url_raw(home_url($nav));
				$href = $raw === '' ? null : $raw;
			}
			$steps[] = array(
				'id' => isset($step['id']) ? (string) $step['id'] : '',
				'title' => isset($step['title']) ? (string) $step['title'] : '',
				'body' => isset($step['body']) ? (string) $step['body'] : '',
				'target' => isset($step['target']) && is_string($step['target']) ? $step['target'] : null,
				'matches' => PageMatch::matches($step['page_match'], $context),
				'href' => $href,
			);
		}
		if ($steps === array()) {
			return null;
		}
		return array(
			'id' => isset($tour['id']) ? (string) $tour['id'] : '',
			'title' => isset($tour['title']) ? (string) $tour['title'] : '',
			'steps' => $steps,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $panels
	 * @param array{path:string,post_type:string,post_content:string} $context
	 * @return array<string, mixed>|null
	 */
	private static function help_payload(array $panels, array $context): ?array {
		$best = null;
		$best_score = -1;
		foreach ($panels as $panel) {
			if (!is_array($panel) || !isset($panel['page_match']) || !is_array($panel['page_match'])) {
				continue;
			}
			if (!PageMatch::matches($panel['page_match'], $context)) {
				continue;
			}
			$score = PageMatch::specificity($panel['page_match']);
			if ($score > $best_score) {
				$best = $panel;
				$best_score = $score;
			}
		}
		if ($best === null) {
			return null;
		}
		$tips = array();
		if (isset($best['tips']) && is_array($best['tips'])) {
			foreach ($best['tips'] as $tip) {
				if (is_string($tip) && $tip !== '') {
					$tips[] = $tip;
				}
			}
		}
		return array(
			'id' => isset($best['id']) ? (string) $best['id'] : '',
			'title' => isset($best['title']) ? (string) $best['title'] : '',
			'body' => isset($best['body']) ? (string) $best['body'] : '',
			'tips' => $tips,
		);
	}

	/**
	 * @param array<int, mixed> $terms
	 * @return array<int, array<string, string>>
	 */
	private static function glossary_payload(array $terms): array {
		$out = array();
		foreach ($terms as $term) {
			if (!is_array($term)) {
				continue;
			}
			$out[] = array(
				'id' => isset($term['id']) ? (string) $term['id'] : '',
				'term' => isset($term['term']) ? (string) $term['term'] : '',
				'definition' => isset($term['definition']) ? (string) $term['definition'] : '',
			);
		}
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private static function strings(): array {
		return array(
			'guide' => __('Guide', 'gm-demo-guide'),
			'tourGuide' => __('Tour Guide', 'gm-demo-guide'),
			'close' => __('Close', 'gm-demo-guide'),
			'closeGuide' => __('Close the guide', 'gm-demo-guide'),
			'thisPage' => __('This page', 'gm-demo-guide'),
			'glossary' => __('Glossary', 'gm-demo-guide'),
			'tour' => __('Tour', 'gm-demo-guide'),
			'start' => __('Start the tour', 'gm-demo-guide'),
			'resume' => __('Resume the tour', 'gm-demo-guide'),
			'restart' => __('Start over', 'gm-demo-guide'),
			'end' => __('End tour', 'gm-demo-guide'),
			'back' => __('Back', 'gm-demo-guide'),
			'next' => __('Next', 'gm-demo-guide'),
			'continue' => __('Continue', 'gm-demo-guide'),
			'done' => __('Done', 'gm-demo-guide'),
			/* translators: 1: current step number, 2: total steps. */
			'step' => __('Step %1$s of %2$s', 'gm-demo-guide'),
			'noHelp' => __('This page has no extra notes. The glossary explains the words used in the demo.', 'gm-demo-guide'),
			'tryThis' => __('Try this', 'gm-demo-guide'),
			'search' => __('Search the glossary', 'gm-demo-guide'),
			'noTerms' => __('No matching terms.', 'gm-demo-guide'),
			'handoff' => __('This part of the tour is on another page. Open that page and the tour will continue there.', 'gm-demo-guide'),
			'tourIntro' => __('A short tour of the demo. You can leave it and come back.', 'gm-demo-guide'),
			'noTour' => __('No tour is available.', 'gm-demo-guide'),
		);
	}
}
