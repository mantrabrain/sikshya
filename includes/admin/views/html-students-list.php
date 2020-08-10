<?php

$students = new Sikshya_Admin_List_Table_Students();

$students->prepare_items();

$message = '';

if ('delete' === $students->current_action()) {
	$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'sikshya'), count($_REQUEST['id'])) . '</p></div>';
}
?>
<div class="wrap">
	<?php echo $message; ?>

	<form id="students-table" method="GET">
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
		<?php $students->display() ?>
	</form>

</div>
