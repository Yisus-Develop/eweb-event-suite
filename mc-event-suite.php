<?php
/**
 * Plugin Name: EWEB Event Suite
 * Description: Advanced Event Management System with Country Routing, Dual Persistence, Cron Dispatcher, and Automated Notifications. Part of the EWEB Plugin Suite.
 * Version: 1.0.0
 * Author: Yisus Develop
 * Author URI: https://github.com/Yisus-Develop
 * Plugin URI: https://enlaweb.co/
 * Text Domain: eweb-event-suite
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * EWEB Event Suite - Developed by Yisus Develop
 */

if (!defined('ABSPATH')) exit;

/**
 * Requirements Check
 */
function eweb_event_suite_check_requirements() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>EWEB Event Suite requires PHP 7.4 or higher.</p></div>';
        });
        return false;
    }
    return true;
}

if (!eweb_event_suite_check_requirements()) {
    return;
}

define('MCES_VERSION', '1.0.0');
define('MCES_FILE', __FILE__);
define('MCES_DIR', plugin_dir_path(__FILE__));
define('MCES_URL', plugin_dir_url(__FILE__));

// Initialize GitHub Updater
if (is_admin()) {
    $updater_file = MCES_DIR . 'includes/class-eweb-github-updater.php';
    if (file_exists($updater_file)) {
        require_once $updater_file;
        new EWEB_GitHub_Updater(__FILE__, 'Yisus-Develop', 'eweb-event-suite');
    }
}

require_once MCES_DIR.'includes/Core/Activator.php';
register_activation_hook(__FILE__, ['MCES\\Core\\Activator','activate']);

require_once MCES_DIR.'includes/Core/I18n.php';
require_once MCES_DIR.'includes/Core/Assets.php';
require_once MCES_DIR.'includes/Core/Settings.php';

require_once MCES_DIR.'includes/Data/DB.php';
require_once MCES_DIR.'includes/Data/PostTypes.php';
require_once MCES_DIR.'includes/Data/ACFFields.php';
require_once MCES_DIR.'includes/Data/Repositories/SubscribersRepo.php';
require_once MCES_DIR.'includes/Data/Repositories/QueueRepo.php';
require_once MCES_DIR.'includes/Data/Repositories/OutboxRepo.php';
require_once MCES_DIR.'includes/Data/Repositories/EventsRepo.php';

require_once MCES_DIR.'includes/Providers/Contracts/EmailProviderInterface.php';
require_once MCES_DIR.'includes/Providers/LocalLogProvider.php';

require_once MCES_DIR.'includes/Subscribers/CF7Hook.php';
require_once MCES_DIR.'includes/Subscribers/Unsubscribe.php';

require_once MCES_DIR.'includes/Notifications/ChangeDetector.php';
require_once MCES_DIR.'includes/Notifications/Dispatcher.php';
require_once MCES_DIR.'includes/Notifications/Cron.php';

require_once MCES_DIR.'includes/Shortcodes/Button.php';
require_once MCES_DIR.'includes/Shortcodes/EventList.php';
require_once MCES_DIR.'includes/Shortcodes/Popup.php';
require_once MCES_DIR.'includes/Shortcodes/Country.php';

// Helpers (antes del shortcode)
require_once MCES_DIR.'includes/Helpers/CountryHelper.php';


// Admin UI (solo en admin)
if ( is_admin() ) {
    require_once MCES_DIR . 'includes/Admin/Menu.php';
    \MCES\Admin\Menu::hooks();
}



// Boot
MCES\Core\I18n::load();
MCES\Core\Assets::hooks();
MCES\Core\Settings::hooks();

MCES\Data\DB::hooks();
MCES\Data\PostTypes::hooks();
MCES\Data\ACFFields::hooks();

MCES\Subscribers\CF7Hook::hooks();
MCES\Subscribers\Unsubscribe::hooks();

MCES\Notifications\ChangeDetector::hooks();
MCES\Notifications\Dispatcher::hooks();
MCES\Notifications\Cron::hooks();

MCES\Shortcodes\Button::hooks();
MCES\Shortcodes\EventList::hooks();
MCES\Shortcodes\Popup::hooks();
MCES\Shortcodes\Country::hooks();
