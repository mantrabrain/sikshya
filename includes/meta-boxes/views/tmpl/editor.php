<div class="ms-editor">
    <div id="wp-<?php echo $id; ?>-wrap" class="wp-core-ui wp-editor-wrap tmce-active">
        <div id="wp-<?php echo $id; ?>-editor-tools" class="wp-editor-tools hide-if-no-js">
            <div id="wp-<?php echo $id; ?>-media-buttons" class="wp-media-buttons">
                <button type="button" class="button insert-media add_media" data-editor="<?php echo $id; ?>"><span
                        class="wp-media-buttons-icon"></span> <?php _e('Add Media', 'sikshya'); ?></button>
            </div>
            <div class="wp-editor-tabs">
                <button type="button" id="<?php echo $id; ?>-tmce" class="wp-switch-editor switch-tmce"
                        data-wp-editor-id="<?php echo $id; ?>"><?php _e('Visual', 'sikshya'); ?></button>
                <button type="button" id="<?php echo $id; ?>-html" class="wp-switch-editor switch-html"
                        data-wp-editor-id="<?php echo $id; ?>"><?php _e('Text', 'sikshya'); ?></button>
            </div>
        </div>
        <div id="wp-<?php echo $id; ?>-editor-container" class="wp-editor-container">
            <div id="qt_<?php echo $id; ?>_toolbar" class="quicktags-toolbar"></div>
            <textarea class="wp-editor-area js-sikshya__editor" rows="7" autocomplete="off" cols="40"
                      name="<?php echo $name; ?>"
                      id="<?php echo $id; ?>"><?php echo esc_textarea($content); ?></textarea>
        </div>
    </div>
</div>