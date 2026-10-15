<?php
/**
 * Decides whether an embed URL is a same-origin screen we can frame.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (PHP_SAPI !== 'cli' && !defined('ABSPATH')) {
	exit;
}

final class EmbedUrl {
	/**
	 * Site-relative path safe to frame, or '' when the URL must be ignored.
	 *
	 * @param array<int, mixed> $blocked
	 */
	public static function allowed(string $url, string $home_url, string $current_path, array $blocked): string {
		$path = self::target_path($url, $home_url);
		if ($path === '') {
			return '';
		}
		$current = PageMatch::normalize_path($current_path);
		if ($current !== '' && $path === $current) {
			return '';
		}
		if (Blocked::contains($path, $blocked)) {
			return '';
		}
		return $path;
	}

	/**
	 * Site-relative path, or '' when the URL is not a same-origin path.
	 */
	public static function target_path(string $url, string $home_url): string {
		$url = trim($url);
		if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url)) {
			return '';
		}
		if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && !preg_match('#^https?://#i', $url)) {
			return '';
		}
		if (strpos($url, '\\') !== false || strpos($url, '//') === 0) {
			return '';
		}

		if (preg_match('#^https?://#i', $url)) {
			$parts = parse_url($url);
			$home = parse_url($home_url);
			if (!is_array($parts) || !is_array($home)) {
				return '';
			}
			if (!empty($parts['user']) || !empty($parts['pass'])) {
				return '';
			}
			$host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
			$home_host = isset($home['host']) ? strtolower((string) $home['host']) : '';
			if ($host === '' || $home_host === '' || $host !== $home_host) {
				return '';
			}
			$scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
			$home_scheme = isset($home['scheme']) ? strtolower((string) $home['scheme']) : '';
			if ($scheme === '' || $home_scheme === '' || $scheme !== $home_scheme) {
				return '';
			}
			if (self::port($scheme, $parts) !== self::port($home_scheme, $home)) {
				return '';
			}
			$path = isset($parts['path']) ? (string) $parts['path'] : '/';
			$site = PageMatch::to_site_path($path, $home_url);
			return $site === '/__gm_unmatched__/' ? '' : $site;
		}

		if ($url === '' || $url[0] !== '/') {
			return '';
		}
		return PageMatch::normalize_path($url);
	}

	/**
	 * @param array<string, mixed> $parts
	 */
	private static function port(string $scheme, array $parts): int {
		if (isset($parts['port'])) {
			return (int) $parts['port'];
		}
		if ($scheme === 'https') {
			return 443;
		}
		if ($scheme === 'http') {
			return 80;
		}
		return 0;
	}
}
