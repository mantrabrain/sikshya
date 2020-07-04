<?php
echo '<h2>' . esc_html__('Report', 'sikshya') . '</h2>';
?>
<table class="sikshya-quiz-report-table">
	<tbody>
	<tr>
		<th><?php echo esc_html__('Total Questions', 'sikshya'); ?></th>
		<td><?php echo esc_html($report_data['total_questions']); ?></td>
	</tr>
	<tr>
		<th><?php echo esc_html__('Answer Questions', 'sikshya'); ?></th>
		<td><?php echo esc_html($report_data['answered_questions']); ?></td>
	</tr>
	<tr>
		<th><?php echo esc_html__('Wrong Answered Questions', 'sikshya'); ?></th>
		<td><?php echo esc_html($report_data['wrong_questions']); ?></td>
	</tr>
	<tr>
		<th><?php echo esc_html__('Correct Answered Questions', 'sikshya'); ?></th>
		<td><?php echo esc_html($report_data['correct_questions']); ?></td>
	</tr>
	<tr>
		<th><?php echo esc_html__('Skipped Questions', 'sikshya'); ?></th>
		<td><?php echo esc_html($report_data['skipped_questions']); ?></td>
	</tr>
	</tbody>
</table>
