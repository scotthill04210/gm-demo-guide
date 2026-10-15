<?php
/**
 * Same-origin iframe shortcode.
 *
 * [gm_demo_screen url="/events/" title="Event calendar" height="640"]
 *
 * This does not register the shortcode already printed as raw text on
 * /attendees/ and /analytic-revenue/. Those pages are left unchanged.
 * A bad URL renders nothing.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (!defined('ABSPATH')) {
	exit;
}

final class Embed {
	public static function register(): void {
		add_action('init', array(self::class, 'register_shortcode'));
	}

	public static function register_shortcode(): void {
		add_shortcode('gm_demo_screen', array(self::class, 'render'));
	}

	/**
	 * @param mixed $atts
	 */
	public static function render($atts = array(), $content = null, $tag = ''): string {
		unset($content, $tag);
		try {
			return self::render_screen(is_array($atts) ? $atts : array());
		} catch (\Throwable $e) {
			return '';
		}
	}

	/**
	 * @param array<string, mixed> $atts
	 */
	private static function render_screen(array $atts): string {
		if (self::is_nested_request()) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'url' => '',
				'title' => '',
				'height' => '640',
			),
			$atts,
			'gm_demo_screen'
		);

		$url = isset($atts['url']) && is_string($atts['url']) ? $atts['url'] : '';
		$blocked = apply_filters('gm_demo_guide_blocked_paths', Blocked::defaults());
		if (!is_array($blocked)) {
			$blocked = Blocked::defaults();
		}
		$path = EmbedUrl::allowed($url, home_url('/'), self::current_path(), $blocked);
		if ($path === '') {
			return '';
		}

		$src = esc_url_raw(add_query_arg('gm_demo_embed', '1', home_url($path)));
		if ($src === '') {
			return '';
		}

		$title = isset($atts['title']) && is_string($atts['title']) ? trim(wp_strip_all_tags($atts['title'])) : '';
		if ($title === '') {
			$title = __('Embedded screen', 'gm-demo-guide');
		}
		if (function_exists('mb_substr')) {
			$title = mb_substr($title, 0, 180);
		} else {
			$title = substr($title, 0, 180);
		}

		$height = isset($atts['height']) ? (int) $atts['height'] : 0;
		if ($height < 240 || $height > 1200) {
			$height = 640;
		}

		return sprintf(
			'<iframe class="gm-demo-screen" src="%1$s" title="%2$s" height="%3$d" loading="lazy" referrerpolicy="same-origin" style="display:block;width:100%%;max-width:100%%;border:1px solid #d5dde5;border-radius:8px;background:#fff;"></iframe>',
			esc_url($src),
			esc_attr($title),
			$height
		);
	}

	private static function is_nested_request(): bool {
		return self::query_flag_is('gm_demo_embed', '1');
	}

	private static function query_flag_is(string $key, string $expected): bool {
		if (!isset($_GET[ $key ])) {
			return false;
		}
		$value = wp_unslash($_GET[ $key ]);
		return is_string($value) && $value === $expected;
	}

	private static function current_path(): string {
		$uri = '/';
		if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
			$uri = wp_unslash($_SERVER['REQUEST_URI']);
		}
		$path = parse_url($uri, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			$path = '/';
		}
		return PageMatch::to_site_path($path, home_url('/'));
	}
}
