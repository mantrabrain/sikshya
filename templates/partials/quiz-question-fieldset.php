<?php
/**
 * Single quiz question (fieldset) for the learn quiz form.
 *
 * @package Sikshya
 *
 * @var int   $qi      Zero-based index.
 * @var array $q       Row from QuizTemplateData::buildQuestionViewRowForId.
 */

if (! defined('ABSPATH')) {
    exit;
}

$qi   = isset($qi) ? (int) $qi : 0;
$q    = isset($q) && is_array($q) ? $q : [];
$qid  = isset($q['id']) ? (int) $q['id'] : 0;
$type = isset($q['type']) ? sanitize_key((string) $q['type']) : 'multiple_choice';
if ($qid <= 0) {
    return;
}

$title  = isset($q['title']) ? (string) $q['title'] : '';
$label  = sprintf(
    /* translators: 1: question number, 2: question text */
    __('%1$d) %2$s', 'sikshya'),
    $qi + 1,
    $title
);
$base_name = 'question_' . $qid;

$opts     = isset($q['options']) && is_array($q['options']) ? array_values($q['options']) : [];
$qtype    = in_array(
    $type,
    [
        'multiple_choice',
        'true_false',
        'multiple_response',
        'fill_blank',
        'short_answer',
        'essay',
        'ordering',
        'matching',
    ],
    true
) ? $type : 'multiple_choice';

if ($qtype === 'true_false' && $opts === []) {
    $opts = [__( 'True', 'sikshya' ), __( 'False', 'sikshya' )];
}
?>
<fieldset class="sikshya-quizQ sikshya-q" data-qid="<?php echo esc_attr((string) $qid); ?>" data-qtype="<?php echo esc_attr($qtype); ?>">
	<legend class="sikshya-quizQ__title sikshya-q__title"><?php echo esc_html($label); ?></legend>

	<?php if ($qtype === 'multiple_choice' || $qtype === 'true_false') : ?>
		<?php foreach ($opts as $oi => $opt) : ?>
			<label class="sikshya-quizQ__opt">
				<input
					type="radio"
					name="<?php echo esc_attr($base_name); ?>"
					value="<?php echo esc_attr((string) (int) $oi); ?>"
				/>
				<span><?php echo esc_html((string) $opt); ?></span>
			</label>
		<?php endforeach; ?>

	<?php elseif ($qtype === 'multiple_response') : ?>
		<?php foreach ($opts as $oi => $opt) : ?>
			<label class="sikshya-quizQ__opt">
				<input
					type="checkbox"
					class="sikshya-q__mr"
					name="<?php echo esc_attr($base_name . '[]'); ?>"
					value="<?php echo esc_attr((string) (int) $oi); ?>"
				/>
				<span><?php echo esc_html((string) $opt); ?></span>
			</label>
		<?php endforeach; ?>

	<?php elseif (in_array($qtype, ['fill_blank', 'short_answer', 'essay'], true)) : ?>
		<label class="sikshya-quizQ__textLabel">
			<span class="sikshya-screen-reader-text"><?php esc_html_e('Your answer', 'sikshya'); ?></span>
			<textarea
				class="sikshya-quizQ__textarea sikshya-textarea sikshya-textarea--full"
				name="<?php echo esc_attr($base_name); ?>"
				rows="<?php echo $qtype === 'essay' ? '8' : '3'; ?>"
			></textarea>
		</label>

	<?php elseif ($qtype === 'ordering' && ! empty($q['ordering_display']) && is_array($q['ordering_display'])) : ?>
		<ol class="sikshya-ordering" start="1">
			<?php foreach ((array) $q['ordering_display'] as $pair) : ?>
				<?php
				if (! is_array($pair)) {
					continue;
				}
				$ix   = isset($pair['index']) ? (int) $pair['index'] : 0;
				$ptxt = isset($pair['text']) ? (string) $pair['text'] : '';
				?>
				<li class="sikshya-ordering__item" data-item-index="<?php echo esc_attr((string) $ix); ?>">
					<span class="sikshya-ordering__text"><?php echo esc_html($ptxt); ?></span>
					<span class="sikshya-ordering__controls">
						<button type="button" class="sikshya-btn sikshya-btn--sm sikshya-ordering__up" aria-label="<?php echo esc_attr__('Move up', 'sikshya'); ?>">↑</button>
						<button type="button" class="sikshya-btn sikshya-btn--sm sikshya-ordering__down" aria-label="<?php echo esc_attr__('Move down', 'sikshya'); ?>">↓</button>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>

	<?php elseif ($qtype === 'matching') : ?>
		<?php
		$left  = isset($q['matching_left']) && is_array($q['matching_left']) ? array_values($q['matching_left']) : [];
		$right = isset($q['matching_right']) && is_array($q['matching_right']) ? array_values($q['matching_right']) : [];
		?>
		<?php if ($left !== [] && $right !== []) : ?>
			<div class="sikshya-matching">
				<?php foreach ($left as $i => $left_text) : ?>
					<div class="sikshya-matching__row">
						<div class="sikshya-matching__left"><?php echo esc_html((string) $left_text); ?></div>
						<div class="sikshya-matching__right">
							<label class="sikshya-screen-reader-text"><?php esc_html_e('Match to', 'sikshya'); ?></label>
							<select class="sikshya-matching__select" aria-label="<?php echo esc_attr__('Select match', 'sikshya'); ?>">
								<?php foreach ($right as $ri => $r_text) : ?>
									<option value="<?php echo esc_attr((string) (int) $ri); ?>"><?php echo esc_html((string) $r_text); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="sikshya-muted"><?php esc_html_e('This question could not be displayed.', 'sikshya'); ?></p>
		<?php endif; ?>

	<?php else : ?>
		<?php if ($opts === []) : ?>
			<p class="sikshya-muted"><?php esc_html_e('This question has no options.', 'sikshya'); ?></p>
		<?php else : ?>
			<?php
			// Fall back: single-choice.
			?>
			<?php foreach ($opts as $oi => $opt) : ?>
				<label class="sikshya-quizQ__opt">
					<input
						type="radio"
						name="<?php echo esc_attr($base_name); ?>"
						value="<?php echo esc_attr((string) (int) $oi); ?>"
					/>
					<span><?php echo esc_html((string) $opt); ?></span>
				</label>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>
</fieldset>
