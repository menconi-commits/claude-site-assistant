<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base di conoscenza: estrae il testo delle pagine (e opzionalmente degli
 * ultimi articoli) del sito e lo salva in un'opzione, pronto per essere
 * inserito nel system prompt. Si ricostruisce dal pulsante nell'admin o al
 * salvataggio delle impostazioni, non a ogni messaggio.
 */
class CSA_Knowledge {

    // Limite di sicurezza sulla dimensione (circa 35-40 mila token): oltre
    // questa soglia ogni domanda diventerebbe inutilmente costosa.
    const MAX_CHARS = 150000;

    public static function rebuild() {
        $posts_limit  = (int) get_option( 'csa_posts_limit', 10 );
        $exclude_ids  = self::parse_ids( get_option( 'csa_exclude_ids', '' ) );
        $items        = array();

        $pages = get_posts(
            array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
                'exclude'        => $exclude_ids,
                'has_password'   => false,
            )
        );
        $items = array_merge( $items, $pages );

        if ( $posts_limit > 0 ) {
            $posts = get_posts(
                array(
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $posts_limit,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'exclude'        => $exclude_ids,
                    'has_password'   => false,
                )
            );
            $items = array_merge( $items, $posts );
        }

        $knowledge = '';
        $count     = 0;
        $truncated = false;

        foreach ( $items as $post ) {
            $text = self::extract_text( $post );
            if ( '' === $text ) {
                continue;
            }
            $section = '### ' . get_the_title( $post ) . "\nURL: " . get_permalink( $post ) . "\n" . $text . "\n\n";

            if ( strlen( $knowledge ) + strlen( $section ) > self::MAX_CHARS ) {
                $truncated = true;
                break;
            }
            $knowledge .= $section;
            $count++;
        }

        update_option( 'csa_knowledge', $knowledge, false );
        update_option(
            'csa_knowledge_info',
            array(
                'built_at'  => current_time( 'mysql' ),
                'count'     => $count,
                'chars'     => strlen( $knowledge ),
                'truncated' => $truncated,
            ),
            false
        );

        return $count;
    }

    /**
     * Testo leggibile di una pagina. Divi salva il contenuto dentro gli
     * shortcode [et_pb_...]: si rimuovono solo le "parentesi" degli
     * shortcode, mantenendo il testo che racchiudono (strip_shortcodes
     * invece eliminerebbe anche il contenuto).
     */
    private static function extract_text( $post ) {
        $raw  = $post->post_content;
        $text = self::clean( preg_replace( '/\[\/?[a-zA-Z0-9_\-]+[^\]]*\]/', ' ', $raw ) );

        // Contenuti salvati come blocchi con attributi JSON (es. Divi 5):
        // se la pulizia semplice non lascia quasi nulla, si prova il rendering.
        if ( strlen( $text ) < 100 && strlen( $raw ) > 200 ) {
            $rendered = apply_filters( 'the_content', $raw );
            $text     = self::clean( $rendered );
        }

        return $text;
    }

    private static function clean( $html ) {
        // I moduli Codice di Divi possono contenere CSS e script inline.
        $html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $html );
        $text = wp_strip_all_tags( $html );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = preg_replace( '/[ \t\x{00A0}]+/u', ' ', $text );
        $text = preg_replace( '/\s*\n\s*/', "\n", $text );
        return trim( $text );
    }

    public static function parse_ids( $value ) {
        return array_values( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) );
    }
}
