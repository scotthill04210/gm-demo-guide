<?php
/**
 * Validates content/default.json and the matching rules that protect it.
 *
 * Usage: php bin/validate-content.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit;
}

$root = dirname(__DIR__);
require_once $root . '/includes/class-page-match.php';
require_once $root . '/includes/class-blocked.php';
require_once $root . '/includes/class-loader.php';
require_once $root . '/includes/class-embed-url.php';

use GmDemoGuide\Blocked;
use GmDemoGuide\EmbedUrl;
use GmDemoGuide\Loader;
use GmDemoGuide\PageMatch;

$failures = array();
$check = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$file = $root . '/content/default.json';
$raw = file_get_contents($file);
$check(is_string($raw) && $raw !== '', 'content file is empty');
$decoded = is_string($raw) ? json_decode($raw, true, 32) : null;
$check(is_array($decoded) && json_last_error() === JSON_ERROR_NONE, 'json_decode failed: ' . json_last_error_msg());

$content = Loader::load($file);
$check(Loader::error() === '', 'loader error: ' . Loader::error());
$check($content['version'] === 1, 'version is not 1');
$check(Loader::sanitize($content) === $content, 'sanitize is not stable');

$ids = Loader::ids($content);
preg_match_all('/"id"\s*:\s*"([^"]+)"/', (string) $raw, $id_matches);
$check(isset($id_matches[1]) && $id_matches[1] === $ids, 'loader changed the reviewed ids');
$check(count($ids) === 50, 'expected 50 ids, got ' . count($ids));
$check(count($ids) === count(array_unique($ids)), 'ids are not unique');
$check(count($content['tours']) === 1, 'expected 1 tour');
$check(isset($content['tours'][0]['steps']) && count($content['tours'][0]['steps']) === 12, 'expected 12 steps');
$check(count($content['help_panels']) === 18, 'expected 18 help panels');
$check(count($content['glossary']) === 19, 'expected 19 glossary terms');

$steps = array();
foreach ($content['tours'][0]['steps'] as $step) {
	$steps[$step['id']] = $step;
}

$expected_targets = array(
	'tour-main-01' => '.dashboard-row',
	'tour-main-02' => '#menu-main-menu',
	'tour-main-03' => '.wpv-filter-form',
	'tour-main-04' => '.js-wpv-view-layout',
	'tour-main-05' => null,
	'tour-main-06' => 'form.cred-form',
	'tour-main-07' => '.tribe-events-calendar-list',
	'tour-main-08' => '.tribe-tickets__tickets-wrapper',
	'tour-main-09' => '.cem-registration-wrap',
	'tour-main-10' => '#gform_wrapper_2',
	'tour-main-11' => '.wc-block-cart',
	'tour-main-12' => 'table.account-orders-table',
);
foreach ($expected_targets as $id => $target) {
	$check(isset($steps[$id]) && $steps[$id]['target'] === $target, 'target changed for ' . $id);
}

$expected_links = array(
	'tour-main-01' => '/',
	'tour-main-02' => '/',
	'tour-main-03' => '/members-page/',
	'tour-main-04' => '/members-page/',
	'tour-main-05' => null,
	'tour-main-06' => '/members-page/add-a-member/',
	'tour-main-07' => '/events/',
	'tour-main-08' => null,
	'tour-main-09' => '/register-georgia-advisory-council-meeting/',
	'tour-main-10' => '/membership-sign-up/',
	'tour-main-11' => '/cart/',
	'tour-main-12' => '/my-account/orders/',
);
foreach ($expected_links as $id => $link) {
	$actual = PageMatch::navigation_path($steps[$id]['page_match'], Blocked::defaults());
	$check($actual === $link, 'navigation for ' . $id . ' is ' . var_export($actual, true));
}

$check($steps['tour-main-05']['page_match'] === array('path' => '/membership/'), 'member step path was rewritten');
$check(PageMatch::matches($steps['tour-main-05']['page_match'], ctx('/membership/member-3481/')) === true, 'member prefix missed');
$check(PageMatch::matches($steps['tour-main-05']['page_match'], ctx('/membership-sign-up/')) === false, 'member prefix matched sign-up');
$check(PageMatch::matches($steps['tour-main-08']['page_match'], ctx('/event/event-6250/')) === true, 'event prefix missed');
$check(PageMatch::matches($steps['tour-main-08']['page_match'], ctx('/events/')) === false, 'event prefix matched the calendar');
$check(PageMatch::matches($steps['tour-main-01']['page_match'], ctx('/')) === true, 'home missed');
$check(PageMatch::matches($steps['tour-main-01']['page_match'], ctx('/members-page/')) === false, 'home matched another page');
$check(PageMatch::matches($steps['tour-main-03']['page_match'], ctx('/members-page/')) === true, 'member list missed');
$check(PageMatch::matches($steps['tour-main-03']['page_match'], ctx('/members-page/add-a-member/')) === false, 'member list matched a child');

$panels = array();
foreach ($content['help_panels'] as $panel) {
	$panels[$panel['id']] = $panel;
	$check(isset($panel['page_match']['path']), 'help panel has no path: ' . $panel['id']);
	$check(!Blocked::contains($panel['page_match']['path'], Blocked::defaults()), 'help panel path is blocked: ' . $panel['id']);
	$check(count($panel['tips']) > 0, 'help panel has no tips: ' . $panel['id']);
}
$check($panels['help-dashboard']['page_match']['post_type'] === null, 'null post_type was dropped');
$check($panels['help-member-detail']['page_match'] === array('path' => '/membership/', 'post_type' => 'membership'), 'member help match changed');

$check(best_help($content['help_panels'], ctx('/')) === 'help-dashboard', 'dashboard help');
$check(best_help($content['help_panels'], ctx('/members-page/')) === 'help-members-list', 'member list help');
$check(best_help($content['help_panels'], ctx('/members-page/add-a-member/')) === 'help-add-member', 'add member help');
$check(best_help($content['help_panels'], ctx('/membership/member-3481/', 'membership')) === 'help-member-detail', 'member detail help');
$check(best_help($content['help_panels'], ctx('/membership/member-3481/', '')) === null, 'member detail help without post type');
$check(best_help($content['help_panels'], ctx('/membership-sign-up/', 'page')) === 'help-membership-signup', 'sign-up help');
$check(best_help($content['help_panels'], ctx('/event/event-6250/', 'tribe_events')) === 'help-event-detail', 'event help');
$check(best_help($content['help_panels'], ctx('/events/', 'page')) === 'help-events-list', 'events help');
$check(best_help($content['help_panels'], ctx('/events/category/member-events/')) === null, 'event category should not use the list panel');
$check(best_help($content['help_panels'], ctx('/my-account/')) === 'help-my-account', 'account help');
$check(best_help($content['help_panels'], ctx('/my-account/orders/')) === 'help-my-orders', 'orders help');
$check(best_help($content['help_panels'], ctx('/my-account/edit-address/')) === null, 'address tab invented a panel');

$check(
	PageMatch::specificity(array('path' => '/my-account/orders/')) > PageMatch::specificity(array('path_prefix' => '/my-account/')),
	'exact path should outrank a prefix'
);
$check(PageMatch::matches(array('path_prefix' => '/my-account/'), ctx('/my-account/orders/')) === true, 'path_prefix missed a child');
$check(PageMatch::matches(array('path_prefix' => '/my-account/'), ctx('/my-account-extra/')) === false, 'path_prefix was too wide');
$check(
	PageMatch::matches(
		array('path' => '/events/', 'shortcode' => 'gm_demo_screen'),
		ctx('/events/', 'page', 'Before [gm_demo_screen url="/cart/"] after')
	) === true,
	'shortcode match failed'
);
$check(
	PageMatch::matches(
		array('shortcode' => 'gm_demo_screen'),
		ctx('/events/', 'page', '[gm_demo_screen_other]')
	) === false,
	'shortcode matched a longer tag'
);

$home = 'https://demo.example/';
$blocked = Blocked::defaults();
$check(EmbedUrl::allowed('/events/', $home, '/cart/', $blocked) === '/events/', 'relative embed rejected');
$check(EmbedUrl::allowed('https://demo.example/events/', $home, '/cart/', $blocked) === '/events/', 'same-origin embed rejected');
$check(EmbedUrl::allowed('https://demo.example/demo/events/', 'https://demo.example/demo/', '/cart/', $blocked) === '/events/', 'subdirectory embed rejected');
$check(EmbedUrl::allowed('https://evil.example/events/', $home, '/cart/', $blocked) === '', 'cross-origin embed allowed');
$check(EmbedUrl::allowed('//demo.example/events/', $home, '/cart/', $blocked) === '', 'protocol-relative embed allowed');
$check(EmbedUrl::allowed('javascript:alert(1)', $home, '/cart/', $blocked) === '', 'javascript embed allowed');
$check(EmbedUrl::allowed('/events/', $home, '/events/', $blocked) === '', 'self embed allowed');
$check(EmbedUrl::allowed('/attendees/', $home, '/cart/', $blocked) === '', 'broken attendees embed allowed');
$check(EmbedUrl::allowed('/analytic-revenue/', $home, '/cart/', $blocked) === '', 'broken revenue embed allowed');
$check(EmbedUrl::allowed('/wp-admin/users.php', $home, '/cart/', $blocked) === '', 'admin embed allowed');
$check(EmbedUrl::allowed('/membership-payment/', $home, '/cart/', $blocked) === '', 'payment embed allowed');
$check(EmbedUrl::allowed('/finance-dashboard/', $home, '/cart/', $blocked) === '', 'finance embed allowed');
$check(EmbedUrl::allowed('/reports/', $home, '/cart/', $blocked) === '', 'reports embed allowed');

$check(PageMatch::to_site_path('/members-page/', 'https://demo.example/') === '/members-page/', 'root site path');
$check(PageMatch::to_site_path('/demo/members-page/', 'https://demo.example/demo/') === '/members-page/', 'subdirectory site path');
$check(PageMatch::to_site_path('/demo', 'https://demo.example/demo/') === '/', 'subdirectory home');
$check(PageMatch::to_site_path('/demographics/', 'https://demo.example/demo/') === '/demographics/', 'prefix collision');
$check(PageMatch::to_site_path('/demo/../wp-admin/', 'https://demo.example/demo/') === '/__gm_unmatched__/', 'dot segments');

$check(PageMatch::navigation_path(array('path' => '/attendees/'), $blocked) === null, 'linked a blocked page');
$check(PageMatch::navigation_path(array('path' => '/wp-admin/post-new.php'), $blocked) === null, 'linked post-new');
$check(preg_match('#https?://|wp-admin|post-new\\.php|/attendees/|/finance-dashboard/|/reports/#', (string) $raw) !== 1, 'reviewed content contains a blocked link');

try {
	$bad = Loader::parse('{');
	$check($bad['tours'] === array() && Loader::error() === 'invalid JSON', 'bad JSON was not rejected');
	$bad_list = Loader::parse('[1,2]');
	$check($bad_list['glossary'] === array() && Loader::error() === 'invalid JSON', 'JSON array was accepted');
	$dup = Loader::parse('{"version":1,"tours":[{"id":"tour-main","title":"T","steps":[{"id":"dup","page_match":{"path":"/"},"target":null,"title":"A","body":"A"},{"id":"dup","page_match":{"path":"/"},"target":null,"title":"B","body":"B"}]}],"help_panels":[],"glossary":[{"id":"dup","term":"T","definition":"D"}]}');
	$check(count($dup['tours'][0]['steps']) === 1 && $dup['glossary'] === array(), 'duplicate id was kept');
	$prefix = Loader::parse('{"version":1,"tours":[],"help_panels":[{"id":"help-a","title":"T","body":"B","page_match":{"path_prefix":"/","post_type":"page","plugin":"x"}}],"glossary":[]}');
	$check(isset($prefix['help_panels'][0]['page_match']) && $prefix['help_panels'][0]['page_match'] === array('post_type' => 'page'), 'bare slash prefix was kept');
	$unknown = Loader::parse('{"version":1,"tours":[],"help_panels":[{"id":"help-b","title":"T","body":"B","page_match":{"nope":true}}],"glossary":[]}');
	$check($unknown['help_panels'] === array() && Loader::error() === '', 'unknown match should drop the item, not the file');
	$screen = Loader::parse('{"version":1,"tours":[],"help_panels":[{"id":"help-c","title":"T","body":"B","page_match":{"path":"/events/","shortcode":"gm_demo_screen"}}],"glossary":[]}');
	$check($screen['help_panels'][0]['page_match'] === array('path' => '/events/', 'shortcode' => 'gm_demo_screen'), 'shortcode key was dropped');
} catch (Throwable $e) {
	$failures[] = 'loader threw: ' . $e->getMessage();
}

$again = Loader::load($file);
$check(Loader::error() === '' && $again === $content, 'reloading the reviewed file failed');

if ($failures !== array()) {
	fwrite(STDERR, "Validation failed:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "OK\n";
echo "json: valid\n";
echo "ids: 50\n";
echo "tours: 1, steps: 12, help_panels: 18, glossary: 19\n";
echo "matcher: ok\n";
echo "embed: ok\n";
echo "loader: ok\n";

/**
 * @param array<int, array<string, mixed>> $panels
 * @param array{path:string,post_type:string,post_content:string} $context
 */
function best_help(array $panels, array $context): ?string {
	$best = null;
	$score = -1;
	foreach ($panels as $panel) {
		if (!PageMatch::matches($panel['page_match'], $context)) {
			continue;
		}
		$panel_score = PageMatch::specificity($panel['page_match']);
		if ($panel_score > $score) {
			$score = $panel_score;
			$best = $panel['id'];
		}
	}
	return $best;
}

/**
 * @return array{path:string,post_type:string,post_content:string}
 */
function ctx(string $path, string $post_type = '', string $post_content = ''): array {
	return array(
		'path' => $path,
		'post_type' => $post_type,
		'post_content' => $post_content,
	);
}
