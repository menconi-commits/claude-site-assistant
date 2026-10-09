<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$csa_options = array(
    'csa_api_key',
    'csa_mode',
    'csa_model',
    'csa_bot_name',
    'csa_welcome',
    'csa_color',
    'csa_position',
    'csa_contact_info',
    'csa_instructions',
    'csa_extra_info',
    'csa_posts_limit',
    'csa_exclude_ids',
    'csa_rate_limit',
    'csa_daily_limit',
    'csa_privacy_text',
    'csa_privacy_url',
    'csa_github_repo',
    'csa_knowledge',
    'csa_knowledge_info',
    'csa_stats',
    'csa_last_error',
);

foreach ( $csa_options as $csa_option ) {
    delete_option( $csa_option );
}

delete_transient( 'csa_update_check' );
