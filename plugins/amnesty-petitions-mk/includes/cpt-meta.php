<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Register CPT
add_action( 'init', 'amnesty_register_petition_cpt' );
function amnesty_register_petition_cpt() {
    $labels = array(
        'name'                  => __( 'Petitions', 'amnesty-petitions' ),
        'singular_name'         => __( 'Petition', 'amnesty-petitions' ),
        'menu_name'             => __( 'Petitions', 'amnesty-petitions' ),
        'add_new'               => __( 'Add New', 'amnesty-petitions' ),
        'add_new_item'          => __( 'Add New Petition', 'amnesty-petitions' ),
        'edit_item'             => __( 'Edit Petition', 'amnesty-petitions' ),
        'all_items'             => __( 'All Petitions', 'amnesty-petitions' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-edit-page',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'rewrite'            => array( 'slug' => 'petycja' ),
        'show_in_rest'       => true,
    );
    register_post_type( 'amnesty_petition', $args );
}

// Enqueue media scripts for repeater
add_action( 'admin_enqueue_scripts', 'amnesty_petition_admin_scripts' );
function amnesty_petition_admin_scripts( $hook_suffix ) {
    global $post_type;
    if ( 'amnesty_petition' === $post_type && in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {
        wp_enqueue_media();
    }
}

// Add Meta Boxes
add_action( 'add_meta_boxes', 'amnesty_petition_add_meta_boxes' );
function amnesty_petition_add_meta_boxes() {
    add_meta_box( 'amnesty_petition_settings', 'Ustawienia Akcji', 'amnesty_petition_render_meta_box', 'amnesty_petition', 'normal', 'high' );
}

function amnesty_petition_render_meta_box( $post ) {
    wp_nonce_field( 'amnesty_save_petition_data', 'amnesty_petition_nonce' );

    // Fetch existing meta
    $topic_name          = get_post_meta( $post->ID, '_amnesty_petition_topic_name', true );
    $country             = get_post_meta( $post->ID, '_amnesty_petition_country', true );
    $addressee           = get_post_meta( $post->ID, '_amnesty_petition_addressee', true );
    $target              = get_post_meta( $post->ID, '_amnesty_petition_target', true );
    $appeal              = get_post_meta( $post->ID, '_amnesty_petition_appeal', true );
    $short_description   = get_post_meta( $post->ID, '_amnesty_petition_short_description', true );
    $top_content         = get_post_meta( $post->ID, '_amnesty_petition_top_content', true );
    $video_link          = get_post_meta( $post->ID, '_amnesty_petition_video_link', true );
    $solidarity_letter   = get_post_meta( $post->ID, '_amnesty_petition_solidarity_letter', true );
    $extra_letters       = get_post_meta( $post->ID, '_amnesty_petition_extra_letters', true );
    $materials           = get_post_meta( $post->ID, '_amnesty_petition_materials', true );
    // $show_map            = get_post_meta( $post->ID, '_amnesty_petition_show_map', true );

    if ( empty( $target ) ) $target = 5000;
    if ( empty( $extra_letters ) ) $extra_letters = 0;
    if ( ! is_array( $materials ) ) $materials = array();

    ?>
    <style>
        .amnesty-meta-row { margin-bottom: 20px; }
        .amnesty-meta-row label.main-label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px; }
        .amnesty-meta-row input[type="text"], .amnesty-meta-row input[type="url"], .amnesty-meta-row input[type="number"], .amnesty-meta-row textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        .amnesty-meta-row p.description { margin-top: 4px; color: #666; font-style: italic; }
        .amnesty-material-item { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; position: relative; }
        .amnesty-material-item .remove-material { position: absolute; top: 15px; right: 15px; color: #a00; cursor: pointer; font-weight: bold; text-decoration: none; }
        .amnesty-material-flex { display: flex; gap: 15px; align-items: flex-end; margin-bottom: 10px; }
        .amnesty-material-flex > div { flex: 1; }
    </style>

    <div class="amnesty-meta-row">
        <label class="main-label" style="color: #005fb2;">Salesforce Topic Name</label>
        <input type="text" name="amnesty_petition_topic_name" value="<?php echo esc_attr( $topic_name ); ?>" />
    </div>

    <div class="amnesty-meta-row">
        <label class="main-label">Skrócony opis</label>
        <textarea name="amnesty_petition_short_description" rows="3"><?php echo esc_textarea( $short_description ); ?></textarea>
    </div>

    <div class="amnesty-meta-row">
        <label class="main-label">Treść nad petycją (WYSIWYG)</label>
        <?php 
        // Render standard WordPress WYSIWYG editor
        wp_editor( $top_content, 'amnesty_petition_top_content', array(
            'textarea_rows' => 6,
            'media_buttons' => true
        ) ); 
        ?>
    </div>

    <div class="amnesty-meta-row">
        <label class="main-label">Link do wideo (YouTube)</label>
        <input type="url" name="amnesty_petition_video_link" value="<?php echo esc_url( $video_link ); ?>" />
    </div>

    <hr>
    <div class="amnesty-meta-row">
        <label class="main-label">Materiały dodatkowe (PDF, PPTX itp.)</label>
        <div id="amnesty-materials-wrapper">
            <?php foreach ( $materials as $index => $material ) : ?>
                <div class="amnesty-material-item">
                    <a href="#" class="remove-material">Usuń</a>
                    <div class="amnesty-material-flex">
                        <div><label>Tytuł</label><input type="text" name="amnesty_materials[<?php echo $index; ?>][title]" value="<?php echo esc_attr( $material['title'] ); ?>" /></div>
                        <div><label>URL</label><input type="text" class="amnesty-material-url" name="amnesty_materials[<?php echo $index; ?>][url]" value="<?php echo esc_url( $material['url'] ); ?>" /></div>
                        <div><button type="button" class="button amnesty-upload-btn">Wybierz</button></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="amnesty-add-material" class="button button-primary">+ <?php __( 'Dodaj materiał', 'amnesty-petitions' ); ?></button>
    </div>
    <hr>

    <div class="amnesty-meta-row"><label class="main-label">Adresat listu</label><input type="text" name="amnesty_petition_addressee" value="<?php echo esc_attr( $addressee ); ?>" /></div>
    <div class="amnesty-meta-row"><label class="main-label">Kraj adresata</label><input type="text" name="amnesty_petition_country" value="<?php echo esc_attr( $country ); ?>" /></div>
    <div class="amnesty-meta-row"><label class="main-label" style="color:#d9534f;">Treść listu do władz</label><textarea name="amnesty_petition_appeal" rows="8"><?php echo esc_textarea( $appeal ); ?></textarea></div>
    <div class="amnesty-meta-row"><label class="main-label" style="color:#5cb85c;">Treść listu solidarnościowego</label><textarea name="amnesty_petition_solidarity_letter" rows="8"><?php echo esc_textarea( $solidarity_letter ); ?></textarea></div>
    <div class="amnesty-meta-row"><label class="main-label">Docelowa liczba listów (Target)</label><input type="number" name="amnesty_petition_target" value="<?php echo esc_attr( $target ); ?>" /></div>
    <div class="amnesty-meta-row"><label class="main-label">Dodatkowa liczba listów (Offline)</label><input type="number" name="amnesty_petition_extra_letters" value="<?php echo esc_attr( $extra_letters ); ?>" /></div>
    <hr>
    <!-- <div class="amnesty-meta-row">
        <label>
            <input type="checkbox" name="amnesty_petition_show_map" value="1" <?php checked( $show_map, 1 ); ?> />
            <strong><?php esc_html_e( 'Włącz mapę dla tej petycji', 'amnesty-petitions' ); ?></strong>
        </label>
        <p class="description"><?php esc_html_e( 'Zaznacz, jeśli chcesz, aby na stronie tej petycji generowała się mapa na podstawie miast wpisywanych przez sygnatariuszy.', 'amnesty-petitions' ); ?></p>
    </div> -->

    <script>
    jQuery(document).ready(function($){
        var materialIndex = <?php echo count($materials); ?>;
        $('#amnesty-add-material').on('click', function(e){
            e.preventDefault();
            var template = `<div class="amnesty-material-item"><a href="#" class="remove-material">Usuń</a><div class="amnesty-material-flex"><div><label>Tytuł</label><input type="text" name="amnesty_materials[${materialIndex}][title]" value="" /></div><div><label>URL</label><input type="text" class="amnesty-material-url" name="amnesty_materials[${materialIndex}][url]" value="" /></div><div><button type="button" class="button amnesty-upload-btn">Wybierz</button></div></div></div>`;
            $('#amnesty-materials-wrapper').append(template);
            materialIndex++;
        });
        $('body').on('click', '.remove-material', function(e){ e.preventDefault(); $(this).closest('.amnesty-material-item').remove(); });
        var fileFrame;
        $('body').on('click', '.amnesty-upload-btn', function(e){
            e.preventDefault();
            var urlInput = $(this).closest('.amnesty-material-flex').find('.amnesty-material-url');
            if ( fileFrame ) { fileFrame.open(); return; }
            fileFrame = wp.media({ title: 'Wybierz plik', button: { text: 'Użyj' }, multiple: false });
            fileFrame.on( 'select', function() { var attachment = fileFrame.state().get('selection').first().toJSON(); urlInput.val(attachment.url); });
            fileFrame.open();
        });
    });
    </script>
    <?php
}

// Save Meta Box Data
add_action( 'save_post', 'amnesty_petition_save_meta_box_data' );
function amnesty_petition_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['amnesty_petition_nonce'] ) || ! wp_verify_nonce( $_POST['amnesty_petition_nonce'], 'amnesty_save_petition_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    $fields = array( '_amnesty_petition_target', '_amnesty_petition_extra_letters' );
    foreach($fields as $field) { if(isset($_POST[substr($field, 1)])) update_post_meta( $post_id, $field, intval($_POST[substr($field, 1)]) ); }

    $text_fields = array( '_amnesty_petition_addressee', '_amnesty_petition_topic_name' );
    foreach($text_fields as $field) { if(isset($_POST[substr($field, 1)])) update_post_meta( $post_id, $field, sanitize_text_field($_POST[substr($field, 1)]) ); }

    $textarea_fields = array( '_amnesty_petition_appeal', '_amnesty_petition_short_description', '_amnesty_petition_solidarity_letter' );
    foreach($textarea_fields as $field) { if(isset($_POST[substr($field, 1)])) update_post_meta( $post_id, $field, sanitize_textarea_field($_POST[substr($field, 1)]) ); }

    // Save WYSIWYG editor securely
    if ( isset( $_POST['amnesty_petition_top_content'] ) ) {
        update_post_meta( $post_id, '_amnesty_petition_top_content', wp_kses_post( wp_unslash( $_POST['amnesty_petition_top_content'] ) ) );
    }

    if ( isset( $_POST['amnesty_petition_video_link'] ) ) update_post_meta( $post_id, '_amnesty_petition_video_link', esc_url_raw( $_POST['amnesty_petition_video_link'] ) );

    $materials_data = array();
    if ( isset( $_POST['amnesty_materials'] ) && is_array( $_POST['amnesty_materials'] ) ) {
        foreach ( $_POST['amnesty_materials'] as $mat ) {
            if ( ! empty( $mat['url'] ) ) $materials_data[] = array( 'title' => sanitize_text_field( $mat['title'] ), 'url' => esc_url_raw( $mat['url'] ) );
        }
    }
    if ( isset( $_POST['amnesty_petition_country'] ) ) {
        update_post_meta( $post_id, '_amnesty_petition_country', sanitize_text_field( $_POST['amnesty_petition_country'] ) );
    }
    update_post_meta( $post_id, '_amnesty_petition_materials', $materials_data );

    // $show_map = isset( $_POST['amnesty_petition_show_map'] ) ? 1 : 0;
    // update_post_meta( $post_id, '_amnesty_petition_show_map', $show_map );
}

// Add custom columns to the Amnesty Petitions list
add_filter( 'manage_amnesty_petition_posts_columns', 'amnesty_petition_custom_columns' );

function amnesty_petition_custom_columns( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        $new_columns[$key] = $value;
        if ( $key === 'title' ) {
            $new_columns['petition_shortcode'] = 'Shortcode';
            // $new_columns['map_shortcode']      = 'Shortcode (Mapa)';
        }
    }
    return $new_columns;
}

// Populate the custom columns
add_action( 'manage_amnesty_petition_posts_custom_column', 'amnesty_petition_custom_column_data', 10, 2 );

function amnesty_petition_custom_column_data( $column, $post_id ) {
    if ( $column === 'petition_shortcode' ) {
        echo '<input type="text" readonly="readonly" value="[amnesty_petition id=&quot;' . esc_attr( $post_id ) . '&quot;]" style="width: 100%; max-width: 220px;" onclick="this.select();" />';
    }
    // if ( $column === 'map_shortcode' ) {
    //     $show_map = get_post_meta( $post_id, '_amnesty_petition_show_map', true );
    //     if ( $show_map ) {
    //         echo '<input type="text" readonly="readonly" value="[amnesty_petition_map id=&quot;' . esc_attr( $post_id ) . '&quot;]" style="width: 100%; max-width: 220px;" onclick="this.select();" />';
    //     } else {
    //         echo '<span style="color: #72777c; font-style: italic;">Mapa nie została wygenerowana</span>';
    //     }
    // }
}