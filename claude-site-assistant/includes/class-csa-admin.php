<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pagina di amministrazione: Impostazioni > Claude Site Assistant.
 */
class CSA_Admin {

    private static $instance = null;

    private static $models = array(
        'claude-opus-5-5'   => 'Claude Opus 5.5 (massima qualità)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (equilibrato)',
        'claude-haiku-5-5'  => 'Claude Haiku 5.5 (più veloce ed economico)',
    );

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_post_csa_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_csa_rebuild_knowledge', array( $this, 'rebuild_knowledge' ) );
    }

    public function add_menu() {
        add_options_page(
            'Claude Site Assistant',
            'Claude Site Assistant',
            'manage_options',
            'csa-settings',
            array( $this, 'render_page' )
        );
    }

    private function post_value( $key, $default = '' ) {
        return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Non autorizzato.' );
        }
        check_admin_referer( 'csa_save_settings' );

        // La chiave non viene mai rimandata alla pagina: se il campo è
        // vuoto si mantiene quella già salvata.
        $api_key = trim( sanitize_text_field( $this->post_value( 'csa_api_key' ) ) );
        if ( '' !== $api_key ) {
            update_option( 'csa_api_key', $api_key );
        }

        $mode = $this->post_value( 'csa_mode', 'test' );
        update_option( 'csa_mode', in_array( $mode, array( 'off', 'test', 'on' ), true ) ? $mode : 'test' );

        $model = $this->post_value( 'csa_model', CSA_DEFAULT_MODEL );
        update_option( 'csa_model', isset( self::$models[ $model ] ) ? $model : CSA_DEFAULT_MODEL );

        update_option( 'csa_bot_name', sanitize_text_field( $this->post_value( 'csa_bot_name', 'Assistente' ) ) );
        update_option( 'csa_welcome', sanitize_textarea_field( $this->post_value( 'csa_welcome' ) ) );
        $color = sanitize_hex_color( $this->post_value( 'csa_color', '#cc0000' ) );
        update_option( 'csa_color', $color ? $color : '#cc0000' );
        update_option( 'csa_position', 'left' === $this->post_value( 'csa_position' ) ? 'left' : 'right' );

        update_option( 'csa_contact_info', sanitize_textarea_field( $this->post_value( 'csa_contact_info' ) ) );
        update_option( 'csa_instructions', sanitize_textarea_field( $this->post_value( 'csa_instructions' ) ) );
        update_option( 'csa_extra_info', sanitize_textarea_field( $this->post_value( 'csa_extra_info' ) ), false );

        update_option( 'csa_posts_limit', min( 100, max( 0, (int) $this->post_value( 'csa_posts_limit', 10 ) ) ) );
        update_option( 'csa_exclude_ids', implode( ',', CSA_Knowledge::parse_ids( $this->post_value( 'csa_exclude_ids' ) ) ) );

        update_option( 'csa_rate_limit', max( 0, (int) $this->post_value( 'csa_rate_limit', 20 ) ) );
        update_option( 'csa_daily_limit', max( 0, (int) $this->post_value( 'csa_daily_limit', 300 ) ) );

        update_option( 'csa_privacy_text', sanitize_textarea_field( $this->post_value( 'csa_privacy_text' ) ) );
        update_option( 'csa_privacy_url', esc_url_raw( $this->post_value( 'csa_privacy_url' ) ) );

        update_option( 'csa_github_repo', sanitize_text_field( $this->post_value( 'csa_github_repo' ) ) );
        delete_transient( 'csa_update_check' );

        CSA_Knowledge::rebuild();

        $this->redirect( array( 'saved' => '1' ) );
    }

    public function rebuild_knowledge() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Non autorizzato.' );
        }
        check_admin_referer( 'csa_rebuild_knowledge' );

        CSA_Knowledge::rebuild();

        $this->redirect( array( 'rebuilt' => '1' ) );
    }

    private function redirect( $args ) {
        wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'csa-settings' ), $args ), admin_url( 'options-general.php' ) ) );
        exit;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $has_key      = '' !== get_option( 'csa_api_key', '' );
        $mode         = get_option( 'csa_mode', 'test' );
        $model        = get_option( 'csa_model', CSA_DEFAULT_MODEL );
        $bot_name     = get_option( 'csa_bot_name', 'Assistente' );
        $welcome      = get_option( 'csa_welcome', 'Ciao! Come posso aiutarti?' );
        $color        = get_option( 'csa_color', '#cc0000' );
        $position     = get_option( 'csa_position', 'right' );
        $contact      = get_option( 'csa_contact_info', '' );
        $instructions = get_option( 'csa_instructions', '' );
        $extra_info   = get_option( 'csa_extra_info', '' );
        $posts_limit  = (int) get_option( 'csa_posts_limit', 10 );
        $exclude_ids  = get_option( 'csa_exclude_ids', '' );
        $rate_limit   = (int) get_option( 'csa_rate_limit', 20 );
        $daily_limit  = (int) get_option( 'csa_daily_limit', 300 );
        $privacy_text = get_option( 'csa_privacy_text', 'Le domande vengono elaborate da Claude (Anthropic). Non inserire dati personali.' );
        $privacy_url  = get_option( 'csa_privacy_url', '' );
        $github_repo  = get_option( 'csa_github_repo', '' );
        $last_error   = get_option( 'csa_last_error', '' );
        $stats        = CSA_Chat::get_stats();
        $kb_info      = get_option( 'csa_knowledge_info', array() );
        $knowledge    = get_option( 'csa_knowledge', '' );
        ?>
        <div class="wrap">
            <h1>Claude Site Assistant</h1>

            <?php if ( isset( $_GET['saved'] ) ) : ?>
                <div class="notice notice-success"><p>Impostazioni salvate e base di conoscenza aggiornata.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['rebuilt'] ) ) : ?>
                <div class="notice notice-success"><p>Base di conoscenza aggiornata.</p></div>
            <?php endif; ?>
            <?php if ( 'test' === $mode ) : ?>
                <div class="notice notice-warning"><p><strong>Modalità test:</strong> la chat è visibile solo agli amministratori collegati. Quando sei soddisfatto delle risposte, imposta lo stato su "Attivo per tutti".</p></div>
            <?php endif; ?>

            <h2>Stato</h2>
            <p>
                <strong>Domande oggi:</strong> <?php echo esc_html( $stats['today'] ); ?>
                <?php if ( $daily_limit > 0 ) : ?>(limite <?php echo esc_html( $daily_limit ); ?>)<?php endif; ?><br>
                <strong>Domande totali:</strong> <?php echo esc_html( $stats['total'] ); ?><br>
                <strong>Base di conoscenza:</strong>
                <?php if ( ! empty( $kb_info['built_at'] ) ) : ?>
                    <?php echo esc_html( $kb_info['count'] ); ?> contenuti,
                    circa <?php echo esc_html( number_format_i18n( ceil( $kb_info['chars'] / 4 ) ) ); ?> token,
                    aggiornata il <?php echo esc_html( $kb_info['built_at'] ); ?>
                    <?php if ( ! empty( $kb_info['truncated'] ) ) : ?>
                        — <span style="color:#b32d2e;">limite di dimensione raggiunto: alcune pagine sono state escluse. Riduci gli articoli o escludi le pagine meno utili.</span>
                    <?php endif; ?>
                <?php else : ?>
                    non ancora generata
                <?php endif; ?>
            </p>
            <?php if ( $last_error ) : ?>
                <p><strong>Ultimo errore API:</strong></p>
                <pre style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-width:700px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html( $last_error ); ?></pre>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
                <input type="hidden" name="action" value="csa_rebuild_knowledge">
                <?php wp_nonce_field( 'csa_rebuild_knowledge' ); ?>
                <button type="submit" class="button button-secondary">Aggiorna base di conoscenza</button>
            </form>
            <?php if ( $knowledge ) : ?>
                <details style="margin-top:12px;max-width:900px;">
                    <summary style="cursor:pointer;">Vedi il testo che l'assistente conosce</summary>
                    <textarea readonly rows="16" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( $knowledge ); ?></textarea>
                </details>
            <?php endif; ?>

            <hr>

            <h2>Impostazioni</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="csa_save_settings">
                <?php wp_nonce_field( 'csa_save_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="csa_mode">Stato della chat</label></th>
                        <td>
                            <select name="csa_mode" id="csa_mode">
                                <option value="off" <?php selected( $mode, 'off' ); ?>>Disattivata</option>
                                <option value="test" <?php selected( $mode, 'test' ); ?>>Test (solo amministratori)</option>
                                <option value="on" <?php selected( $mode, 'on' ); ?>>Attivo per tutti</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_api_key">API Key Claude (Anthropic)</label></th>
                        <td>
                            <input type="password" name="csa_api_key" id="csa_api_key" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr( '•••••••• salvata — lascia vuoto per non cambiarla' ) : ''; ?>">
                            <p class="description">Generala su <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>. Consigliato: una chiave dedicata a questo sito, con un limite di spesa mensile impostato nella console.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_model">Modello</label></th>
                        <td>
                            <select name="csa_model" id="csa_model">
                                <?php foreach ( self::$models as $id => $label ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $model, $id ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Per un sito con molte visite Haiku 5.5 costa molto meno a parità di domande; Opus 5.5 dà le risposte più accurate.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_bot_name">Nome dell'assistente</label></th>
                        <td><input type="text" name="csa_bot_name" id="csa_bot_name" value="<?php echo esc_attr( $bot_name ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="csa_welcome">Messaggio di benvenuto</label></th>
                        <td><textarea name="csa_welcome" id="csa_welcome" rows="2" cols="70"><?php echo esc_textarea( $welcome ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="csa_color">Colore</label></th>
                        <td><input type="color" name="csa_color" id="csa_color" value="<?php echo esc_attr( $color ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="csa_position">Posizione</label></th>
                        <td>
                            <select name="csa_position" id="csa_position">
                                <option value="right" <?php selected( $position, 'right' ); ?>>In basso a destra</option>
                                <option value="left" <?php selected( $position, 'left' ); ?>>In basso a sinistra</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_contact_info">Contatti da proporre</label></th>
                        <td>
                            <textarea name="csa_contact_info" id="csa_contact_info" rows="2" cols="70" placeholder="Es. email info@tuosito.it, WhatsApp https://wa.me/39..., pagina https://www.tuosito.it/contatti/"><?php echo esc_textarea( $contact ); ?></textarea>
                            <p class="description">L'assistente li indica quando non conosce la risposta o il visitatore chiede un preventivo.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_extra_info">Informazioni principali</label></th>
                        <td>
                            <textarea name="csa_extra_info" id="csa_extra_info" rows="6" cols="70" placeholder="Orari, zona servita, servizi principali, risposte alle domande frequenti..."><?php echo esc_textarea( $extra_info ); ?></textarea>
                            <p class="description">Informazioni che non sono (o non sono chiare) nelle pagine del sito.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_instructions">Istruzioni di stile (opzionale)</label></th>
                        <td><textarea name="csa_instructions" id="csa_instructions" rows="3" cols="70" placeholder="Es. Dai del tu, tono professionale ma amichevole. Proponi sempre una chiamata conoscitiva gratuita."><?php echo esc_textarea( $instructions ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="csa_posts_limit">Articoli del blog da includere</label></th>
                        <td>
                            <input type="number" min="0" max="100" name="csa_posts_limit" id="csa_posts_limit" value="<?php echo esc_attr( $posts_limit ); ?>" class="small-text">
                            <p class="description">Le pagine pubblicate sono sempre incluse; qui scegli quanti degli articoli più recenti aggiungere (0 = nessuno).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_exclude_ids">ID da escludere</label></th>
                        <td>
                            <input type="text" name="csa_exclude_ids" id="csa_exclude_ids" value="<?php echo esc_attr( $exclude_ids ); ?>" class="regular-text" placeholder="Es. 12, 45, 230">
                            <p class="description">ID di pagine o articoli da non far conoscere all'assistente (es. pagine legali molto lunghe, landing riservate).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_rate_limit">Domande per visitatore all'ora</label></th>
                        <td><input type="number" min="0" name="csa_rate_limit" id="csa_rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" class="small-text"> <span class="description">0 = nessun limite</span></td>
                    </tr>
                    <tr>
                        <th><label for="csa_daily_limit">Domande totali al giorno</label></th>
                        <td><input type="number" min="0" name="csa_daily_limit" id="csa_daily_limit" value="<?php echo esc_attr( $daily_limit ); ?>" class="small-text"> <span class="description">Tetto di sicurezza sui costi. 0 = nessun limite</span></td>
                    </tr>
                    <tr>
                        <th><label for="csa_privacy_text">Avviso privacy nella chat</label></th>
                        <td><textarea name="csa_privacy_text" id="csa_privacy_text" rows="2" cols="70"><?php echo esc_textarea( $privacy_text ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="csa_privacy_url">Link privacy policy</label></th>
                        <td>
                            <input type="url" name="csa_privacy_url" id="csa_privacy_url" value="<?php echo esc_attr( $privacy_url ); ?>" class="regular-text">
                            <p class="description">Se vuoto, usa la pagina privacy impostata in Impostazioni &gt; Privacy.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="csa_github_repo">Repository GitHub aggiornamenti (opzionale)</label></th>
                        <td>
                            <input type="text" name="csa_github_repo" id="csa_github_repo" value="<?php echo esc_attr( $github_repo ); ?>" class="regular-text" placeholder="menconi-commits/claude-site-assistant">
                            <p class="description">Formato "utente/repository". Se compilato, WordPress mostrerà il banner di aggiornamento quando pubblichi una nuova Release su GitHub con un tag di versione superiore.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Salva impostazioni' ); ?>
            </form>

            <p style="color:#646970;font-size:12px;margin-top:24px;">
                Claude Site Assistant v<?php echo esc_html( CSA_VERSION ); ?> —
                sviluppato da <a href="https://www.menconi.it" target="_blank" rel="noopener noreferrer">Pietro Menconi</a> —
                supporto: <a href="mailto:cloud@menconi.it">cloud@menconi.it</a>
            </p>
        </div>
        <?php
    }
}
