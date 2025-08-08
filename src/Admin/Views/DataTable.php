<?php

namespace Sikshya\Admin\Views;

/**
 * Reusable DataTable Component
 *
 * @package Sikshya\Admin\Views
 */
class DataTable extends BaseView
{
    /**
     * Table configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Table data
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Constructor
     *
     * @param \Sikshya\Core\Plugin $plugin
     * @param array $config
     */
    public function __construct(\Sikshya\Core\Plugin $plugin, array $config = [])
    {
        parent::__construct($plugin);
        $this->config = $this->getDefaultConfig();
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Set table items
     *
     * @param array $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }

    /**
     * Set table configuration
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Add column
     *
     * @param string $key
     * @param array $column
     * @return $this
     */
    public function addColumn(string $key, array $column): self
    {
        $this->config['columns'][$key] = $column;
        return $this;
    }

    /**
     * Add action
     *
     * @param string $key
     * @param array $action
     * @return $this
     */
    public function addAction(string $key, array $action): self
    {
        $this->config['actions'][$key] = $action;
        return $this;
    }

    /**
     * Add bulk action
     *
     * @param string $key
     * @param array $action
     * @return $this
     */
    public function addBulkAction(string $key, array $action): self
    {
        $this->config['bulk_actions'][$key] = $action;
        return $this;
    }

    /**
     * Set filters
     *
     * @param array $filters
     * @return $this
     */
    public function setFilters(array $filters): self
    {
        $this->config['filters'] = $filters;
        return $this;
    }

    /**
     * Render the table
     *
     * @return string
     */
    public function renderTable(): string
    {
        $this->enqueueAssets();
        
        return $this->render('datatable', [
            'config' => $this->config,
            'items' => $this->items,
            'table_id' => $this->config['id'] ?? 'sikshya-datatable',
        ]);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'id' => 'sikshya-datatable',
            'title' => '',
            'description' => '',
            'columns' => [],
            'actions' => [],
            'bulk_actions' => [],
            'filters' => [],
            'pagination' => true,
            'search' => true,
            'sortable' => true,
            'selectable' => true,
            'responsive' => true,
            'empty_message' => __('No items found.', 'sikshya'),
            'per_page' => 20,
            'per_page_options' => [10, 20, 50, 100],
        ];
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        wp_enqueue_style('sikshya-datatable');
        wp_enqueue_script('sikshya-datatable');
    }
} 