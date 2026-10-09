<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carica il widget della chat (bolla in basso) nelle pagine del sito.
 */
class CSA_Widget {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    /**
     * Stato del widget: "off", "test" (visibile solo agli amministratori
     * collegati, per provarlo prima di pubblicarlo) oppure "on".
     */
    public static function is_active_for_current_visitor() {
        $mode = get_option( 'csa_mode', 'test' );
        if ( 'on' === $mode ) {
            return true;
        }
        if ( 'test' === $mode ) {
            return current_user_can( 'manage_options' );
        }
        return false;
    }

    public function enqueue() {
        if ( ! self::is_active_for_current_visitor() || ! get_option( 'csa_api_key', '' ) ) {
            return;
        }

        wp_enqueue_style( 'csa-widget', CSA_URL . 'assets/csa-widget.css', array(), CSA_VERSION );
        wp_enqueue_script( 'csa-widget', CSA_URL . 'assets/csa-widget.js', array(), CSA_VERSION, true );

        $privacy_url = get_option( 'csa_privacy_url', '' );
        if ( ! $privacy_url && function_exists( 'get_privacy_policy_url' ) ) {
            $privacy_url = get_privacy_policy_url();
        }

        $config = array(
            'restUrl'     => esc_url_raw( rest_url( 'csa/v1/chat' ) ),
            'botName'     => get_option( 'csa_bot_name', 'Assistente' ),
            'welcome'     => get_option( 'csa_welcome', 'Ciao! Come posso aiutarti?' ),
            'color'       => get_option( 'csa_color', '#cc0000' ),
            'position'    => get_option( 'csa_position', 'right' ),
            'privacyText' => get_option( 'csa_privacy_text', 'Le domande vengono elaborate da Claude (Anthropic). Non inserire dati personali.' ),
            'privacyUrl'  => $privacy_url,
            'maxChars'    => CSA_Chat::MAX_USER_CHARS,
            'maxMessages' => CSA_Chat::MAX_MESSAGES,
        );

        // Il nonce serve solo agli utenti collegati (es. modalità test):
        // per i visitatori anonimi non viene inserito, così la pagina resta
        // compatibile con i plugin di cache.
        if ( is_user_logged_in() ) {
            $config['nonce'] = wp_create_nonce( 'wp_rest' );
        }

        wp_add_inline_script( 'csa-widget', 'window.CSA_CONFIG = ' . wp_json_encode( $config ) . ';', 'before' );
    }
}
