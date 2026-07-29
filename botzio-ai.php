<?php
/*
Plugin Name: Botzio AI - Smart Chatbot & Website Assistant
Description: Botzio AI helps website owners add an AI-powered support assistant to answer visitor questions using site-specific business information.
Version: 1.0.14
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
    define( 'BOTZIO_AI_VERSION', '1.0.14' );
}

function botzio_ai_is_pro_plugin_active() {
    if ( defined( 'BOTZIO_AI_PRO_VERSION' ) ) {
        return true;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active( 'botzio-ai-pro/botzio-ai-pro.php' );
}

function botzio_ai_is_ai_connection_ready() {
    $provider           = get_option( 'botzio_ai_provider', 'openrouter' );
    $api_key            = get_option( 'botzio_ai_api_key', '' );
    $model              = get_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );
    $verified           = get_option( 'botzio_ai_connection_verified', 'no' );
    $verified_provider  = get_option( 'botzio_ai_connection_verified_provider', '' );
    $verified_model     = get_option( 'botzio_ai_connection_verified_model', '' );
    $verified_signature = get_option( 'botzio_ai_connection_verified_signature', '' );

    if ( empty( $api_key ) || empty( $model ) || 'yes' !== $verified ) {
        return false;
    }

    if (
        strtolower( $provider ) !== strtolower( $verified_provider ) ||
        strtolower( $model ) !== strtolower( $verified_model )
    ) {
        return false;
    }

    return hash_equals(
        (string) $verified_signature,
        botzio_ai_connection_signature( $provider, $api_key, $model )
    );
}

function botzio_ai_is_pro_chatbot_module_ready() {
    if ( ! botzio_ai_is_pro_plugin_active() ) {
        return false;
    }

    if (
        class_exists( 'Botzio_AI_Pro' ) &&
        method_exists( 'Botzio_AI_Pro', 'instance' )
    ) {
        $botzio_ai_pro = Botzio_AI_Pro::instance();

        if (
            method_exists( $botzio_ai_pro, 'can_load_pro_features' ) &&
            ! $botzio_ai_pro->can_load_pro_features()
        ) {
            return false;
        }
    }

    $product_assistant_ready = (
        'yes' === get_option( 'botzio_ai_pro_wc_assistant_enabled', 'yes' ) &&
        function_exists( 'wc_get_products' )
    );

    $order_lookup_ready = (
        'yes' === get_option( 'botzio_ai_pro_wc_order_lookup_enabled', 'yes' ) &&
        function_exists( 'wc_get_order' ) &&
        function_exists( 'wc_get_orders' )
    );

    $handoff_ready = 'yes' === get_option( 'botzio_ai_pro_handoff_enabled', 'yes' );
    $lead_ready    = 'yes' === get_option( 'botzio_ai_pro_lead_capture_enabled', 'yes' );

    return (bool) apply_filters(
        'botzio_ai_is_pro_chatbot_module_ready',
        $product_assistant_ready || $order_lookup_ready || $handoff_ready || $lead_ready
    );
}

function botzio_ai_is_frontend_chatbot_ready() {
    return botzio_ai_is_ai_connection_ready() || botzio_ai_is_pro_chatbot_module_ready();
}

/**
 * Activation
 */
function botzio_ai_plugin_activate() {
    add_option( 'botzio_ai_do_activation_redirect', true );

    if ( 'yes' !== get_option( 'botzio_ai_installed_once', 'no' ) ) {
        add_option( 'botzio_ai_show_welcome', 'yes' );
        update_option( 'botzio_ai_installed_once', 'yes' );
    }

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
        __( 'AI Connection', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-connection'
    );

    add_settings_section(
        'botzio_ai_content_section',
        __( 'Business Knowledge', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-content'
    );

    add_settings_section(
        'botzio_ai_appearance_section',
        __( 'Chat Experience', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-appearance'
    );

    add_settings_section(
        'botzio_ai_limits_section',
        __( 'Usage Controls', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-limits'
    );

    add_settings_section(
        'botzio_ai_advanced_section',
        __( 'Data Management', 'botzio-ai' ),
        '__return_false',
        'botzio-ai-advanced'
    );

    /**
     * Connection tab
     */
    add_settings_field(
        'botzio_ai_connection_status',
        __( 'AI Readiness', 'botzio-ai' ),
        'botzio_ai_connection_status_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );

    add_settings_field(
        'botzio_ai_provider',
        __( 'Provider', 'botzio-ai' ),
        'botzio_ai_provider_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );

    add_settings_field(
        'botzio_ai_api_key',
        __( 'Provider API Key', 'botzio-ai' ),
        'botzio_ai_api_key_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );

    add_settings_field(
        'botzio_ai_model',
        __( 'AI Model', 'botzio-ai' ),
        'botzio_ai_model_field',
        'botzio-ai-connection',
        'botzio_ai_connection_section'
    );
    

    /**
     * Content tab
     */
    add_settings_field(
        'botzio_ai_knowledge_base',
        __( 'Business Knowledge', 'botzio-ai' ),
        'botzio_ai_knowledge_base_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    add_settings_field(
        'botzio_ai_custom_prompt',
        __( 'Response Guidelines', 'botzio-ai' ),
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
        __( 'Off-topic Reply', 'botzio-ai' ),
        'botzio_ai_error_message_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    add_settings_field(
        'botzio_ai_starter_suggestions',
        __( 'Starter Questions', 'botzio-ai' ),
        'botzio_ai_starter_suggestions_field',
        'botzio-ai-content',
        'botzio_ai_content_section'
    );

    /**
     * Appearance tab
     */
    add_settings_field(
        'botzio_ai_display_chatbot',
        __( 'Frontend Visibility', 'botzio-ai' ),
        'botzio_ai_display_chatbot_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );
    add_settings_field(
        'botzio_ai_chat_title',
        __( 'Chat Header Title', 'botzio-ai' ),
        'botzio_ai_chat_title_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );

    add_settings_field(
        'botzio_ai_primary_color',
        __( 'Brand Color', 'botzio-ai' ),
        'botzio_ai_primary_color_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );

    add_settings_field(
        'botzio_ai_bubble_position',
        __( 'Launcher Position', 'botzio-ai' ),
        'botzio_ai_bubble_position_field',
        'botzio-ai-appearance',
        'botzio_ai_appearance_section'
    );

    /**
     * Limits tab
     */
    add_settings_field(
        'botzio_ai_daily_limit',
        __( 'Daily Visitor Limit', 'botzio-ai' ),
        'botzio_ai_daily_limit_field',
        'botzio-ai-limits',
        'botzio_ai_limits_section'
    );

    add_settings_field(
        'botzio_ai_temperature',
        __( 'Answer Creativity', 'botzio-ai' ),
        'botzio_ai_temperature_field',
        'botzio-ai-limits',
        'botzio_ai_limits_section'
    );

    add_settings_field(
        'botzio_ai_max_tokens',
        __( 'Answer Length', 'botzio-ai' ),
        'botzio_ai_max_tokens_field',
        'botzio-ai-limits',
        'botzio_ai_limits_section'
    );

    /**
     * Advanced tab
     */
    add_settings_field(
        'botzio_ai_delete_data_on_uninstall',
        __( 'Uninstall Cleanup', 'botzio-ai' ),
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

function botzio_ai_after_settings_save_reset_verification( $option_name = '' ) {
    $connection_options = array(
        'botzio_ai_provider',
        'botzio_ai_api_key',
        'botzio_ai_model',
    );

    if (
        ! in_array( $option_name, $connection_options, true ) ||
        ! function_exists( 'wp_verify_nonce' ) ||
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
add_action( 'updated_option', 'botzio_ai_after_settings_save_reset_verification', 10, 1 );
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

        <?php echo esc_html__( 'Remove Botzio settings and visitor question analytics when the plugin is uninstalled.', 'botzio-ai' ); ?>
    </label>

    <?php
}

// Callback function for the temperature field
function botzio_ai_temperature_field() {
    $value = get_option( 'botzio_ai_temperature', 0.3 );
    ?>
    <input type="number" name="botzio_ai_temperature" step="0.01" min="0" max="1" value="<?php echo esc_attr( $value ); ?>" class="small-text" />
    <p class="description">
        <?php echo esc_html__( 'Choose how creative the chatbot should be. Lower values keep answers more consistent; higher values make replies more flexible.', 'botzio-ai' ); ?>
    </p>
    <?php
}
function botzio_ai_connection_status_field() {
    $provider          = get_option( 'botzio_ai_provider', 'openrouter' );
    $verified_model    = get_option( 'botzio_ai_connection_verified_model', '' );
    $is_verified       = botzio_ai_is_ai_connection_ready();
    ?>
    <div class="botzio-ai-status-row">
        <span class="botzio-ai-status-dot <?php echo $is_verified ? 'connected' : 'disconnected'; ?>"></span>

        <strong>
            <?php
            if ( $is_verified ) {
                echo esc_html( ucfirst( $provider ) . ' is ready' );

                if ( ! empty( $verified_model ) ) {
                    echo esc_html( ' - ' . $verified_model );
                }
            } else {
                echo esc_html( ucfirst( $provider ) . ' needs testing' );
            }
            ?>
        </strong>
    </div>

    <?php if ( ! $is_verified ) : ?>
        <p class="description botzio-ai-settings-hint">
            <?php echo esc_html__( 'Prepare your chatbot knowledge now. Connect and test AI when you are ready to show it to visitors.', 'botzio-ai' ); ?>
        </p>
    <?php endif; ?>
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

function botzio_ai_dismiss_welcome() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    update_option( 'botzio_ai_show_welcome', 'no' );

    wp_send_json_success(
        array(
            'message' => 'Welcome guide dismissed.',
        )
    );
}
add_action( 'wp_ajax_botzio_ai_dismiss_welcome', 'botzio_ai_dismiss_welcome' );

function botzio_ai_save_welcome_suggestions() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    $suggestions = isset( $_POST['suggestions'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['suggestions'] ) )
        : '';

    update_option( 'botzio_ai_starter_suggestions', $suggestions );

    wp_send_json_success(
        array(
            'message' => 'Starter questions saved.',
        )
    );
}
add_action( 'wp_ajax_botzio_ai_save_welcome_suggestions', 'botzio_ai_save_welcome_suggestions' );

function botzio_ai_save_welcome_business_info() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    $business_name  = isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '';
    $business_offer = isset( $_POST['business_offer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['business_offer'] ) ) : '';
    $audience       = isset( $_POST['audience'] ) ? sanitize_text_field( wp_unslash( $_POST['audience'] ) ) : '';
    $support        = isset( $_POST['support'] ) ? sanitize_text_field( wp_unslash( $_POST['support'] ) ) : '';

    if ( '' === $business_name && '' === $business_offer && '' === $audience && '' === $support ) {
        wp_send_json_error(
            array(
            'message' => 'Add at least one business detail before saving.',
            )
        );
    }

    $lines = array( 'Quick Business Info:' );

    if ( '' !== $business_name ) {
        $lines[] = '- Business name: ' . $business_name;
    }

    if ( '' !== $business_offer ) {
        $lines[] = '- What we offer: ' . $business_offer;
    }

    if ( '' !== $audience ) {
        $lines[] = '- Who we help: ' . $audience;
    }

    if ( '' !== $support ) {
        $lines[] = '- Support contact: ' . $support;
    }

    $quick_info     = implode( "\n", $lines );
    $knowledge_base = trim( get_option( 'botzio_ai_knowledge_base', '' ) );

    if ( preg_match( '/Quick Business Info:\s*.*?(?=\n\n[A-Z][^\n:]+:|\z)/s', $knowledge_base ) ) {
        $knowledge_base = preg_replace( '/Quick Business Info:\s*.*?(?=\n\n[A-Z][^\n:]+:|\z)/s', $quick_info, $knowledge_base );
    } else {
        $knowledge_base = $knowledge_base ? $quick_info . "\n\n" . $knowledge_base : $quick_info;
    }

    update_option( 'botzio_ai_knowledge_base', trim( $knowledge_base ) );

    wp_send_json_success(
        array(
            'message'        => 'Business basics saved to your knowledge base.',
            'knowledge_base' => trim( $knowledge_base ),
        )
    );
}
add_action( 'wp_ajax_botzio_ai_save_welcome_business_info', 'botzio_ai_save_welcome_business_info' );

function botzio_ai_save_welcome_appearance() {
    check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error(
            array(
                'message' => 'Permission denied.',
            )
        );
    }

    $chat_title      = isset( $_POST['chat_title'] ) ? sanitize_text_field( wp_unslash( $_POST['chat_title'] ) ) : '';
    $primary_color   = isset( $_POST['primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) : '';
    $welcome_message = isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : '';
    $bubble_position = isset( $_POST['bubble_position'] ) ? botzio_ai_sanitize_bubble_position( sanitize_text_field( wp_unslash( $_POST['bubble_position'] ) ) ) : 'right';

    update_option( 'botzio_ai_chat_title', '' !== $chat_title ? $chat_title : 'Chat Support' );
    update_option( 'botzio_ai_primary_color', $primary_color ? $primary_color : '#111111' );
    update_option( 'botzio_ai_welcome_message', '' !== $welcome_message ? $welcome_message : 'Hello! Welcome to our website. How can I help you today?' );
    update_option( 'botzio_ai_bubble_position', $bubble_position );

    wp_send_json_success(
        array(
            'message' => 'Chat preview saved.',
        )
    );
}
add_action( 'wp_ajax_botzio_ai_save_welcome_appearance', 'botzio_ai_save_welcome_appearance' );

// Callback function for the max_tokens field
function botzio_ai_max_tokens_field() {
    $value = get_option( 'botzio_ai_max_tokens', 150 );
    ?>
    <input type="number" name="botzio_ai_max_tokens" value="<?php echo esc_attr( $value ); ?>" class="small-text" />
    <p class="description">
        <?php echo esc_html__( 'Set the preferred reply size. Higher values allow fuller answers; lower values keep responses concise.', 'botzio-ai' ); ?>
    </p>
    <?php
}
function botzio_ai_knowledge_base_field() {
    $value = get_option( 'botzio_ai_knowledge_base', '' );
    ?>
    <div class="botzio-ai-kb-builder">
        <div class="botzio-ai-kb-builder-copy">
            <strong><?php echo esc_html__( 'Prepare your chatbot knowledge', 'botzio-ai' ); ?></strong>
            <p><?php echo esc_html__( 'Add the business details visitors should hear: what you offer, who you serve, policies, contact options, and your most common answers.', 'botzio-ai' ); ?></p>
        </div>

        <div class="botzio-ai-kb-template-grid">
            <button type="button" class="button botzio-ai-kb-template" data-template="business">
                <span class="dashicons dashicons-store"></span>
                <?php echo esc_html__( 'Business Profile', 'botzio-ai' ); ?>
            </button>

            <button type="button" class="button botzio-ai-kb-template" data-template="products">
                <span class="dashicons dashicons-cart"></span>
                <?php echo esc_html__( 'Offers & Services', 'botzio-ai' ); ?>
            </button>

            <button type="button" class="button botzio-ai-kb-template" data-template="policies">
                <span class="dashicons dashicons-clipboard"></span>
                <?php echo esc_html__( 'Policies', 'botzio-ai' ); ?>
            </button>

            <button type="button" class="button botzio-ai-kb-template" data-template="support">
                <span class="dashicons dashicons-email-alt"></span>
                <?php echo esc_html__( 'Support Details', 'botzio-ai' ); ?>
            </button>

            <button type="button" class="button botzio-ai-kb-template" data-template="faq">
                <span class="dashicons dashicons-editor-help"></span>
                <?php echo esc_html__( 'FAQ Block', 'botzio-ai' ); ?>
            </button>
        </div>
    </div>

    <textarea
        name="botzio_ai_knowledge_base"
        id="botzio_knowledge_base"
        rows="10"
        cols="60"
        class="large-text"
        placeholder="Add your business overview, offers, FAQs, policies, contact details, shipping, returns, and support instructions."
    ><?php echo esc_textarea( $value ); ?></textarea>

    <p class="description botzio-ai-settings-hint">
        <?php echo esc_html__( 'Keep this focused. Short, structured business information usually creates better answers than pasted pages or long marketing copy.', 'botzio-ai' ); ?>
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

function botzio_ai_get_max_tokens() {
    $stored_value = get_option( 'botzio_ai_max_tokens', 150 );
    $max_tokens   = botzio_ai_sanitize_max_tokens( $stored_value );

    if ( absint( $stored_value ) !== $max_tokens ) {
        update_option( 'botzio_ai_max_tokens', $max_tokens );
    }

    return $max_tokens;
}

function botzio_ai_should_retry_incomplete_response( $content, $finish_reason = '' ) {
    $content       = trim( wp_strip_all_tags( (string) $content ) );
    $finish_reason = strtolower( (string) $finish_reason );

    if ( '' === $content ) {
        return false;
    }

    if ( in_array( $finish_reason, array( 'length', 'max_tokens' ), true ) ) {
        return true;
    }

    $word_count = str_word_count( $content );

    if ( $word_count < 4 ) {
        return false;
    }

    if ( strlen( $content ) > 180 ) {
        return false;
    }

    return ! preg_match( '/[.!?。؟)]$/u', $content );
}

function botzio_ai_get_retry_max_tokens( $max_tokens ) {
    return min( 1000, max( 250, absint( $max_tokens ) * 2 ) );
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
        function_exists( 'wp_verify_nonce' ) &&
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
            __( 'Test the selected provider, API key, and model before saving changes.', 'botzio-ai' ),
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
    $is_verified       = botzio_ai_is_ai_connection_ready();
    $chatbot_ready     = botzio_ai_is_frontend_chatbot_ready();
    $pro_module_ready  = botzio_ai_is_pro_chatbot_module_ready();
    $pro_is_active     = botzio_ai_is_pro_plugin_active();
    $pro_upgrade_url   = apply_filters( 'botzio_ai_pro_upgrade_url', admin_url( 'plugins.php' ) );
    $knowledge_base    = trim( get_option( 'botzio_ai_knowledge_base', '' ) );
    $daily_limit       = absint( get_option( 'botzio_ai_daily_limit', 20 ) );
    $chatbot_enabled   = 'yes' === get_option( 'botzio_ai_display_chatbot', 'yes' );
    $show_welcome      = 'yes' === get_option( 'botzio_ai_show_welcome', 'no' );
    $default_welcome   = 'Hello! Welcome to our website. How can I help you today?';
    $starter_suggestions = get_option(
        'botzio_ai_starter_suggestions',
        "What products do you sell?\nWhat is your return policy?\nDo you offer Cash on Delivery?"
    );
    $chat_title        = get_option( 'botzio_ai_chat_title', 'Chat Support' );
    $primary_color     = sanitize_hex_color( get_option( 'botzio_ai_primary_color', '#111111' ) );
    $primary_color     = $primary_color ? $primary_color : '#111111';
    $bubble_position   = get_option( 'botzio_ai_bubble_position', 'right' );
    $starter_questions = array_values(
        array_filter(
            array_map( 'trim', explode( "\n", wp_strip_all_tags( $starter_suggestions ) ) )
        )
    );

    if ( empty( $starter_questions ) ) {
        $starter_questions = array(
            __( 'What products do you sell?', 'botzio-ai' ),
            __( 'What is your return policy?', 'botzio-ai' ),
            __( 'How can I contact support?', 'botzio-ai' ),
        );
    }

    $preview_questions = array_slice( $starter_questions, 0, 3 );
    $has_starter_questions = ! empty( trim( $starter_suggestions ) );
    $appearance_done   = (
        $chat_title !== 'Chat Support' ||
        $primary_color !== '#111111' ||
        $bubble_position !== 'right' ||
        get_option( 'botzio_ai_welcome_message', $default_welcome ) !== $default_welcome
    );
    $has_knowledge     = ! empty( $knowledge_base );
    $setup_complete    = $has_knowledge && $has_starter_questions && $appearance_done && $is_verified;
    $setup_completed_count = absint( $has_knowledge ) + absint( $has_starter_questions ) + absint( $appearance_done ) + absint( $is_verified );
    $active_setup_step = 'done';

    if ( ! $has_knowledge ) {
        $active_setup_step = 'content';
    } elseif ( ! $has_starter_questions ) {
        $active_setup_step = 'suggestions';
    } elseif ( ! $appearance_done ) {
        $active_setup_step = 'appearance';
    } elseif ( ! $is_verified ) {
        $active_setup_step = 'connection';
    }

    $setup_total_steps     = 4;
    $setup_remaining_count = max( 0, $setup_total_steps - $setup_completed_count );
    $setup_progress        = min( 100, round( ( $setup_completed_count / $setup_total_steps ) * 100 ) );
    ?>
    <div class="wrap botzio-ai-admin-wrap">
        <h1 class="botzio-ai-page-title"><?php echo esc_html__( 'Botzio AI Settings', 'botzio-ai' ); ?></h1>

            <div class="botzio-ai-welcome-overlay <?php echo $show_welcome ? '' : 'is-hidden'; ?>" role="dialog" aria-modal="true" aria-labelledby="botzio-ai-welcome-title">
                <div class="botzio-ai-welcome-modal">
                    <button type="button" class="botzio-ai-welcome-close" aria-label="<?php echo esc_attr__( 'Close welcome guide', 'botzio-ai' ); ?>">
                        &times;
                    </button>

                    <div class="botzio-ai-welcome-visual">
                        <div class="botzio-ai-welcome-logo">
                            <img
                                src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/botzio-ai-chat-icon.png' ); ?>"
                                alt=""
                            />
                        </div>

                        <span class="botzio-ai-welcome-spark">
                            <span class="dashicons dashicons-lightbulb"></span>
                        </span>

                        <div class="botzio-ai-welcome-progress">
                            <strong>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: completed setup tasks, 2: total setup tasks */
                                        __( '%1$d of %2$d completed', 'botzio-ai' ),
                                        $setup_completed_count,
                                        4
                                    )
                                );
                                ?>
                            </strong>

                            <div class="botzio-ai-welcome-progress-bar" aria-hidden="true">
                                <span style="width: <?php echo esc_attr( ( $setup_completed_count / 4 ) * 100 ); ?>%;"></span>
                            </div>

                            <ul>
                                <li class="<?php echo $has_knowledge ? 'is-complete' : 'is-pending'; ?>" data-welcome-step="business">
                                    <span class="dashicons <?php echo $has_knowledge ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
                                    <?php echo esc_html__( 'Business Basics', 'botzio-ai' ); ?>
                                </li>

                                <li class="<?php echo $has_starter_questions ? 'is-complete' : 'is-pending'; ?>" data-welcome-step="suggestions">
                                    <span class="dashicons <?php echo $has_starter_questions ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
                                    <?php echo esc_html__( 'Starter Questions', 'botzio-ai' ); ?>
                                </li>

                                <li class="<?php echo $appearance_done ? 'is-complete' : 'is-pending'; ?>" data-welcome-step="preview">
                                    <span class="dashicons <?php echo $appearance_done ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
                                    <?php echo esc_html__( 'Preview', 'botzio-ai' ); ?>
                                </li>

                                <li class="<?php echo $is_verified ? 'is-complete' : 'is-pending'; ?>" data-welcome-step="connection">
                                    <span class="dashicons <?php echo $is_verified ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span>
                                    <?php echo esc_html__( 'Go Live', 'botzio-ai' ); ?>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="botzio-ai-welcome-content">
                        <div class="botzio-ai-welcome-panel is-active" data-panel="start">
                        <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Welcome to Botzio AI', 'botzio-ai' ); ?></p>
                        <h2 id="botzio-ai-welcome-title"><?php echo esc_html__( 'Prepare your chatbot knowledge now. Connect AI when ready.', 'botzio-ai' ); ?></h2>
                        <p>
                            <?php echo esc_html__( 'Start with the business details visitors ask about most, add helpful starter questions, preview the experience, then connect AI when you are ready to launch.', 'botzio-ai' ); ?>
                        </p>

                        <div class="botzio-ai-welcome-options">
                            <a class="botzio-ai-welcome-option is-recommended botzio-ai-open-welcome-panel" href="#business" data-panel="business" data-welcome-step="business">
                                <span class="dashicons dashicons-database"></span>
                                <em><?php echo esc_html__( 'Recommended', 'botzio-ai' ); ?></em>
                                <strong><?php echo esc_html__( 'Add Business Basics', 'botzio-ai' ); ?></strong>
                                <small><?php echo esc_html__( 'Tell Botzio what your business offers, who you serve, and how customers can reach you.', 'botzio-ai' ); ?></small>
                                <span class="botzio-ai-welcome-option-status <?php echo $has_knowledge ? 'is-complete' : 'is-pending'; ?>">
                                    <?php echo esc_html( $has_knowledge ? __( 'Completed', 'botzio-ai' ) : __( 'Remaining', 'botzio-ai' ) ); ?>
                                </span>
                                <b><?php echo esc_html__( 'Start Here', 'botzio-ai' ); ?></b>
                            </a>

                            <a class="botzio-ai-welcome-option botzio-ai-open-welcome-panel" href="#suggestions" data-panel="suggestions" data-welcome-step="suggestions">
                                <span class="dashicons dashicons-editor-help"></span>
                                <strong><?php echo esc_html__( 'Shape First Questions', 'botzio-ai' ); ?></strong>
                                <small><?php echo esc_html__( 'Show visitors useful prompts based on what they usually want to know first.', 'botzio-ai' ); ?></small>
                                <span class="botzio-ai-welcome-option-status <?php echo $has_starter_questions ? 'is-complete' : 'is-pending'; ?>">
                                    <?php echo esc_html( $has_starter_questions ? __( 'Completed', 'botzio-ai' ) : __( 'Remaining', 'botzio-ai' ) ); ?>
                                </span>
                                <b><?php echo esc_html__( 'Edit Prompts', 'botzio-ai' ); ?></b>
                            </a>

                            <a class="botzio-ai-welcome-option botzio-ai-open-welcome-panel" href="#preview" data-panel="preview" data-welcome-step="preview">
                                <span class="dashicons dashicons-admin-appearance"></span>
                                <strong><?php echo esc_html__( 'Style the Chat', 'botzio-ai' ); ?></strong>
                                <small><?php echo esc_html__( 'Match the chatbot title, color, welcome message, and launcher position to your site.', 'botzio-ai' ); ?></small>
                                <span class="botzio-ai-welcome-option-status <?php echo $appearance_done ? 'is-complete' : 'is-pending'; ?>">
                                    <?php echo esc_html( $appearance_done ? __( 'Completed', 'botzio-ai' ) : __( 'Remaining', 'botzio-ai' ) ); ?>
                                </span>
                                <b><?php echo esc_html__( 'Preview Chatbot', 'botzio-ai' ); ?></b>
                            </a>

                            <a class="botzio-ai-welcome-option botzio-ai-welcome-action botzio-ai-jump-tab" href="#botzio-ai-tab-connection" data-welcome-step="connection">
                                <span class="dashicons dashicons-admin-network"></span>
                                <strong><?php echo esc_html__( 'Connect AI to Launch', 'botzio-ai' ); ?></strong>
                                <small><?php echo esc_html__( 'Already have an API key? Connect your provider, test it, and make the chatbot available to visitors.', 'botzio-ai' ); ?></small>
                                <span class="botzio-ai-welcome-option-status <?php echo $is_verified ? 'is-complete' : 'is-pending'; ?>">
                                    <?php echo esc_html( $is_verified ? __( 'Completed', 'botzio-ai' ) : __( 'Remaining', 'botzio-ai' ) ); ?>
                                </span>
                                <b><?php echo esc_html__( 'Connect AI', 'botzio-ai' ); ?></b>
                            </a>
                        </div>

                        <div class="botzio-ai-welcome-actions">
                            <button type="button" class="button-link botzio-ai-welcome-skip">
                                <?php echo esc_html__( 'I will do this later', 'botzio-ai' ); ?>
                            </button>
                        </div>
                        </div>

                        <div class="botzio-ai-welcome-panel botzio-ai-welcome-business" data-panel="business">
                            <div>
                                <button type="button" class="button-link botzio-ai-welcome-back" data-panel="start">
                                    <?php echo esc_html__( 'Back', 'botzio-ai' ); ?>
                                </button>
                                <strong><?php echo esc_html__( 'Business Basics', 'botzio-ai' ); ?></strong>
                                <p><?php echo esc_html__( 'Add a short business profile. Botzio will turn it into useful chatbot knowledge for you.', 'botzio-ai' ); ?></p>
                            </div>

                            <div class="botzio-ai-welcome-business-grid">
                                <label>
                                    <span><?php echo esc_html__( 'Business name', 'botzio-ai' ); ?></span>
                                    <input
                                        type="text"
                                        id="botzio-ai-welcome-business-name"
                                        value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
                                    />
                                </label>

                                <label>
                                    <span><?php echo esc_html__( 'Ideal customers', 'botzio-ai' ); ?></span>
                                    <input
                                        type="text"
                                        id="botzio-ai-welcome-business-audience"
                                        placeholder="<?php echo esc_attr__( 'Example: online shoppers, local customers, small businesses', 'botzio-ai' ); ?>"
                                    />
                                </label>

                                <label class="is-wide">
                                    <span><?php echo esc_html__( 'Main offers', 'botzio-ai' ); ?></span>
                                    <textarea
                                        id="botzio-ai-welcome-business-offer"
                                        rows="3"
                                        placeholder="<?php echo esc_attr__( 'Example: women clothing, delivery services, digital marketing packages', 'botzio-ai' ); ?>"
                                    ></textarea>
                                </label>

                                <label class="is-wide">
                                    <span><?php echo esc_html__( 'Support email or WhatsApp', 'botzio-ai' ); ?></span>
                                    <input
                                        type="text"
                                        id="botzio-ai-welcome-business-support"
                                        placeholder="<?php echo esc_attr__( 'Example: support@example.com or +123456789', 'botzio-ai' ); ?>"
                                    />
                                </label>
                            </div>

                            <div class="botzio-ai-welcome-suggestions-actions">
                                <button type="button" class="button button-secondary" id="botzio-ai-save-welcome-business-info">
                                    <?php echo esc_html__( 'Save & Continue', 'botzio-ai' ); ?>
                                </button>

                                <button type="button" class="button-link botzio-ai-open-welcome-panel" data-panel="suggestions">
                                    <?php echo esc_html__( 'Skip for Now', 'botzio-ai' ); ?>
                                </button>

                                <span id="botzio-ai-welcome-business-result"></span>
                            </div>
                        </div>

                        <div class="botzio-ai-welcome-panel botzio-ai-welcome-suggestions" data-panel="suggestions">
                            <div>
                                <button type="button" class="button-link botzio-ai-welcome-back" data-panel="start">
                                    <?php echo esc_html__( 'Back', 'botzio-ai' ); ?>
                                </button>
                                <strong><?php echo esc_html__( 'Starter Questions', 'botzio-ai' ); ?></strong>
                                <p><?php echo esc_html__( 'Add one helpful question per line. These appear when visitors open a new chat.', 'botzio-ai' ); ?></p>
                            </div>

                            <textarea id="botzio-ai-welcome-starter-suggestions" rows="4"><?php echo esc_textarea( $starter_suggestions ); ?></textarea>

                            <div class="botzio-ai-welcome-suggestions-actions">
                                <button type="button" class="button button-secondary" id="botzio-ai-save-welcome-suggestions">
                                    <?php echo esc_html__( 'Save & Continue', 'botzio-ai' ); ?>
                                </button>

                                <button type="button" class="button-link botzio-ai-open-welcome-panel" data-panel="preview">
                                    <?php echo esc_html__( 'Skip for Now', 'botzio-ai' ); ?>
                                </button>

                                <span id="botzio-ai-welcome-suggestions-result"></span>
                            </div>
                        </div>

                        <div class="botzio-ai-welcome-panel botzio-ai-welcome-preview-step" data-panel="preview">
                            <div class="botzio-ai-welcome-step-heading">
                                <button type="button" class="button-link botzio-ai-welcome-back" data-panel="start">
                                    <?php echo esc_html__( 'Back', 'botzio-ai' ); ?>
                                </button>
                                <strong><?php echo esc_html__( 'Style the Chatbot', 'botzio-ai' ); ?></strong>
                                <p><?php echo esc_html__( 'Tune the first impression before visitors see it on your site.', 'botzio-ai' ); ?></p>
                            </div>

                            <div class="botzio-ai-welcome-preview-layout">
                                <div class="botzio-ai-welcome-preview-fields">
                                    <label>
                                        <span><?php echo esc_html__( 'Chat title', 'botzio-ai' ); ?></span>
                                        <input
                                            type="text"
                                            id="botzio-ai-welcome-chat-title"
                                            value="<?php echo esc_attr( $chat_title ); ?>"
                                        />
                                    </label>

                                    <label>
                                        <span><?php echo esc_html__( 'Primary color', 'botzio-ai' ); ?></span>
                                        <input
                                            type="color"
                                            id="botzio-ai-welcome-primary-color"
                                            value="<?php echo esc_attr( $primary_color ); ?>"
                                        />
                                    </label>

                                    <label>
                                        <span><?php echo esc_html__( 'Bubble position', 'botzio-ai' ); ?></span>
                                        <select id="botzio-ai-welcome-bubble-position">
                                            <option value="right" <?php selected( $bubble_position, 'right' ); ?>>
                                                <?php echo esc_html__( 'Bottom Right', 'botzio-ai' ); ?>
                                            </option>
                                            <option value="left" <?php selected( $bubble_position, 'left' ); ?>>
                                                <?php echo esc_html__( 'Bottom Left', 'botzio-ai' ); ?>
                                            </option>
                                        </select>
                                    </label>

                                    <label class="is-wide">
                                        <span><?php echo esc_html__( 'Welcome message', 'botzio-ai' ); ?></span>
                                        <textarea id="botzio-ai-welcome-message-preview" rows="3"><?php echo esc_textarea( get_option( 'botzio_ai_welcome_message', $default_welcome ) ); ?></textarea>
                                    </label>
                                </div>

                                <div class="botzio-ai-preview-phone" style="<?php echo esc_attr( '--botzio-preview-primary:' . $primary_color . ';' ); ?>">
                                    <div class="botzio-ai-preview-chat">
                                        <div class="botzio-ai-preview-header">
                                            <span class="botzio-ai-preview-avatar" aria-hidden="true">
                                                <img
                                                    src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/botzio-ai-chat-icon.png' ); ?>"
                                                    alt=""
                                                />
                                            </span>
                                            <strong class="botzio-ai-preview-title"><?php echo esc_html( $chat_title ); ?></strong>
                                            <em><?php echo esc_html__( 'Preview', 'botzio-ai' ); ?></em>
                                        </div>

                                        <div class="botzio-ai-preview-window" aria-live="polite">
                                            <div class="botzio-ai-preview-row is-bot">
                                                <div class="botzio-ai-preview-bubble">
                                                    <span><?php echo esc_html__( 'Botzio:', 'botzio-ai' ); ?></span>
                                                    <span class="botzio-ai-preview-welcome-message"><?php echo esc_html( get_option( 'botzio_ai_welcome_message', $default_welcome ) ); ?></span>
                                                </div>
                                            </div>

                                            <div class="botzio-ai-preview-answer">
                                                <div class="botzio-ai-preview-row is-user">
                                                    <div class="botzio-ai-preview-bubble"><?php echo esc_html( $preview_questions[0] ); ?></div>
                                                </div>

                                                <div class="botzio-ai-preview-row is-bot">
                                                    <div class="botzio-ai-preview-bubble">
                                                        <span><?php echo esc_html__( 'Botzio:', 'botzio-ai' ); ?></span>
                                                        <?php echo esc_html__( 'Sample preview: once AI is connected, Botzio will answer using your saved business knowledge and site instructions.', 'botzio-ai' ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="botzio-ai-welcome-suggestions-actions botzio-ai-preview-edit-actions">
                                <button type="button" class="button button-secondary" id="botzio-ai-save-welcome-appearance">
            <?php echo esc_html__( 'Save Preview', 'botzio-ai' ); ?>
                                </button>

                                <a class="button-link botzio-ai-welcome-action botzio-ai-jump-tab" href="#botzio-ai-tab-appearance">
                                    <?php echo esc_html__( 'Open Full Design Settings', 'botzio-ai' ); ?>
                                </a>

                                <span id="botzio-ai-welcome-appearance-result"></span>
                            </div>

                            <div class="botzio-ai-preview-next is-hidden">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <div>
                                    <strong><?php echo esc_html__( 'Your chatbot foundation is ready.', 'botzio-ai' ); ?></strong>
                                    <p><?php echo esc_html__( 'Connect AI when you are ready to make the chatbot live for visitors.', 'botzio-ai' ); ?></p>
                                </div>
                                <a class="button button-primary botzio-ai-welcome-action botzio-ai-jump-tab" href="#botzio-ai-tab-connection">
                                    <?php echo esc_html__( 'Connect AI', 'botzio-ai' ); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <div class="botzio-ai-admin-hero">
            <div class="botzio-ai-admin-brand">
                <div class="botzio-ai-admin-logo-mark">
                    <img
                        src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/botzio-ai-chat-icon.png' ); ?>"
                        alt=""
                    />
                </div>

                <div>
                    <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Smart Website Assistant', 'botzio-ai' ); ?></p>
                    <div class="botzio-ai-admin-title"><?php echo esc_html__( 'Botzio AI', 'botzio-ai' ); ?></div>
                    <p class="botzio-ai-admin-subtitle">
                        <?php echo esc_html__( 'Prepare your chatbot knowledge, shape the visitor experience, and connect AI when you are ready to go live.', 'botzio-ai' ); ?>
                    </p>
                </div>
            </div>

            <div class="botzio-ai-hero-actions">
                <button type="button" class="button button-secondary botzio-ai-open-configurator">
                    <?php echo esc_html__( 'Setup Guide', 'botzio-ai' ); ?>
                </button>

                <a class="button button-secondary botzio-ai-pro-compare-cta botzio-ai-scroll-pro-comparison" href="#botzio-ai-pro-comparison">
                    <?php echo esc_html( $pro_is_active ? __( 'View Pro Features', 'botzio-ai' ) : __( 'See What Pro Adds', 'botzio-ai' ) ); ?>
                </a>
            </div>
        </div>

        <?php if ( $chatbot_enabled && ! $chatbot_ready ) : ?>
            <div class="botzio-ai-readiness-notice">
                <span class="dashicons dashicons-hidden"></span>
                <div>
                    <strong><?php echo esc_html__( 'Your chatbot is hidden until AI is ready.', 'botzio-ai' ); ?></strong>
                    <p><?php echo esc_html__( 'Prepare your knowledge first, then connect and test AI before showing the chatbot to visitors.', 'botzio-ai' ); ?></p>
                </div>
                <a class="button button-primary botzio-ai-jump-tab" href="#botzio-ai-tab-connection">
                    <?php echo esc_html__( 'Connect AI', 'botzio-ai' ); ?>
                </a>
            </div>
        <?php elseif ( $chatbot_enabled && $pro_module_ready && ! $is_verified ) : ?>
            <div class="botzio-ai-readiness-notice is-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div>
                    <strong><?php echo esc_html__( 'Pro modules can assist visitors without a general AI reply.', 'botzio-ai' ); ?></strong>
                    <p><?php echo esc_html__( 'Enabled Pro workflows can still handle store, order, lead, or handoff requests while the AI connection is pending.', 'botzio-ai' ); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! $setup_complete ) : ?>
        <div class="botzio-ai-setup-guide is-incomplete">
            <div class="botzio-ai-setup-guide-heading">
                <div>
                    <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Launch Checklist', 'botzio-ai' ); ?></p>
                    <h2>
                        <?php
                        echo esc_html(
                            $setup_complete
                                ? __( 'Your chatbot basics are ready.', 'botzio-ai' )
                                : __( 'Prepare the chatbot before launch.', 'botzio-ai' )
                        );
                        ?>
                    </h2>
                    <p>
                        <?php
                        echo esc_html(
                            $setup_complete
                                ? __( 'You can keep improving responses by refining the knowledge base and appearance anytime.', 'botzio-ai' )
                                : __( 'Add useful business knowledge, shape starter questions, preview the chat, then connect AI when you are ready.', 'botzio-ai' )
                        );
                        ?>
                    </p>
                </div>

                <div class="botzio-ai-setup-progress">
                    <strong>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: completed setup tasks, 2: total setup tasks */
                                __( '%1$d of %2$d complete', 'botzio-ai' ),
                                $setup_completed_count,
                                $setup_total_steps
                            )
                        );
                        ?>
                    </strong>
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: remaining setup tasks */
                                _n( '%d task remaining', '%d tasks remaining', $setup_remaining_count, 'botzio-ai' ),
                                $setup_remaining_count
                            )
                        );
                        ?>
                    </span>
                    <div class="botzio-ai-setup-progress-bar" aria-hidden="true">
                        <i style="width: <?php echo esc_attr( $setup_progress ); ?>%;"></i>
                    </div>
                </div>

                <button type="button" class="button button-secondary botzio-ai-open-configurator">
                    <?php echo esc_html__( 'Open Setup Guide', 'botzio-ai' ); ?>
                </button>
            </div>

            <div class="botzio-ai-setup-steps">
                <a class="botzio-ai-setup-step botzio-ai-jump-tab <?php echo $has_knowledge ? 'is-complete' : ( 'content' === $active_setup_step ? 'is-active' : 'is-pending' ); ?>" href="#botzio-ai-tab-content">
                    <span class="botzio-ai-setup-step-number">1</span>
                    <span class="dashicons dashicons-store"></span>
                    <strong><?php echo esc_html__( 'Business Basics', 'botzio-ai' ); ?></strong>
                    <small><?php echo esc_html__( 'Add what you offer, who you help, and how customers can contact you.', 'botzio-ai' ); ?></small>
                    <em><?php echo esc_html( $has_knowledge ? __( 'Done', 'botzio-ai' ) : __( 'Start here', 'botzio-ai' ) ); ?></em>
                </a>

                <a class="botzio-ai-setup-step botzio-ai-jump-tab <?php echo $has_starter_questions ? 'is-complete' : ( 'suggestions' === $active_setup_step ? 'is-active' : 'is-pending' ); ?>" href="#botzio-ai-tab-content">
                    <span class="botzio-ai-setup-step-number">2</span>
                    <span class="dashicons dashicons-editor-help"></span>
                    <strong><?php echo esc_html__( 'Starter Questions', 'botzio-ai' ); ?></strong>
                    <small><?php echo esc_html__( 'Guide visitors with helpful questions they can tap immediately.', 'botzio-ai' ); ?></small>
                    <em><?php echo esc_html( $has_starter_questions ? __( 'Done', 'botzio-ai' ) : ( 'suggestions' === $active_setup_step ? __( 'Next', 'botzio-ai' ) : __( 'Review next', 'botzio-ai' ) ) ); ?></em>
                </a>

                <a class="botzio-ai-setup-step botzio-ai-jump-tab <?php echo $appearance_done ? 'is-complete' : ( 'appearance' === $active_setup_step ? 'is-active' : 'is-pending' ); ?>" href="#botzio-ai-tab-appearance">
                    <span class="botzio-ai-setup-step-number">3</span>
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <strong><?php echo esc_html__( 'Chat Preview', 'botzio-ai' ); ?></strong>
                    <small><?php echo esc_html__( 'Check the title, color, welcome message, and first impression.', 'botzio-ai' ); ?></small>
                    <em><?php echo esc_html( $appearance_done ? __( 'Done', 'botzio-ai' ) : ( 'appearance' === $active_setup_step ? __( 'Next', 'botzio-ai' ) : __( 'After questions', 'botzio-ai' ) ) ); ?></em>
                </a>

                <a class="botzio-ai-setup-step botzio-ai-jump-tab <?php echo $is_verified ? 'is-complete' : ( 'connection' === $active_setup_step ? 'is-active' : 'is-pending' ); ?>" href="#botzio-ai-tab-connection">
                    <span class="botzio-ai-setup-step-number">4</span>
                    <span class="dashicons dashicons-admin-network"></span>
                    <strong><?php echo esc_html__( 'Connect AI', 'botzio-ai' ); ?></strong>
                    <small><?php echo esc_html__( 'Choose a provider, test the model, and make the chatbot ready for visitors.', 'botzio-ai' ); ?></small>
                    <em><?php echo esc_html( $is_verified ? __( 'Done', 'botzio-ai' ) : ( 'connection' === $active_setup_step ? __( 'Go live', 'botzio-ai' ) : __( 'Final step', 'botzio-ai' ) ) ); ?></em>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $total_chats = absint( get_option( 'botzio_ai_total_chats', 0 ) );
        $recent_questions = get_option( 'botzio_ai_recent_questions', array() );
        $recent_questions = is_array( $recent_questions ) ? $recent_questions : array();
        $total_questions = max( absint( get_option( 'botzio_ai_total_questions', 0 ) ), count( $recent_questions ) );
        $question_topics = botzio_ai_get_question_topics( $recent_questions );
        $needs_review_questions = botzio_ai_get_review_questions( $recent_questions );
        $needs_review_count = count( $needs_review_questions );
        $top_topic_count = ! empty( $question_topics ) ? max( $question_topics ) : 0;
        $free_features = array(
            array(
                'icon'  => 'dashicons-admin-network',
                'label' => __( 'AI model connection', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-database',
                'label' => __( 'Focused business knowledge', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-admin-appearance',
                'label' => __( 'Chat design controls', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-chart-line',
                'label' => __( 'Usage limits and safety controls', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-chart-bar',
                'label' => __( 'Visitor question insights', 'botzio-ai' ),
            ),
        );

        $pro_features = array(
            array(
                'icon'  => 'dashicons-cart',
                'label' => __( 'WooCommerce product assistant', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-star-filled',
                'label' => __( 'Alternative product recommendations', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-clipboard',
                'label' => __( 'Customer order status lookup', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-id-alt',
                'label' => __( 'Lead capture inside the chatbot', 'botzio-ai' ),
            ),
            array(
                'icon'  => 'dashicons-phone',
                'label' => __( 'Human handoff through WhatsApp or email', 'botzio-ai' ),
            ),
        );
        ?>

        <div class="botzio-ai-compact-overview">
            <div class="botzio-ai-compact-stat">
                <span class="dashicons dashicons-format-chat"></span>
                <div>
                    <small><?php echo esc_html__( 'Visitor Chats', 'botzio-ai' ); ?></small>
                    <strong><?php echo esc_html( $total_chats ); ?></strong>
                    <em><?php echo esc_html__( 'Messages captured', 'botzio-ai' ); ?></em>
                </div>
            </div>

            <div class="botzio-ai-compact-stat">
                <span class="dashicons dashicons-admin-network"></span>
                <div>
                    <small><?php echo esc_html__( 'AI Connection', 'botzio-ai' ); ?></small>
                    <strong><?php echo esc_html( ucfirst( $provider ) ); ?></strong>
                    <em><?php echo esc_html( $is_verified ? __( 'Ready to answer', 'botzio-ai' ) : __( 'Needs testing', 'botzio-ai' ) ); ?></em>
                </div>
            </div>

            <div class="botzio-ai-compact-stat">
                <span class="dashicons dashicons-database"></span>
                <div>
                    <small><?php echo esc_html__( 'Knowledge Base', 'botzio-ai' ); ?></small>
                    <strong><?php echo esc_html( $has_knowledge ? __( 'Prepared', 'botzio-ai' ) : __( 'Needs content', 'botzio-ai' ) ); ?></strong>
                    <em><?php echo esc_html( $has_knowledge ? __( 'Business info added', 'botzio-ai' ) : __( 'Add business basics', 'botzio-ai' ) ); ?></em>
                </div>
            </div>

            <div class="botzio-ai-compact-stat">
                <span class="dashicons dashicons-yes-alt"></span>
                <div>
                    <small><?php echo esc_html__( 'Launch Progress', 'botzio-ai' ); ?></small>
                    <strong>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: completed setup tasks, 2: total setup tasks */
                                __( '%1$d/%2$d complete', 'botzio-ai' ),
                                $setup_completed_count,
                                $setup_total_steps
                            )
                        );
                        ?>
                    </strong>
                    <em><?php echo esc_html( $setup_complete ? __( 'Ready for visitors', 'botzio-ai' ) : sprintf( _n( '%d task remaining', '%d tasks remaining', $setup_remaining_count, 'botzio-ai' ), $setup_remaining_count ) ); ?></em>
                </div>
            </div>

        </div>

        <form method="post" action="options.php" class="botzio-ai-settings-form">
            <?php settings_fields( 'botzio_ai_settings_group' ); ?>

            <h2 class="nav-tab-wrapper botzio-ai-tabs">
                <a href="#botzio-ai-tab-connection" class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php echo esc_html__( 'AI Setup', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-content" class="nav-tab">
                    <span class="dashicons dashicons-database"></span>
                    <?php echo esc_html__( 'Knowledge', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-appearance" class="nav-tab">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <?php echo esc_html__( 'Design', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-limits" class="nav-tab">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php echo esc_html__( 'Controls', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-analytics" class="nav-tab">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php echo esc_html__( 'Insights', 'botzio-ai' ); ?>
                </a>
                <a href="#botzio-ai-tab-advanced" class="nav-tab">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php echo esc_html__( 'Data', 'botzio-ai' ); ?>
                </a>
            </h2>

            <div id="botzio-ai-tab-connection" class="botzio-ai-tab-content active">
                <?php do_settings_sections( 'botzio-ai-connection' ); ?>
            </div>

            <div id="botzio-ai-tab-content" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-content' ); ?>
            </div>

            <div id="botzio-ai-tab-appearance" class="botzio-ai-tab-content">
                <div class="botzio-ai-appearance-layout">
                    <div class="botzio-ai-appearance-settings">
                        <?php do_settings_sections( 'botzio-ai-appearance' ); ?>
                    </div>

                    <aside class="botzio-ai-admin-preview">
                        <div class="botzio-ai-preview-copy">
                            <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Visitor Preview', 'botzio-ai' ); ?></p>
                            <h2><?php echo esc_html__( 'Preview the chat experience.', 'botzio-ai' ); ?></h2>
                            <p>
                                <?php echo esc_html__( 'See how the chatbot will look before it appears on your site. Changes update here as you edit the design.', 'botzio-ai' ); ?>
                            </p>

                            <div class="botzio-ai-preview-actions">
                                <a class="button button-primary botzio-ai-jump-tab" href="#botzio-ai-tab-connection">
                                    <?php echo esc_html__( 'Connect AI to Launch', 'botzio-ai' ); ?>
                                </a>
                            </div>
                        </div>

                        <div
                            class="botzio-ai-preview-phone"
                            style="<?php echo esc_attr( '--botzio-preview-primary:' . $primary_color . ';' ); ?>"
                        >
                            <div class="botzio-ai-preview-chat">
                                <div class="botzio-ai-preview-header">
                                    <span class="botzio-ai-preview-avatar" aria-hidden="true">
                                        <img
                                            src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/botzio-ai-chat-icon.png' ); ?>"
                                            alt=""
                                        />
                                    </span>
                                    <strong class="botzio-ai-preview-title"><?php echo esc_html( $chat_title ); ?></strong>
                                    <em><?php echo esc_html( $chatbot_ready ? __( 'Ready', 'botzio-ai' ) : __( 'Preview', 'botzio-ai' ) ); ?></em>
                                </div>

                                <div class="botzio-ai-preview-window" aria-live="polite">
                                    <div class="botzio-ai-preview-row is-bot">
                                        <div class="botzio-ai-preview-bubble">
                                            <span><?php echo esc_html__( 'Botzio:', 'botzio-ai' ); ?></span>
                                            <span class="botzio-ai-preview-welcome-message"><?php echo esc_html( get_option( 'botzio_ai_welcome_message', $default_welcome ) ); ?></span>
                                        </div>
                                    </div>

                                    <div class="botzio-ai-preview-answer">
                                        <div class="botzio-ai-preview-row is-user">
                                            <div class="botzio-ai-preview-bubble"><?php echo esc_html( $preview_questions[0] ); ?></div>
                                        </div>

                                        <div class="botzio-ai-preview-row is-bot">
                                            <div class="botzio-ai-preview-bubble">
                                                <span><?php echo esc_html__( 'Botzio:', 'botzio-ai' ); ?></span>
                                                <?php echo esc_html__( 'Sample preview: once AI is connected, Botzio will answer using your saved business knowledge and site instructions.', 'botzio-ai' ); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="botzio-ai-preview-suggestions">
                                    <?php foreach ( $preview_questions as $index => $question ) : ?>
                                        <button
                                            type="button"
                                            class="botzio-ai-preview-question <?php echo 0 === $index ? 'is-active' : ''; ?>"
                                            data-question="<?php echo esc_attr( $question ); ?>"
                                        >
                                            <?php echo esc_html( $question ); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <div class="botzio-ai-preview-input">
                                    <span><?php echo esc_html__( 'Ask a question...', 'botzio-ai' ); ?></span>
                                    <button type="button" aria-label="<?php echo esc_attr__( 'Preview send button', 'botzio-ai' ); ?>">
                                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <div id="botzio-ai-tab-limits" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-limits' ); ?>
            </div>

            <div id="botzio-ai-tab-analytics" class="botzio-ai-tab-content">
                <div class="botzio-ai-analytics-layout">
                    <div class="botzio-ai-analytics-summary">
                        <div class="botzio-ai-analytics-metric">
                            <span class="dashicons dashicons-format-chat"></span>
                            <strong><?php echo esc_html( $total_chats ); ?></strong>
                            <small><?php echo esc_html__( 'Visitor messages captured', 'botzio-ai' ); ?></small>
                        </div>

                        <div class="botzio-ai-analytics-metric">
                            <span class="dashicons dashicons-editor-help"></span>
                            <strong><?php echo esc_html( $total_questions ); ?></strong>
                            <small><?php echo esc_html__( 'Total visitor questions', 'botzio-ai' ); ?></small>
                        </div>

                        <div class="botzio-ai-analytics-metric">
                            <span class="dashicons dashicons-warning"></span>
                            <strong><?php echo esc_html( $needs_review_count ); ?></strong>
                            <small><?php echo esc_html__( 'Questions that may need review', 'botzio-ai' ); ?></small>
                        </div>

                        <div class="botzio-ai-analytics-metric">
                            <span class="dashicons dashicons-admin-network"></span>
                            <strong><?php echo esc_html( $is_verified ? __( 'AI Ready', 'botzio-ai' ) : __( 'Needs Test', 'botzio-ai' ) ); ?></strong>
                            <small><?php echo esc_html__( 'AI connection status', 'botzio-ai' ); ?></small>
                        </div>
                    </div>

                    <div class="botzio-ai-insight-grid">
                        <div class="botzio-ai-insight-panel">
                            <div class="botzio-ai-section-heading">
                                <div>
                                    <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Most Asked Topics', 'botzio-ai' ); ?></p>
                                    <h2><?php echo esc_html__( 'What visitors ask about', 'botzio-ai' ); ?></h2>
                                </div>
                            </div>

                            <?php if ( ! empty( $question_topics ) ) : ?>
                                <ul class="botzio-ai-topic-list">
                                    <?php foreach ( $question_topics as $topic => $count ) : ?>
                                        <li>
                                            <div>
                                                <strong><?php echo esc_html( $topic ); ?></strong>
                                                <span>
                                                    <?php
                                                    echo esc_html(
                                                        sprintf(
                                                            /* translators: %d: number of visitor questions */
                                                            _n( '%d question', '%d questions', $count, 'botzio-ai' ),
                                                            $count
                                                        )
                                                    );
                                                    ?>
                                                </span>
                                            </div>
                                            <em>
                                                <i style="width: <?php echo esc_attr( $top_topic_count > 0 ? round( ( $count / $top_topic_count ) * 100 ) : 0 ); ?>%;"></i>
                                            </em>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <div class="botzio-ai-mini-empty">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <strong><?php echo esc_html__( 'No topic trends yet', 'botzio-ai' ); ?></strong>
                                    <p><?php echo esc_html__( 'Topics will appear after visitors start asking questions.', 'botzio-ai' ); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="botzio-ai-insight-panel">
                            <div class="botzio-ai-section-heading">
                                <div>
                                    <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Needs Review', 'botzio-ai' ); ?></p>
                                    <h2><?php echo esc_html__( 'Questions to improve', 'botzio-ai' ); ?></h2>
                                </div>
                            </div>

                            <?php if ( ! empty( $needs_review_questions ) ) : ?>
                                <ul class="botzio-ai-review-list">
                                    <?php foreach ( array_slice( $needs_review_questions, 0, 5 ) as $item ) : ?>
                                        <li>
                                            <span><?php echo esc_html( botzio_ai_extract_question_text( $item ) ); ?></span>
                                            <small>
                                                <?php echo esc_html( is_array( $item ) && ! empty( $item['needs_review'] ) ? __( 'Check knowledge base coverage', 'botzio-ai' ) : __( 'Repeated or vague question', 'botzio-ai' ) ); ?>
                                            </small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <div class="botzio-ai-mini-empty">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <strong><?php echo esc_html__( 'Nothing urgent to review', 'botzio-ai' ); ?></strong>
                                    <p><?php echo esc_html__( 'Questions that repeat or miss your knowledge base will appear here.', 'botzio-ai' ); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="botzio-ai-recent-questions-panel">
                        <div class="botzio-ai-section-heading">
                            <div>
                                <p class="botzio-ai-eyebrow"><?php echo esc_html__( 'Visitor Insights', 'botzio-ai' ); ?></p>
                                <h2><?php echo esc_html__( 'Recent Visitor Questions', 'botzio-ai' ); ?></h2>
                            </div>
                            <?php if ( ! empty( $recent_questions ) ) : ?>
                                <div class="botzio-ai-question-filter-wrap">
                                    <label class="botzio-ai-question-filter">
                                        <span><?php echo esc_html__( 'Show', 'botzio-ai' ); ?></span>
                                        <select id="botzio-ai-question-limit">
                                            <option value="5"><?php echo esc_html__( 'Last 5', 'botzio-ai' ); ?></option>
                                            <option value="10" selected><?php echo esc_html__( 'Last 10', 'botzio-ai' ); ?></option>
                                            <option value="20"><?php echo esc_html__( 'Last 20', 'botzio-ai' ); ?></option>
                                        </select>
                                    </label>

                                    <span id="botzio-ai-question-filter-count"></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ( ! empty( $recent_questions ) ) : ?>
                            <ul class="botzio-ai-recent-question-list">
                                <?php foreach ( array_slice( $recent_questions, 0, 20 ) as $index => $item ) : ?>
                                    <li data-question-index="<?php echo esc_attr( $index ); ?>">
                                        <span><?php echo esc_html( isset( $item['question'] ) ? $item['question'] : '' ); ?></span>
                                        <time>
                                            <?php
                                            echo esc_html(
                                                wp_date(
                                                    'M j, Y g:i A',
                                                    isset( $item['time'] ) ? absint( $item['time'] ) : current_time( 'timestamp' )
                                                )
                                            );
                                            ?>
                                        </time>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <div class="botzio-ai-empty-state">
                                <span class="dashicons dashicons-format-chat"></span>
                                <strong><?php echo esc_html__( 'No visitor questions yet', 'botzio-ai' ); ?></strong>
                                <p><?php echo esc_html__( 'Once visitors start chatting, their questions will appear here so you can spot content gaps and improve future answers.', 'botzio-ai' ); ?></p>
                                <div class="botzio-ai-empty-state-actions">
                                    <?php if ( ! $chatbot_ready ) : ?>
                                        <a class="button button-primary botzio-ai-jump-tab" href="#botzio-ai-tab-connection">
                                            <?php echo esc_html__( 'Connect AI', 'botzio-ai' ); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a class="button button-secondary botzio-ai-jump-tab" href="#botzio-ai-tab-appearance">
                                        <?php echo esc_html__( 'Preview Chat', 'botzio-ai' ); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="botzio-ai-tab-advanced" class="botzio-ai-tab-content">
                <?php do_settings_sections( 'botzio-ai-advanced' ); ?>
            </div>

            <div class="botzio-ai-settings-actions">
                <?php submit_button( __( 'Save Changes', 'botzio-ai' ), 'primary', 'submit', false ); ?>

                <button
                    type="button" class="button button-secondary" id="botzio-ai-reset-settings" style="margin-left:10px;"
                >
                    <?php echo esc_html__( 'Restore Defaults', 'botzio-ai' ); ?>
                </button>

                <span id="botzio-ai-reset-result" style="margin-left:10px;"></span>
            </div>
        </form>

        <details id="botzio-ai-pro-comparison" class="botzio-ai-plan-clarity">
            <summary>
                <span>
                    <strong><?php echo esc_html__( 'Compare Free and Pro', 'botzio-ai' ); ?></strong>
                    <small><?php echo esc_html__( 'Free builds the chatbot foundation. Pro adds store automation, order help, lead capture, recommendations, and handoff.', 'botzio-ai' ); ?></small>
                </span>
                <span class="botzio-ai-plan-summary-action"><?php echo esc_html__( 'View details', 'botzio-ai' ); ?></span>
            </summary>

            <div class="botzio-ai-plan-clarity-grid" aria-label="<?php echo esc_attr__( 'Free and Pro feature comparison', 'botzio-ai' ); ?>">
                <div class="botzio-ai-plan-card is-free">
                    <div class="botzio-ai-plan-card-heading">
                        <span class="botzio-ai-plan-badge"><?php echo esc_html__( 'Free', 'botzio-ai' ); ?></span>
                        <h2><?php echo esc_html__( 'Your chatbot foundation', 'botzio-ai' ); ?></h2>
                        <p><?php echo esc_html__( 'Use Botzio AI Free to prepare business knowledge, connect an AI model, style the chatbot, manage usage, and review visitor questions.', 'botzio-ai' ); ?></p>
                    </div>

                    <ul class="botzio-ai-feature-list">
                        <?php foreach ( $free_features as $feature ) : ?>
                            <li>
                                <span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span>
                                <?php echo esc_html( $feature['label'] ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="botzio-ai-plan-card is-pro">
                    <div class="botzio-ai-plan-card-heading">
                        <span class="botzio-ai-plan-badge is-pro"><?php echo esc_html( $pro_is_active ? __( 'Pro active', 'botzio-ai' ) : __( 'Pro upgrade', 'botzio-ai' ) ); ?></span>
                        <h2><?php echo esc_html__( 'Sales and support automation', 'botzio-ai' ); ?></h2>
                        <p><?php echo esc_html__( 'Upgrade when you want Botzio to answer WooCommerce product questions, recommend alternatives, check orders, capture leads, and route visitors to a human.', 'botzio-ai' ); ?></p>
                    </div>

                    <ul class="botzio-ai-feature-list">
                        <?php foreach ( $pro_features as $feature ) : ?>
                            <li>
                                <span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span>
                                <?php echo esc_html( $feature['label'] ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ( $pro_is_active ) : ?>
                        <span class="botzio-ai-pro-installed">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html__( 'Botzio AI Pro is installed on this site.', 'botzio-ai' ); ?>
                        </span>
                    <?php else : ?>
                        <a class="button button-primary" href="<?php echo esc_url( $pro_upgrade_url ); ?>">
                            <?php echo esc_html__( 'Upgrade to Pro', 'botzio-ai' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    </div>
    <?php
}

/**
 * Fields
 */
function botzio_ai_display_chatbot_field() {
    $value = get_option( 'botzio_ai_display_chatbot', 'yes' );
    $ready = botzio_ai_is_frontend_chatbot_ready();
    ?>
    <label>
        <input type="checkbox" name="botzio_ai_display_chatbot" value="yes" <?php checked( $value, 'yes' ); ?> />
        <?php echo esc_html__( 'Show the chatbot to visitors once it is ready to answer.', 'botzio-ai' ); ?>
    </label>
    <p class="description botzio-ai-settings-hint">
        <?php
        echo esc_html(
            $ready
                ? __( 'Your chatbot has a ready connection or an enabled Pro workflow, so it can appear on the frontend.', 'botzio-ai' )
                : __( 'The chatbot stays hidden until AI is tested successfully or a Pro workflow is available to handle visitor requests.', 'botzio-ai' )
        );
        ?>
    </p>
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

    <p class="description" id="botzio-ai-provider-help-text">Need an API key? Create one from <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">OpenRouter API Keys</a>. Recommended starter model: <code>openrouter/free</code>.</p>
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
        <?php echo esc_html__( 'Paste the API key from your selected AI provider. Botzio stores it on your site and uses it to answer visitor questions.', 'botzio-ai' ); ?>
    </p>
    <?php
}

function botzio_ai_model_field() {
    $value = get_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );
    $provider = get_option( 'botzio_ai_provider', 'openrouter' );
    $cached_models = botzio_ai_get_cached_models();
    $has_cached_models = ! empty( $cached_models[ $provider ] );
    $is_verified = botzio_ai_is_ai_connection_ready();
    ?>
    <div class="botzio-ai-model-picker-row">
        <input
            type="text"
            name="botzio_ai_model"
            id="botzio_ai_model"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text <?php echo $is_verified ? 'botzio-ai-readonly-model' : ''; ?>"
            placeholder="<?php echo esc_attr__( 'Choose or enter a model', 'botzio-ai' ); ?>"
            <?php echo $is_verified ? 'readonly="readonly"' : ''; ?>
        />

        <button type="button" class="button button-secondary" id="botzio-ai-fetch-models">
            <?php echo esc_html__( 'Find Models', 'botzio-ai' ); ?>
        </button>

        <button type="button" class="button button-secondary" id="botzio-ai-change-model" style="display:none;">
            <?php echo esc_html__( 'Change Model', 'botzio-ai' ); ?>
        </button>
    </div>

    <div class="botzio-ai-model-select-row">
        <select id="botzio-ai-model-dropdown" style="display:none;" data-has-cached-models="<?php echo $has_cached_models ? 'yes' : 'no'; ?>">
            <option value=""><?php echo esc_html__( 'Select a model for Botzio', 'botzio-ai' ); ?></option>
        </select>
    </div>

    <div class="botzio-ai-connection-action-row">
        <button type="button" class="button button-primary" id="botzio-ai-test-connection" disabled>
            <?php echo esc_html__( 'Test AI Connection', 'botzio-ai' ); ?>
        </button>

        <?php if ( ! empty( get_option( 'botzio_ai_api_key', '' ) ) ) : ?>
            <button type="button" class="button button-secondary" id="botzio-ai-disconnect-api">
            <?php echo esc_html__( 'Disconnect AI', 'botzio-ai' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <div class="botzio-ai-connection-message-row">
        <span id="botzio-ai-model-result"></span>
        <span id="botzio-ai-test-result"></span>
    </div>

    <p class="description">
        <?php
        echo esc_html(
            $is_verified
                ? __( 'Your tested model is locked to prevent accidental changes. Use Change Model when you want to switch, then test again before saving.', 'botzio-ai' )
                : __( 'Paste your API key, find available models, choose one, and test the connection before saving.', 'botzio-ai' )
        );
        ?>
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
        <?php echo esc_html__( 'Add simple instructions for tone, boundaries, response style, or anything the chatbot should avoid.', 'botzio-ai' ); ?>
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
        <?php echo esc_html__( 'Control how many messages each visitor can send per day so usage stays predictable.', 'botzio-ai' ); ?>
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

    if ( ! botzio_ai_is_frontend_chatbot_ready() ) {
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
            'connection_verified' => botzio_ai_is_ai_connection_ready(),
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

    if ( ! botzio_ai_is_frontend_chatbot_ready() ) {
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

function botzio_ai_extract_question_text( $item ) {
    if ( is_array( $item ) ) {
        return isset( $item['question'] ) ? (string) $item['question'] : '';
    }

    return (string) $item;
}

function botzio_ai_get_question_topic_label( $question ) {
    $question = strtolower( wp_strip_all_tags( (string) $question ) );

    $topics = array(
        'Products & Services' => array( 'product', 'products', 'service', 'services', 'offer', 'offers', 'sell', 'available', 'availability', 'stock', 'brand', 'collection', 'item' ),
        'Pricing & Discounts' => array( 'price', 'pricing', 'cost', 'charge', 'charges', 'fee', 'discount', 'sale', 'deal', 'coupon' ),
        'Shipping & Delivery' => array( 'shipping', 'ship', 'delivery', 'deliver', 'courier', 'dispatch', 'arrival' ),
        'Returns & Policies'  => array( 'return', 'refund', 'exchange', 'policy', 'warranty', 'guarantee' ),
        'Orders & Tracking'   => array( 'order', 'tracking', 'track', 'status', 'shipment', 'invoice' ),
        'Payments'            => array( 'payment', 'pay', 'paid', 'card', 'cash', 'cod', 'checkout' ),
        'Support & Contact'   => array( 'support', 'contact', 'whatsapp', 'email', 'phone', 'call', 'help', 'agent' ),
    );

    foreach ( $topics as $label => $keywords ) {
        foreach ( $keywords as $keyword ) {
            if ( false !== strpos( $question, $keyword ) ) {
                return $label;
            }
        }
    }

    return 'General Questions';
}

function botzio_ai_get_question_topics( $recent_questions ) {
    $topics = array();

    foreach ( $recent_questions as $item ) {
        $question = trim( botzio_ai_extract_question_text( $item ) );

        if ( '' === $question ) {
            continue;
        }

        $topic = botzio_ai_get_question_topic_label( $question );

        if ( ! isset( $topics[ $topic ] ) ) {
            $topics[ $topic ] = 0;
        }

        $topics[ $topic ]++;
    }

    arsort( $topics );

    return array_slice( $topics, 0, 5, true );
}

function botzio_ai_get_review_questions( $recent_questions ) {
    $review_questions = array();
    $question_counts  = array();

    foreach ( $recent_questions as $item ) {
        $question = trim( botzio_ai_extract_question_text( $item ) );

        if ( '' === $question ) {
            continue;
        }

        $normalized = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', $question ) );
        $normalized = trim( preg_replace( '/\s+/', ' ', $normalized ) );

        if ( '' === $normalized ) {
            continue;
        }

        if ( ! isset( $question_counts[ $normalized ] ) ) {
            $question_counts[ $normalized ] = 0;
        }

        $question_counts[ $normalized ]++;
    }

    foreach ( $recent_questions as $item ) {
        $question = trim( botzio_ai_extract_question_text( $item ) );

        if ( '' === $question ) {
            continue;
        }

        $normalized    = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', $question ) );
        $normalized    = trim( preg_replace( '/\s+/', ' ', $normalized ) );
        $word_count    = str_word_count( wp_strip_all_tags( $question ) );
        $needs_review  = is_array( $item ) && ! empty( $item['needs_review'] );
        $is_repeated   = isset( $question_counts[ $normalized ] ) && $question_counts[ $normalized ] > 1;
        $is_too_vague  = $word_count > 0 && $word_count <= 3;

        if ( $needs_review || $is_repeated || $is_too_vague ) {
            $review_questions[] = $item;
        }
    }

    return $review_questions;
}

function botzio_ai_store_analytics( $message, $answer = '', $error_message = '' ) {

    $total_chats = absint( get_option( 'botzio_ai_total_chats', 0 ) );
    update_option( 'botzio_ai_total_chats', $total_chats + 1 );

    $total_questions = absint( get_option( 'botzio_ai_total_questions', 0 ) );
    update_option( 'botzio_ai_total_questions', $total_questions + 1 );

    $recent_questions = get_option( 'botzio_ai_recent_questions', array() );

    if ( ! is_array( $recent_questions ) ) {
        $recent_questions = array();
    }

    $clean_message = sanitize_text_field( $message );
    $clean_answer  = trim( wp_strip_all_tags( (string) $answer ) );
    $error_message = trim( wp_strip_all_tags( (string) $error_message ) );
    $answered      = '' !== $clean_answer;

    if ( $answered && '' !== $error_message ) {
        $answered = strtolower( $clean_answer ) !== strtolower( $error_message );
    }

    array_unshift(
        $recent_questions,
        array(
            'question'     => $clean_message,
            'time'         => current_time( 'timestamp' ),
            'topic'        => botzio_ai_get_question_topic_label( $clean_message ),
            'answered'     => $answered,
            'needs_review' => ! $answered,
        )
    );

    $recent_questions = array_slice( $recent_questions, 0, 50 );

    update_option( 'botzio_ai_recent_questions', $recent_questions );
}

/**
 * AJAX processor
 */
function botzio_ai_process() {
     check_ajax_referer( 'botzio_ai_nonce', 'nonce' );

    // Check if user input is provided
    if ( empty( $_POST['user_input'] ) ) {
        wp_send_json_error(
            array(
                'message' => 'No input provided.',
            )
        );
    }

    if ( ! botzio_ai_is_ai_connection_ready() ) {
        wp_send_json_error(
            array(
                'message' => 'The chatbot is not ready yet. Please connect and test an AI provider in plugin settings.',
            )
        );
    }

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

    // Sanitize and retrieve options
    $user_input    = sanitize_text_field( wp_unslash( $_POST['user_input'] ) );
    $provider      = get_option( 'botzio_ai_provider', 'openrouter' );
    $api_key       = get_option( 'botzio_ai_api_key', '' );
    $model         = get_option( 'botzio_ai_model', 'openai/gpt-oss-20b:free' );
    $custom_prompt = get_option( 'botzio_ai_custom_prompt', 'Answer only related to the current website, nothing else.' );
    $error_message = get_option( 'botzio_ai_error_message', 'I can only answer questions related to this website.' );

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
    botzio_ai_store_analytics( $user_input, $result, $error_message );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_botzio_ai_process', 'botzio_ai_process' );
add_action( 'wp_ajax_nopriv_botzio_ai_process', 'botzio_ai_process' );

function botzio_ai_get_request_timeout() {
    $timeout = absint( apply_filters( 'botzio_ai_request_timeout', 60 ) );

    if ( $timeout < 30 ) {
        return 30;
    }

    if ( $timeout > 120 ) {
        return 120;
    }

    return $timeout;
}

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
            'timeout' => botzio_ai_get_request_timeout(),
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
            'timeout' => botzio_ai_get_request_timeout(),
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
            'timeout' => botzio_ai_get_request_timeout(),
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
    $max_tokens  = botzio_ai_get_max_tokens();
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
            'timeout' => botzio_ai_get_request_timeout(),
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
        $content       = trim( $body['choices'][0]['message']['content'] );
        $finish_reason = isset( $body['choices'][0]['finish_reason'] ) ? $body['choices'][0]['finish_reason'] : '';

        if ( botzio_ai_should_retry_incomplete_response( $content, $finish_reason ) ) {
            $retry_payload               = $payload;
            $retry_payload['max_tokens'] = botzio_ai_get_retry_max_tokens( $max_tokens );
            $retry_payload['messages'][] = array(
                'role'    => 'assistant',
                'content' => $content,
            );
            $retry_payload['messages'][] = array(
                'role'    => 'user',
                'content' => 'Your previous answer was cut off. Rewrite the full answer from the beginning in 1 to 3 complete sentences.',
            );

            $retry_response = wp_remote_post(
                'https://openrouter.ai/api/v1/chat/completions',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $retry_payload ),
                    'timeout' => botzio_ai_get_request_timeout(),
                )
            );

            if ( ! is_wp_error( $retry_response ) ) {
                $retry_body        = json_decode( wp_remote_retrieve_body( $retry_response ), true );
                $retry_status_code = wp_remote_retrieve_response_code( $retry_response );

                if ( $retry_status_code >= 200 && $retry_status_code < 300 && ! empty( $retry_body['choices'][0]['message']['content'] ) ) {
                    $retry_content = trim( $retry_body['choices'][0]['message']['content'] );

                    if ( strlen( $retry_content ) > strlen( $content ) ) {
                        return $retry_content;
                    }
                }
            }
        }

        return $content;
    }

    return new WP_Error( 'botzio_ai_openrouter_error', 'No response from OpenRouter.' );
}

/**
 * OpenAI
 */
function botzio_ai_call_openai( $api_key, $model, $prompt, $user_input ) {
    $temperature = floatval( get_option( 'botzio_ai_temperature', 0.3 ) );
    $max_tokens  = botzio_ai_get_max_tokens();
    $payload = array(
        'model'       => $model,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
        'messages'    => array(
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
            'timeout' => botzio_ai_get_request_timeout(),
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
        $content       = trim( $body['choices'][0]['message']['content'] );
        $finish_reason = isset( $body['choices'][0]['finish_reason'] ) ? $body['choices'][0]['finish_reason'] : '';

        if ( botzio_ai_should_retry_incomplete_response( $content, $finish_reason ) ) {
            $retry_payload                = $payload;
            $retry_payload['max_tokens']  = botzio_ai_get_retry_max_tokens( $max_tokens );
            $retry_payload['messages'][]  = array(
                'role'    => 'assistant',
                'content' => $content,
            );
            $retry_payload['messages'][]  = array(
                'role'    => 'user',
                'content' => 'Your previous answer was cut off. Rewrite the full answer from the beginning in 1 to 3 complete sentences.',
            );

            $retry_response = wp_remote_post(
                'https://api.openai.com/v1/chat/completions',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $retry_payload ),
                    'timeout' => botzio_ai_get_request_timeout(),
                )
            );

            if ( ! is_wp_error( $retry_response ) ) {
                $retry_body        = json_decode( wp_remote_retrieve_body( $retry_response ), true );
                $retry_status_code = wp_remote_retrieve_response_code( $retry_response );

                if ( $retry_status_code >= 200 && $retry_status_code < 300 && ! empty( $retry_body['choices'][0]['message']['content'] ) ) {
                    $retry_content = trim( $retry_body['choices'][0]['message']['content'] );

                    if ( strlen( $retry_content ) > strlen( $content ) ) {
                        return $retry_content;
                    }
                }
            }
        }

        return $content;
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
            'message' => 'Botzio defaults restored.',
        )
    );
}

add_action( 'wp_ajax_botzio_ai_reset_settings', 'botzio_ai_reset_settings' );

/**
 * Gemini
 */
function botzio_ai_call_gemini( $api_key, $model, $prompt, $user_input ) {
    $temperature = floatval( get_option( 'botzio_ai_temperature', 0.3 ) );
    $max_tokens  = botzio_ai_get_max_tokens();
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
        'generationConfig' => array(
            'temperature'     => $temperature,
            'maxOutputTokens' => $max_tokens,
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
            'timeout' => botzio_ai_get_request_timeout(),
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
        $content       = trim( $body['candidates'][0]['content']['parts'][0]['text'] );
        $finish_reason = isset( $body['candidates'][0]['finishReason'] ) ? $body['candidates'][0]['finishReason'] : '';

        if ( botzio_ai_should_retry_incomplete_response( $content, $finish_reason ) ) {
            $retry_payload = array(
                'contents'         => array(
                    array(
                        'parts' => array(
                            array(
                                'text' => $prompt . "\n\nUser question: " . $user_input . "\n\nThe previous answer was cut off: " . $content . "\n\nRewrite the full answer from the beginning in 1 to 3 complete sentences.",
                            ),
                        ),
                    ),
                ),
                'generationConfig' => array(
                    'temperature'     => $temperature,
                    'maxOutputTokens' => botzio_ai_get_retry_max_tokens( $max_tokens ),
                ),
            );

            $retry_response = wp_remote_post(
                $endpoint,
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                    ),
                    'body'    => wp_json_encode( $retry_payload ),
                    'timeout' => botzio_ai_get_request_timeout(),
                )
            );

            if ( ! is_wp_error( $retry_response ) ) {
                $retry_body        = json_decode( wp_remote_retrieve_body( $retry_response ), true );
                $retry_status_code = wp_remote_retrieve_response_code( $retry_response );

                if ( $retry_status_code >= 200 && $retry_status_code < 300 && ! empty( $retry_body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                    $retry_content = trim( $retry_body['candidates'][0]['content']['parts'][0]['text'] );

                    if ( strlen( $retry_content ) > strlen( $content ) ) {
                        return $retry_content;
                    }
                }
            }
        }

        return $content;
    }

    return new WP_Error( 'botzio_ai_gemini_error', 'No response from Gemini.' );
}
