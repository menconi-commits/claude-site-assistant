<?php
/**
 * Plugin Name: Claude Site Assistant
 * Plugin URI: https://www.menconi.it
 * Description: Chatbot per i visitatori del sito basato sull'API di Claude (Anthropic): risponde alle domande usando i contenuti delle tue pagine e le informazioni che inserisci tu.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Tested up to: 7.0.2
 * Author: Pietro Menconi
 * Author URI: https://www.menconi.it
 * License: GPL v2 or later
 * Text Domain: claude-site-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CSA_VERSION', '1.0.0' );
define( 'CSA_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSA_URL', plugin_dir_url( __FILE__ ) );
define( 'CSA_DEFAULT_MODEL', 'claude-opus-5-5' );

require_once CSA_DIR . 'includes/class-csa-api-client.php';
require_once CSA_DIR . 'includes/class-csa-knowledge.php';
require_once CSA_DIR . 'includes/class-csa-chat.php';
require_once CSA_DIR . 'includes/class-csa-widget.php';
require_once CSA_DIR . 'includes/class-csa-admin.php';
require_once CSA_DIR . 'includes/class-csa-updater.php';

register_activation_hook(
    __FILE__,
    function () {
        // Primo popolamento della base di conoscenza con le pagine pubblicate.
        if ( false === get_option( 'csa_knowledge', false ) ) {
            CSA_Knowledge::rebuild();
        }
    }
);

add_action(
    'plugins_loaded',
    function () {
        CSA_Chat::instance();
        CSA_Widget::instance();
        if ( is_admin() ) {
            CSA_Admin::instance();
            new CSA_Updater( __FILE__, CSA_VERSION );
        }
    }
);
