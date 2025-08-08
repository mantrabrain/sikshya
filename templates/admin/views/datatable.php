<?php
/**
 * DataTable Template
 *
 * @package Sikshya\Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-admin">
    <div class="sikshya-container">
        <!-- Header -->
        <div class="sikshya-header">
            <div class="sikshya-header-content">
                <div>
                    <h1 class="sikshya-header-title"><?php echo esc_html($config['title']); ?></h1>
                    <?php if (!empty($config['description'])): ?>
                        <p class="sikshya-header-description"><?php echo esc_html($config['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="sikshya-header-actions">
                    <?php if (!empty($config['actions'])): ?>
                        <?php foreach ($config['actions'] as $action): ?>
                            <a href="<?php echo esc_url($action['url']); ?>" 
                               class="sikshya-btn sikshya-btn-primary">
                                <?php echo esc_html($action['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <?php if (!empty($config['filters'])): ?>
            <div class="sikshya-card sikshya-mb-6">
                <div class="sikshya-card-body">
                    <form id="sikshya-filters-form" class="sikshya-grid sikshya-grid-cols-3">
                        <?php foreach ($config['filters'] as $key => $filter): ?>
                            <div class="sikshya-form-group">
                                <label class="sikshya-form-label"><?php echo esc_html($filter['title']); ?></label>
                                <?php if ($filter['type'] === 'select'): ?>
                                    <select name="<?php echo esc_attr($key); ?>" class="sikshya-form-select">
                                        <?php foreach ($filter['options'] as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>">
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($filter['type'] === 'text'): ?>
                                    <input type="text" 
                                           name="<?php echo esc_attr($key); ?>" 
                                           class="sikshya-form-input" 
                                           placeholder="<?php echo esc_attr($filter['placeholder'] ?? ''); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="sikshya-form-group">
                            <label class="sikshya-form-label">&nbsp;</label>
                            <button type="submit" class="sikshya-btn sikshya-btn-primary">
                                <?php _e('Apply Filters', 'sikshya'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- DataTable -->
        <div class="sikshya-card">
            <div class="sikshya-card-header">
                <div class="sikshya-card-header-content">
                    <h2 class="sikshya-card-title"><?php echo esc_html($config['title']); ?></h2>
                    <div class="sikshya-card-header-actions">
                        <?php if ($config['search']): ?>
                            <div class="sikshya-search">
                                <input type="text" 
                                       id="sikshya-search-input" 
                                       class="sikshya-form-input" 
                                       placeholder="<?php _e('Search...', 'sikshya'); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sikshya-card-body">
                <!-- Bulk Actions -->
                <?php if (!empty($config['bulk_actions']) && $config['selectable']): ?>
                    <div class="sikshya-bulk-actions sikshya-mb-4">
                        <select id="sikshya-bulk-action" class="sikshya-form-select">
                            <option value=""><?php _e('Bulk Actions', 'sikshya'); ?></option>
                            <?php foreach ($config['bulk_actions'] as $key => $action): ?>
                                <option value="<?php echo esc_attr($action['action']); ?>">
                                    <?php echo esc_html($action['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="sikshya-bulk-apply" class="sikshya-btn sikshya-btn-secondary">
                            <?php _e('Apply', 'sikshya'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="sikshya-table-wrapper">
                    <table id="<?php echo esc_attr($table_id); ?>" class="sikshya-table">
                        <thead>
                            <tr>
                                <?php if ($config['selectable']): ?>
                                    <th width="40">
                                        <input type="checkbox" id="sikshya-select-all">
                                    </th>
                                <?php endif; ?>
                                <?php foreach ($config['columns'] as $key => $column): ?>
                                    <th <?php echo !empty($column['width']) ? 'width="' . esc_attr($column['width']) . '"' : ''; ?>>
                                        <?php echo esc_html($column['title']); ?>
                                        <?php if ($column['sortable'] ?? false): ?>
                                            <span class="sikshya-sort-icon">↕</span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                                <?php if (!empty($config['actions'])): ?>
                                    <th width="120"><?php _e('Actions', 'sikshya'); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="<?php echo count($config['columns']) + ($config['selectable'] ? 1 : 0) + (!empty($config['actions']) ? 1 : 0); ?>" 
                                        class="sikshya-text-center sikshya-p-6">
                                        <p class="sikshya-text-gray-500"><?php echo esc_html($config['empty_message']); ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr data-id="<?php echo esc_attr($item->id ?? $item->ID); ?>">
                                        <?php if ($config['selectable']): ?>
                                            <td>
                                                <input type="checkbox" class="sikshya-row-select" value="<?php echo esc_attr($item->id ?? $item->ID); ?>">
                                            </td>
                                        <?php endif; ?>
                                        <?php foreach ($config['columns'] as $key => $column): ?>
                                            <td>
                                                <?php if (isset($column['render']) && is_callable($column['render'])): ?>
                                                    <?php echo call_user_func($column['render'], $item, $key); ?>
                                                <?php else: ?>
                                                    <?php echo esc_html($item->$key ?? ''); ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <?php if (!empty($config['actions'])): ?>
                                            <td>
                                                <div class="sikshya-actions">
                                                    <?php foreach ($config['actions'] as $action_key => $action): ?>
                                                        <?php 
                                                        $url = str_replace('{id}', $item->id ?? $item->ID, $action['url']);
                                                        $onclick = str_replace('{id}', $item->id ?? $item->ID, $action['onclick'] ?? '');
                                                        ?>
                                                        <a href="<?php echo esc_url($url); ?>" 
                                                           class="sikshya-btn sikshya-btn-sm <?php echo esc_attr($action['class']); ?>"
                                                           <?php echo !empty($onclick) ? 'onclick="' . esc_js($onclick) . '"' : ''; ?>>
                                                            <?php echo esc_html($action['title']); ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($config['pagination']): ?>
                    <div class="sikshya-pagination sikshya-mt-6">
                        <div class="sikshya-pagination-info">
                            <span class="sikshya-text-sm sikshya-text-gray-500">
                                <?php _e('Showing', 'sikshya'); ?> 
                                <span id="sikshya-pagination-from">1</span> 
                                <?php _e('to', 'sikshya'); ?> 
                                <span id="sikshya-pagination-to"><?php echo count($items); ?></span> 
                                <?php _e('of', 'sikshya'); ?> 
                                <span id="sikshya-pagination-total"><?php echo count($items); ?></span> 
                                <?php _e('results', 'sikshya'); ?>
                            </span>
                        </div>
                        <div class="sikshya-pagination-controls">
                            <button type="button" id="sikshya-prev-page" class="sikshya-btn sikshya-btn-secondary" disabled>
                                <?php _e('Previous', 'sikshya'); ?>
                            </button>
                            <span class="sikshya-pagination-pages">
                                <span id="sikshya-current-page">1</span> 
                                <?php _e('of', 'sikshya'); ?> 
                                <span id="sikshya-total-pages">1</span>
                            </span>
                            <button type="button" id="sikshya-next-page" class="sikshya-btn sikshya-btn-secondary" disabled>
                                <?php _e('Next', 'sikshya'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize DataTable
    const tableId = '<?php echo esc_js($table_id); ?>';
    const config = <?php echo json_encode($config); ?>;
    
    // Search functionality
    if (config.search) {
        $('#sikshya-search-input').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $(`#${tableId} tbody tr`).each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
    }
    
    // Bulk actions
    if (config.selectable) {
        $('#sikshya-select-all').on('change', function() {
            $('.sikshya-row-select').prop('checked', $(this).is(':checked'));
        });
        
        $('#sikshya-bulk-apply').on('click', function() {
            const action = $('#sikshya-bulk-action').val();
            const selectedIds = $('.sikshya-row-select:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (!action || selectedIds.length === 0) {
                alert('<?php _e('Please select an action and items', 'sikshya'); ?>');
                return;
            }
            
            if (confirm('<?php _e('Are you sure you want to perform this action?', 'sikshya'); ?>')) {
                // Perform bulk action via AJAX
                $.post(ajaxurl, {
                    action: action,
                    ids: selectedIds,
                    nonce: sikshya.nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Action failed', 'sikshya'); ?>');
                    }
                });
            }
        });
    }
    
    // Sorting functionality
    if (config.sortable) {
        $('.sikshya-table th').on('click', function() {
            const column = $(this).data('column');
            if (column) {
                // Implement sorting logic
                console.log('Sort by:', column);
            }
        });
    }
});
</script> 