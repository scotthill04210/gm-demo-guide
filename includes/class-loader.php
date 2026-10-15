<?php
/**
 * Loads content/default.json and drops anything that is not safe to render.
 *
 * page_match may use path (exact), path_prefix, post_type, and shortcode.
 * A null post_type is accepted and means "do not filter on post type".
 * A broken file yields empty content and a short error string. It does not throw.
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

namespace GmDemoGuide;

if (PHP_SAPI !== 'cli' && !defined('ABSPATH')) {
	exit;
}

final class Loader {
	/** @var string */
	private static $error = '';

	public static function error(): string {
		return self::$error;
	}

	/**
	 * @return array{version:int,tours:array<int,array<string,mixed>>,help_panels:array<int,array<string,mixed>>,glossary:array<int,array<string,mixed>>}
	 */
	public static function empty_content(): array {
		return array(
			'version' => 0,
			'tours' => array(),
			'help_panels' => array(),
			'glossary' => array(),
		);
	}

	/**
	 * @return array{version:int,tours:array<int,array<string,mixed>>,help_panels:array<int,array<string,mixed>>,glossary:array<int,array<string,mixed>>}
	 */
	public static function load(string $file): array {
		self::$error = '';
		try {
			if (!is_file($file) || !is_readable($file)) {
				self::$error = 'missing file';
				return self::empty_content();
			}
			$size = filesize($file);
			if ($size === false || $size > 1048576) {
				self::$error = 'file too large';
				return self::empty_content();
			}
			$raw = self::read($file);
			if (!is_string($raw)) {
				self::$error = 'unreadable file';
				return self::empty_content();
			}
			return self::parse($raw);
		} catch (\Throwable $e) {
			self::$error = 'unreadable file';
			return self::empty_content();
		}
	}

	/**
	 * @return array{version:int,tours:array<int,array<string,mixed>>,help_panels:array<int,array<string,mixed>>,glossary:array<int,array<string,mixed>>}
	 */
	public static function parse(string $raw): array {
		self::$error = '';
		try {
			if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
				$raw = substr($raw, 3);
			}
			$data = json_decode($raw, true, 32);
			if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
				self::$error = 'invalid JSON';
				return self::empty_content();
			}
			if ($data !== array() && self::is_list($data)) {
				self::$error = 'invalid JSON';
				return self::empty_content();
			}
			return self::sanitize($data);
		} catch (\Throwable $e) {
			self::$error = 'invalid JSON';
			return self::empty_content();
		}
	}

	/**
	 * @param mixed $data
	 * @return array{version:int,tours:array<int,array<string,mixed>>,help_panels:array<int,array<string,mixed>>,glossary:array<int,array<string,mixed>>}
	 */
	public static function sanitize($data): array {
		if (!is_array($data) || self::is_list($data)) {
			return self::empty_content();
		}

		$seen = array();
		$version = (isset($data['version']) && is_int($data['version'])) ? $data['version'] : 0;

		$tours = array();
		if (isset($data['tours']) && is_array($data['tours'])) {
			foreach ($data['tours'] as $tour) {
				$clean = self::sanitize_tour($tour, $seen);
				if ($clean !== null) {
					$tours[] = $clean;
				}
			}
		}

		$panels = array();
		if (isset($data['help_panels']) && is_array($data['help_panels'])) {
			foreach ($data['help_panels'] as $panel) {
				$clean = self::sanitize_panel($panel, $seen);
				if ($clean !== null) {
					$panels[] = $clean;
				}
			}
		}

		$glossary = array();
		if (isset($data['glossary']) && is_array($data['glossary'])) {
			foreach ($data['glossary'] as $term) {
				$clean = self::sanitize_term($term, $seen);
				if ($clean !== null) {
					$glossary[] = $clean;
				}
			}
		}

		return array(
			'version' => $version,
			'tours' => $tours,
			'help_panels' => $panels,
			'glossary' => $glossary,
		);
	}

	/**
	 * @param array<string, mixed> $content
	 * @return array<int, string>
	 */
	public static function ids(array $content): array {
		$ids = array();
		if (isset($content['tours']) && is_array($content['tours'])) {
			foreach ($content['tours'] as $tour) {
				if (is_array($tour) && isset($tour['id']) && is_string($tour['id'])) {
					$ids[] = $tour['id'];
				}
				if (is_array($tour) && isset($tour['steps']) && is_array($tour['steps'])) {
					foreach ($tour['steps'] as $step) {
						if (is_array($step) && isset($step['id']) && is_string($step['id'])) {
							$ids[] = $step['id'];
						}
					}
				}
			}
		}
		if (isset($content['help_panels']) && is_array($content['help_panels'])) {
			foreach ($content['help_panels'] as $panel) {
				if (is_array($panel) && isset($panel['id']) && is_string($panel['id'])) {
					$ids[] = $panel['id'];
				}
			}
		}
		if (isset($content['glossary']) && is_array($content['glossary'])) {
			foreach ($content['glossary'] as $term) {
				if (is_array($term) && isset($term['id']) && is_string($term['id'])) {
					$ids[] = $term['id'];
				}
			}
		}
		return $ids;
	}

	/**
	 * @param mixed              $tour
	 * @param array<string,bool> $seen
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_tour($tour, array &$seen): ?array {
		if (!is_array($tour)) {
			return null;
		}
		$id = self::claim_id(isset($tour['id']) ? $tour['id'] : null, $seen);
		$title = self::text(isset($tour['title']) ? $tour['title'] : null);
		if ($id === null || $title === null || !isset($tour['steps']) || !is_array($tour['steps'])) {
			return null;
		}
		$steps = array();
		foreach ($tour['steps'] as $step) {
			$clean = self::sanitize_step($step, $seen);
			if ($clean !== null) {
				$steps[] = $clean;
			}
		}
		if ($steps === array()) {
			return null;
		}
		return array(
			'id' => $id,
			'title' => $title,
			'steps' => $steps,
		);
	}

	/**
	 * @param mixed              $step
	 * @param array<string,bool> $seen
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_step($step, array &$seen): ?array {
		if (!is_array($step)) {
			return null;
		}
		$id = self::claim_id(isset($step['id']) ? $step['id'] : null, $seen);
		$title = self::text(isset($step['title']) ? $step['title'] : null);
		$body = self::text(isset($step['body']) ? $step['body'] : null);
		$match = self::sanitize_match(isset($step['page_match']) ? $step['page_match'] : null);
		$target_ok = true;
		$target = null;
		self::read_target($step, $target, $target_ok);
		if ($id === null || $title === null || $body === null || $match === null || !$target_ok) {
			return null;
		}
		return array(
			'id' => $id,
			'page_match' => $match,
			'target' => $target,
			'title' => $title,
			'body' => $body,
		);
	}

	/**
	 * @param mixed              $panel
	 * @param array<string,bool> $seen
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_panel($panel, array &$seen): ?array {
		if (!is_array($panel)) {
			return null;
		}
		$id = self::claim_id(isset($panel['id']) ? $panel['id'] : null, $seen);
		$title = self::text(isset($panel['title']) ? $panel['title'] : null);
		$body = self::text(isset($panel['body']) ? $panel['body'] : null);
		$match = self::sanitize_match(isset($panel['page_match']) ? $panel['page_match'] : null);
		if ($id === null || $title === null || $body === null || $match === null) {
			return null;
		}
		$tips = array();
		if (isset($panel['tips']) && is_array($panel['tips'])) {
			foreach ($panel['tips'] as $tip) {
				$text = self::text($tip);
				if ($text !== null) {
					$tips[] = $text;
				}
				if (count($tips) >= 20) {
					break;
				}
			}
		}
		return array(
			'id' => $id,
			'page_match' => $match,
			'title' => $title,
			'body' => $body,
			'tips' => $tips,
		);
	}

	/**
	 * @param mixed              $term
	 * @param array<string,bool> $seen
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_term($term, array &$seen): ?array {
		if (!is_array($term)) {
			return null;
		}
		$id = self::claim_id(isset($term['id']) ? $term['id'] : null, $seen);
		$label = self::text(isset($term['term']) ? $term['term'] : null);
		$definition = self::text(isset($term['definition']) ? $term['definition'] : null);
		if ($id === null || $label === null || $definition === null) {
			return null;
		}
		return array(
			'id' => $id,
			'term' => $label,
			'definition' => $definition,
		);
	}

	/**
	 * Accepts path, path_prefix, post_type, and shortcode. Other keys are ignored.
	 *
	 * @param mixed $match
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_match($match): ?array {
		if (!is_array($match) || self::is_list($match)) {
			return null;
		}
		$clean = array();

		if (isset($match['path']) && is_string($match['path'])) {
			$path = PageMatch::normalize_path($match['path']);
			if ($path !== '') {
				$clean['path'] = $path;
			}
		}
		if (isset($match['path_prefix']) && is_string($match['path_prefix'])) {
			$prefix = PageMatch::normalize_path($match['path_prefix']);
			if ($prefix !== '' && $prefix !== '/') {
				$clean['path_prefix'] = $prefix;
			}
		}
		if (array_key_exists('post_type', $match)) {
			if ($match['post_type'] === null) {
				$clean['post_type'] = null;
			} elseif (is_string($match['post_type']) && preg_match('/^[a-z0-9_-]+$/', $match['post_type'])) {
				$clean['post_type'] = $match['post_type'];
			}
		}
		if (isset($match['shortcode']) && is_string($match['shortcode']) && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $match['shortcode'])) {
			$clean['shortcode'] = $match['shortcode'];
		}

		$active = !empty($clean['path']) || !empty($clean['path_prefix']) || !empty($clean['shortcode']);
		if (isset($clean['post_type']) && is_string($clean['post_type']) && $clean['post_type'] !== '') {
			$active = true;
		}
		return $active ? $clean : null;
	}

	/**
	 * @param array<string,mixed> $step
	 */
	private static function read_target(array $step, ?string &$target, bool &$ok): void {
		$target = null;
		$ok = true;
		if (!array_key_exists('target', $step) || $step['target'] === null) {
			return;
		}
		if (!is_string($step['target'])) {
			$ok = false;
			return;
		}
		$value = trim(strip_tags($step['target']));
		if ($value === '' || preg_match('/[\x00-\x1f\x7f]/', $value) || strlen($value) > 500) {
			$ok = $value === '';
			$target = null;
			return;
		}
		$target = $value;
	}

	/**
	 * @param mixed              $id
	 * @param array<string,bool> $seen
	 */
	private static function claim_id($id, array &$seen): ?string {
		if (!is_string($id) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id)) {
			return null;
		}
		if (isset($seen[$id])) {
			return null;
		}
		$seen[$id] = true;
		return $id;
	}

	/**
	 * @param mixed $value
	 */
	private static function text($value): ?string {
		if (!is_string($value)) {
			return null;
		}
		$value = strip_tags($value);
		$value = str_replace(array("\r\n", "\r"), "\n", $value);
		$value = trim($value);
		return $value === '' ? null : $value;
	}

	/**
	 * @param array<mixed> $data
	 */
	private static function is_list(array $data): bool {
		$i = 0;
		foreach ($data as $key => $unused) {
			unset($unused);
			if ($key !== $i) {
				return false;
			}
			$i++;
		}
		return true;
	}

	private static function read(string $file): ?string {
		set_error_handler(
			static function (): bool {
				return true;
			}
		);
		try {
			$raw = file_get_contents($file);
		} finally {
			restore_error_handler();
		}
		return is_string($raw) ? $raw : null;
	}
}
