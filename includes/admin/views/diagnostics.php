<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cbia_render_view_diagnostics' ) ) {
    function cbia_render_view_diagnostics() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( 'cbia_settings', array() );
        $api_key = (string) ( $settings['openai_api_key'] ?? '' );
        $api_masked = $api_key !== '' ? ( substr( $api_key, 0, 4 ) . '...' . substr( $api_key, -4 ) ) : '';

        $info = array(
            __( 'Plugin version', 'cbiastudio-blogflow-ai' ) => defined( 'CBIA_VERSION' ) ? CBIA_VERSION : 'n/a',
            'WordPress' => get_bloginfo( 'version' ),
            'PHP' => PHP_VERSION,
            __( 'Memory (PHP)', 'cbiastudio-blogflow-ai' ) => (string) ini_get( 'memory_limit' ),
            __( 'Max execution time', 'cbiastudio-blogflow-ai' ) => (string) ini_get( 'max_execution_time' ),
            __( 'Upload max', 'cbiastudio-blogflow-ai' ) => (string) ini_get( 'upload_max_filesize' ),
            __( 'Post max', 'cbiastudio-blogflow-ai' ) => (string) ini_get( 'post_max_size' ),
            'WP_DEBUG' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'true' : 'false',
            'WP_DEBUG_LOG' => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'true' : 'false',
            'DISABLE_WP_CRON' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'true' : 'false',
            __( 'Timezone', 'cbiastudio-blogflow-ai' ) => (string) wp_timezone_string(),
            // translators: %s is a masked API key preview, e.g. "sk-12...9abc".
            __( 'OpenAI API key', 'cbiastudio-blogflow-ai' ) => $api_key !== '' ? sprintf( __( 'Yes (%s)', 'cbiastudio-blogflow-ai' ), $api_masked ) : __( 'No', 'cbiastudio-blogflow-ai' ),
            __( 'Plugin dir writable', 'cbiastudio-blogflow-ai' ) => wp_is_writable( CBIA_PLUGIN_DIR ) ? __( 'Yes', 'cbiastudio-blogflow-ai' ) : __( 'No', 'cbiastudio-blogflow-ai' ),
            __( 'WP content writable', 'cbiastudio-blogflow-ai' ) => ( defined( 'WP_CONTENT_DIR' ) && wp_is_writable( WP_CONTENT_DIR ) ) ? __( 'Yes', 'cbiastudio-blogflow-ai' ) : __( 'No', 'cbiastudio-blogflow-ai' ),
        );

        $log = (string) get_option( CBIA_OPTION_LOG, '' );
        $log_lines = $log ? array_slice( explode( "\n", $log ), -20 ) : array();
        ?>
        <div class="wrap" style="padding-left:0;">
            <h2><?php echo esc_html__( 'Diagnostics', 'cbiastudio-blogflow-ai' ); ?></h2>

            <p class="description">
                <?php echo esc_html__( 'Quick environment and plugin status overview. Useful for support and debugging.', 'cbiastudio-blogflow-ai' ); ?>
            </p>

            <table class="widefat striped" style="max-width:980px;">
                <tbody>
                <?php foreach ( $info as $label => $value ) : ?>
                    <tr>
                        <td style="width:280px;"><strong><?php echo esc_html( $label ); ?></strong></td>
                        <td><code><?php echo esc_html( (string) $value ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="margin-top:24px;"><?php echo esc_html__( 'Latest log lines', 'cbiastudio-blogflow-ai' ); ?></h3>
            <textarea rows="10" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea( implode( "\n", $log_lines ) ); ?></textarea>
        </div>
        <?php
    }
}

cbia_render_view_diagnostics();
