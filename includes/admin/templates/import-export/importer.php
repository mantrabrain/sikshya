<h2>Sikshya Importer</h2>
<div class="upload-theme sikshya-upload-file">
	<p class="install-help">
		<?php
		echo __('If you have a exported courses file, you can upload it from here and import the courses Or You can upload sample course file. (file format .json)', 'sikshya')
		?></p>
	<form method="post" enctype="multipart/form-data" class="wp-upload-form sikshya-import-course-form"
		  action="<?php echo esc_attr('admin-ajax.php'); ?>">
		<label class="screen-reader-text"
			   for="coursesfile"><?php echo esc_html__('Course exported file', 'sikshya'); ?></label>
		<input type="file" id="sikshya_import_file" name="sikshya_import_file" accept=".json">
		<input type="submit" name="install-theme-submit" id="install-theme-submit" class="button"
			   value="<?php echo __('Import Now', 'sikshya') ?>"
			   disabled="">

		<input type="hidden" value="sikshya_import_course" name="action"/>
		<input type="hidden" value="sikshya_import_course" name="sikshya_course_import_notice"/>
		<input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_import_course_nonce') ?>"
			   name="sikshya_nonce"/>

	</form>
</div>
