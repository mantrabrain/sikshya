<div class="upload-theme sikshya-upload-file">
	<p class="install-help">
		<?php
		echo __('If you have a exported courses file, you can upload it from here and import the courses.', 'sikshya')
		?></p>
	<form method="post" enctype="multipart/form-data" class="wp-upload-form"
		  action="">
		<input type="hidden" id="_wpnonce" name="_wpnonce" value="49c1b3acae">
		<input type="hidden"
			   name="_wp_http_referer"
			   value="/WordPressThemes/wp-admin/theme-install.php">
		<label class="screen-reader-text"
			   for="coursesfile"><?php echo esc_html__('Course exported file', 'sikshya'); ?></label>
		<input type="file" id="themezip" name="themezip" accept=".zip">
		<input type="submit" name="install-theme-submit" id="install-theme-submit" class="button"
			   value="<?php echo __('Import Now') ?>"
			   disabled=""></form>
</div>
