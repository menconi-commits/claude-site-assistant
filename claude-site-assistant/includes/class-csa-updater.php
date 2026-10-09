<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Controllo aggiornamenti tramite le Release di un repository GitHub,
 * senza librerie esterne (stessa logica usata in GDrive Daily Backup).
 *
 * Requisiti sul repository GitHub:
 * - una Release pubblicata con tag tipo "1.1.0" o "v1.1.0";
 * - un file .zip allegato alla release (Assets), con dentro la cartella
 *   "claude-site-assistant/..." come lo zip di installazione.
 */
class CSA_Updater {

    private $plugin_file;
    private $plugin_slug;
    private $current_version;

    public function __construct( $plugin_file, $current_version ) {
        $this->plugin_file     = plugin_basename( $plugin_file );
        $this->plugin_slug     = dirname( $this->plugin_file );
        $this->current_version = $current_version;

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
    }

    private function get_repo() {
        return trim( get_option( 'csa_github_repo', '' ) );
    }

    public function clear_cache() {
        delete_transient( 'csa_update_check' );
    }

    private function fetch_latest_release() {
        $repo = $this->get_repo();
        if ( empty( $repo ) ) {
            return null;
        }

        $cached = get_transient( 'csa_update_check' );
        if ( false !== $cached ) {
            return $cached;
        }

        $response = wp_remote_get(
            "https://api.github.com/repos/{$repo}/releases/latest",
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress-CSA-Updater',
                ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        set_transient( 'csa_update_check', $data, 12 * HOUR_IN_SECONDS );
        return $data;
    }

    private function get_asset_url( $release ) {
        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( isset( $asset['browser_download_url'], $asset['name'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
                    return $asset['browser_download_url'];
                }
            }
        }
        return null;
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->fetch_latest_release();
        if ( ! $release || empty( $release['tag_name'] ) ) {
            return $transient;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );
        $download_url   = $this->get_asset_url( $release );

        if ( $download_url && version_compare( $remote_version, $this->current_version, '>' ) ) {
            $item               = new stdClass();
            $item->slug         = $this->plugin_slug;
            $item->plugin       = $this->plugin_file;
            $item->new_version  = $remote_version;
            $item->url          = isset( $release['html_url'] ) ? $release['html_url'] : '';
            $item->package      = $download_url;
            $item->tested       = get_bloginfo( 'version' );

            $transient->response[ $this->plugin_file ] = $item;
        }

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $release = $this->fetch_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $info                = new stdClass();
        $info->name          = 'Claude Site Assistant';
        $info->slug          = $this->plugin_slug;
        $info->version       = ltrim( $release['tag_name'], 'vV' );
        $info->download_link = $this->get_asset_url( $release );
        $info->sections      = array(
            'description' => 'Chatbot per i visitatori del sito basato sull\'API di Claude.',
            'changelog'   => isset( $release['body'] ) ? wpautop( wp_kses_post( $release['body'] ) ) : '',
        );

        return $info;
    }
}
