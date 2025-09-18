/**
 * Course Categories Management JavaScript
 */

(function($) {
    'use strict';

    const CategoriesManager = {
        isEditMode: false,
        currentCategoryId: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Edit category buttons
            $(document).on('click', '.sikshya-edit-category', this.showEditForm.bind(this));
            
            // Delete category buttons
            $(document).on('click', '.sikshya-delete-category', this.showDeleteConfirmation.bind(this));
            
            // Save category
            $(document).on('click', '#sikshya-save-category', this.saveCategory.bind(this));
            
            // Cancel edit
            $(document).on('click', '#sikshya-cancel-edit', this.cancelEdit.bind(this));
            
            // Auto-generate slug from name
            $(document).on('input', '#sikshya-category-name', this.generateSlug.bind(this));
            
            // Form submission
            $(document).on('submit', '#sikshya-category-form', this.handleFormSubmit.bind(this));
            
            // Save on Enter key press
            $(document).on('keydown', '#sikshya-category-form input, #sikshya-category-form textarea, #sikshya-category-form select', this.handleEnterKey.bind(this));
        },

        showEditForm: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const categoryId = $btn.data('category-id');
            const categoryName = $btn.data('category-name');
            const categoryDescription = $btn.data('category-description');
            const categorySlug = $btn.data('category-slug');

            this.isEditMode = true;
            this.currentCategoryId = categoryId;
            
            // Populate form
            $('#sikshya-category-term-id').val(categoryId);
            $('#sikshya-category-name').val(categoryName);
            $('#sikshya-category-description').val(categoryDescription);
            $('#sikshya-category-slug').val(categorySlug);
            
            // Update form title and button
            this.updateFormTitle('Edit Category');
            this.updateSaveButton('Update Category');
            this.showCancelButton();
            
            // Focus on name field
            $('#sikshya-category-name').focus();
        },

        cancelEdit: function(e) {
            e.preventDefault();
            this.resetToAddMode();
        },

        resetToAddMode: function() {
            this.isEditMode = false;
            this.currentCategoryId = null;
            this.resetForm();
            this.updateFormTitle('Add New Category');
            this.updateSaveButton('Add Category');
            this.hideCancelButton();
        },

        showDeleteConfirmation: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const categoryId = $btn.data('category-id');
            const categoryName = $btn.data('category-name');

            if (typeof sikshyaConfirm !== 'undefined') {
                sikshyaConfirm(
                    `Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`,
                    {
                        title: 'Delete Category',
                        confirmText: 'Delete',
                        cancelText: 'Cancel'
                    }
                ).then(() => {
                    this.deleteCategory(categoryId);
                }).catch(() => {
                    // User cancelled
                });
            } else {
                if (confirm(`Are you sure you want to delete the category "${categoryName}"?`)) {
                    this.deleteCategory(categoryId);
                }
            }
        },

        saveCategory: function(e) {
            e.preventDefault();
            
            const formData = this.getFormData();
            
            if (!this.validateForm(formData)) {
                return;
            }

            this.setLoading(true);
            
            const action = this.isEditMode ? 'updateCategory' : 'createCategory';
            const ajaxData = {
                action: 'sikshya_categories_action',
                sub_action: action,
                nonce: sikshyaAdmin.nonce,
                ...formData
            };

            $.ajax({
                url: sikshyaAdmin.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    this.setLoading(false);
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.resetToAddMode();
                        this.refreshPage();
                    } else {
                        this.showError(response.data.message || 'An error occurred');
                    }
                },
                error: (xhr, status, error) => {
                    this.setLoading(false);
                    this.showError('Network error occurred');
                }
            });
        },

        deleteCategory: function(categoryId) {
            const ajaxData = {
                action: 'sikshya_categories_action',
                sub_action: 'deleteCategory',
                nonce: sikshyaAdmin.nonce,
                term_id: categoryId
            };

            $.ajax({
                url: sikshyaAdmin.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.refreshPage();
                    } else {
                        this.showError(response.data.message || 'An error occurred');
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Network error occurred');
                }
            });
        },

        getFormData: function() {
            return {
                term_id: $('#sikshya-category-term-id').val(),
                name: $('#sikshya-category-name').val().trim(),
                description: $('#sikshya-category-description').val().trim(),
                slug: $('#sikshya-category-slug').val().trim()
            };
        },

        validateForm: function(data) {
            if (!data.name) {
                this.showError('Category name is required');
                $('#sikshya-category-name').focus();
                return false;
            }
            return true;
        },

        generateSlug: function(e) {
            const name = $(e.target).val();
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            
            $('#sikshya-category-slug').val(slug);
        },

        handleFormSubmit: function(e) {
            e.preventDefault();
            this.saveCategory(e);
        },

        handleEnterKey: function(e) {
            // Save on Enter key press (but not on textarea unless Ctrl+Enter)
            if (e.key === 'Enter') {
                const $target = $(e.target);
                
                // If it's a textarea, only save on Ctrl+Enter
                if ($target.is('textarea')) {
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.saveCategory(e);
                    }
                } else {
                    // For input fields and select, save on Enter
                    e.preventDefault();
                    this.saveCategory(e);
                }
            }
        },

        resetForm: function() {
            $('#sikshya-category-form')[0].reset();
            $('#sikshya-category-term-id').val('');
        },

        updateFormTitle: function(title) {
            $('#sikshya-form-title').text(title);
        },

        updateSaveButton: function(text) {
            $('#sikshya-save-btn-text').text(text);
        },

        showCancelButton: function() {
            $('#sikshya-cancel-edit').show();
        },

        hideCancelButton: function() {
            $('#sikshya-cancel-edit').hide();
        },

        setLoading: function(loading) {
            const $btn = $('#sikshya-save-category');
            const $btnText = $('#sikshya-save-btn-text');
            if (loading) {
                $btn.prop('disabled', true);
                $btnText.text('Saving...');
            } else {
                $btn.prop('disabled', false);
                $btnText.text(this.isEditMode ? 'Update Category' : 'Add Category');
            }
        },

        showSuccess: function(message) {
            if (window.SikshyaToast) {
                SikshyaToast.successMessage(message);
            } else {
                alert(message);
            }
        },

        showError: function(message) {
            if (window.SikshyaToast) {
                SikshyaToast.errorMessage(message);
            } else {
                alert(message);
            }
        },

        refreshPage: function() {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CategoriesManager.init();
    });

    // Make CategoriesManager globally available
    window.SikshyaCategoriesManager = CategoriesManager;

})(jQuery);
