<?php
/*
Plugin Name: Botzio AI - Smart Chatbot & Website Assistant
Description: Botzio AI helps website owners add an AI-powered support assistant to answer visitor questions using site-specific business information.
Version: 1.0.1
Requires at least: 6.0
Requires PHP: 7.4
Author: Faraz Shoaib
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: botzio-ai
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'BOTZIO_AI_VERSION' ) ) {
    define( 'BOTZIO_AI_VERSION', '1.0.1' );
}

/**
 * Activation
 */
function botzio_ai_plugin_activate() {
    add_option( 'botzio_ai_do_activation_redirect', true );
    add_option( 'botzio_ai_display_chatbot', 'yes' );
    add_option( 'botzio_ai_provider', 'openrouter' );
    add_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );
    add_option( 'botzio_ai_cached_models', array() );
    add_option( 'botzio_ai_custom_prompt', 'Answer only related to the current website, nothing else.' );
    add_option( 'botzio_ai_error_message', 'I can only answer questions related to this website.' );
    add_option( 'botzio_ai_welcome_message', 'Hello! Welcome to our website. How can I help you today?' );
}
register_activation_hook( __FILE__, 'botzio_ai_plugin_activate' );


/**
 * Redirect after activation
 */
function botzio_ai_plugin_redirect_after_activation() {
    if ( get_option( 'botzio_ai_do_activation_redirect', false ) ) {
        delete_option( 'botzio_ai_do_activation_redirect' );

        $activate_multi = isset( $_GET['activate-multi'] ) ? sanitize_text_field( wp_unslash( $_GET['activate-multi'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( empty( $activate_multi ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=botzio-ai-settings' ) );
            exit;
        }
    }
}
add_action( 'admin_init', 'botzio_ai_plugin_redirect_after_activation' );

/**
 * Admin menu
 */
function botzio_ai_plugin_menu() {
    add_menu_page(
        __( 'Botzio AI Settings', 'botzio-ai' ),
        __( 'Botzio AI', 'botzio-ai' ),
        'manage_options',
        'botzio-ai-settings',
        'botzio_ai_settings_page',
        'dashicons-format-chat',
        80
    );
}
add_action( 'admin_menu', 'botzio_ai_plugin_menu' );

/**
 * Register settings
 */
function botzio_ai_plugin_settings() {

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_display_chatbot',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'botzio_ai_sanitize_checkbox',
            'default'           => 'yes',
        )
    );
    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_starter_suggestions',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => "What products do you sell?\nWhat is your return policy?\nDo you offer Cash on Delivery?",
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_provider',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'botzio_ai_sanitize_provider',
            'default'           => 'openrouter',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_api_key',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'botzio_ai_sanitize_api_key',
            'default'           => '',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_model',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'botzio_ai_sanitize_model',
            'default'           => 'openrouter/free',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_temperature',
        array(
            'type'              => 'number',
            'sanitize_callback' => 'botzio_ai_sanitize_temperature',
            'default'           => 0.3,
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_max_tokens',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'botzio_ai_sanitize_max_tokens',
            'default'           => 150,
        )
    );    

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_daily_limit',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_knowledge_base',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_custom_prompt',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'Answer only related to the current website, nothing else.',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_error_message',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'I can only answer questions related to this website.',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_welcome_message',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'Hello! Welcome to our website. How can I help you today?',
        )
    );
    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_delete_data_on_uninstall',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'botzio_ai_sanitize_checkbox',
            'default'           => 'no',
        )
    );
    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_chat_title',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Chat Support',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_primary_color',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#111111',
        )
    );

    register_setting(
        'botzio_ai_settings_group',
        'botzio_ai_bubble_position',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'botzio_ai_sanitize_bubble_position',
            'default'           => 'right',
        )
    );

    add_settings_section(
        'botzio_ai_connection_section',
        __( 'Connection Settings', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-connection'
    );

    add_settings_section(
        'botzio_ai_content_section',
        __( 'Content Settings', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-content'
    );

    add_settings_section(
        'botzio_ai_appearance_section',
        __( 'Appearance Settings', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-appearance'
    );

    add_settings_section(
        'botzio_ai_limits_section',
        __( 'Limits Settings', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-limits'
    );

    add_settings_section(
        'botzio_ai_advanced_section',
        __( 'Advanced Settings', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-advanced'
    );

    /**
     * Connection tab
     */
    add_settings_field(
        'botzio_ai_connection_status',
        __( 'Connection Status', 'botzio-ai' ),
        'botzio_ai_connection_status_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );

    add_settings_field(
        'botzio_ai_provider',
        __( 'AI Provider', 'botzio-ai' ),
        'botzio_ai_provider_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );

    add_settings_field(
        'botzio_ai_api_key',
        __( 'API Key', 'botzio-ai' ),
        'botzio_ai_api_key_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );

    add_settings_field(
        'botzio_ai_model',
        __( 'Model', 'botzio-ai' ),
        'botzio_ai_model_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );
    

    /**
     * Content tab
     */
    add_settings_field(
        'botzio_ai_knowledge_base',
        __( 'Knowledge Base', 'botzio-ai' ),
        'botzio_ai_knowledge_base_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    add_settings_field(
        'botzio_ai_custom_prompt',
        __( 'Custom Prompt', 'botzio-ai' ),
        'botzio_ai_custom_prompt_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    add_settings_field(
        'botzio_ai_welcome_message',
        __( 'Welcome Message', 'botzio-ai' ),
        'botzio_ai_welcome_message_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    add_settings_field(
        'botzio_ai_error_message',
        __( 'Irrelevant Question Message', 'botzio-ai' ),
        'botzio_ai_error_message_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    add_settings_field(
        'botzio_ai_starter_suggestions',
        __( 'Starter Suggestions', 'botzio-ai' ),
        'botzio_ai_starter_suggestions_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    /**
     * Appearance tab
     */
    add_settings_field(
        'botzio_ai_display_chatbot',
        __( 'Enable Chatbot', 'botzio-ai' ),
        'botzio_ai_display_chatbot_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );
    add_settings_field(
        'botzio_ai_chat_title',
        __( 'Chat Title', 'botzio-ai' ),
        'botzio_ai_chat_title_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );

    add_settings_field(
        'botzio_ai_primary_color',
        __( 'Primary Color', 'botzio-ai' ),
        'botzio_ai_primary_color_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );

    add_settings_field(
        'botzio_ai_bubble_position',
        __( 'Bubble Position', 'botzio-ai' ),
        'botzio_ai_bubble_position_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );

    /**
     * Limits tab
     */
    add_settings_field(
        'botzio_ai_daily_limit',
        __( 'Daily Message Limit', 'botzio-ai' ),
        'botzio_ai_daily_limit_field',
        'botzio-ai-limits',
        'botzio_ai_limits_section'
    );

    add_settings_field(
        'botzio_ai_temperature',
        __( 'Temperature', 'botzio-ai' ),
        'botzio_ai_temperature_field',
        'botzio-ai-limits',
        'botzio_ai_limits_section'
    );

    add_settings_field(
        'botzio_ai_max_tokens',
        __( 'Max Tokens', 'botzio-ai' ),
        'botzio_ai_max_tokens_field',
        'botzio-ai-limits',
        'botzio_ai_limits_section'
    );

    /**
     * Advanced tab
     */
    add_settings_field(
        'botzio_ai_delete_data_on_uninstall',
        __( 'Delete Data on Uninstall', 'botzio-ai' ),
        'botzio_ai_delete_data_on_uninstall_field',
        'botzio-ai-advanced',
        'botzio_ai_advanced_section'
    );
}
add_action( 'admin_init', 'botzio_ai_plugin_settings' );

function botzio_ai_sanitize_bubble_position( $value ) {
    $allowed = array( 'left', 'right' );

    return in_array( $value, $allowed, true ) ? $value : 'right';
}
function botzio_ai_chat_title_field() {
    $value = get_option( 'botzio_ai_chat_title', 'Chat Support' );
    ?>
    <input
        type="text"
        name="botzio_ai_chat_title"
        value="<?php echo esc_attr( $value ); ?>"
        class="regular-text"
    />
    <?php
}

function botzio_ai_primary_color_field() {
    $value = get_option( 'botzio_ai_primary_color', '#111111' );
    ?>
    <input
        type="color"
        name="botzio_ai_primary_color"
        value="<?php echo esc_attr( $value ); ?>"
    />
    <?php
}

function botzio_ai_bubble_position_field() {
    $value = get_option( 'botzio_ai_bubble_position', 'right' );
    ?>
    <select name="botzio_ai_bubble_position">
        <option value="right" <?php selected( $value, 'right' ); ?>>
            <?php echo esc_html__( 'Bottom Right', 'botzio-ai' ); ?>
        </option>
        <option value="left" <?php selected( $value, 'left' ); ?>>
            <?php echo esc_html__( 'Bottom Left', 'botzio-ai' ); ?>
        </option>
    </select>
    <?php
}

function botzio_ai_starter_suggestions_field() {
    $value = get_option(
        'botzio_ai_starter_suggestions',
        "What products do you sell?\nWhat is your return policy?\nDo you offer Cash on Delivery?"
    );
    ?>
    <textarea
        name="botzio_ai_starter_suggestions"
        rows="4"
        cols="60"
        class="large-text"
    ><?php echo esc_textarea( $value ); ?></textarea>

    <p class="description botzio-ai-settings-hint">
        <?php echo esc_html__( 'Add one starter question per line. These will appear only when a visitor starts a new chat.', 'botzio-ai' ); ?>
    </p>
    <?php
}
function botzio_ai_should_reset_connection_verification( $provider, $api_key, $model ) {
    $saved_signature = get_option( 'botzio_ai_connection_verified_signature', '' );

    if ( empty( $saved_signature ) ) {
        return true;
    }

    $current_signature = botzio_ai_connection_signature( $provider, $api_key, $model );

    return $saved_signature !== $current_signature;
}

function botzio_ai_reset_connection_verification() {
    update_option( 'botzio_ai_connection_verified', 'no' );
    delete_option( 'botzio_ai_connection_verified_provider' );
    delete_option( 'botzio_ai_connection_verified_model' );
    delete_option( 'botzio_ai_connection_verified_signature' );
}
function botzio_ai_connection_signature( $provider, $api_key, $model ) {
    return hash(
        'sha256',
        strtolower( trim( $provider ) ) . '|' . trim( $api_key ) . '|' . strtolower( trim( $model ) )
    );
}

function botzio_ai_get_cached_models() {
    $cached_models = get_option( 'botzio_ai_cached_models', array() );

    return is_array( $cached_models ) ? $cached_models : array();
}

function botzio_ai_cache_models_for_provider( $provider, $models ) {
    $provider = botzio_ai_sanitize_provider( $provider );
    $models   = is_array( $models ) ? array_values( array_unique( array_map( 'sanitize_text_field', $models ) ) ) : array();

    if ( empty( $models ) ) {
        return;
    }

    $cached_models              = botzio_ai_get_cached_models();
    $cached_models[ $provider ] = $models;

    update_option( 'botzio_ai_cached_models', $cached_models );
}

function botzio_ai_after_settings_save_reset_verification() {
    if (
        ! isset( $_POST['option_page'], $_POST['_wpnonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            'botzio_ai_settings_group-options'
        ) ||
        'botzio_ai_settings_group' !== sanitize_text_field( wp_unslash( $_POST['option_page'] ) )
    ) {
        return;
    }

    $provider = get_option( 'botzio_ai_provider', 'openrouter' );
    $api_key  = get_option( 'botzio_ai_api_key', '' );
    $model    = get_option( 'botzio_ai_model', 'openrouter/free' );

    if ( botzio_ai_should_reset_connection_verification( $provider, $api_key, $model ) ) {
        botzio_ai_reset_connection_verification();
    }
}
add_action( 'updated_option', 'botzio_ai_after_settings_save_reset_verification' );
function botzio_ai_delete_data_on_uninstall_field() {

    $value = get_option(
        'botzio_ai_delete_data_on_uninstall',
        'no'
    );
    ?>

    <label>
        <input
            type="checkbox"
            name="botzio_ai_delete_data_on_uninstall"
            value="yes"
            <?php checked( $value, 'yes' ); ?>
        />

        <?php echo esc_html__( 'Delete all plugin settings and analytics when the plugin is uninstalled.', 'botzio-ai' ); ?>
    </label>

    <?php
}

// Callback function for the temperature field
function botzio_ai_temperature_field() {
    $value = get_option( 'botzio_ai_temperature', 0.3 );
    ?>
    <input type="number" name="botzio_ai_temperature" step="0.01" min="0" max="1" value="<?php echo esc_attr( $value ); ?>" class="small-text" />
    <p class="description">
        <?php echo esc_html__( 'Controls the randomness of AI responses. Lower is more deterministic.', 'botzio-ai' ); ?>
    </p>
    <?php
}
function botzio_ai_connection_status_field() {
    $provider          = get_option( 'botzio_ai_provider', 'openrouter' );
    $verified          = get_option( 'botzio_ai_connection_verified', 'no' );
    $verified_provider = get_option( 'botzio_ai_connection_verified_provider', '' );
    $verified_model    = get_option( 'botzio_ai_connection_verified_model', '' );

    $is_verified = (
        'yes' === $verified &&
        strtolower( $provider ) === strtolower( $verified_provider )
    );
    ?>
    <div class="botzio-ai-status-row">
        <span class="botzio-ai-status-dot <?php echo $is_verified ? 'connected' : 'disconnected'; ?>"></span>

        <strong>
            <?php
            if ( $is_verified ) {
                echo esc_html( ucfirst( $provider ) . ' connection verified' );

                if ( ! empty( $verified_model ) ) {
                    echo esc_html( ' - ' . $verified_model );
                }
            } else {
                echo esc_html( ucfirst( $provider ) . ' connection not verified' );
            }
            ?>
        </strong>
    </div>
    <?php
}
function botzio_ai_test_connection() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );
    update_option( 'botzio_ai_connection_verified', 'no' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    $provider = isset( $_POST['provider'] )
        ? sanitize_text_field( wp_unslash( $_POST['provider'] ) )
        : get_option( 'botzio_ai_provider', 'openrouter' );

    $api_key = isset( $_POST['api_key'] )
        ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )
        : get_option( 'botzio_ai_api_key', '' );

    $model = isset( $_POST['model'] )
        ? sanitize_text_field( wp_unslash( $_POST['model'] ) )
        : get_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );

    if ( empty( $api_key ) ) {
        wp_send_json_error(
            array(
                'message' => 'API key is missing.',
            )
        );
    }

    $result = botzio_ai_call_provider(
        $provider,
        $api_key,
        $model,
        'Reply only with: Connection successful.',
        'Test connection'
    );

    if ( is_wp_error( $result ) ) {
        botzio_ai_reset_connection_verification();

        wp_send_json_error(
            array(
                'message' => 'Connection failed. Please check your API key, provider, and model.',
            )
        );
    }

    update_option( 'botzio_ai_provider', $provider );
    update_option( 'botzio_ai_api_key', $api_key );
    update_option( 'botzio_ai_model', $model );
    update_option( 'botzio_ai_connection_verified', 'yes' );
    update_option( 'botzio_ai_connection_verified_provider', $provider );
    update_option( 'botzio_ai_connection_verified_model', $model );
    update_option(
        'botzio_ai_connection_verified_signature',
        botzio_ai_connection_signature( $provider, $api_key, $model )
    );

    wp_send_json_success(
        array(
            'message' => 'Connection successful.',
        )
    );
}
add_action( 'wp_ajax_botzio_ai_test_connection', 'botzio_ai_test_connection' );

function botzio_ai_disconnect_api() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    update_option( 'botzio_ai_api_key', '' );
    botzio_ai_reset_connection_verification();

    wp_send_json_success(
        array(
            'message' => 'API disconnected successfully.',
        )
    );
}
add_action( 'wp_ajax_botzio_ai_disconnect_api', 'botzio_ai_disconnect_api' );

// Callback function for the max_tokens field
function botzio_ai_max_tokens_field() {
    $value = get_option( 'botzio_ai_max_tokens', 150 );
    ?>
    <input type="number" name="botzio_ai_max_tokens" value="<?php echo esc_attr( $value ); ?>" class="small-text" />
    <p class="description">
        <?php echo esc_html__( 'Limits the number of tokens (words/characters) in the AI response.', 'botzio-ai' ); ?>
    </p>
    <?php
}
function botzio_ai_knowledge_base_field() {
    $value = get_option( 'botzio_ai_knowledge_base', '' );
    ?>
    <textarea
        name="botzio_ai_knowledge_base"
        rows="10"
        cols="60"
        class="large-text"
        placeholder="Add business FAQs, services, policies, contact details, pricing info, shipping info, return policy, etc."
    ><?php echo esc_textarea( $value ); ?></textarea>

    <p class="description botzio-ai-settings-hint">
        <?php echo esc_html__( 'Add structured business information such as services, FAQs, policies, contact details, shipping, returns, and support instructions. Avoid pasting full website pages or long marketing content.', 'botzio-ai' ); ?>
    </p>
    <?php
}
/**
 * Sanitizers
 */
function botzio_ai_sanitize_checkbox( $value ) {
    return ( 'yes' === $value ) ? 'yes' : 'no';
}

function botzio_ai_sanitize_provider( $value ) {
    $allowed = array( 'openrouter', 'openai', 'gemini' );
    $value   = in_array( $value, $allowed, true ) ? $value : 'openrouter';

    return $value;
}
function botzio_ai_sanitize_api_key( $value ) {
    $value = trim( sanitize_text_field( $value ) );

    return $value;
}

function botzio_ai_sanitize_temperature( $value ) {
    $value = floatval( $value );

    if ( $value < 0 ) {
        $value = 0;
    }

    if ( $value > 1 ) {
        $value = 1;
    }

    return $value;
}

function botzio_ai_sanitize_max_tokens( $value ) {
    $value = absint( $value );

    if ( $value < 50 ) {
        $value = 50;
    }

    if ( $value > 1000 ) {
        $value = 1000;
    }

    return $value;
}

function botzio_ai_validate_model_for_provider( $provider, $model ) {
    $provider = strtolower( trim( $provider ) );
    $model    = strtolower( trim( $model ) );

    switch ( $provider ) {
        case 'openai':
            return false !== strpos( $model, 'gpt-' );

        case 'gemini':
            return false !== strpos( $model, 'gemini-' );

        case 'openrouter':
            return ! empty( $model );

        default:
            return false;
    }
}

function botzio_ai_sanitize_model( $value ) {
    $value = sanitize_text_field( $value );

    $provider = get_option( 'botzio_ai_provider', 'openrouter' );
    $api_key  = get_option( 'botzio_ai_api_key', '' );

    if (
        isset( $_POST['botzio_ai_provider'], $_POST['botzio_ai_api_key'], $_POST['_wpnonce'] ) &&
        wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            'botzio_ai_settings_group-options'
        )
    ) {
        $provider = sanitize_text_field( wp_unslash( $_POST['botzio_ai_provider'] ) );
        $api_key  = sanitize_text_field( wp_unslash( $_POST['botzio_ai_api_key'] ) );
    }

    if ( empty( $value ) ) {
        add_settings_error(
            'botzio_ai_model',
            'botzio_ai_model_empty',
            __( 'Model field cannot be empty.', 'botzio-ai' ),
            'error'
        );

        return get_option( 'botzio_ai_model', 'openrouter/free' );
    }

    $verified_signature = get_option( 'botzio_ai_connection_verified_signature', '' );
    $current_signature  = botzio_ai_connection_signature( $provider, $api_key, $value );

    if ( ! empty( $api_key ) && ( empty( $verified_signature ) || $verified_signature !== $current_signature ) ) {
        add_settings_error(
            'botzio_ai_model',
            'botzio_ai_model_not_verified',
            __( 'Please test the selected provider, API key, and model before saving settings.', 'botzio-ai' ),
            'error'
        );

        botzio_ai_reset_connection_verification();

        return get_option( 'botzio_ai_model', 'openrouter/free' );
    }

    if ( ! botzio_ai_validate_model_for_provider( $provider, $value ) ) {
        add_settings_error(
            'botzio_ai_model',
            'botzio_ai_invalid_model',
            __( 'Selected model does not match the selected provider.', 'botzio-ai' ),
            'error'
        );

        return get_option( 'botzio_ai_model', 'openrouter/free' );
    }

    return $value;
}

/**
 * Settings page
 */
function botzio_ai_settings_page() {
    $provider          = get_option( 'botzio_ai_provider', 'openrouter' );
    $verified          = get_option( 'botzio_ai_connection_verified', 'no' );
    $verified_provider = get_option( 'botzio_ai_connection_verified_provider', '' );
    $is_verified       = (
        'yes' === $verified &&
        strtolower( $provider ) === strtolower( $verified_provider )
    );
    $knowledge_base    = trim( get_option( 'botzio_ai_knowledge_base', '' ) );
    $daily_limit       = absint( get_option( 'botzio_ai_daily_limit', 20 ) );
    $chatbot_enabled   = 'yes' === get_option( 'botzio_ai_display_chatbot', 'yes' );
    ?>
    <div class="wrap botzio-ai-admin-wrap">
        <div class="botzio-ai-admin-hero">
            <div class="botzio-ai-admin-brand">
                <div class="botzio-ai-admin-logo-mark">
                    <img
                        src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/botzio-ai-chat-icon.png' ); ?>"
                        alt=""
                    />
                </div>

                <div>
                    <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Smart website assistant', 'botzio-ai' ); ?></p>
                    <h1><?php echo esc_html__( 'Botzio AI', 'botzio-ai' ); ?></h1>
                    <p class="botzio-ai-admin-subtitle">
                        <?php echo esc_html__( 'Configure your AI provider, focused knowledge base, appearance, and visitor usage controls from one clean workspace.', 'botzio-ai' ); ?>
                    </p>
                </div>
            </div>

            <div class="botzio-ai-hero-actions">
                <span class="botzio-ai-pill <?php echo $is_verified ? 'is-success' : 'is-warning'; ?>">
                    <?php
                    echo esc_html(
                        $is_verified
                            ? __( 'Connection verified', 'botzio-ai' )
                            : __( 'Connection pending', 'botzio-ai' )
                    );
                    ?>
                </span>

                <a class="button button-primary botzio-ai-jump-tab" href="#botzio-ai-tab-connection">
                    <?php echo esc_html__( 'Configure AI', 'botzio-ai' ); ?>
                </a>
            </div>
        </div>

        <?php
        $total_chats = absint( get_option( 'botzio_ai_total_chats', 0 ) );
        $recent_questions = get_option( 'botzio_ai_recent_questions', array() );
        $recent_questions_preview = array_slice( $recent_questions, 0, 3 );
        ?>

        <div class="botzio-ai-stats-grid">
            <div class="botzio-ai-stat-card">
                <span class="dashicons dashicons-format-chat"></span>
                <div>
                    <strong><?php echo esc_html( $total_chats ); ?></strong>
                    <span><?php echo esc_html__( 'Total messages', 'botzio-ai' ); ?></span>
                    <small><?php echo esc_html__( 'Simple chatbot analytics', 'botzio-ai' ); ?></small>
                </div>
            </div>

            <div class="botzio-ai-stat-card">
                <span class="dashicons dashicons-admin-network"></span>
                <div>
                    <strong><?php echo esc_html( ucfirst( $provider ) ); ?></strong>
                    <span><?php echo esc_html__( 'AI provider', 'botzio-ai' ); ?></span>
                    <small><?php echo esc_html( $is_verified ? __( 'Ready to respond', 'botzio-ai' ) : __( 'Needs verification', 'botzio-ai' ) ); ?></small>
                </div>
            </div>

            <div class="botzio-ai-stat-card">
                <span class="dashicons dashicons-database"></span>
                <div>
                    <strong><?php echo esc_html( empty( $knowledge_base ) ? __( 'Manual', 'botzio-ai' ) : __( 'Added', 'botzio-ai' ) ); ?></strong>
                    <span><?php echo esc_html__( 'Knowledge base', 'botzio-ai' ); ?></span>
                    <small><?php echo esc_html__( 'Focused business content', 'botzio-ai' ); ?></small>
                </div>
            </div>

            <div class="botzio-ai-stat-card">
                <span class="dashicons dashicons-chart-line"></span>
                <div>
                    <strong><?php echo esc_html( $daily_limit ); ?></strong>
                    <span><?php echo esc_html__( 'Daily limit', 'botzio-ai' ); ?></span>
                    <small><?php echo esc_html( $chatbot_enabled ? __( 'Chatbot enabled', 'botzio-ai' ) : __( 'Chatbot disabled', 'botzio-ai' ) ); ?></small>
                </div>
            </div>
        </div>

        <div class="botzio-ai-overview-row">
            <div class="botzio-ai-overview-card botzio-ai-analytics-card">
                <div>
                    <h2><?php echo esc_html__( 'Chatbot Analytics', 'botzio-ai' ); ?></h2>
                    <p class="botzio-ai-stat">
                        <span><?php echo esc_html( $total_chats ); ?></span>
                        <?php echo esc_html__( 'total messages', 'botzio-ai' ); ?>
                    </p>
                </div>

                <div class="botzio-ai-recent-questions">
                    <h3><?php echo esc_html__( 'Recent Questions', 'botzio-ai' ); ?></h3>

                    <?php if ( ! empty( $recent_questions_preview ) ) : ?>

                        <ul>

                            <?php foreach ( $recent_questions_preview as $item ) : ?>

                                <li>
                                    <span><?php echo esc_html( $item['question'] ); ?></span>

                                    <small>
                                        <?php
                                        echo esc_html(
                                            wp_date(
                                                'M j, Y g:i A',
                                                absint( $item['time'] )
                                            )
                                        );
                                        ?>
                                    </small>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php else : ?>

                        <p><?php echo esc_html__( 'No questions yet.', 'botzio-ai' ); ?></p>

                    <?php endif; ?>
                </div>
            </div>

            <div class="botzio-ai-overview-card botzio-ai-coming-soon-card">
                <h2><?php echo esc_html__( 'Coming Soon: Pro Features', 'botzio-ai' ); ?></h2>

                <p>
                    <?php echo esc_html__( 'Optional upgrades are being planned for more advanced chatbot workflows.', 'botzio-ai' ); ?>
                </p>

                <ul>
                    <li><?php echo esc_html__( 'Lead capture', 'botzio-ai' ); ?></li>
                    <li><?php echo esc_html__( 'Website training', 'botzio-ai' ); ?></li>
                    <li><?php echo esc_html__( 'WooCommerce assistant', 'botzio-ai' ); ?></li>
                </ul>
            </div>
        </div>

        <form method="post" action="options.php" class="botzio-ai-settings-form">
            <?php settings_fields( 'botzio_ai_settings_group' ); ?>

            <h2 class="nav-tab-wrapper botzio-ai-tabs">
                <a href="#botzio-ai-tab-connection" class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php echo esc_html__( 'Connection', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-content" class="nav-tab">
                    <span class="dashicons dashicons-database"></span>
                    <?php echo esc_html__( 'Content', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-appearance" class="nav-tab">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <?php echo esc_html__( 'Appearance', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-limits" class="nav-tab">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php echo esc_html__( 'Limits', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-advanced" class="nav-tab">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php echo esc_html__( 'Advanced', 'botzio-ai' ); ?>
                </a>
            </h2>

            <div id="botzio-ai-tab-connection" class="botzio-ai-tab-content active">
                <?php do_settings_sections( 'botzio-ai-connection' ); ?>
            </div>

            <div id="botzio-ai-tab-content" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-content' ); ?>
            </div>

            <div id="botzio-ai-tab-appearance" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-appearance' ); ?>
            </div>

            <div id="botzio-ai-tab-limits" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-limits' ); ?>
            </div>

            <div id="botzio-ai-tab-advanced" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-advanced' ); ?>
            </div>

            <div class="botzio-ai-settings-actions">
                <?php submit_button( __( 'Save Settings', 'botzio-ai' ), 'primary', 'submit', false ); ?>

                <button
                    type="button" class="button button-secondary" id="botzio-ai-reset-settings" style="margin-left:10px;"
                >
                    <?php echo esc_html__( 'Reset to Defaults', 'botzio-ai' ); ?>
                </button>

                <span id="botzio-ai-reset-result" style="margin-left:10px;"></span>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Fields
 */
function botzio_ai_display_chatbot_field() {
    $value = get_option( 'botzio_ai_display_chatbot', 'yes' );
    ?>
    <label>
        <input type="checkbox" name="botzio_ai_display_chatbot" value="yes" <?php checked( $value, 'yes' ); ?> />
        <?php echo esc_html__( 'Show chatbot automatically on all pages', 'botzio-ai' ); ?>
    </label>
    <?php
}

function botzio_ai_provider_field() {
    $value = get_option( 'botzio_ai_provider', 'openrouter' );
    ?>
    <select name="botzio_ai_provider" id="botzio_ai_provider">
        <option value="openrouter" <?php selected( $value, 'openrouter' ); ?>>OpenRouter</option>
        <option value="openai" <?php selected( $value, 'openai' ); ?>>OpenAI</option>
        <option value="gemini" <?php selected( $value, 'gemini' ); ?>>Gemini</option>
    </select>

    <p class="description" id="botzio-ai-provider-help-text">Need an API key? Create one from <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">OpenRouter API Keys</a>. Recommended model: <code>openrouter/free</code>.</p>
    <?php
}

function botzio_ai_api_key_field() {
    $value = get_option( 'botzio_ai_api_key', '' );
    ?>
    <input
        type="password"
        name="botzio_ai_api_key"
        id="botzio_ai_api_key"
        value="<?php echo esc_attr( $value ); ?>"
        class="regular-text"
        autocomplete="off"
    />

    <button type="button" class="button button-secondary" id="botzio-ai-toggle-api-key">
        <?php echo esc_html__( 'Show', 'botzio-ai' ); ?>
    </button>

    <p class="description">
        <?php echo esc_html__( 'Enter your provider API key.', 'botzio-ai' ); ?>
    </p>
    <?php
}

function botzio_ai_model_field() {
    $value = get_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );
    $provider = get_option( 'botzio_ai_provider', 'openrouter' );
    $cached_models = botzio_ai_get_cached_models();
    $has_cached_models = ! empty( $cached_models[ $provider ] );
    ?>
    <div class="botzio-ai-model-picker-row">
        <input
            type="text"
            name="botzio_ai_model"
            id="botzio_ai_model"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            placeholder="<?php echo esc_attr__( 'Model ID', 'botzio-ai' ); ?>"
        />

        <button type="button" class="button button-secondary" id="botzio-ai-fetch-models">
            <?php echo esc_html__( 'Fetch Models', 'botzio-ai' ); ?>
        </button>

        <button type="button" class="button button-secondary" id="botzio-ai-change-model" style="display:none;">
            <?php echo esc_html__( 'Change Model', 'botzio-ai' ); ?>
        </button>
    </div>

    <div class="botzio-ai-model-select-row">
        <select id="botzio-ai-model-dropdown" style="display:none;" data-has-cached-models="<?php echo $has_cached_models ? 'yes' : 'no'; ?>">
            <option value=""><?php echo esc_html__( 'Select a model', 'botzio-ai' ); ?></option>
        </select>
    </div>

    <div class="botzio-ai-connection-action-row">
        <button type="button" class="button button-primary" id="botzio-ai-test-connection" disabled>
            <?php echo esc_html__( 'Test Connection', 'botzio-ai' ); ?>
        </button>

        <?php if ( ! empty( get_option( 'botzio_ai_api_key', '' ) ) ) : ?>
            <button type="button" class="button button-secondary" id="botzio-ai-disconnect-api">
                <?php echo esc_html__( 'Disconnect API', 'botzio-ai' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <div class="botzio-ai-connection-message-row">
        <span id="botzio-ai-model-result"></span>
        <span id="botzio-ai-test-result"></span>
    </div>

    <p class="description">
        <?php echo esc_html__( 'Paste your API key, fetch available models, select one, then test the connection before saving.', 'botzio-ai' ); ?>
    </p>
    <?php
}

function botzio_ai_custom_prompt_field() {
    $value = get_option( 'botzio_ai_custom_prompt', 'Answer only related to the current website, nothing else.' );
    ?>
    <textarea
        name="botzio_ai_custom_prompt"
        rows="6"
        cols="60"
        class="large-text"
    ><?php echo esc_textarea( $value ); ?></textarea>
    <p class="description botzio-ai-settings-hint">
        <?php echo esc_html__( 'Use this for additional behavior rules, such as tone, response length, or what the chatbot should avoid.', 'botzio-ai' ); ?>
    </p>
    <?php
}

function botzio_ai_error_message_field() {
    $value = get_option( 'botzio_ai_error_message', 'I can only answer questions related to this website.' );
    ?>
    <textarea
        name="botzio_ai_error_message"
        rows="3"
        cols="60"
        class="large-text"
    ><?php echo esc_textarea( $value ); ?></textarea>
    <?php
}

function botzio_ai_welcome_message_field() {
    $value = get_option( 'botzio_ai_welcome_message', 'Hello! Welcome to our website. How can I help you today?' );
    ?>
    <textarea
        name="botzio_ai_welcome_message"
        rows="3"
        cols="60"
        class="large-text"
    ><?php echo esc_textarea( $value ); ?></textarea>
    <?php
}
function botzio_ai_daily_limit_field() {
    $value = get_option( 'botzio_ai_daily_limit', 30 );
    ?>
    <input
        type="number"
        name="botzio_ai_daily_limit"
        value="<?php echo esc_attr( max( 1, absint( $value ) ) ); ?>"
        min="1"
        step="1"
        class="small-text"
    />
    <p class="description">
        <?php echo esc_html__( 'Set the maximum number of chatbot messages allowed per visitor per day.', 'botzio-ai' ); ?>
    </p>
    <?php
}

/**
 * Enqueue frontend assets
 */
function botzio_ai_plugin_enqueue_scripts() {
    if ( 'yes' !== get_option( 'botzio_ai_display_chatbot', 'yes' ) ) {
        return;
    }

    wp_enqueue_script(
        'botzio-ai-script',
        plugin_dir_url( __FILE__ ) . 'js/chatbot.js',
        array( 'jquery' ),
        BOTZIO_AI_VERSION,
        true
    );

    wp_enqueue_style(
        'botzio-ai-style',
        plugin_dir_url( __FILE__ ) . 'css/chatbot.css',
        array(),
        BOTZIO_AI_VERSION
    );

    $botzio_ai_primary_color = sanitize_hex_color( get_option( 'botzio_ai_primary_color', '#111111' ) );
    if ( empty( $botzio_ai_primary_color ) ) {
        $botzio_ai_primary_color = '#111111';
    }

    wp_add_inline_style(
        'botzio-ai-style',
        '#botzio-ai-bubble,#chatbot-header,.user-bubble{background:' . $botzio_ai_primary_color . ';}#chatbot-send{border-color:' . $botzio_ai_primary_color . ';}#botzio-ai-bubble,#chatbot-container{--botzio-ai-primary:' . $botzio_ai_primary_color . ';}'
    );

    $starter_suggestions = get_option(
    'botzio_ai_starter_suggestions',
    "What products do you sell?\nWhat is your return policy?\nDo you offer Cash on Delivery?"
    );

    wp_localize_script(
        'botzio-ai-script',
        'botzioAiFrontend',
        array(
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'welcome_message'     => get_option( 'botzio_ai_welcome_message', 'Hello! Welcome to our website. How can I help you today?' ),
            'starter_suggestions' => array_filter(
                array_map(
                    'trim',
                    explode( "\n", $starter_suggestions )
                )
            ),
            'nonce'               => wp_create_nonce( 'botzio_ai_nonce' ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'botzio_ai_plugin_enqueue_scripts' );

function botzio_ai_admin_enqueue_scripts( $hook ) {
    if ( 'toplevel_page_botzio-ai-settings' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'botzio-ai-admin-script',
        plugin_dir_url( __FILE__ ) . 'js/chatbot-admin.js',
        array( 'jquery' ),
        BOTZIO_AI_VERSION,
        true
    );

    wp_enqueue_style(
        'botzio-ai-admin-style',
        plugin_dir_url( __FILE__ ) . 'css/chatbot-admin.css',
        array(),
        BOTZIO_AI_VERSION
    );

    wp_localize_script(
        'botzio-ai-admin-script',
        'botzioAiAdmin',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'botzio_ai_nonce' ),
            'cached_models'       => botzio_ai_get_cached_models(),
            'connection_verified' => 'yes' === get_option( 'botzio_ai_connection_verified', 'no' ),
            'verified_provider'   => get_option( 'botzio_ai_connection_verified_provider', '' ),
            'verified_model'      => get_option( 'botzio_ai_connection_verified_model', '' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'botzio_ai_admin_enqueue_scripts' );

/**
 * Inject chatbot UI
 */
function botzio_ai_inject_chatbot() {
    if ( is_admin() ) {
        return;
    }

    if ( 'yes' !== get_option( 'botzio_ai_display_chatbot', 'yes' ) ) {
        return;
    }

    $send_icon       = esc_url( plugin_dir_url( __FILE__ ) . 'assets/send.png' );
    $chat_icon       = esc_url( plugin_dir_url( __FILE__ ) . 'assets/botzio-ai-chat-icon.png' );
    $chat_title      = get_option( 'botzio_ai_chat_title', 'Chat Support' );
    $bubble_position = get_option( 'botzio_ai_bubble_position', 'right' );
    $position_class = 'left' === $bubble_position ? 'botzio-ai-left' : 'botzio-ai-right';

    ?>
    <div id="botzio-ai-bubble" class="<?php echo esc_attr( $position_class ); ?>" aria-label="<?php echo esc_attr__( 'Open chat', 'botzio-ai' ); ?>">
        <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
        <img class="botzio-ai-bubble-icon" src="<?php echo esc_url( $chat_icon ); ?>" alt="" aria-hidden="true" />
    </div>

    <div id="chatbot-container" class="chatbot-hidden <?php echo esc_attr( $position_class ); ?>">
        <div id="chatbot-header">
            <div class="chatbot-brand">
                <span class="chatbot-avatar" aria-hidden="true">
                    <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                    <img src="<?php echo esc_url( $chat_icon ); ?>" alt="" />
                </span>
                <span class="chatbot-title"><?php echo esc_html( $chat_title ); ?></span>
            </div>
            <div class="chatbot-header-actions">
                <button id="chatbot-clear" type="button">
                    <?php echo esc_html__( 'Clear', 'botzio-ai' ); ?>
                </button>

                <button id="chatbot-close" type="button" aria-label="<?php echo esc_attr__( 'Close chat', 'botzio-ai' ); ?>">&times;</button>
            </div>
        </div>

        <div id="chatbot-window"></div>

        <div class="send--wrap">
            <span class="chatbot-input-icon" aria-hidden="true">
                <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                <img src="<?php echo esc_url( $send_icon ); ?>" alt="" />
            </span>
            <input
                type="text"
                id="chatbot-input"
                placeholder="<?php echo esc_attr__( 'Type your message...', 'botzio-ai' ); ?>"
            />
            <button id="chatbot-send" type="button" aria-label="<?php echo esc_attr__( 'Send', 'botzio-ai' ); ?>">
                <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                <img src="<?php echo esc_url( $chat_icon ); ?>" alt="<?php echo esc_attr__( 'Send', 'botzio-ai' ); ?>" />
            </button>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'botzio_ai_inject_chatbot' );

function botzio_ai_is_rate_limited() {

    $ip = isset( $_SERVER['REMOTE_ADDR'] )
        ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP )
        : '';

    if ( false === $ip ) {
        $ip = '';
    }

    $transient_key = 'botzio_ai_rate_' . md5( $ip );

    $requests = get_transient( $transient_key );

    if ( false === $requests ) {
        $requests = 0;
    }

    $requests++;

    set_transient( $transient_key, $requests, MINUTE_IN_SECONDS );

    if ( $requests > 10 ) {
        return true;
    }

    return false;
}
function botzio_ai_daily_limit_reached() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
        : 'unknown';

    $daily_limit = max( 1, absint( get_option( 'botzio_ai_daily_limit', 30 ) ) );

    $key = 'botzio_ai_daily_' . md5( $ip . gmdate( 'Y-m-d' ) );

    $count = get_transient( $key );

    if ( false === $count ) {
        $count = 0;
    }

    if ( $count >= $daily_limit ) {
        return true;
    }

    set_transient( $key, $count + 1, DAY_IN_SECONDS );

    return false;
}
function botzio_ai_store_analytics( $message ) {

    $total_chats = absint( get_option( 'botzio_ai_total_chats', 0 ) );
    update_option( 'botzio_ai_total_chats', $total_chats + 1 );

    $recent_questions = get_option( 'botzio_ai_recent_questions', array() );

    if ( ! is_array( $recent_questions ) ) {
        $recent_questions = array();
    }

    array_unshift(
        $recent_questions,
        array(
            'question' => sanitize_text_field( $message ),
            'time'     => current_time( 'timestamp' ),
        )
    );

    $recent_questions = array_slice( $recent_questions, 0, 5 );

    update_option( 'botzio_ai_recent_questions', $recent_questions );
}

/**
 * AJAX processor
 */
function botzio_ai_process() {
     check_ajax_referer( 'botzio_ai_nonce', 'nonce' );
     if ( botzio_ai_is_rate_limited() ) {
        wp_send_json_error(
            array(
                'message' => 'Too many requests. Please wait a minute before trying again.',
            )
        );
    }
    
    if ( botzio_ai_daily_limit_reached() ) {
        wp_send_json_error(
            array(
                'message' => 'Daily chat limit reached. Please try again tomorrow.',
            )
        );
    }
    // Check if user input is provided
    if ( empty( $_POST['user_input'] ) ) {
        wp_send_json_error(
            array(
                'message' => 'No input provided.',
            )
        );
    }
   

    // Sanitize and retrieve options
    $user_input    = sanitize_text_field( wp_unslash( $_POST['user_input'] ) );
    $provider      = get_option( 'botzio_ai_provider', 'openrouter' );
    $api_key       = get_option( 'botzio_ai_api_key', '' );
    $model         = get_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );
    $custom_prompt = get_option( 'botzio_ai_custom_prompt', 'Answer only related to the current website, nothing else.' );
    $error_message = get_option( 'botzio_ai_error_message', 'I can only answer questions related to this website.' );

    // Check if API key is set
    if ( empty( $api_key ) ) {
        wp_send_json_success( 'Our agent is currently unavailable. Please configure the API key in plugin settings.' );
    }
    // Construct the final concise prompt with website content
    $knowledge_base = get_option( 'botzio_ai_knowledge_base', '' );
    $final_prompt = "
    You are a helpful website assistant.

    Rules:
    1. Only answer using the business information provided below.
    2. Do not answer unrelated questions.
    3. If the answer is not available in the business information, reply exactly with:
    {$error_message}
    4. Keep answers short, clear, and useful.
    5. Format answers clearly using short paragraphs.
    6. Use **bold headings** when helpful.
    7. Use bullet points for lists.
    8. Do not return HTML.

    Business Information:
    {$knowledge_base}

    Additional Admin Instruction:
    {$custom_prompt}
    ";

    // Call the AI provider function
    $result = botzio_ai_call_provider( $provider, $api_key, $model, $final_prompt, $user_input );

    // If there was an error with the AI request, return fallback message
    if ( is_wp_error( $result ) ) {
        wp_send_json_error(
            array(
                'message' => $result->get_error_message(),
            )
        );
    } 
    botzio_ai_store_analytics( $user_input );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_botzio_ai_process', 'botzio_ai_process' );
add_action( 'wp_ajax_nopriv_botzio_ai_process', 'botzio_ai_process' );
function botzio_ai_fetch_models() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    $provider = isset( $_POST['provider'] )
        ? sanitize_text_field( wp_unslash( $_POST['provider'] ) )
        : get_option( 'botzio_ai_provider', 'openrouter' );

    $api_key = isset( $_POST['api_key'] )
        ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )
        : get_option( 'botzio_ai_api_key', '' );

    if ( empty( $api_key ) ) {
        wp_send_json_error(
            array(
                'message' => 'API key is required to fetch models.',
            )
        );
    }

    switch ( $provider ) {
        case 'openai':
            $models = botzio_ai_fetch_openai_models( $api_key );
            break;

        case 'gemini':
            $models = botzio_ai_fetch_gemini_models( $api_key );
            break;

        case 'openrouter':
        default:
            $models = botzio_ai_fetch_openrouter_models( $api_key );
            break;
    }

    if ( is_wp_error( $models ) ) {
        wp_send_json_error(
            array(
                'message' => $models->get_error_message(),
            )
        );
    }

    botzio_ai_cache_models_for_provider( $provider, $models );

    wp_send_json_success(
        array(
            'models' => $models,
        )
    );
}
add_action( 'wp_ajax_botzio_ai_fetch_models', 'botzio_ai_fetch_models' );

function botzio_ai_fetch_openai_models( $api_key ) {
    $response = wp_remote_get(
        'https://api.openai.com/v1/models',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['data'] ) || ! is_array( $body['data'] ) ) {
        return new WP_Error( 'openai_models_error', 'Unable to fetch OpenAI models.' );
    }

    $models = array('gpt-4o-mini','gpt-4o');

    foreach ( $body['data'] as $model ) {
        if ( ! empty( $model['id'] ) ) {
            $models[] = sanitize_text_field( $model['id'] );
        }
    }

    sort( $models );

    return $models;
}

function botzio_ai_fetch_gemini_models( $api_key ) {
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode( $api_key );

    $response = wp_remote_get(
        $endpoint,
        array(
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['models'] ) || ! is_array( $body['models'] ) ) {
        return new WP_Error( 'gemini_models_error', 'Unable to fetch Gemini models.' );
    }

    $models = array();

    foreach ( $body['models'] as $model ) {
        if ( empty( $model['name'] ) ) {
            continue;
        }

        if (
            empty( $model['supportedGenerationMethods'] ) ||
            ! in_array( 'generateContent', $model['supportedGenerationMethods'], true )
        ) {
            continue;
        }

        $model_name = str_replace( 'models/', '', $model['name'] );

        // Keep only common chatbot-friendly Gemini models.
        if (
            false !== strpos( $model_name, 'flash' ) ||
            false !== strpos( $model_name, 'pro' )
        ) {
            $models[] = sanitize_text_field( $model_name );
        }
    }

    $models = array_unique( $models );

    $preferred_order = array(
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-1.5-pro',
    );

    usort(
        $models,
        function ( $a, $b ) use ( $preferred_order ) {
            $a_index = array_search( $a, $preferred_order, true );
            $b_index = array_search( $b, $preferred_order, true );

            $a_index = false === $a_index ? 999 : $a_index;
            $b_index = false === $b_index ? 999 : $b_index;

            if ( $a_index === $b_index ) {
                return strcmp( $a, $b );
            }

            return $a_index - $b_index;
        }
    );

    return $models;
}

function botzio_ai_fetch_openrouter_models( $api_key = '' ) {
    $headers = array(
        'Content-Type' => 'application/json',
    );

    if ( ! empty( $api_key ) ) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    $response = wp_remote_get(
        'https://openrouter.ai/api/v1/models',
        array(
            'headers' => $headers,
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['data'] ) || ! is_array( $body['data'] ) ) {
        return new WP_Error( 'openrouter_models_error', 'Unable to fetch OpenRouter models.' );
    }

    $models = array();

    foreach ( $body['data'] as $model ) {
        if ( empty( $model['id'] ) ) {
            continue;
        }

        $model_id = sanitize_text_field( $model['id'] );

        if ( false !== strpos( $model_id, ':free' ) || 'openrouter/free' === $model_id ) {
            $models[] = $model_id;
        }
    }

    sort( $models );

    return $models;
}

/**
 * Route provider calls
 */
function botzio_ai_call_provider( $provider, $api_key, $model, $prompt, $user_input ) {
    switch ( $provider ) {
        case 'openai':
            return botzio_ai_call_openai( $api_key, $model, $prompt, $user_input );

        case 'gemini':
            return botzio_ai_call_gemini( $api_key, $model, $prompt, $user_input );

        case 'openrouter':
        default:
            return botzio_ai_call_openrouter( $api_key, $model, $prompt, $user_input );
    }
}

/**
 * OpenRouter
 */
function botzio_ai_call_openrouter( $api_key, $model, $prompt, $user_input ) {
    $temperature = floatval( get_option( 'botzio_ai_temperature', 0.3 ) );
    $max_tokens  = absint( get_option( 'botzio_ai_max_tokens', 150 ) );
    $payload = array(
        'model'    => $model,
        'temperature' => $temperature,
        'max_tokens' => $max_tokens,
        'messages' => array(
            array(
                'role'    => 'system',
                'content' => $prompt,
            ),
            array(
                'role'    => 'user',
                'content' => $user_input,
            ),
        ),
    );

    $response = wp_remote_post(
        'https://openrouter.ai/api/v1/chat/completions',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code < 200 || $status_code >= 300 ) {
        $message = isset( $body['error']['message'] )
            ? sanitize_text_field( $body['error']['message'] )
            : 'OpenRouter request failed.';

        return new WP_Error( 'botzio_ai_openrouter_error', $message );
    }

    if ( isset( $body['choices'][0]['message']['content'] ) ) {
        return trim( $body['choices'][0]['message']['content'] );
    }

    return new WP_Error( 'botzio_ai_openrouter_error', 'No response from OpenRouter.' );
}

/**
 * OpenAI
 */
function botzio_ai_call_openai( $api_key, $model, $prompt, $user_input ) {
    $payload = array(
        'model'    => $model,
        'messages' => array(
            array(
                'role'    => 'system',
                'content' => $prompt,
            ),
            array(
                'role'    => 'user',
                'content' => $user_input,
            ),
        ),
    );

    $response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code < 200 || $status_code >= 300 ) {
        $message = isset( $body['error']['message'] )
            ? sanitize_text_field( $body['error']['message'] )
            : 'OpenAI request failed.';

        return new WP_Error( 'botzio_ai_openai_error', $message );
    }

    if ( isset( $body['choices'][0]['message']['content'] ) ) {
        return trim( $body['choices'][0]['message']['content'] );
    }

    return new WP_Error( 'botzio_ai_openai_error', 'No response from OpenAI.' );
}
function botzio_ai_reset_settings() {

    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    $defaults = array(
        'botzio_ai_provider'              => 'openrouter',
        'botzio_ai_api_key'               => '',
        'botzio_ai_model'                 => 'openrouter/free',
        'botzio_ai_custom_prompt'         => 'Answer only related to the current website, nothing else.',
        'botzio_ai_error_message'         => 'I can only answer questions related to this website.',
        'botzio_ai_welcome_message'       => 'Hello! Welcome to our website. How can I help you today?',
        'botzio_ai_knowledge_base'        => '',
        'botzio_ai_display_chatbot'       => 'yes',
        'botzio_ai_temperature'           => 0.3,
        'botzio_ai_max_tokens'            => 150,
        'botzio_ai_daily_limit'           => 30,
        'botzio_ai_starter_suggestions'   => "What products do you sell?\nWhat is your return policy?\nDo you offer Cash on Delivery?",
        'botzio_ai_cached_models'         => array(),
    );

    foreach ( $defaults as $key => $value ) {
        update_option( $key, $value );
    }

    botzio_ai_reset_connection_verification();

    wp_send_json_success(
        array(
            'message' => 'Settings reset successfully.',
        )
    );
}

add_action( 'wp_ajax_botzio_ai_reset_settings', 'botzio_ai_reset_settings' );

/**
 * Gemini
 */
function botzio_ai_call_gemini( $api_key, $model, $prompt, $user_input ) {
    $payload = array(
        'contents' => array(
            array(
                'parts' => array(
                    array(
                        'text' => $prompt . "\n\nUser question: " . $user_input,
                    ),
                ),
            ),
        ),
    );

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

    $response = wp_remote_post(
        $endpoint,
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code < 200 || $status_code >= 300 ) {
        $message = isset( $body['error']['message'] )
            ? sanitize_text_field( $body['error']['message'] )
            : 'Gemini request failed.';

        return new WP_Error( 'botzio_ai_gemini_error', $message );
    }

    if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
        return trim( $body['candidates'][0]['content']['parts'][0]['text'] );
    }

    return new WP_Error( 'botzio_ai_gemini_error', 'No response from Gemini.' );
}
