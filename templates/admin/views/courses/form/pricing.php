<?php
/**
 * Course Pricing Form Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Pricing Tab -->
<div class="sikshya-tab-content <?php echo ($active_tab === 'pricing') ? 'active' : ''; ?>" id="pricing">
    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Pricing</h3>
                <p class="sikshya-section-desc">Set up pricing and access controls for your course</p>
            </div>
        </div>
        
        <div class="sikshya-form-row">
            <label>Course Type</label>
            <select name="course_type" onchange="togglePricing(this)">
                <option value="free">Free Course</option>
                <option value="paid" selected>Paid Course</option>
                <option value="subscription">Subscription Only</option>
            </select>
        </div>

        <div class="sikshya-form-grid" id="pricing-fields">
            <div class="sikshya-form-row">
                <label>Course Price *</label>
                <input type="number" name="price" placeholder="99.99" step="0.01" min="0">
            </div>

            <div class="sikshya-form-row">
                <label>Discount Price</label>
                <input type="number" name="sale_price" placeholder="79.99" step="0.01" min="0">
            </div>
        </div>
    </div>

    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Access & Enrollment</h3>
                <p class="sikshya-section-desc">Control who can enroll and how long they have access</p>
            </div>
        </div>
        
        <div class="sikshya-form-row">
            <label>Enrollment Status</label>
            <select name="enrollment_status">
                <option value="open">Open Enrollment</option>
                <option value="closed">Closed Enrollment</option>
                <option value="invite_only">Invite Only</option>
            </select>
        </div>
        
        <div class="sikshya-form-grid">
            <div class="sikshya-form-row">
                <label>Maximum Students</label>
                <input type="number" name="max_students" placeholder="100" min="1" value="0">
                <p class="sikshya-help-text">Leave empty for unlimited enrollment</p>
            </div>
            
            <div class="sikshya-form-row">
                <label>Course Duration (Days)</label>
                <input type="number" name="course_duration" placeholder="90" min="1" value="0">
                <p class="sikshya-help-text">How long students have access</p>
            </div>
        </div>
    </div>

    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Prerequisites</h3>
                <p class="sikshya-section-desc">What should students know before taking this course?</p>
            </div>
        </div>
        
        <div class="sikshya-repeater" id="prerequisites">
            <div class="sikshya-repeater-item">
                <div class="sikshya-repeater-input">
                    <input type="text" name="prerequisites[]" placeholder="Basic knowledge of...">
                </div>
                <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('prerequisites', 'prerequisites[]', 'Basic knowledge of...')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Add Prerequisite
        </button>
    </div>

    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Course Requirements</h3>
                <p class="sikshya-section-desc">What tools or materials will students need?</p>
            </div>
        </div>
        
        <div class="sikshya-repeater" id="requirements">
            <div class="sikshya-repeater-item">
                <div class="sikshya-repeater-input">
                    <input type="text" name="requirements[]" placeholder="Computer with internet access">
                </div>
                <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('requirements', 'requirements[]', 'Computer with internet access')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Add Requirement
        </button>
    </div>

    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Drip Content</h3>
                <p class="sikshya-section-desc">Release lessons gradually over time</p>
            </div>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_drip" onchange="toggleDripContent(this)">
                <span class="sikshya-checkbox"></span>
                Enable Drip Content
            </label>
            <p class="sikshya-help-text">Release lessons gradually over time</p>
        </div>
        
        <div class="sikshya-form-grid" id="drip-settings" style="display: none;">
            <div class="sikshya-form-row">
                <label>Drip Type</label>
                <select name="drip_type">
                    <option value="interval">Time Interval</option>
                    <option value="completion">After Completion</option>
                    <option value="specific_date">Specific Dates</option>
                </select>
            </div>
            
            <div class="sikshya-form-row">
                <label>Drip Interval (Days)</label>
                <input type="number" name="drip_interval" placeholder="7" min="1" value="0">
            </div>
        </div>
    </div>
</div>

