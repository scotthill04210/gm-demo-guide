<?php
/**
 * Path normalization and page_match rules.
 *
 * The reviewed content file uses "path" for both exact pages and two prefixes
 * (/membership/ and /event/). Those two values stay stored as "path" and are
 * compared as prefixes. Every other "path" is exact, which keeps "/" and
 * "/members-page/" from matching their child pages.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (PHP_SAPI !== 'cli' && !defined('ABSPATH')) {
	exit;
}

final class PageMatch {
	/**
	 * Shipped path values that are prefixes, not exact URLs.
	 */
	public const LEGACY_PREFIX_PATHS = array(
		'/membership/',
		'/event/',
	);

	public static function normalize_path(string $path): string {
		$path = trim($path);
		if ($path === '') {
			return '';
		}
		if (strpos($path, '\\') !== false || strpos($path, "\0") !== false) {
			return '';
		}
		if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) || strpos($path, '//') === 0) {
			return '';
		}

		$path = substr($path, 0, strcspn($path, '?#'));
		$path = rawurldecode($path);
		if ($path === '' || strpos($path, "\0") !== false || $path[0] !== '/') {
			return '';
		}
		if (preg_match('#/(?:\.|\.\.)(?:/|$)#', $path)) {
			return '';
		}

		$collapsed = preg_replace('#/+#', '/', $path);
		if (!is_string($collapsed) || $collapsed === '') {
			return '';
		}
		$path = strtolower($collapsed);
		if ($path !== '/') {
			$path = rtrim($path, '/') . '/';
		}
		return $path;
	}

	/**
	 * Turn a browser path into a site-relative path. Subdirectory installs
	 * keep the site folder in the request URI; content paths do not.
	 */
	public static function to_site_path(string $request_path, string $home_url): string {
		$path = self::remove_home_prefix($request_path, $home_url);
		$normalized = self::normalize_path($path);
		if ($normalized !== '') {
			return $normalized;
		}
		$trim = trim($path);
		if ($trim === '' || $trim === '/') {
			return '/';
		}
		return '/__gm_unmatched__/';
	}

	public static function is_legacy_prefix(string $path): bool {
		$path = self::normalize_path($path);
		return in_array($path, self::LEGACY_PREFIX_PATHS, true);
	}

	/**
	 * @param array<string, mixed> $match
	 * @param array<string, mixed> $context path, post_type, post_content.
	 */
	public static function matches(array $match, array $context): bool {
		$path = isset($context['path']) && is_string($context['path'])
			? self::normalize_path($context['path'])
			: '';
		if ($path === '') {
			$path = '/';
		}
		$post_type = isset($context['post_type']) && is_string($context['post_type'])
			? $context['post_type']
			: '';
		$post_content = isset($context['post_content']) && is_string($context['post_content'])
			? $context['post_content']
			: '';

		$checked = 0;

		if (!empty($match['path']) && is_string($match['path'])) {
			$checked++;
			$want = self::normalize_path($match['path']);
			if ($want === '') {
				return false;
			}
			if (self::is_legacy_prefix($want)) {
				if (strpos($path, $want) !== 0) {
					return false;
				}
			} elseif ($path !== $want) {
				return false;
			}
		}

		if (!empty($match['path_prefix']) && is_string($match['path_prefix'])) {
			$checked++;
			$prefix = self::normalize_path($match['path_prefix']);
			if ($prefix === '' || $prefix === '/' || strpos($path, $prefix) !== 0) {
				return false;
			}
		}

		if (array_key_exists('post_type', $match) && $match['post_type'] !== null && $match['post_type'] !== '') {
			$checked++;
			if (!is_string($match['post_type']) || $post_type !== $match['post_type']) {
				return false;
			}
		}

		if (!empty($match['shortcode']) && is_string($match['shortcode'])) {
			$checked++;
			if (!self::content_has_shortcode($post_content, $match['shortcode'])) {
				return false;
			}
		}

		return $checked > 0;
	}

	/**
	 * Higher scores win when more than one help panel matches.
	 *
	 * @param array<string, mixed> $match
	 */
	public static function specificity(array $match): int {
		$score = 0;
		if (!empty($match['path']) && is_string($match['path'])) {
			$want = self::normalize_path($match['path']);
			if ($want !== '') {
				$score += self::is_legacy_prefix($want) ? 200 + strlen($want) : 1000 + strlen($want);
			}
		}
		if (!empty($match['path_prefix']) && is_string($match['path_prefix'])) {
			$prefix = self::normalize_path($match['path_prefix']);
			if ($prefix !== '' && $prefix !== '/') {
				$score += 200 + strlen($prefix);
			}
		}
		if (isset($match['post_type']) && is_string($match['post_type']) && $match['post_type'] !== '') {
			$score += 80;
		}
		if (!empty($match['shortcode']) && is_string($match['shortcode'])) {
			$score += 80;
		}
		return $score;
	}

	/**
	 * Page the tour may open. Prefix-only steps and blocked pages return null.
	 *
	 * @param array<string, mixed> $match
	 * @param array<int, string>   $blocked
	 */
	public static function navigation_path(array $match, array $blocked): ?string {
		if (empty($match['path']) || !is_string($match['path'])) {
			return null;
		}
		$path = self::normalize_path($match['path']);
		if ($path === '' || self::is_legacy_prefix($path)) {
			return null;
		}
		if (Blocked::contains($path, $blocked)) {
			return null;
		}
		return $path;
	}

	public static function content_has_shortcode(string $content, string $tag): bool {
		if ($content === '' || $tag === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $tag)) {
			return false;
		}
		$pattern = '/\[' . preg_quote($tag, '/') . '(?:\s|\])/';
		return preg_match($pattern, $content) === 1;
	}

	private static function remove_home_prefix(string $request_path, string $home_url): string {
		$path = substr($request_path, 0, strcspn($request_path, '?#'));
		$home = parse_url($home_url);
		$home_path = (is_array($home) && isset($home['path']) && is_string($home['path'])) ? $home['path'] : '';
		$prefix = self::normalize_path($home_path === '' ? '/' : $home_path);
		if ($prefix === '' || $prefix === '/') {
			return $path;
		}

		$prefix_noslash = rtrim($prefix, '/');
		$lower = strtolower($path);
		if ($lower === $prefix_noslash || strpos($lower, $prefix_noslash . '/') === 0) {
			$path = substr($path, strlen($prefix_noslash));
			if ($path === '') {
				$path = '/';
			}
		}
		return $path;
	}
}
