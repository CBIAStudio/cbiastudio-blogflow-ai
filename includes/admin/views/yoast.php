<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Yoast tab view (extracted from legacy cbia-yoast.php)

if ( ! function_exists( 'cbia_render_view_yoast' ) ) {
    function cbia_render_view_yoast() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $batch = 50;
        $offset = 0;
        $force = false;
        $only_cbia = true;

        $service = isset( $cbia_yoast_service ) ? $cbia_yoast_service : null;

        if ( $service && method_exists( $service, 'handle_post' ) ) {
            list( $batch, $offset, $force, $only_cbia ) = $service->handle_post( $batch, $offset, $force, $only_cbia );
        } elseif ( function_exists( 'cbia_yoast_handle_post' ) ) {
            list( $batch, $offset, $force, $only_cbia ) = cbia_yoast_handle_post( $batch, $offset, $force, $only_cbia );
        }

        $log = $service && method_exists( $service, 'get_log' )
            ? $service->get_log()
            : cbia_yoast_log_get();
        ?>
        <div class="wrap">
            <h2><?php echo esc_html__( 'Yoast', 'cbiastudio-blogflow-ai' ); ?></h2>

            <?php
            $yoast_active = defined( 'WPSEO_VERSION' );
            $faq_block_available = false;
            if ( function_exists( 'cbia_yoast_faq_block_available' ) ) {
                $faq_block_available = cbia_yoast_faq_block_available();
            } elseif ( class_exists( 'WP_Block_Type_Registry' ) ) {
                $registry = WP_Block_Type_Registry::get_instance();
                $faq_block_available = is_object( $registry ) && $registry->is_registered( 'yoast/faq-block' );
            }
            ?>

            <p>
                <strong><?php echo esc_html__( 'FAQ Schema (Yoast)', 'cbiastudio-blogflow-ai' ); ?></strong>:
                <?php
                if ( ! $yoast_active ) {
                    echo '<span style="color:#b70000;">' . esc_html__( 'Yoast is not active.', 'cbiastudio-blogflow-ai' ) . '</span> ' . esc_html__( 'Install/activate Yoast SEO to use the FAQ block.', 'cbiastudio-blogflow-ai' );
                } elseif ( $faq_block_available ) {
                    echo '<span style="color:#1e7e34;font-weight:600;">' . esc_html__( 'FAQ block available.', 'cbiastudio-blogflow-ai' ) . '</span> ' . esc_html__( 'The FAQ section will be automatically converted to a Yoast block.', 'cbiastudio-blogflow-ai' );
                } else {
                    echo '<span style="color:#b70000;">' . esc_html__( 'FAQ block NOT available.', 'cbiastudio-blogflow-ai' ) . '</span> ' . esc_html__( 'Make sure you use Gutenberg and Yoast blocks are enabled.', 'cbiastudio-blogflow-ai' );
                }
                ?>
            </p>

            <p>
                <strong><?php echo esc_html__( 'Traffic light', 'cbiastudio-blogflow-ai' ); ?></strong>
                <?php echo esc_html__( 'here means filling:', 'cbiastudio-blogflow-ai' ); ?>
                <code>_yoast_wpseo_linkdex</code> (SEO) and <code>_yoast_wpseo_content_score</code> (Readability),
                <?php echo esc_html__( 'so it is no longer gray in the post list.', 'cbiastudio-blogflow-ai' ); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field( 'cbia_yoast_nonce_action', 'cbia_yoast_nonce' ); ?>

                <h3><?php echo esc_html__( 'Batch actions', 'cbiastudio-blogflow-ai' ); ?></h3>

                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__( 'Batch', 'cbiastudio-blogflow-ai' ); ?></th>
                        <td><input type="number" name="cbia_yoast_batch" min="1" max="500" value="<?php echo esc_attr( $batch ); ?>" style="width:110px;" /></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__( 'Offset', 'cbiastudio-blogflow-ai' ); ?></th>
                        <td><input type="number" name="cbia_yoast_offset" min="0" value="<?php echo esc_attr( $offset ); ?>" style="width:110px;" /></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__( 'Options', 'cbiastudio-blogflow-ai' ); ?></th>
                        <td>
                            <label style="display:inline-block;margin-right:18px;">
                                <input type="checkbox" name="cbia_yoast_force" value="1" <?php checked( $force ); ?> />
                                <?php echo esc_html__( 'Force (rewrite meta/scores even if they already exist)', 'cbiastudio-blogflow-ai' ); ?>
                            </label>
                            <label style="display:inline-block;margin-right:18px;">
                                <input type="checkbox" name="cbia_yoast_include_unmarked" value="1" <?php checked( ! $only_cbia ); ?> />
                                <?php echo esc_html__( 'Include non-CBIA posts (without _cbia_created)', 'cbiastudio-blogflow-ai' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-secondary" name="cbia_yoast_action" value="metas"><?php echo esc_html__( 'Recalculate meta only', 'cbiastudio-blogflow-ai' ); ?></button>
                    <button type="submit" class="button button-secondary" name="cbia_yoast_action" value="semaphore" style="margin-left:8px;"><?php echo esc_html__( 'Update traffic light only', 'cbiastudio-blogflow-ai' ); ?></button>
                    <button type="submit" class="button button-primary" name="cbia_yoast_action" value="both" style="margin-left:8px;"><?php echo esc_html__( 'Meta + traffic light', 'cbiastudio-blogflow-ai' ); ?></button>
                    <button type="submit" class="button" name="cbia_yoast_action" value="clear_log" style="margin-left:8px;"><?php echo esc_html__( 'Clear log', 'cbiastudio-blogflow-ai' ); ?></button>
                </p>

                <hr/>

                <h3><?php echo esc_html__( 'Mark old posts as CBIA', 'cbiastudio-blogflow-ai' ); ?></h3>
                <p><?php echo esc_html__( 'This adds _cbia_created=1 to posts that do not have it, so they are included in default batches.', 'cbiastudio-blogflow-ai' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__( 'From (optional)', 'cbiastudio-blogflow-ai' ); ?></th>
                        <td><input type="datetime-local" name="cbia_yoast_date_from" value="" /></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__( 'To (optional)', 'cbiastudio-blogflow-ai' ); ?></th>
                        <td><input type="datetime-local" name="cbia_yoast_date_to" value="" /></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__( 'Only CBIA signals', 'cbiastudio-blogflow-ai' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cbia_yoast_only_signals" value="1" />
                                <?php echo esc_html__( 'Mark only if signals are detected (FAQ JSON-LD / pending markers / content markers)', 'cbiastudio-blogflow-ai' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary" name="cbia_yoast_action" value="mark_legacy"><?php echo esc_html__( 'Mark old posts as CBIA', 'cbiastudio-blogflow-ai' ); ?></button>
                </p>

                <hr/>

                <h3><?php echo esc_html__( 'Yoast log', 'cbiastudio-blogflow-ai' ); ?></h3>
                <textarea rows="14" cols="120" readonly style="background:#f9f9f9;width:100%;"><?php echo esc_textarea( $log ); ?></textarea>
            </form>
        </div>
        <?php
    }
}

cbia_render_view_yoast();