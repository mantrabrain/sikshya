<?php
/**
 * Tab Registry for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TabRegistry
{
    /**
     * Registered tabs
     * 
     * @var array
     */
    private static $tabs = [];
    
    /**
     * Register a tab
     * 
     * @param TabInterface $tab
     * @return void
     */
    public static function registerTab(TabInterface $tab): void
    {
        $tab_id = $tab->getId();
        self::$tabs[$tab_id] = $tab;
    }
    
    /**
     * Get a specific tab
     * 
     * @param string $tab_id
     * @return TabInterface|null
     */
    public static function getTab(string $tab_id): ?TabInterface
    {
        return self::$tabs[$tab_id] ?? null;
    }
    
    /**
     * Get all registered tabs
     * 
     * @return array
     */
    public static function getAllTabs(): array
    {
        return self::$tabs;
    }
    
    /**
     * Get all tabs sorted by order
     * 
     * @return array
     */
    public static function getTabsByOrder(): array
    {
        $tabs = self::$tabs;
        
        // Sort by order
        uasort($tabs, function($a, $b) {
            return $a->getOrder() - $b->getOrder();
        });
        
        return $tabs;
    }
    
    /**
     * Get tab order for navigation
     * 
     * @return array
     */
    public static function getTabOrder(): array
    {
        $tabs = self::getTabsByOrder();
        return array_keys($tabs);
    }
    
    /**
     * Check if a tab exists
     * 
     * @param string $tab_id
     * @return bool
     */
    public static function hasTab(string $tab_id): bool
    {
        return isset(self::$tabs[$tab_id]);
    }
    
    /**
     * Get the first tab (for default active tab)
     * 
     * @return TabInterface|null
     */
    public static function getFirstTab(): ?TabInterface
    {
        $tabs = self::getTabsByOrder();
        return !empty($tabs) ? reset($tabs) : null;
    }
    
    /**
     * Get the first tab ID
     * 
     * @return string|null
     */
    public static function getFirstTabId(): ?string
    {
        $first_tab = self::getFirstTab();
        return $first_tab ? $first_tab->getId() : null;
    }
    
    /**
     * Clear all registered tabs (for testing)
     * 
     * @return void
     */
    public static function clearTabs(): void
    {
        self::$tabs = [];
    }
}
