<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$botzio_ai_delete_data = get_option(
    'botzio_ai_delete_data_on_uninstall',
    'no'
);

if ( 'yes' !== $botzio_ai_delete_data ) {
    return;
}

$botzio_ai_options = array(
    'botzio_ai_provider',
    'botzio_ai_api_key',
    'botzio_ai_model',
    'botzio_ai_custom_prompt',
    'botzio_ai_error_message',
    'botzio_ai_welcome_message',
    'botzio_ai_knowledge_base',
    'botzio_ai_display_chatbot',
    'botzio_ai_temperature',
    'botzio_ai_max_tokens',
    'botzio_ai_daily_limit',
    'botzio_ai_starter_suggestions',
    'botzio_ai_total_chats',
    'botzio_ai_total_questions',
    'botzio_ai_recent_questions',
    'botzio_ai_connection_verified',
    'botzio_ai_connection_verified_provider',
    'botzio_ai_connection_verified_model',
    'botzio_ai_connection_verified_signature',
    'botzio_ai_cached_models',
    'botzio_ai_show_welcome',
    'botzio_ai_installed_once',
    'botzio_ai_chat_title',
    'botzio_ai_primary_color',
    'botzio_ai_bubble_position',
    'botzio_ai_delete_data_on_uninstall',
);

foreach ( $botzio_ai_options as $botzio_ai_option ) {
    delete_option( $botzio_ai_option );
}
