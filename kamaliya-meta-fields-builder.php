<?php
/**
 * Plugin Name: Kamaliya Meta Fields Builder
 * Plugin URI:  https://github.com/kamleshkamaliya/kamaliya-meta-fields-builder
 * Description: A powerful and lightweight custom fields and meta box builder for WordPress.
 * Version:     1.4.6
 * Author:      Kamlesh Kamaliya
 * Author URI:  https://github.com/kamleshkamaliya
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kamaliya-meta-fields-builder
 */

if ( ! defined( 'ABSPATH' ) ) exit; 

define( 'KMFB_VERSION', '1.4.6' );

class KMFB_Meta_Builder {
    /**
     * Recursively sanitize an array of inputs
     */
    private function kmfb_sanitize_array( $data ) {
        if ( is_array( $data ) ) {
            return array_map( array( $this, 'kmfb_sanitize_array' ), $data );
        }
        return sanitize_textarea_field( wp_unslash( (string) $data ) );
    }

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'kmfb_add_admin_menu' ) );
        add_action( 'admin_menu', array( $this, 'kmfb_add_tools_menu' ) ); 
        add_action( 'admin_init', array( $this, 'kmfb_process_export_import' ) ); 
        add_action( 'init', array( $this, 'kmfb_register_field_group_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'kmfb_add_field_group_metaboxes' ) );
        add_action( 'save_post', array( $this, 'kmfb_save_field_group_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'kmfb_enqueue_admin_scripts' ) );
        
        add_action( 'add_meta_boxes', array( $this, 'kmfb_show_fields_on_edit_screen' ) );
        add_action( 'save_post', array( $this, 'kmfb_save_frontend_post_data' ) );

        add_action( 'enqueue_block_editor_assets', array( $this, 'kmfb_enqueue_block_editor_assets' ) );
        add_action( 'init', array( $this, 'kmfb_register_php_blocks' ) );
        
        add_action( 'admin_init', array( $this, 'kmfb_ensure_modules_folder' ) );
    }

    public function kmfb_enqueue_admin_scripts( $hook ) {
        global $typenow, $pagenow;
        if ( $typenow === 'kmfb_field_group' || in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
            wp_enqueue_media();
            wp_enqueue_editor();
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_style( 'kmfb-admin-style', plugins_url( 'kmfb-admin.css', __FILE__ ), array(), KMFB_VERSION );
            wp_enqueue_script( 'kmfb-builder', plugins_url( 'kmfb-builder.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ), KMFB_VERSION, true );
            wp_enqueue_script( 'kmfb-frontend', plugins_url( 'kmfb-frontend.js', __FILE__ ), array( 'jquery' ), KMFB_VERSION, true );
        }
        
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'kmfb_field_group_page_kmfb-tools' ) {
            wp_add_inline_script( 'jquery', 'jQuery(document).ready(function($){ $("#kmfb-toggle-all").on("change",function(){ $(".kmfb-export-checkbox").prop("checked",$(this).prop("checked")); }); });' );
        }
    }

    public function kmfb_register_field_group_cpt() {
        $args = array(
            'labels'             => array(
                'name'          => 'Field Groups',
                'singular_name' => 'Field Group',
                'add_new_item'  => 'Add New Field Group',
                'edit_item'     => 'Edit Field Group',
                'menu_name'     => 'Field Groups'
            ),
            'public'             => false,  
            'show_ui'            => true,   
            'show_in_menu'       => 'kamaliya-meta-fields-builder', 
            'capability_type'    => 'post',
            'supports'           => array( 'title' ) 
        );
        register_post_type( 'kmfb_field_group', $args );
    }

    public function kmfb_add_field_group_metaboxes() {
        add_meta_box( 'kmfb_fields_builder', 'Fields & Location Rules', array( $this, 'kmfb_fields_builder_html' ), 'kmfb_field_group', 'normal', 'high' );
    }

    public function kmfb_save_field_group_data( $post_id ) {
        if ( ! isset( $_POST['kmfb_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kmfb_nonce'] ), 'kmfb_save_fields_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['kmfb_fields'] ) && is_array( $_POST['kmfb_fields'] ) ) {
            update_post_meta( $post_id, '_kmfb_fields_data', $this->kmfb_sanitize_array( $_POST['kmfb_fields'] ) );
        } else {
            delete_post_meta( $post_id, '_kmfb_fields_data' );
        }

        if ( isset( $_POST['kmfb_location'] ) && is_array( $_POST['kmfb_location'] ) ) {
            update_post_meta( $post_id, '_kmfb_location_data', $this->kmfb_sanitize_array( $_POST['kmfb_location'] ) );
        }
    }

    public function kmfb_fields_builder_html( $post ) {
        wp_nonce_field( 'kmfb_save_fields_nonce', 'kmfb_nonce' );
        
        $saved_fields = get_post_meta( $post->ID, '_kmfb_fields_data', true );
        if ( ! is_array( $saved_fields ) ) $saved_fields = array();
        
        $saved_location = get_post_meta( $post->ID, '_kmfb_location_data', true );
        if ( ! is_array( $saved_location ) ) $saved_location = array();

        $loc_param = isset( $saved_location['param'] ) ? $saved_location['param'] : 'post_type';
        $loc_value = isset( $saved_location['value'] ) ? $saved_location['value'] : 'post';
        
        $max_index = ! empty( $saved_fields ) ? max( array_keys( $saved_fields ) ) + 1 : 0;
        $post_types = get_post_types( array( 'show_ui' => true ), 'objects' ); 
        $pages = get_pages();

        $templates = wp_get_theme()->get_page_templates();
        if ( function_exists( 'get_block_templates' ) ) {
            $block_templates = get_block_templates();
            foreach ( $block_templates as $block_template ) {
                $templates[ $block_template->title ] = $block_template->slug;
            }
        }
        ?>
        <div id="kmfb-fields-wrapper" style="margin-bottom: 40px;">
            <div class="kmfb-section-title"><strong>Fields</strong></div>
            <div id="kmfb-fields-container">
                <?php
                if ( ! empty( $saved_fields ) ) {
                    foreach ( $saved_fields as $index => $field ) {
                        if ( ! is_array( $field ) ) continue; 

                        // LATE ESCAPING: Raw data fetch karna
                        $label = $field['label'] ?? 'New Field';
                        $name  = $field['name'] ?? 'new_field';
                        $type  = $field['type'] ?? 'text';
                        $tab   = $field['tab'] ?? 'content'; 
                        
                        $default_val = $field['default_value'] ?? '';
                        $img_alt = $field['default_image_alt'] ?? '';
                        $img_title = $field['default_image_title'] ?? '';
                        $sub_fields = is_array($field['sub_fields'] ?? null) ? $field['sub_fields'] : array();
                        ?>
                        <div class="kmfb-field-wrap" data-index="<?php echo esc_attr($index); ?>">
                      <div class="kmfb-field-header">
                                <span class="hdr-label"><?php echo esc_html( $label ); ?></span>
                                <div style="flex: 1; display: flex; align-items: center; gap: 6px;">
                                    <span class="hdr-name" style="flex:none;"><?php echo esc_html( $name ); ?></span>
                                    <span class="dashicons dashicons-admin-page kmfb-copy-header-slug" title="<?php esc_attr_e('Copy Slug', 'kamaliya-meta-fields-builder'); ?>" style="flex:none; font-size:14px; cursor:copy; color:#2271b1; font-weight:normal; margin-top:2px;"></span>
                                </div>
                                <span class="hdr-tab-badge"><?php echo esc_html( $tab ); ?></span>
                                <span class="hdr-type"><?php echo esc_html( ucwords( str_replace( '_', ' ', $type ) ) ); ?></span>
                                <span class="hdr-icon"><span class="dashicons dashicons-arrow-down-alt2"></span></span>
                            </div>
                            <div class="kmfb-field-body">
                                
                                <div class="kmfb-field-row">
                                    <label>Placement Tab (Gutenberg Sidebar)</label>
                                    <select name="kmfb_fields[<?php echo esc_attr($index); ?>][tab]" class="kmfb-input-tab">
                                        <option value="content" <?php selected($tab, 'content'); ?>>Content Tab</option>
                                        <option value="style" <?php selected($tab, 'style'); ?>>Style Tab</option>
                                    </select>
                                </div>

                                <div class="kmfb-field-row">
                                    <label>Field Type</label>
                                    <select name="kmfb_fields[<?php echo esc_attr($index); ?>][type]" class="kmfb-input-type">
                                        <option value="text" <?php selected($type, 'text'); ?>>Text</option>
                                        <option value="textarea" <?php selected($type, 'textarea'); ?>>Text Area</option>
                                        <option value="number" <?php selected($type, 'number'); ?>>Number</option>
                                        <option value="image" <?php selected($type, 'image'); ?>>Image</option>
                                        <option value="repeater" <?php selected($type, 'repeater'); ?>>Repeater</option>
                                        <option value="color" <?php selected($type, 'color'); ?>>Color Picker</option>
                                        <option value="boolean" <?php selected($type, 'boolean'); ?>>True/False</option>
                                        <option value="select" <?php selected($type, 'select'); ?>>Select Dropdown</option>
                                        <option value="file" <?php selected($type, 'file'); ?>>File Upload</option>
                                        <option value="link" <?php selected($type, 'link'); ?>>Link</option>
                                        <option value="menu" <?php selected($type, 'menu'); ?>>WP Menu Selector</option>
                                        <option value="group" <?php selected($type, 'group'); ?>>Group</option>
                                    </select>
                                </div>
                                <div class="kmfb-field-row">
                                    <label>Field Label</label>
                                    <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][label]" class="kmfb-input-label" value="<?php echo esc_attr( $label ); ?>" />
                                </div>
                                <div class="kmfb-field-row">
                                    <label>Field Name (Slug) <span class="kmfb-copy-name" title="Copy for Frontend">搭 Copy Slug</span></label>
                                    <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][name]" class="kmfb-input-name" value="<?php echo esc_attr($name); ?>" />
                                </div>
                                
                              <div class="kmfb-field-row kmfb-row-default-val" style="<?php echo ($type === 'repeater' || $type === 'group' || $type === 'link') ? 'display:none;' : ''; ?>">
                                    <label>Default Value</label>
                                    
                                    <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-default-text <?php echo ($type === 'text') ? '' : 'kmfb-hidden'; ?>" value="<?php echo esc_attr($type === 'text' ? $default_val : ''); ?>" placeholder="Enter default text" />
                                    
                                    <textarea name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-default-textarea <?php echo ($type === 'textarea') ? '' : 'kmfb-hidden'; ?>" rows="3" placeholder="Enter default text block" <?php echo ($type !== 'textarea') ? 'disabled' : ''; ?>><?php echo esc_textarea($type === 'textarea' ? $default_val : ''); ?></textarea>
                                    
                                    <input type="number" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-default-number <?php echo ($type === 'number') ? '' : 'kmfb-hidden'; ?>" value="<?php echo esc_attr($type === 'number' ? $default_val : ''); ?>" placeholder="Enter default number" <?php echo ($type !== 'number') ? 'disabled' : ''; ?> />
                                    
                                    <input type="color" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-default-color <?php echo ($type === 'color') ? '' : 'kmfb-hidden'; ?>" value="<?php echo esc_attr($type === 'color' && !empty($default_val) ? $default_val : '#000000'); ?>" <?php echo ($type !== 'color') ? 'disabled' : ''; ?> style="height:35px; width:80px; padding:0; cursor:pointer;" />

                                    <select name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-default-boolean <?php echo ($type === 'boolean') ? '' : 'kmfb-hidden'; ?>" <?php echo ($type !== 'boolean') ? 'disabled' : ''; ?>>
                                        <option value="false" <?php selected($type === 'boolean' && $default_val, 'false'); ?>>False</option>
                                        <option value="true" <?php selected($type === 'boolean' && $default_val, 'true'); ?>>True</option>
                                    </select>

                                    <div class="kmfb-choices-wrap <?php echo ($type === 'select') ? '' : 'kmfb-hidden'; ?>" style="margin-top:10px; background:#f6f7f7; padding:10px; border:1px solid #ddd;">
                                        <label>Choices (one line, Format: <code>value : Label</code>)</label>
                                        <textarea name="kmfb_fields[<?php echo esc_attr($index); ?>][choices]" class="kmfb-input-choices" rows="3" placeholder="red : Red Color&#10;blue : Blue Color" style="width:100%; margin-bottom:10px;" <?php echo ($type !== 'select') ? 'disabled' : ''; ?>><?php echo esc_textarea($field['choices'] ?? ''); ?></textarea>
                                        
                                        <label>Default Value (just value, e.g. <code>red</code>)</label>
                                        <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-default-select" value="<?php echo esc_attr($type === 'select' ? $default_val : ''); ?>" <?php echo ($type !== 'select') ? 'disabled' : ''; ?> style="width:100%;" />
                                    </div>

                                    <div class="kmfb-default-file <?php echo ($type === 'file') ? '' : 'kmfb-hidden'; ?>" style="margin-top:10px;">
                                        <div style="display:flex; gap:10px;">
                                            <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-file-url" value="<?php echo esc_attr($type === 'file' ? $default_val : ''); ?>" <?php echo ($type !== 'file') ? 'disabled' : ''; ?> placeholder="File URL" style="flex:1;" />
                                            <button type="button" class="button kmfb-media-file-btn">Select File</button>
                                        </div>
                                    </div>

                                    <div class="kmfb-default-image <?php echo ($type === 'image') ? '' : 'kmfb-hidden'; ?>">
                                        <input type="hidden" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_value]" class="kmfb-input-default kmfb-image-url" value="<?php echo esc_attr($type === 'image' ? $default_val : ''); ?>" <?php echo ($type !== 'image') ? 'disabled' : ''; ?>/>
                                        <input type="hidden" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_image_alt]" class="kmfb-image-alt" value="<?php echo esc_attr($img_alt); ?>" />
                                        <input type="hidden" name="kmfb_fields[<?php echo esc_attr($index); ?>][default_image_title]" class="kmfb-image-title" value="<?php echo esc_attr($img_title); ?>" />
                                        
                                        <div class="kmfb-image-preview-wrap" style="<?php echo (empty($default_val) || $type !== 'image') ? 'display:none;' : ''; ?>">
                                            <img src="<?php echo esc_url($type === 'image' ? $default_val : ''); ?>" class="kmfb-image-preview" />
                                            <div class="kmfb-image-meta">
                                                <strong>Name:</strong> <span class="meta-title"><?php echo esc_html( $img_title ); ?></span><br>
                                                <strong>Alt:</strong> <span class="meta-alt"><?php echo esc_html( $img_alt ? $img_alt : 'None' ); ?></span>
                                            </div>
                                        </div>

                                        <button type="button" class="button kmfb-media-btn" style="margin-top: 8px;"><?php echo !empty($default_val) && $type === 'image' ? 'Change Image' : 'Select Image'; ?></button>
                                        <button type="button" class="button kmfb-remove-image-btn" style="color: #d63638; margin-top: 8px; <?php echo (empty($default_val) || $type !== 'image') ? 'display:none;' : ''; ?>">Remove</button>
                                    </div>
                                </div>

                              <div class="kmfb-field-row kmfb-row-sub-fields" style="<?php echo ($type === 'repeater' || $type === 'group') ? '' : 'display:none;'; ?>">
                                    <label>Sub Fields</label>
                                    <div class="kmfb-sub-fields-box">
                                        <div class="kmfb-sub-fields-container">
                                            <?php 
                                            if(!empty($sub_fields)) {
                                                foreach($sub_fields as $sub_index => $sub_field) {
                                                    if ( ! is_array( $sub_field ) ) continue;
                                                    
                                                    $sub_label = $sub_field['label'] ?? '';
                                                    $sub_name = $sub_field['name'] ?? '';
                                                    $sub_type = $sub_field['type'] ?? 'text';
                                                    $sub_def = $sub_field['default_value'] ?? '';
                                                    $sub_alt = $sub_field['default_image_alt'] ?? '';
                                                    $sub_title = $sub_field['default_image_title'] ?? '';
                                                    ?>
                                                   <div class="kmfb-sub-field-wrap">
                                                        <div class="kmfb-sub-field-header" style="display:flex; padding:10px 15px; background:#fafafa; border-bottom:1px solid #ddd; cursor:pointer; align-items:center;">
                                                            <strong class="s-title" style="flex:1;"><?php echo esc_html( $sub_label ? $sub_label : 'New Sub Field' ); ?></strong>
                                                            <span class="s-type" style="color:#50575e; margin-right:15px;"><?php echo esc_html( ucwords( str_replace( '_', ' ', $sub_type ) ) ); ?></span>
                                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                                        </div>
                                                        <div class="kmfb-sub-field-body" style="padding:15px; display:none; border-left:3px solid #6c7781;">
                                                            <div style="display:flex; gap:10px;">
                                                                <div style="flex:1;">
                                                                    
                                                                <label style="font-weight:600; display:block; margin-bottom:5px;">Field Label</label>
                                                                <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][label]" class="kmfb-sub-label" value="<?php echo esc_attr($sub_label); ?>">
                                                            </div>
                                                            <div style="flex:1;">
                                                                <label style="font-weight:600; display:block; margin-bottom:5px;">Field Name <span class="kmfb-copy-name" title="Copy for Frontend">搭</span></label>
                                                                <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][name]" class="kmfb-input-name kmfb-sub-name" value="<?php echo esc_attr($sub_name); ?>">
                                                            </div>
                                                            <div style="flex:1;">
                                                                <label style="font-weight:600; display:block; margin-bottom:5px;">Field Type</label>
                                                                <select name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][type]" class="kmfb-input-type">
                                                                    <option value="text" <?php selected($sub_type, 'text'); ?>>Text</option>
                                                                    <option value="textarea" <?php selected($sub_type, 'textarea'); ?>>Text Area</option>
                                                                    <option value="image" <?php selected($sub_type, 'image'); ?>>Image</option>
                                                                    <option value="number" <?php selected($sub_type, 'number'); ?>>Number</option>
                                                                    <option value="color" <?php selected($sub_type, 'color'); ?>>Color Picker</option>
                                                                    <option value="boolean" <?php selected($sub_type, 'boolean'); ?>>True/False</option>
                                                                    <option value="select" <?php selected($sub_type, 'select'); ?>>Select Dropdown</option>
                                                                    <option value="file" <?php selected($sub_type, 'file'); ?>>File Upload</option>
                                                                  <option value="link" <?php selected($sub_type, 'link'); ?>>Link</option>
                                                                    <option value="repeater" <?php selected($sub_type, 'repeater'); ?>>Repeater (Nested)</option>
                                                                </select>
                                                            </div>
                                                            <div style="padding-top:25px;">
                                                                <button type="button" class="button kmfb-remove-sub-field" style="color:#d63638; border-color:#d63638;">X</button>
                                                            </div>
                                                        </div>

                                                        <div class="kmfb-sub-row-nested-fields" style="<?php echo ($sub_type === 'repeater') ? '' : 'display:none;'; ?> margin-top:15px; padding:15px; background:#eef0f2; border:1px solid #ccd0d4;">
                                                            <label style="font-weight:600; display:block; margin-bottom:10px;">Nested Fields (Repeater inside Repeater)</label>
                                                            <div class="kmfb-nested-fields-container">
                                                                <?php 
                                                                $nested_fields = is_array($sub_field['nested_fields'] ?? null) ? $sub_field['nested_fields'] : array();
                                                                if(!empty($nested_fields)) {
                                                                    foreach($nested_fields as $nest_index => $nest_field) {
                                                                        if ( ! is_array( $nest_field ) ) continue;
                                                                        $nest_label = $nest_field['label'] ?? '';
                                                                        $nest_name = $nest_field['name'] ?? '';
                                                                        $nest_type = $nest_field['type'] ?? 'text';
                                                                        ?>
                                                                        <div class="kmfb-nested-field-wrap" style="background:#fff; border:1px solid #ddd; margin-bottom:5px; padding:10px; display:flex; gap:10px;">
                                                                            <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][nested_fields][<?php echo esc_attr($nest_index); ?>][label]" value="<?php echo esc_attr($nest_label); ?>" placeholder="Label" style="flex:1;" />
                                                                            <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][nested_fields][<?php echo esc_attr($nest_index); ?>][name]" value="<?php echo esc_attr($nest_name); ?>" placeholder="Slug" style="flex:1;" />
                                                                            <select name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][nested_fields][<?php echo esc_attr($nest_index); ?>][type]" style="flex:1;">
                                                                                <option value="text" <?php selected($nest_type, 'text'); ?>>Text</option>
                                                                                <option value="textarea" <?php selected($nest_type, 'textarea'); ?>>Text Area</option>
                                                                                <option value="image" <?php selected($nest_type, 'image'); ?>>Image</option>
                                                                                <option value="link" <?php selected($nest_type, 'link'); ?>>Link</option>
                                                                            </select>
                                                                            <button type="button" class="button kmfb-remove-nested-field" style="color:#d63638;">X</button>
                                                                        </div>
                                                                        <?php
                                                                    }
                                                                }
                                                                ?>
                                                            </div>
                                                            <button type="button" class="button kmfb-add-nested-field" data-parent="<?php echo esc_attr($index); ?>" data-sub="<?php echo esc_attr($sub_index); ?>" style="margin-top:10px;">+ Add Nested Field</button>
                                                        </div>
                                                        
                                                        <div class="kmfb-sub-row-default-val" style="<?php echo ($sub_type === 'repeater' || $sub_type === 'link') ? 'display:none;' : ''; ?>">
                                                            <label style="font-weight:600; display:block; margin-bottom:5px;">Default Value</label>
                                                            <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-default-text <?php echo ($sub_type === 'text') ? '' : 'kmfb-hidden'; ?>" value="<?php echo esc_attr($sub_type === 'text' ? $sub_def : ''); ?>" />
                                                            
                                                            <textarea name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-default-textarea <?php echo ($sub_type === 'textarea') ? '' : 'kmfb-hidden'; ?>" rows="2" <?php echo ($sub_type !== 'textarea') ? 'disabled' : ''; ?>><?php echo esc_textarea($sub_type === 'textarea' ? $sub_def : ''); ?></textarea>
                                                            
                                                            <input type="number" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-default-number <?php echo ($sub_type === 'number') ? '' : 'kmfb-hidden'; ?>" value="<?php echo esc_attr($sub_type === 'number' ? $sub_def : ''); ?>" <?php echo ($sub_type !== 'number') ? 'disabled' : ''; ?> />
                                                            
                                                            <input type="color" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-default-color <?php echo ($sub_type === 'color') ? '' : 'kmfb-hidden'; ?>" value="<?php echo esc_attr($sub_type === 'color' && !empty($sub_def) ? $sub_def : '#000000'); ?>" <?php echo ($sub_type !== 'color') ? 'disabled' : ''; ?> style="height:35px; width:80px; padding:0; cursor:pointer;" />

                                                            <select name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-default-boolean <?php echo ($sub_type === 'boolean') ? '' : 'kmfb-hidden'; ?>" <?php echo ($sub_type !== 'boolean') ? 'disabled' : ''; ?>>
                                                                <option value="false" <?php selected($sub_type === 'boolean' && $sub_def, 'false'); ?>>False</option>
                                                                <option value="true" <?php selected($sub_type === 'boolean' && $sub_def, 'true'); ?>>True</option>
                                                            </select>

                                                            <div class="kmfb-choices-wrap <?php echo ($sub_type === 'select') ? '' : 'kmfb-hidden'; ?>" style="margin-top:10px; background:#f6f7f7; padding:10px; border:1px solid #ddd;">
                                                                <label>Choices (value : Label)</label>
                                                                <textarea name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][choices]" class="kmfb-input-choices" rows="2" placeholder="red : Red Color&#10;blue : Blue Color" style="width:100%; margin-bottom:10px;" <?php echo ($sub_type !== 'select') ? 'disabled' : ''; ?>><?php echo esc_textarea($sub_field['choices'] ?? ''); ?></textarea>
                                                                <label>Default Value</label>
                                                                <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-default-select" value="<?php echo esc_attr($sub_type === 'select' ? $sub_def : ''); ?>" <?php echo ($sub_type !== 'select') ? 'disabled' : ''; ?> style="width:100%;" />
                                                            </div>

                                                            <div class="kmfb-default-file <?php echo ($sub_type === 'file') ? '' : 'kmfb-hidden'; ?>" style="margin-top:10px;">
                                                                <div style="display:flex; gap:10px;">
                                                                    <input type="text" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-file-url" value="<?php echo esc_attr($sub_type === 'file' ? $sub_def : ''); ?>" <?php echo ($sub_type !== 'file') ? 'disabled' : ''; ?> placeholder="File URL" style="flex:1;" />
                                                                    <button type="button" class="button kmfb-media-file-btn">Select File</button>
                                                                </div>
                                                            </div>

                                                            <div class="kmfb-default-image <?php echo ($sub_type === 'image') ? '' : 'kmfb-hidden'; ?>">
                                                                <input type="hidden" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_value]" class="kmfb-input-default kmfb-image-url" value="<?php echo esc_attr($sub_type === 'image' ? $sub_def : ''); ?>" <?php echo ($sub_type !== 'image') ? 'disabled' : ''; ?>/>
                                                                <input type="hidden" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_image_alt]" class="kmfb-image-alt" value="<?php echo esc_attr($sub_alt); ?>" />
                                                                <input type="hidden" name="kmfb_fields[<?php echo esc_attr($index); ?>][sub_fields][<?php echo esc_attr($sub_index); ?>][default_image_title]" class="kmfb-image-title" value="<?php echo esc_attr($sub_title); ?>" />
                                                                
                                                                <div class="kmfb-image-preview-wrap" style="<?php echo (empty($sub_def) || $sub_type !== 'image') ? 'display:none;' : ''; ?>">
                                                                    <img src="<?php echo esc_url($sub_type === 'image' ? $sub_def : ''); ?>" class="kmfb-image-preview" style="max-width:80px;" />
                                                                    <div class="kmfb-image-meta">
                                                                        <strong>Name:</strong> <span class="meta-title"><?php echo esc_html( $sub_title ); ?></span><br>
                                                                        <strong>Alt:</strong> <span class="meta-alt"><?php echo esc_html( $sub_alt ? $sub_alt : 'None' ); ?></span>
                                                                    </div>
                                                                </div>

                                                                <button type="button" class="button kmfb-media-btn" style="margin-top: 8px;"><?php echo !empty($sub_def) && $sub_type === 'image' ? 'Change Image' : 'Select Image'; ?></button>
                                                                <button type="button" class="button kmfb-remove-image-btn" style="color: #d63638; margin-top: 8px; <?php echo (empty($sub_def) || $sub_type !== 'image') ? 'display:none;' : ''; ?>">Remove</button>
                                                            </div>
                                                        </div>
                                                        </div> </div> <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                        <button type="button" class="button kmfb-add-sub-field" data-parent="<?php echo esc_attr($index); ?>">+ Add Sub Field</button>
                                    </div>
                                </div>

                                <div style="text-align: right;">
                                    <button type="button" class="button kmfb-close-field">Close Field</button>
                                    <button type="button" class="button kmfb-remove-field" style="color: #d63638; border-color: #d63638; margin-left:10px;">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="button button-primary" id="kmfb-add-field" style="margin-top: 15px;">+ Add Field</button>
        </div>

        <div id="kmfb-location-rules-wrapper">
            <div class="kmfb-section-title"><strong>Location Rules</strong></div>
            <div class="kmfb-location-box">
                <select name="kmfb_location[param]" class="kmfb-loc-param" style="flex: 1;">
                    <option value="post_type" <?php selected($loc_param, 'post_type'); ?>>Post Type</option>
                    <option value="page" <?php selected($loc_param, 'page'); ?>>Page</option>
                    <option value="page_template" <?php selected($loc_param, 'page_template'); ?>>Page Template</option>
                    <option value="block" <?php selected($loc_param, 'block'); ?>>Block (Gutenberg)</option>
                </select>
                <select name="kmfb_location[operator]" style="flex: 1;">
                    <option value="==">is equal to</option>
                </select>
                <div style="flex: 1; display: flex;">
                    <select name="kmfb_location[value]" class="kmfb-loc-value" data-param="post_type" style="width:100%; <?php echo $loc_param !== 'post_type' ? 'display:none;' : ''; ?>" <?php disabled($loc_param !== 'post_type'); ?>>
                        <?php foreach ( $post_types as $pt ): ?>
                            <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($loc_param == 'post_type' && $loc_value == $pt->name); ?>><?php echo esc_html($pt->label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="kmfb_location[value]" class="kmfb-loc-value" data-param="page" style="width:100%; <?php echo $loc_param !== 'page' ? 'display:none;' : ''; ?>" <?php disabled($loc_param !== 'page'); ?>>
                        <?php foreach ( $pages as $p ): ?>
                            <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($loc_param == 'page' && $loc_value == $p->ID); ?>><?php echo esc_html($p->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="kmfb_location[value]" class="kmfb-loc-value" data-param="page_template" style="width:100%; <?php echo $loc_param !== 'page_template' ? 'display:none;' : ''; ?>" <?php disabled($loc_param !== 'page_template'); ?>>
                        <option value="default" <?php selected($loc_param == 'page_template' && $loc_value == 'default'); ?>>Default Template</option>
                        <?php foreach ( $templates as $tpl_name => $tpl_file ): ?>
                            <option value="<?php echo esc_attr($tpl_file); ?>" <?php selected($loc_param == 'page_template' && $loc_value == $tpl_file); ?>><?php echo esc_html($tpl_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="kmfb_location[value]" class="kmfb-loc-value" data-param="block" placeholder="e.g. core/paragraph" style="width:100%; <?php echo $loc_param !== 'block' ? 'display:none;' : ''; ?>" <?php disabled($loc_param !== 'block'); ?> value="<?php echo esc_attr($loc_param == 'block' ? $loc_value : ''); ?>" />
                </div>
            </div>
        </div>

        <script type="text/template" id="kmfb-field-template">
            <div class="kmfb-field-wrap" data-index="__INDEX__">
               <div class="kmfb-field-header">
                    <span class="hdr-label">New Field</span>
                    <div style="flex: 1; display: flex; align-items: center; gap: 6px;">
                        <span class="hdr-name" style="flex:none;">new_field</span>
                        <span class="dashicons dashicons-admin-page kmfb-copy-header-slug" title="Copy Slug" style="flex:none; font-size:14px; cursor:copy; color:#2271b1; font-weight:normal; margin-top:2px;"></span>
                    </div>
                    <span class="hdr-tab-badge">content</span>
                    <span class="hdr-type">Text</span>
                    <span class="hdr-icon"><span class="dashicons dashicons-arrow-down-alt2"></span></span>
                </div>
                <div class="kmfb-field-body" style="display:block;">
                    
                    <div class="kmfb-field-row">
                        <label>Placement Tab (Gutenberg Sidebar)</label>
                        <select name="kmfb_fields[__INDEX__][tab]" class="kmfb-input-tab">
                            <option value="content">Content Tab</option>
                            <option value="style">Style Tab</option>
                        </select>
                    </div>

                    <div class="kmfb-field-row">
                        <label>Field Type</label>
                        <select name="kmfb_fields[__INDEX__][type]" class="kmfb-input-type ddd">
                            <option value="text">Text</option>
                            <option value="textarea">Text Area</option>
                            <option value="number">Number</option>
                            <option value="image">Image</option>
                            <option value="repeater">Repeater</option>
                            <option value="color">Color Picker</option>
                            <option value="boolean">True/False</option>
                            <option value="select">Select Dropdown</option>
                            <option value="file">File Upload</option>
                          <option value="link">Link</option>
                            <option value="menu">WP Menu Selector</option>
                            <option value="group">Group</option> 
                        </select>
                    </div>
                    <div class="kmfb-field-row">
                        <label>Field Label</label>
                        <input type="text" name="kmfb_fields[__INDEX__][label]" class="kmfb-input-label" placeholder="e.g. Hero Title" />
                    </div>
                    <div class="kmfb-field-row">
                        <label>Field Name (Slug) <span class="kmfb-copy-name" title="Copy for Frontend">搭 Copy Slug</span></label>
                        <input type="text" name="kmfb_fields[__INDEX__][name]" class="kmfb-input-name" placeholder="e.g. hero_title" />
                    </div>
                    
                    <div class="kmfb-field-row kmfb-row-default-val">
                        <label>Default Value</label>
                        <input type="text" name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-default-text" placeholder="Enter default text" />
                        <textarea name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-default-textarea kmfb-hidden" rows="3" placeholder="Enter default text block" disabled></textarea>
                        <input type="number" name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-default-number kmfb-hidden" placeholder="Enter default number" disabled />
                        <input type="color" name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-default-color kmfb-hidden" value="#000000" disabled style="height:35px; width:80px; padding:0; cursor:pointer;" />

                        <select name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-default-boolean kmfb-hidden" disabled>
                            <option value="false">False</option>
                            <option value="true">True</option>
                        </select>

                        <div class="kmfb-choices-wrap kmfb-hidden" style="margin-top:10px; background:#f6f7f7; padding:10px; border:1px solid #ddd;">
                            <label>Choices (value : Label)</label>
                            <textarea name="kmfb_fields[__INDEX__][choices]" class="kmfb-input-choices" rows="3" placeholder="red : Red Color&#10;blue : Blue Color" style="width:100%; margin-bottom:10px;" disabled></textarea>
                            <label>Default Value</label>
                            <input type="text" name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-default-select" disabled style="width:100%;" />
                        </div>

                        <div class="kmfb-default-file kmfb-hidden" style="margin-top:10px;">
                            <div style="display:flex; gap:10px;">
                                <input type="text" name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-file-url" disabled placeholder="File URL" style="flex:1;" />
                                <button type="button" class="button kmfb-media-file-btn">Select File</button>
                            </div>
                        </div>

                        <div class="kmfb-default-image kmfb-hidden">
                            <input type="hidden" name="kmfb_fields[__INDEX__][default_value]" class="kmfb-input-default kmfb-image-url" readonly disabled />
                            <input type="hidden" name="kmfb_fields[__INDEX__][default_image_alt]" class="kmfb-image-alt" disabled />
                            <input type="hidden" name="kmfb_fields[__INDEX__][default_image_title]" class="kmfb-image-title" disabled />
                            <div class="kmfb-image-preview-wrap" style="display:none;">
                                <img src="" class="kmfb-image-preview" />
                                <div class="kmfb-image-meta">
                                    <strong>Name:</strong> <span class="meta-title"></span><br>
                                    <strong>Alt:</strong> <span class="meta-alt"></span>
                                </div>
                            </div>
                            <button type="button" class="button kmfb-media-btn" style="margin-top: 8px;">Select Image</button>
                            <button type="button" class="button kmfb-remove-image-btn" style="color: #d63638; margin-top: 8px; display:none;">Remove</button>
                        </div>
                    </div>

                    <div class="kmfb-field-row kmfb-row-sub-fields" style="display:none;">
                        <label>Sub Fields</label>
                        <div class="kmfb-sub-fields-box">
                            <div class="kmfb-sub-fields-container"></div>
                            <button type="button" class="button kmfb-add-sub-field" data-parent="__INDEX__">+ Add Sub Field</button>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="button" class="button kmfb-close-field">Close Field</button>
                        <button type="button" class="button kmfb-remove-field" style="color: #d63638; border-color: #d63638; margin-left:10px;">Delete</button>
                    </div>
                </div>
            </div>
        </script>

      <script type="text/template" id="kmfb-sub-field-template">
            <div class="kmfb-sub-field-wrap">
                <div class="kmfb-sub-field-header" style="display:flex; padding:10px 15px; background:#fafafa; border-bottom:1px solid #ddd; cursor:pointer; align-items:center;">
                    <strong class="s-title" style="flex:1;">New Sub Field</strong>
                    <span class="s-type" style="color:#50575e; margin-right:15px;">Text</span>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="kmfb-sub-field-body" style="padding:15px; display:block; border-left:3px solid #6c7781;">
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label style="font-weight:600; display:block; margin-bottom:5px;">Field Label</label>
                        <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][label]" class="kmfb-sub-label" placeholder="Sub Label">
                    </div>
                    <div style="flex:1;">
                        <label style="font-weight:600; display:block; margin-bottom:5px;">Field Name <span class="kmfb-copy-name" title="Copy for Frontend">搭</span></label>
                        <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][name]" class="kmfb-input-name kmfb-sub-name" placeholder="sub_name">
                    </div>
                    <div style="flex:1;">
                        <label style="font-weight:600; display:block; margin-bottom:5px;">Field Type</label>
                        <select name="kmfb_fields[__PARENT__][sub_fields][__SUB__][type]" class="kmfb-input-type">
                            <option value="text">Text</option>
                            <option value="textarea">Text Area</option>
                            <option value="image">Image</option>
                            <option value="number">Number</option>
                            <option value="color">Color Picker</option>
                            <option value="boolean">True/False</option>
                            <option value="select">Select Dropdown</option>
                            <option value="file">File Upload</option>
                            <option value="link">Link</option>
                            <option value="repeater">Repeater (Nested)</option>
                        </select>
                    </div>
                    <div style="padding-top:25px;">
                        <button type="button" class="button kmfb-remove-sub-field" style="color:#d63638; border-color:#d63638;">X</button>
                    </div>
                </div>

                <div class="kmfb-sub-row-nested-fields" style="display:none; margin-top:15px; padding:15px; background:#eef0f2; border:1px solid #ccd0d4;">
                    <label style="font-weight:600; display:block; margin-bottom:10px;">Nested Fields (Repeater inside Repeater)</label>
                    <div class="kmfb-nested-fields-container"></div>
                    <button type="button" class="button kmfb-add-nested-field" data-parent="__PARENT__" data-sub="__SUB__" style="margin-top:10px;">+ Add Nested Field</button>
                </div>
                
                <div class="kmfb-sub-row-default-val">
                    <label style="font-weight:600; display:block; margin-bottom:5px;">Default Value</label>
                    <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-default-text" placeholder="Enter default text" />
                    <textarea name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-default-textarea kmfb-hidden" rows="2" placeholder="Enter default block" disabled></textarea>
                    <input type="number" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-default-number kmfb-hidden" placeholder="Enter number" disabled />
                    
                    <input type="color" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-default-color kmfb-hidden" value="#000000" disabled style="height:35px; width:80px; padding:0; cursor:pointer;" />

                    <select name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-default-boolean kmfb-hidden" disabled>
                        <option value="false">False</option>
                        <option value="true">True</option>
                    </select>

                    <div class="kmfb-choices-wrap kmfb-hidden" style="margin-top:10px; background:#f6f7f7; padding:10px; border:1px solid #ddd;">
                        <label>Choices (value : Label)</label>
                        <textarea name="kmfb_fields[__PARENT__][sub_fields][__SUB__][choices]" class="kmfb-input-choices" rows="2" placeholder="red : Red Color&#10;blue : Blue Color" style="width:100%; margin-bottom:10px;" disabled></textarea>
                        <label>Default Value</label>
                        <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-default-select" disabled style="width:100%;" />
                    </div>

                    <div class="kmfb-default-file kmfb-hidden" style="margin-top:10px;">
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-file-url" disabled placeholder="File URL" style="flex:1;" />
                            <button type="button" class="button kmfb-media-file-btn">Select File</button>
                        </div>
                    </div>

                    <div class="kmfb-default-image kmfb-hidden">
                        <input type="hidden" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_value]" class="kmfb-input-default kmfb-image-url" disabled />
                        <input type="hidden" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_image_alt]" class="kmfb-image-alt" disabled />
                        <input type="hidden" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][default_image_title]" class="kmfb-image-title" disabled />
                        
                        <div class="kmfb-image-preview-wrap" style="display:none; margin-bottom:5px;">
                            <img src="" class="kmfb-image-preview" style="max-width:80px;" />
                            <div class="kmfb-image-meta">
                                <strong>Name:</strong> <span class="meta-title"></span><br>
                                <strong>Alt:</strong> <span class="meta-alt"></span>
                            </div>
                        </div>
                        <button type="button" class="button kmfb-media-btn">Select Image</button>
        <button type="button" class="button kmfb-remove-image-btn" style="color: #d63638; display:none;">Remove</button>
                    </div>
                </div>
                </div> </div> </script>

        <script type="text/template" id="kmfb-nested-field-template">
            <div class="kmfb-nested-field-wrap" style="background:#fff; border:1px solid #ddd; margin-bottom:5px; padding:10px; display:flex; gap:10px;">
                <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][nested_fields][__NEST__][label]" placeholder="Label" style="flex:1;" />
                <input type="text" name="kmfb_fields[__PARENT__][sub_fields][__SUB__][nested_fields][__NEST__][name]" placeholder="Slug" style="flex:1;" />
                <select name="kmfb_fields[__PARENT__][sub_fields][__SUB__][nested_fields][__NEST__][type]" style="flex:1;">
                    <option value="text">Text</option>
                    <option value="textarea">Text Area</option>
                    <option value="image">Image</option>
                    <option value="link">Link</option>
                </select>
                <button type="button" class="button kmfb-remove-nested-field" style="color:#d63638;">X</button>
            </div>
        </script>

   <?php
        wp_localize_script( 'kmfb-builder', 'kmfbBuilderData', array( 'fieldIndex' => $max_index ) );
    }

    public function kmfb_add_admin_menu() {
        add_menu_page( 'Kamaliya Meta Builder', 'Kamaliya Meta', 'manage_options', 'kamaliya-meta-fields-builder', array( $this, 'kmfb_admin_page_html' ), 'dashicons-layout', 80 );
    }

    public function kmfb_admin_page_html() {
        echo '<div class="wrap"><h1>' . esc_html__('Welcome to Kamaliya Meta Builder', 'kamaliya-meta-fields-builder') . '</h1><p>' . esc_html__('Check the Field Groups menu to create custom fields.', 'kamaliya-meta-fields-builder') . '</p></div>';
    }

    // =========================================================================
    // FRONTEND META BOX & REPEATER ENGINE (POST / PAGE EDIT SCREEN)
    // =========================================================================

    public function kmfb_show_fields_on_edit_screen( $post_type ) {
        global $post;
        if ( ! $post ) return;

        $field_groups = get_posts( array( 'post_type' => 'kmfb_field_group', 'posts_per_page' => -1, 'post_status' => 'publish' ) );

        foreach ( $field_groups as $group ) {
            $location = get_post_meta( $group->ID, '_kmfb_location_data', true );
            if ( ! is_array( $location ) ) continue;

            $param = $location['param'] ?? '';
            $value = $location['value'] ?? '';
            $show_box = false;

            if ( $param === 'post_type' && $post->post_type === $value ) $show_box = true;
            if ( $param === 'page' && $post->ID == $value ) $show_box = true;
            if ( $param === 'page_template' ) {
                $current_template = get_page_template_slug( $post->ID ) ?: 'default';
                if ( $current_template === $value ) $show_box = true;
            }

            if ( $show_box ) {
                add_meta_box(
                    'kmfb_group_' . $group->ID,
                    $group->post_title,
                    array( $this, 'kmfb_render_frontend_inputs' ),
                    $post->post_type,
                    'normal',
                    'high',
                    array( 'group_id' => $group->ID )
                );
            }
        }
    }

    public function kmfb_render_frontend_inputs( $post, $metabox ) {
        $group_id = $metabox['args']['group_id'];
        $fields = get_post_meta( $group_id, '_kmfb_fields_data', true );
        if ( ! is_array( $fields ) || empty( $fields ) ) {
            echo '<p>' . esc_html__( 'No fields found in this group.', 'kamaliya-meta-fields-builder' ) . '</p>';
            return;
        }

        $saved_data = get_post_meta( $post->ID, '_kmfb_frontend_data', true ) ?: array();
        wp_nonce_field( 'kmfb_frontend_save', 'kmfb_frontend_nonce' );

        echo '<div class="kmfb-frontend-wrapper" style="padding: 10px 0;">';
        
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) continue;
            
            $name = $field['name'] ?? '';
            $label = $field['label'] ?? '';
            $type = $field['type'] ?? 'text';
            $val = isset( $saved_data[$name] ) ? $saved_data[$name] : ( $field['default_value'] ?? '' );
            
            echo '<div class="kmfb-frontend-row" style="margin-bottom: 25px; border-bottom:1px solid #f0f0f1; padding-bottom:20px;">';
            echo '<label style="font-weight: 600; display: block; margin-bottom: 8px; font-size:14px; color:#1d2327;">' . esc_html( $label ) . '</label>';
            
            if ( $type === 'text' ) {
                echo '<input type="text" name="kmfb_data[' . esc_attr($name) . ']" value="' . esc_attr( $val ) . '" style="width: 100%; max-width:100%; padding: 8px; border:1px solid #8c8f94; border-radius:3px;" />';
           } elseif ( $type === 'textarea' ) {
                echo '<textarea name="kmfb_data[' . esc_attr($name) . ']" style="width: 100%; max-width:100%; padding: 8px; border:1px solid #8c8f94; border-radius:3px;" rows="4">' . esc_textarea( $val ) . '</textarea>';
            } elseif ( $type === 'number' ) {
                echo '<input type="number" name="kmfb_data[' . esc_attr($name) . ']" value="' . esc_attr( $val ) . '" style="width: 100%; max-width:100%; padding: 8px; border:1px solid #8c8f94; border-radius:3px;" />';
            } elseif ( $type === 'color' ) {
                echo '<input type="color" name="kmfb_data[' . esc_attr($name) . ']" value="' . esc_attr( $val ) . '" style="height:40px; width:100px; padding:0; border:1px solid #8c8f94; border-radius:3px; cursor:pointer;" />';
            } elseif ( $type === 'boolean' ) {
                echo '<input type="hidden" name="kmfb_data[' . esc_attr($name) . ']" value="false" />';
                echo '<label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="kmfb_data[' . esc_attr($name) . ']" value="true" ' . checked($val, 'true', false) . ' /> ' . esc_html__('True/False', 'kamaliya-meta-fields-builder') . '</label>';
            } elseif ( $type === 'select' ) {
                $choices_str = $field['choices'] ?? '';
                $lines = explode("\n", $choices_str);
                echo '<select name="kmfb_data[' . esc_attr($name) . ']" style="width: 100%; max-width:100%; padding: 8px; border:1px solid #8c8f94; border-radius:3px;">';
                foreach($lines as $line) {
                    $parts = explode(':', $line);
                    if(count($parts) > 0) {
                        $opt_val = trim($parts[0]);
                        $opt_label = isset($parts[1]) ? trim($parts[1]) : $opt_val;
                        if($opt_val !== '') {
                            echo '<option value="' . esc_attr($opt_val) . '" ' . selected($val, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                        }
                    }
                }
                echo '</select>';
            } elseif ( $type === 'image' ) {
                $img_src = esc_url($val);
                $display = $img_src ? 'block' : 'none';
                echo '<div class="kmfb-frontend-image-wrap">';
                echo '<input type="hidden" name="kmfb_data[' . esc_attr($name) . ']" class="kmfb-frontend-image-url" value="' . esc_attr( $val ) . '" />';
                echo '<div class="kmfb-frontend-image-preview" style="display:' . esc_attr($display) . '; margin-bottom:10px;">';
                echo '<img src="' . esc_url($img_src) . '" style="max-width:150px; height:auto; border:1px solid #c3c4c7; padding:3px; border-radius:3px; background:#fff;" />';
                echo '</div>';
                echo '<button type="button" class="button kmfb-frontend-media-btn">' . ($img_src ? esc_html__('Change Image', 'kamaliya-meta-fields-builder') : esc_html__('Select Image', 'kamaliya-meta-fields-builder')) . '</button>';
                echo '<button type="button" class="button kmfb-frontend-remove-img" style="color:#d63638; display:' . esc_attr($display) . '; margin-left:5px;">' . esc_html__('Remove', 'kamaliya-meta-fields-builder') . '</button>';
                echo '</div>';
            } elseif ( $type === 'file' ) {
                $file_src = esc_url($val);
                echo '<div class="kmfb-frontend-file-wrap" style="display:flex; gap:10px; align-items:center;">';
                echo '<input type="text" name="kmfb_data[' . esc_attr($name) . ']" class="kmfb-frontend-file-url" value="' . esc_attr( $file_src ) . '" style="flex:1; padding:8px; border:1px solid #8c8f94; border-radius:3px;" placeholder="' . esc_attr__('File URL', 'kamaliya-meta-fields-builder') . '" readonly />';
                echo '<button type="button" class="button kmfb-frontend-media-file-btn">' . esc_html__('Select File', 'kamaliya-meta-fields-builder') . '</button>';
                echo '<button type="button" class="button kmfb-frontend-remove-file" style="color:#d63638; ' . ( $file_src ? '' : 'display:none;' ) . '">' . esc_html__('Remove', 'kamaliya-meta-fields-builder') . '</button>';
                echo '</div>';
            } elseif ( $type === 'link' ) {
                $link_url = isset($val['url']) ? $val['url'] : '';
                $link_title = isset($val['title']) ? $val['title'] : '';
                $link_target = isset($val['target']) ? $val['target'] : '';
                
                $display_style = $link_url ? 'display:flex;' : 'display:none;';
                $btn_style = $link_url ? 'display:none;' : 'display:inline-block;';

                echo '<div class="kmfb-link-field-wrapper" style="margin-bottom:10px;">';
                echo '<input type="hidden" name="kmfb_data[' . esc_attr($name) . '][url]" class="kmfb-link-url" value="' . esc_attr($link_url) . '" />';
                echo '<input type="hidden" name="kmfb_data[' . esc_attr($name) . '][title]" class="kmfb-link-title" value="' . esc_attr($link_title) . '" />';
                echo '<input type="hidden" name="kmfb_data[' . esc_attr($name) . '][target]" class="kmfb-link-target" value="' . esc_attr($link_target) . '" />';
                
                echo '<button type="button" class="button kmfb-select-link-btn" style="' . esc_attr($btn_style) . '">' . esc_html__('Select Link', 'kamaliya-meta-fields-builder') . '</button>';
                
                echo '<div class="kmfb-link-result-box" style="' . esc_attr( $display_style ) . ' align-items:center; gap:15px; border:1px solid #ccd0d4; padding:8px 12px; background:#fff; border-radius:3px;">';
                echo '<span class="kmfb-display-title" style="font-weight:600; font-size:14px;">' . esc_html($link_title) . '</span>';
                echo '<a href="' . esc_url($link_url) . '" class="kmfb-display-url" target="_blank" style="color:#2271b1; text-decoration:none; font-size:13px;">' . esc_html($link_url) . '</a>';
                echo '<div style="margin-left:auto; display:flex; gap:5px;">';
                echo '<button type="button" class="button button-small kmfb-select-link-btn" title="' . esc_attr__('Edit', 'kamaliya-meta-fields-builder') . '"><span class="dashicons dashicons-edit" style="margin-top:3px;"></span></button>';
                echo '<button type="button" class="button button-small kmfb-remove-link-btn" title="' . esc_attr__('Remove', 'kamaliya-meta-fields-builder') . '"><span class="dashicons dashicons-no-alt" style="margin-top:3px; color:#d63638;"></span></button>';
                echo '</div></div></div>';
            } elseif ( $type === 'repeater' ) {
                $sub_fields = $field['sub_fields'] ?? array();
                $saved_rows = is_array($val) ? $val : array();
                
                echo '<div class="kmfb-frontend-repeater" data-name="' . esc_attr($name) . '">';
                echo '<div class="kmfb-frontend-repeater-rows">';
                if ( !empty($saved_rows) ) {
                    foreach ( $saved_rows as $row_index => $row_data ) {
                        $this->kmfb_render_repeater_row_html($name, $row_index, $sub_fields, $row_data);
                    }
                }
                echo '</div>'; 
                echo '<button type="button" class="button button-primary kmfb-frontend-add-row" style="margin-top:10px;">+ ' . esc_html__('Add Row', 'kamaliya-meta-fields-builder') . '</button>';
                echo '<div class="kmfb-frontend-repeater-template" style="display:none;">';
                $this->kmfb_render_repeater_row_html($name, '__ROW_INDEX__', $sub_fields, array());
                echo '</div></div>';
            }
            
            echo '</div>'; 
        }
        echo '</div>'; 
    }

    public function kmfb_render_repeater_row_html($parent_name, $row_index, $sub_fields, $row_data = array()) {
        echo '<div class="kmfb-repeater-row" style="border:1px solid #ccd0d4; background:#f9f9f9; padding:15px; margin-bottom:10px; position:relative; border-left: 3px solid #2271b1;">';
        echo '<div style="display:flex; flex-wrap:wrap; gap:15px;">';
        
        foreach ($sub_fields as $sub) {
            if(!is_array($sub)) continue;
            $s_name = $sub['name'] ?? '';
            $s_label = $sub['label'] ?? '';
            $s_type = $sub['type'] ?? 'text';
            $s_val = isset($row_data[$s_name]) ? $row_data[$s_name] : ($sub['default_value'] ?? '');
            $input_name = "kmfb_data[{$parent_name}][{$row_index}][{$s_name}]";
            
            echo '<div class="kmfb-sub-field" style="flex:1; min-width:200px;">';
            echo '<label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px; color:#1d2327;">' . esc_html( $s_label ) . '</label>';
            
            if ($s_type === 'text') {
                echo '<input type="text" name="' . esc_attr( $input_name ) . '" value="' . esc_attr($s_val) . '" style="width:100%; padding:6px; border:1px solid #8c8f94; border-radius:3px;" />';
           } elseif ($s_type === 'textarea') {
                echo '<textarea name="' . esc_attr( $input_name ) . '" style="width:100%; padding:6px; border:1px solid #8c8f94; border-radius:3px;" rows="2">' . esc_textarea($s_val) . '</textarea>';
            
            } elseif ($s_type === 'number') {
                echo '<input type="number" name="' . esc_attr( $input_name ) . '" value="' . esc_attr($s_val) . '" style="width:100%; padding:6px; border:1px solid #8c8f94; border-radius:3px;" />';
            } elseif ($s_type === 'color') {
                echo '<input type="color" name="' . esc_attr( $input_name ) . '" value="' . esc_attr($s_val) . '" style="height:35px; width:80px; padding:0; border:1px solid #8c8f94; border-radius:3px; cursor:pointer;" />';
            } elseif ($s_type === 'boolean') {
                echo '<input type="hidden" name="' . esc_attr( $input_name ) . '" value="false" />';
                echo '<label style="display:flex; align-items:center; gap:5px;"><input type="checkbox" name="' . esc_attr( $input_name ) . '" value="true" ' . checked($s_val, 'true', false) . ' /> ' . esc_html__('Yes', 'kamaliya-meta-fields-builder') . '</label>';
            } elseif ($s_type === 'select') {
                $choices_str = $sub['choices'] ?? '';
                $lines = explode("\n", $choices_str);
                echo '<select name="' . esc_attr( $input_name ) . '" style="width:100%; padding:6px; border:1px solid #8c8f94; border-radius:3px;">';
                foreach($lines as $line) {
                    $parts = explode(':', $line);
                    if(count($parts) > 0) {
                        $opt_val = trim($parts[0]);
                        $opt_label = isset($parts[1]) ? trim($parts[1]) : $opt_val;
                        if($opt_val !== '') {
                            echo '<option value="' . esc_attr($opt_val) . '" ' . selected($s_val, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                        }
                    }
                }
                echo '</select>';
            } elseif ($s_type === 'image') {
                $img_src = esc_url($s_val);
                $display = $img_src ? 'block' : 'none';
                echo '<div class="kmfb-frontend-image-wrap">';
                echo '<input type="hidden" name="' . esc_attr( $input_name ) . '" class="kmfb-frontend-image-url" value="' . esc_attr($s_val) . '" />';
                echo '<div class="kmfb-frontend-image-preview" style="display:' . esc_attr($display) . '; margin-bottom:5px;">';
                echo '<img src="' . esc_url($img_src) . '" style="max-width:100px; height:auto; border:1px solid #ccc; padding:3px; border-radius:3px; background:#fff;" />';
                echo '</div>';
                echo '<button type="button" class="button button-small kmfb-frontend-media-btn">' . ($img_src ? esc_html__('Change', 'kamaliya-meta-fields-builder') : esc_html__('Select', 'kamaliya-meta-fields-builder')) . '</button>';
                echo '<button type="button" class="button button-small kmfb-frontend-remove-img" style="color:#d63638; display:' . esc_attr($display) . '; margin-left:5px;">' . esc_html__('Remove', 'kamaliya-meta-fields-builder') . '</button>';
                echo '</div>';
            } elseif ($s_type === 'file') {
                $file_src = esc_url($s_val);
                echo '<div class="kmfb-frontend-file-wrap" style="display:flex; flex-direction:column; gap:5px;">';
                echo '<input type="text" name="' . esc_attr( $input_name ) . '" class="kmfb-frontend-file-url" value="' . esc_attr( $file_src ) . '" style="width:100%; padding:6px; border:1px solid #8c8f94; border-radius:3px;" placeholder="URL" readonly />';
                echo '<div style="display:flex; gap:5px;">';
                echo '<button type="button" class="button button-small kmfb-frontend-media-file-btn">' . esc_html__('Select File', 'kamaliya-meta-fields-builder') . '</button>';
                echo '<button type="button" class="button button-small kmfb-frontend-remove-file" style="color:#d63638; ' . ( $file_src ? '' : 'display:none;' ) . '">X</button>';
                echo '</div></div>';
            } elseif ($s_type === 'link') {
                $link_url = isset($s_val['url']) ? $s_val['url'] : '';
                $link_title = isset($s_val['title']) ? $s_val['title'] : '';
                $link_target = isset($s_val['target']) ? $s_val['target'] : '';
                
                $display_style = $link_url ? 'display:flex;' : 'display:none;';
                $btn_style = $link_url ? 'display:none;' : 'display:inline-block;';

                echo '<div class="kmfb-link-field-wrapper" style="margin-bottom:5px;">';
                echo '<input type="hidden" name="' . esc_attr( $input_name ) . '[url]" class="kmfb-link-url" value="' . esc_attr($link_url) . '" />';
                echo '<input type="hidden" name="' . esc_attr( $input_name ) . '[title]" class="kmfb-link-title" value="' . esc_attr($link_title) . '" />';
                echo '<input type="hidden" name="' . esc_attr( $input_name ) . '[target]" class="kmfb-link-target" value="' . esc_attr($link_target) . '" />';
                
                echo '<button type="button" class="button button-small kmfb-select-link-btn" style="' . esc_attr( $btn_style ) . '">' . esc_html__('Select Link', 'kamaliya-meta-fields-builder') . '</button>';
                
                echo '<div class="kmfb-link-result-box" style="' . esc_attr( $display_style ) . ' align-items:center; gap:10px; border:1px solid #ccd0d4; padding:6px 10px; background:#fff; border-radius:3px;">';
                echo '<span class="kmfb-display-title" style="font-weight:600; font-size:13px;">' . esc_html($link_title) . '</span>';
                echo '<a href="' . esc_url($link_url) . '" class="kmfb-display-url" target="_blank" style="color:#2271b1; text-decoration:none; font-size:12px; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html($link_url) . '</a>';
                echo '<div style="margin-left:auto; display:flex; gap:5px;">';
                echo '<button type="button" class="button button-small kmfb-select-link-btn" title="' . esc_attr__('Edit', 'kamaliya-meta-fields-builder') . '"><span class="dashicons dashicons-edit" style="margin-top:2px; font-size:16px;"></span></button>';
                echo '<button type="button" class="button button-small kmfb-remove-link-btn" title="' . esc_attr__('Remove', 'kamaliya-meta-fields-builder') . '"><span class="dashicons dashicons-no-alt" style="margin-top:2px; font-size:16px; color:#d63638;"></span></button>';
                echo '</div></div></div>';
            } 
            echo '</div>';
        }
        
        echo '<div style="flex: 0 0 auto; padding-top:22px;">';
        echo '<button type="button" class="button kmfb-remove-repeater-row" style="color:#d63638; border-color:#d63638;" title="' . esc_attr__('Delete Row', 'kamaliya-meta-fields-builder') . '">X</button>';
        echo '</div></div></div>'; 
    }

    
    public function kmfb_save_frontend_post_data( $post_id ) {
        if ( ! isset( $_POST['kmfb_frontend_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['kmfb_frontend_nonce'] ), 'kmfb_frontend_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['kmfb_data'] ) && is_array( $_POST['kmfb_data'] ) ) {
            update_post_meta( $post_id, '_kmfb_frontend_data', $this->kmfb_sanitize_array( $_POST['kmfb_data'] ) );
        } else {
            delete_post_meta( $post_id, '_kmfb_frontend_data' );
        }
    }

    // =========================================================================
    // PHASE 2: GUTENBERG NATIVE BLOCKS ENGINE
    // =========================================================================

    public function kmfb_enqueue_block_editor_assets() {
        wp_enqueue_script(
            'kmfb-blocks-js',
            plugins_url( 'kmfb-blocks.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-block-editor' ),
            KMFB_VERSION,
            true
        );

        $field_groups = get_posts( array( 'post_type' => 'kmfb_field_group', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        $blocks_data = array();

        foreach ( $field_groups as $group ) {
            $location = get_post_meta( $group->ID, '_kmfb_location_data', true );
            
            if ( isset($location['param']) && $location['param'] === 'block' ) {
                $block_slug = !empty($location['value']) ? $location['value'] : 'kmfb/block-' . $group->ID;
                if ( strpos($block_slug, '/') === false ) {
                    $block_slug = 'kmfb/' . sanitize_title($block_slug);
                }

                $fields = get_post_meta( $group->ID, '_kmfb_fields_data', true );
                if ( !is_array($fields) ) $fields = array();

                $blocks_data[] = array(
                    'name'   => sanitize_text_field($block_slug),
                    'title'  => sanitize_text_field($group->post_title),
                    'fields' => $fields
                );
            }
        }

        wp_localize_script( 'kmfb-blocks-js', 'kmfbBlocksData', $blocks_data );

        $menus = wp_get_nav_menus();
        $menu_options = array( array( 'label' => '-- Select a Menu --', 'value' => '' ) );
        foreach ( $menus as $menu ) {
            $menu_options[] = array( 'label' => $menu->name, 'value' => $menu->term_id );
        }
        wp_localize_script( 'kmfb-blocks-js', 'kmfbGlobalData', array( 'menus' => $menu_options ) );

        wp_enqueue_style( 'kmfb-block-editor-style', plugins_url( 'kmfb-block-editor.css', __FILE__ ), array(), KMFB_VERSION );
    }

    public function kmfb_register_php_blocks() {
        $field_groups = get_posts( array( 'post_type' => 'kmfb_field_group', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        
        foreach ( $field_groups as $group ) {
            $location = get_post_meta( $group->ID, '_kmfb_location_data', true );
            
            if ( isset($location['param']) && $location['param'] === 'block' ) {
                $block_slug = !empty($location['value']) ? $location['value'] : 'kmfb/block-' . $group->ID;
                if ( strpos($block_slug, '/') === false ) {
                    $block_slug = 'kmfb/' . sanitize_title($block_slug);
                }

            $fields = get_post_meta( $group->ID, '_kmfb_fields_data', true );
                
          $attributes = array(
                    'kmfb_custom_class' => array( 'type' => 'string', 'default' => '' ),
                    'className'        => array( 'type' => 'string', 'default' => '' ),
                    'align'            => array( 'type' => 'string', 'default' => '' ),
                    'lock'             => array( 'type' => 'object', 'default' => array() )
                );

                if ( is_array($fields) ) {
                    foreach ( $fields as $field ) {
                        if ( empty( $field['name'] ) ) continue; 

                        $attributes[$field['name']] = array(
                            'type'    => 'string',
                            'default' => $field['default_value'] ?? '',
                        );
                    }
                }

                $clean_slug       = str_replace('kmfb/', '', $block_slug);
                $theme_css_path   = get_stylesheet_directory() . "/kmfb-modules/{$clean_slug}.css";
                $theme_css_url    = get_stylesheet_directory_uri() . "/kmfb-modules/{$clean_slug}.css";
                $plugin_css_path  = plugin_dir_path( __FILE__ ) . "modules/{$clean_slug}.css";
                $plugin_css_url   = plugins_url( "modules/{$clean_slug}.css", __FILE__ );

                $editor_style_handle = 'kmfb-editor-css-' . $clean_slug;
                $has_editor_style    = false;

                if ( file_exists( $theme_css_path ) ) {
                    wp_register_style( $editor_style_handle, $theme_css_url, array(), filemtime($theme_css_path) );
                    $has_editor_style = true;
                } elseif ( file_exists( $plugin_css_path ) ) {
                    wp_register_style( $editor_style_handle, $plugin_css_url, array(), filemtime($plugin_css_path) );
                    $has_editor_style = true;
                }

                $block_args = array(
                    'attributes'      => $attributes,
                    'render_callback' => array( $this, 'kmfb_render_dynamic_block_frontend' )
                );

                if ( $has_editor_style ) {
                    $block_args['editor_style'] = $editor_style_handle;
                }

                register_block_type( $block_slug, $block_args );
            }
        }
    }


public function kmfb_render_dynamic_block_frontend( $attributes, $content, $block ) {
        $block_slug = str_replace('kmfb/', '', $block->name); 

        $theme_module     = get_stylesheet_directory() . "/kmfb-modules/{$block_slug}.php";
        $theme_css_path   = get_stylesheet_directory() . "/kmfb-modules/{$block_slug}.css";
        $theme_css_url    = get_stylesheet_directory_uri() . "/kmfb-modules/{$block_slug}.css";
        
        $plugin_module    = plugin_dir_path( __FILE__ ) . "modules/{$block_slug}.php";
        $plugin_css_path  = plugin_dir_path( __FILE__ ) . "modules/{$block_slug}.css";
        $plugin_css_url   = plugins_url( "modules/{$block_slug}.css", __FILE__ );

        ob_start();

        if ( file_exists( $theme_css_path ) ) {
            wp_enqueue_style( 'kmfb-css-' . $block_slug, $theme_css_url, array(), filemtime($theme_css_path) );
        } elseif ( file_exists( $plugin_css_path ) ) {
            wp_enqueue_style( 'kmfb-css-' . $block_slug, $plugin_css_url, array(), filemtime($plugin_css_path) );
        }

        $align_class = !empty($attributes['align']) ? 'align' . $attributes['align'] : '';
        $custom_class = !empty($attributes['kmfb_custom_class']) ? $attributes['kmfb_custom_class'] : '';
        
        $wrapper_classes = trim("kmfb-block-wrapper {$align_class} {$custom_class}");
        
        echo '<div class="' . esc_attr( $wrapper_classes ) . '">';

        if ( file_exists( $theme_module ) ) {
            extract( $attributes );
            include( $theme_module );
        } 
        elseif ( file_exists( $plugin_module ) ) {
            extract( $attributes );
            include( $plugin_module );
        } 
        else {
            echo '<div style="padding:20px; border:1px dashed #ccc;"><strong>' . esc_html__('Module Missing:', 'kamaliya-meta-fields-builder') . '</strong> Create <code>' . esc_html( $block_slug ) . '.php</code></div>';
        }

        echo '</div>'; 

        return ob_get_clean();
    }


   public function kmfb_ensure_modules_folder() {
        $theme_dir = get_stylesheet_directory() . '/kmfb-modules';
        if ( ! file_exists( $theme_dir ) ) {
            wp_mkdir_p( $theme_dir );
        }

        $plugin_dir = plugin_dir_path( __FILE__ ) . 'modules';
        if ( ! file_exists( $plugin_dir ) ) {
            wp_mkdir_p( $plugin_dir );
        }
    }

    // =========================================================================
    // JSON IMPORT / EXPORT TOOLS
    // =========================================================================

    public function kmfb_add_tools_menu() {
        add_submenu_page( 
            'kamaliya-meta-fields-builder', 
            'Tools', 
            'Tools', 
            'manage_options', 
            'kmfb-tools', 
            array( $this, 'kmfb_tools_page_html' ) 
        );
    }

    public function kmfb_process_export_import() {
        if ( isset( $_POST['kmfb_export_action'] ) && check_admin_referer( 'kmfb_export_nonce' ) ) {
            if ( !empty( $_POST['kmfb_export_groups'] ) && is_array( $_POST['kmfb_export_groups'] ) ) {
                $export_data = array();
                foreach ( $_POST['kmfb_export_groups'] as $group_id ) {
                    $post = get_post( intval( $group_id ) );
                    if ( $post && $post->post_type === 'kmfb_field_group' ) {
                        $export_data[] = array(
                            'title'    => $post->post_title,
                            'fields'   => get_post_meta( $post->ID, '_kmfb_fields_data', true ),
                            'location' => get_post_meta( $post->ID, '_kmfb_location_data', true )
                        );
                    }
                }
                
                $json = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
                $filename = 'kmfb-export-' . date('Y-m-d') . '.json';
                
                header( 'Content-Description: File Transfer' );
                header( 'Content-Disposition: attachment; filename=' . $filename );
                header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
                echo $json;
                exit;
            }
        }

        if ( isset( $_POST['kmfb_import_action'] ) && check_admin_referer( 'kmfb_import_nonce' ) ) {
            if ( !empty( $_FILES['kmfb_import_file']['tmp_name'] ) ) {
                $file_contents = file_get_contents( $_FILES['kmfb_import_file']['tmp_name'] );
                $import_data = json_decode( $file_contents, true );

                if ( is_array( $import_data ) ) {
                    $imported_count = 0;
                    foreach ( $import_data as $group ) {
                        if ( !empty( $group['title'] ) ) {
                            $post_id = wp_insert_post( array(
                                'post_title'  => sanitize_text_field( $group['title'] ),
                                'post_type'   => 'kmfb_field_group',
                                'post_status' => 'publish'
                            ) );

                            if ( $post_id && !is_wp_error( $post_id ) ) {
                                if ( isset( $group['fields'] ) ) update_post_meta( $post_id, '_kmfb_fields_data', $group['fields'] );
                                if ( isset( $group['location'] ) ) update_post_meta( $post_id, '_kmfb_location_data', $group['location'] );
                                $imported_count++;
                            }
                        }
                    }
                    
                    add_action( 'admin_notices', function() use ( $imported_count ) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __('Successfully imported %d Field Group(s).', 'kamaliya-meta-fields-builder'), (int)$imported_count ) ) . '</p></div>';
                    });
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error: Invalid JSON file.', 'kamaliya-meta-fields-builder') . '</p></div>';
                    });
                }
            }
        }
    }

    public function kmfb_tools_page_html() {
        $field_groups = get_posts( array( 'post_type' => 'kmfb_field_group', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 20px;"><?php esc_html_e('Tools', 'kamaliya-meta-fields-builder'); ?></h1>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                
                <div style="flex: 1; min-width: 400px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="padding: 15px 20px; border-bottom: 1px solid #ccd0d4; background: #fcfcfc;">
                        <h2 style="margin: 0; font-size: 16px;"><?php esc_html_e('Export Field Groups', 'kamaliya-meta-fields-builder'); ?></h2>
                    </div>
                    <div style="padding: 20px;">
                        <form method="post" action="">
                            <?php wp_nonce_field( 'kmfb_export_nonce' ); ?>
                            <input type="hidden" name="kmfb_export_action" value="1">
                            
                            <p style="margin-top:0;"><?php esc_html_e('Select the field groups you would like to export.', 'kamaliya-meta-fields-builder'); ?></p>
                            
                            <div style="border: 1px solid #ccd0d4; border-radius: 3px; padding: 10px; max-height: 250px; overflow-y: auto; margin-bottom: 15px; background: #fafafa;">
                                <?php if ( empty( $field_groups ) ) : ?>
                                    <p><?php esc_html_e('No field groups found.', 'kamaliya-meta-fields-builder'); ?></p>
                                <?php else : ?>
                                    <label style="display:block; margin-bottom:10px; font-weight:600; padding-bottom:5px; border-bottom:1px solid #ddd;">
                                        <input type="checkbox" id="kmfb-toggle-all"> <?php esc_html_e('Toggle All', 'kamaliya-meta-fields-builder'); ?>
                                    </label>
                                    <div style="column-count: 2; column-gap: 20px;">
                                        <?php foreach ( $field_groups as $group ) : ?>
                                            <label style="display:block; margin-bottom:8px; break-inside: avoid;">
                                                <input type="checkbox" name="kmfb_export_groups[]" class="kmfb-export-checkbox" value="<?php echo esc_attr( $group->ID ); ?>"> 
                                                <?php echo esc_html( $group->post_title ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="button button-primary"><?php esc_html_e('Export As JSON', 'kamaliya-meta-fields-builder'); ?></button>
                        </form>
                    </div>
                </div>

                <div style="flex: 1; min-width: 400px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="padding: 15px 20px; border-bottom: 1px solid #ccd0d4; background: #fcfcfc;">
                        <h2 style="margin: 0; font-size: 16px;"><?php esc_html_e('Import Field Groups', 'kamaliya-meta-fields-builder'); ?></h2>
                    </div>
                    <div style="padding: 20px;">
                        <form method="post" action="" enctype="multipart/form-data">
                            <?php wp_nonce_field( 'kmfb_import_nonce' ); ?>
                            <input type="hidden" name="kmfb_import_action" value="1">
                            
                            <p style="margin-top:0;"><?php esc_html_e('Select a JSON file containing exported field groups.', 'kamaliya-meta-fields-builder'); ?></p>
                            
                            <div style="border: 1px solid #ccd0d4; border-radius: 3px; padding: 15px; margin-bottom: 15px; background: #fafafa; display:flex; align-items:center;">
                                <input type="file" name="kmfb_import_file" accept=".json" required style="width:100%;">
                            </div>
                            
                            <button type="submit" class="button button-primary"><?php esc_html_e('Import JSON', 'kamaliya-meta-fields-builder'); ?></button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

} 

new KMFB_Meta_Builder();