<?php
/**
 * Plugin Name: GM Demo Guide
 * Description: Front-end tour, page help, and glossary for the Demo Growth Memberships demo. Content is loaded from content/default.json.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Demo Growth Memberships
 * License: GPL-2.0-or-later
 * Text Domain: gm-demo-guide
 *
 * @package GmDemoGuide
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

define('GM_DEMO_GUIDE_VERSION', '1.0.0');
define('GM_DEMO_GUIDE_FILE', __FILE__);
define('GM_DEMO_GUIDE_DIR', plugin_dir_path(__FILE__));
define('GM_DEMO_GUIDE_URL', plugin_dir_url(__FILE__));
define('GM_DEMO_GUIDE_CONTENT', GM_DEMO_GUIDE_DIR . 'content/default.json');

require_once GM_DEMO_GUIDE_DIR . 'includes/class-page-match.php';
require_once GM_DEMO_GUIDE_DIR . 'includes/class-blocked.php';
require_once GM_DEMO_GUIDE_DIR . 'includes/class-loader.php';
require_once GM_DEMO_GUIDE_DIR . 'includes/class-embed-url.php';
require_once GM_DEMO_GUIDE_DIR . 'includes/class-embed.php';
require_once GM_DEMO_GUIDE_DIR . 'includes/class-guide.php';

GmDemoGuide\Guide::register();
GmDemoGuide\Embed::register();
