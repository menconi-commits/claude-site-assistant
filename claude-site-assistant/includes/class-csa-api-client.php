<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Client minimale per l'API Messages di Anthropic (Claude), senza librerie
 * esterne: usa solo le funzioni HTTP di WordPress (stessa base di
 * Claude Content Generator, adattata alla conversazione a più turni).
 */
class CSA_Api_Client {

    private $api_key;
    private $model;

    // Modelli per cui è disponibile il fallback lato server in caso di
    // rifiuto da parte dei classificatori di sicurezza.
    private static $fallback_models = array( 'claude-opus-5-5', 'claude-sonnet-5-5' );

    public function __construct( $api_key, $model ) {
        if ( empty( $api_key ) ) {
            throw new Exception( 'API key di Claude non impostata.' );
        }
        $this->api_key = $api_key;
        $this->model   = $model ? $model : CSA_DEFAULT_MODEL;
    }

    /**
     * Invia la conversazione a Claude.
     *
     * Il system prompt (istruzioni + base di conoscenza) cambia solo quando
     * l'amministratore salva le impostazioni: è marcato con cache_control,
     * così dalla seconda domanda in poi viene letto dalla cache a costo
     * ridotto invece di essere pagato per intero a ogni messaggio.
     *
     * @return array { text: string, content: array, stop_reason: string }
     */
    public function chat( $system_text, array $messages, $max_tokens = 4000 ) {
        $body = array(
            'model'         => $this->model,
            'max_tokens'    => $max_tokens,
            'system'        => array(
                array(
                    'type'          => 'text',
                    'text'          => $system_text,
                    'cache_control' => array( 'type' => 'ephemeral' ),
                ),
            ),
            'messages'      => $messages,
            // Per una chat di assistenza basta un ragionamento leggero:
            // risposte più rapide e meno token.
            'output_config' => array( 'effort' => 'low' ),
        );

        $headers = array(
            'x-api-key'         => $this->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        );

        if ( in_array( $this->model, self::$fallback_models, true ) ) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks']         = 'default';
        }

        $response = wp_remote_post(
            'https://api.anthropic.com/v1/messages',
            array(
                'timeout' => 60,
                'headers' => $headers,
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Errore nella richiesta a Claude: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : substr( wp_remote_retrieve_body( $response ), 0, 500 );
            throw new Exception( 'Claude ha risposto con un errore (HTTP ' . $code . '): ' . $error_msg );
        }

        $content     = ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) ? $data['content'] : array();
        $stop_reason = isset( $data['stop_reason'] ) ? $data['stop_reason'] : '';

        // La risposta può contenere blocchi "thinking" oltre a quelli "text":
        // per il visitatore si concatenano solo i blocchi di testo, ma il
        // contenuto completo va rimandato invariato nei turni successivi.
        $text = '';
        foreach ( $content as $block ) {
            if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
                $text .= $block['text'];
            }
        }

        return array(
            'text'        => $text,
            'content'     => $content,
            'stop_reason' => $stop_reason,
        );
    }
}
