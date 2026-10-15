<?php
/**
 * Pages the guide must not link to or embed.
 *
 * These are the back-office screens, broken shortcode pages, and the payment
 * page that writes on load. A blocked entry matches that path and anything
 * under it. "/" is ignored so a bad filter cannot block the whole site.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (PHP_SAPI !== 'cli' && !defined('ABSPATH')) {
	exit;
}

final class Blocked {
	/**
	 * @return array<int, string>
	 */
	public static function defaults(): array {
		return array(
			'/upcoming-member-events/',
			'/tickets-checkout/',
			'/tickets-order/',
			'/finance-dashboard/',
			'/reports/',
			'/attendees/',
			'/analytic-revenue/',
			'/insert-edit-link/',
			'/edit-snippet-add-new/',
			'/edit-product/',
			'/membership-payment/',
			'/wp-admin/',
			'/wp-login.php',
		);
	}

	/**
	 * @param array<int, mixed> $blocked
	 */
	public static function contains(string $path, array $blocked): bool {
		$path = PageMatch::normalize_path($path);
		if ($path === '') {
			return true;
		}
		foreach ($blocked as $item) {
			if (!is_string($item)) {
				continue;
			}
			$item = PageMatch::normalize_path($item);
			if ($item === '' || $item === '/') {
				continue;
			}
			if ($path === $item || strpos($path, $item) === 0) {
				return true;
			}
		}
		return false;
	}
}
