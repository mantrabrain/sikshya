// Course Builder JavaScript - Clean Version with PHP Templates
// No static HTML - everything loaded via AJAX from PHP templates

let lessonCount = 0;
let chapterCount = 0;
let currentContentType = null;
let currentChapterId = null;
let selectedItems = new Set();
let isBulkMode = false;

// AJAX helper function
function sikshyaAjax(action, data, callback) {
    const ajaxData = {
        action: action,
        nonce: sikshya_ajax.nonce,
        ...data
    };

    jQuery.post(sikshya_ajax.ajax_url, ajaxData, function(response) {
        if (response.success) {
            callback(response.data);
        } else {
            console.error('Sikshya AJAX Error:', response.data);
            alert('Error: ' + response.data);
        }
    }).fail(function(xhr, status, error) {
        console.error('Sikshya AJAX Failed:', error);
        alert('Request failed: ' + error);
    });
}

// Tab switching
function switchTab(tabName) {
    // Remove active class from all nav links and content
    document.querySelectorAll('.sikshya-nav-link').forEach(link => {
        link.classList.remove('active');
    });
    document.querySelectorAll('.sikshya-tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // Add active class to clicked tab and corresponding content
    const clickedLink = event.target.closest('.sikshya-nav-link');
    if (clickedLink) {
        clickedLink.classList.add('active');
    }
    
    const targetContent = document.getElementById(tabName);
    if (targetContent) {
        targetContent.classList.add('active');
    }
}

// Pricing toggle
function togglePricing(select) {
    const pricingFields = document.getElementById('pricing-fields');
    if (select.value === 'free') {
        pricingFields.style.display = 'none';
    } else {
        pricingFields.style.display = 'grid';
    }
}

// Bulk Operations
function toggleBulkMode() {
    isBulkMode = !isBulkMode;
    selectedItems.clear();
    
    const bulkActions = document.getElementById('bulk-actions');
    const curriculumContent = document.getElementById('curriculum-content');
    
    if (isBulkMode) {
        // Show bulk actions
        if (!bulkActions) {
            const bulkDiv = document.createElement('div');
            bulkDiv.id = 'bulk-actions';
            bulkDiv.className = 'sikshya-bulk-actions';
            bulkDiv.innerHTML = `
                <div class="sikshya-bulk-info">0 items selected</div>
                <button class="sikshya-btn sikshya-btn-secondary" onclick="bulkMove()">
                    <i class="fas fa-arrows-alt"></i> Move
                </button>
                <button class="sikshya-btn sikshya-btn-secondary" onclick="bulkDuplicate()">
                    <i class="fas fa-copy"></i> Duplicate
                </button>
                <button class="sikshya-btn sikshya-btn-danger" onclick="bulkDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button class="sikshya-btn" onclick="toggleBulkMode()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
            curriculumContent.insertBefore(bulkDiv, curriculumContent.firstChild);
        }
        bulkActions.classList.add('active');
        
        // Add selection checkboxes
        addSelectionCheckboxes();
    } else {
        // Hide bulk actions
        if (bulkActions) {
            bulkActions.classList.remove('active');
        }
        
        // Remove selection checkboxes
        removeSelectionCheckboxes();
    }
}

function addSelectionCheckboxes() {
    const chapters = document.querySelectorAll('.sikshya-chapter');
    const contentItems = document.querySelectorAll('.sikshya-lesson-item');
    
    chapters.forEach(chapter => {
        if (!chapter.querySelector('.sikshya-select-checkbox')) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'sikshya-select-checkbox';
            checkbox.style.cssText = 'margin-right: 8px;';
            checkbox.onchange = function() {
                if (this.checked) {
                    selectedItems.add(chapter.id);
                    chapter.classList.add('selected');
                } else {
                    selectedItems.delete(chapter.id);
                    chapter.classList.remove('selected');
                }
                updateBulkInfo();
            };
            
            const title = chapter.querySelector('.sikshya-chapter-title');
            title.insertBefore(checkbox, title.firstChild);
        }
    });
    
    contentItems.forEach(item => {
        if (!item.querySelector('.sikshya-select-checkbox')) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'sikshya-select-checkbox';
            checkbox.style.cssText = 'margin-right: 8px;';
            checkbox.onchange = function() {
                if (this.checked) {
                    selectedItems.add(item.dataset.id || item.id);
                    item.classList.add('selected');
                } else {
                    selectedItems.delete(item.dataset.id || item.id);
                    item.classList.remove('selected');
                }
                updateBulkInfo();
            };
            
            const title = item.querySelector('.sikshya-lesson-title');
            title.insertBefore(checkbox, title.firstChild);
        }
    });
}

function removeSelectionCheckboxes() {
    const checkboxes = document.querySelectorAll('.sikshya-select-checkbox');
    checkboxes.forEach(checkbox => checkbox.remove());
    
    // Remove selected states
    document.querySelectorAll('.sikshya-chapter.selected, .sikshya-lesson-item.selected').forEach(item => {
        item.classList.remove('selected');
    });
}

function updateBulkInfo() {
    const bulkInfo = document.querySelector('.sikshya-bulk-info');
    if (bulkInfo) {
        bulkInfo.textContent = `${selectedItems.size} item${selectedItems.size !== 1 ? 's' : ''} selected`;
    }
}

function bulkMove() {
    if (selectedItems.size === 0) {
        alert('Please select items to move.');
        return;
    }
    alert(`Move ${selectedItems.size} items functionality will be implemented here.`);
}

function bulkDuplicate() {
    if (selectedItems.size === 0) {
        alert('Please select items to duplicate.');
        return;
    }
    alert(`Duplicate ${selectedItems.size} items functionality will be implemented here.`);
}

function bulkDelete() {
    if (selectedItems.size === 0) {
        alert('Please select items to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedItems.size} selected item${selectedItems.size !== 1 ? 's' : ''}?`)) {
        selectedItems.forEach(itemId => {
            const item = document.getElementById(itemId);
            if (item) {
                item.remove();
                if (item.classList.contains('sikshya-chapter')) {
                    chapterCount--;
                } else {
                    lessonCount--;
                }
            }
        });
        
        selectedItems.clear();
        toggleBulkMode();
        updateProgress();
        
        // Show empty state if no content
        const curriculumContent = document.getElementById('curriculum-content');
        if (curriculumContent.children.length === 0) {
            showEmptyState();
        }
    }
}

// Expandable Forms
function toggleAdvancedForm(button) {
    const form = button.closest('.sikshya-form-section');
    const advancedSection = form.querySelector('.sikshya-form-advanced');
    
    button.classList.toggle('active');
    advancedSection.classList.toggle('active');
}

// Chapter Management
function showChapterModal() {
    sikshyaAjax('sikshya_load_modal_template', {
        modal_type: 'chapter',
        chapter_order: chapterCount + 1
    }, function(data) {
        document.body.insertAdjacentHTML('beforeend', data.html);
        const modal = document.querySelector('.sikshya-modal-overlay');
        
        if (modal) {
        openModal(modal);
        } else {
            console.error('Modal not found after loading template');
        }
    });
}

function saveChapter() {
    const title = document.getElementById('chapter-title').value;
    const description = document.getElementById('chapter-description').value;
    const duration = document.getElementById('chapter-duration').value;
    const order = document.getElementById('chapter-order').value;
    
    if (!title) {
        alert('Please enter a chapter title.');
        return;
    }
    
    sikshyaAjax('sikshya_create_chapter', {
        title: title,
        description: description,
        duration: duration,
        order: order
    }, function(data) {
        // Add chapter to curriculum
        addChapterToCurriculum(data.html, data.chapter_id);
        
        // Close modal
        closeModal(document.querySelector('.sikshya-modal-overlay'));
        
        // Update progress
        updateProgress();
        
        // Clear form
        document.getElementById('chapter-title').value = '';
        document.getElementById('chapter-description').value = '';
        document.getElementById('chapter-duration').value = '';
        document.getElementById('chapter-order').value = chapterCount + 2;
    });
}

function addChapterToCurriculum(html, chapterId) {
    const curriculumContent = document.getElementById('curriculum-content');
    
    // Remove empty state if it exists
    const emptyState = curriculumContent.querySelector('.sikshya-empty-state');
    if (emptyState) {
        emptyState.remove();
    }
    
    // Add chapter HTML
    curriculumContent.insertAdjacentHTML('beforeend', html);
    
    chapterCount++;
}

function toggleChapter(chapterId) {
    const chapter = document.getElementById(chapterId);
    const header = chapter.querySelector('.sikshya-chapter-header');
    const content = chapter.querySelector('.sikshya-chapter-content');
    
    header.classList.toggle('expanded');
    content.classList.toggle('expanded');
}

function addContentToChapter(chapterId) {
    currentChapterId = chapterId;
    showContentTypeModal();
}

function editChapter(chapterId) {
    const chapter = document.getElementById(chapterId);
    const titleElement = chapter.querySelector('.sikshya-chapter-title');
    const currentTitle = titleElement.textContent.trim();
    const currentDescription = chapter.dataset.description || '';
    const currentDuration = chapter.dataset.duration || '';
    const currentOrder = chapter.dataset.order || '';
    
    // Load chapter edit modal
    sikshyaAjax('sikshya_load_modal_template', {
        modal_type: 'chapter',
        chapter_order: currentOrder || chapterCount + 1
    }, function(data) {
        document.body.insertAdjacentHTML('beforeend', data.html);
        const modal = document.querySelector('.sikshya-modal-overlay');
        openModal(modal);
        
        // Populate form with current data
        setTimeout(() => {
            document.getElementById('chapter-title').value = currentTitle;
            document.getElementById('chapter-description').value = currentDescription;
            document.getElementById('chapter-duration').value = currentDuration;
            document.getElementById('chapter-order').value = currentOrder;
            
            // Update modal title and button
            const modalTitle = modal.querySelector('.sikshya-modal-title');
            modalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Chapter';
            
            const modalSubtitle = modal.querySelector('.sikshya-modal-subtitle');
            modalSubtitle.textContent = 'Update your chapter information';
            
            const saveButton = modal.querySelector('.sikshya-modal-footer .sikshya-btn-primary');
            saveButton.textContent = 'Update Chapter';
            saveButton.onclick = function() { updateChapter(chapterId); };
        }, 100);
    });
}

function updateChapter(chapterId) {
    const title = document.getElementById('chapter-title').value;
    const description = document.getElementById('chapter-description').value;
    const duration = document.getElementById('chapter-duration').value;
    const order = document.getElementById('chapter-order').value;
    
    if (!title) {
        alert('Please enter a chapter title.');
        return;
    }
    
    // Update the chapter in the DOM
    const chapter = document.getElementById(chapterId);
    const titleElement = chapter.querySelector('.sikshya-chapter-title');
    
    // Update title (keep the icon)
    const icon = titleElement.querySelector('i');
    titleElement.innerHTML = icon.outerHTML + ' ' + title;
    
    // Update data attributes
    chapter.dataset.description = description;
    chapter.dataset.duration = duration;
    chapter.dataset.order = order;
    
    // Update duration display
    const infoElement = chapter.querySelector('.sikshya-chapter-info');
    const durationSpan = infoElement.querySelector('span:last-child');
    if (duration) {
        if (durationSpan) {
            durationSpan.textContent = duration + ' hours';
        } else {
            const newDurationSpan = document.createElement('span');
            newDurationSpan.textContent = duration + ' hours';
            infoElement.appendChild(newDurationSpan);
        }
    } else if (durationSpan) {
        durationSpan.remove();
    }
    
    // Close modal
    closeModal(document.querySelector('.sikshya-modal-overlay'));
    
    // Show success message
    alert('Chapter updated successfully!');
}

function deleteChapter(chapterId) {
    if (confirm('Are you sure you want to delete this chapter and all its content?')) {
        const chapter = document.getElementById(chapterId);
        chapter.remove();
        chapterCount--;
        
        // Show empty state if no chapters
        const curriculumContent = document.getElementById('curriculum-content');
        if (curriculumContent.children.length === 0) {
            showEmptyState();
        }
        updateProgress();
    }
}

// Content Management
function showContentTypeModal() {
    sikshyaAjax('sikshya_load_modal_template', {
        modal_type: 'content-type'
    }, function(data) {
        document.body.insertAdjacentHTML('beforeend', data.html);
        const modal = document.querySelector('.sikshya-modal-overlay');
        openModal(modal);
    });
}

function selectContentType(type) {
    // Remove previous selection
    document.querySelectorAll('.sikshya-content-type').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Add selection to clicked item
    event.target.closest('.sikshya-content-type').classList.add('selected');
    
    // Enable continue button
    const continueBtn = document.querySelector('.sikshya-modal-footer .sikshya-btn-primary');
    continueBtn.disabled = false;
    
    currentContentType = type;
}

function proceedToContentForm() {
    if (!currentContentType) return;
    
    // Close current modal
    const currentModal = document.querySelector('.sikshya-modal-overlay.active');
    if (currentModal) {
        closeModal(currentModal.querySelector('.sikshya-modal-close'));
    }
    
    // Show advanced content form modal directly
    setTimeout(() => {
        showFullWidthModal(currentContentType);
    }, 300);
}

function showContentFormModal(contentType) {
    // This function is no longer needed - we go directly to advanced editor
    showFullWidthModal(contentType);
}

function showFullWidthModal(contentType) {
    console.log('Loading form for content type:', contentType); // Debug log
    
    sikshyaAjax('sikshya_load_form_template', {
        content_type: contentType
    }, function(data) {
        console.log('Form template loaded successfully:', data); // Debug log
        console.log('HTML content length:', data.html ? data.html.length : 0); // Debug log
        
        // Create modal wrapper
        const modal = document.createElement('div');
        modal.className = 'sikshya-modal-overlay';
        modal.innerHTML = `
            <div class="sikshya-modal sikshya-modal-full">
                <div class="sikshya-modal-header">
                    <button class="sikshya-modal-close" onclick="closeModal(this)">×</button>
                    <h3 class="sikshya-modal-title">
                        <i class="fas fa-edit"></i>
                        Advanced ${contentType.charAt(0).toUpperCase() + contentType.slice(1)} Editor
                    </h3>
                    <p class="sikshya-modal-subtitle">Create detailed ${contentType} with advanced options</p>
                </div>
                <div class="sikshya-modal-body">
                    ${data.html || '<p>No form content loaded</p>'}
                </div>
                <div class="sikshya-modal-footer">
                    <button class="sikshya-btn" onclick="closeModal(this)">Cancel</button>
                    <button class="sikshya-btn sikshya-btn-secondary" onclick="saveAsDraft('${contentType}')">Save as Draft</button>
                    <button class="sikshya-btn sikshya-btn-primary" onclick="saveContent('${contentType}')">Add to Chapter</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        openModal(modal);
        
        console.log('Modal created and opened for:', contentType); // Debug log
    });
}

// Modal management functions
function openModal(modalElement) {
    if (!modalElement) {
        return;
    }
    
    // Prevent body scrolling
    document.body.classList.add('sikshya-modal-open');
    
    // Show modal immediately
        modalElement.classList.add('active');
}

function closeModal(button) {
    const modal = button.closest('.sikshya-modal-overlay');
    
    // Remove active class
    modal.classList.remove('active');
    
    // Re-enable body scrolling after animation
    setTimeout(() => {
        modal.remove();
        document.body.classList.remove('sikshya-modal-open');
    }, 300);
}

// Close modal when clicking overlay (but not modal content)
function handleModalOverlayClick(event) {
    if (event.target.classList.contains('sikshya-modal-overlay')) {
        const closeButton = event.target.querySelector('.sikshya-modal-close');
        if (closeButton) {
            closeModal(closeButton);
        }
    }
}

// Close modal with Escape key
function handleEscapeKey(event) {
    if (event.key === 'Escape') {
        const activeModal = document.querySelector('.sikshya-modal-overlay.active');
        if (activeModal) {
            const closeButton = activeModal.querySelector('.sikshya-modal-close');
            if (closeButton) {
                closeModal(closeButton);
            }
        }
    }
}

// Add event listeners for modal behavior
document.addEventListener('click', handleModalOverlayClick);
document.addEventListener('keydown', handleEscapeKey);

function saveContent(contentType) {
    const formData = getFormData(contentType);
    
    console.log('Saving content with data:', formData); // Debug log
    console.log('Content type:', contentType); // Debug log
    
    if (!formData.title) {
        alert('Please enter a title.');
        console.error('Title is empty!'); // Debug log
        return;
    }
    
    // If no chapter is selected, try to find one or create a default one
    if (!currentChapterId) {
        const existingChapters = document.querySelectorAll('.sikshya-chapter');
        if (existingChapters.length > 0) {
            // Use the first chapter
            currentChapterId = existingChapters[0].id;
            console.log('Auto-selected first chapter:', currentChapterId);
        } else {
            // Create a default chapter first
            console.log('No chapters exist, creating default chapter');
            createDefaultChapterAndAddContent(contentType, formData);
            return;
        }
    }
    
    console.log('Sending AJAX request with title:', formData.title); // Debug log
    console.log('Adding to chapter:', currentChapterId); // Debug log
    
    sikshyaAjax('sikshya_create_content', {
        type: contentType,
        title: formData.title,
        description: formData.description || '',
        duration: formData.duration || ''
    }, function(data) {
        console.log('Content created successfully:', data); // Debug log
        console.log('HTML received:', data.html); // Debug log
        
        // Add content to current chapter
        addContentToChapterContent(data.html, data.content_id);
        
        // Close modal
        closeModal(document.querySelector('.sikshya-modal-overlay'));
        
        // Update progress
        updateProgress();
        
        lessonCount++;
    });
}

function createDefaultChapterAndAddContent(contentType, formData) {
    // Create a default chapter first
    const defaultChapterData = {
        title: 'Chapter 1',
        description: 'Introduction to the course',
        duration: '',
        order: 1
    };
    
    sikshyaAjax('sikshya_create_chapter', defaultChapterData, function(data) {
        console.log('Default chapter created:', data);
        
        // Add chapter to curriculum
        addChapterToCurriculum(data.html, data.chapter_id);
        
        // Set as current chapter
        currentChapterId = data.chapter_id;
        
        // Now create the content
        sikshyaAjax('sikshya_create_content', {
            type: contentType,
            title: formData.title,
            description: formData.description || '',
            duration: formData.duration || ''
        }, function(contentData) {
            console.log('Content created in default chapter:', contentData);
            
            // Add content to the new chapter
            addContentToChapterContent(contentData.html, contentData.content_id);
            
            // Close modal
            closeModal(document.querySelector('.sikshya-modal-overlay'));
            
            // Update progress
            updateProgress();
            
            lessonCount++;
        });
    });
}

function getFormData(contentType) {
    const data = {};
    
    // Get common fields with debugging
    const titleField1 = document.getElementById(contentType + '-lesson-title');
    const titleField2 = document.getElementById(contentType + '-title');
    
    console.log('Looking for title fields:', {
        field1: contentType + '-lesson-title',
        field2: contentType + '-title',
        found1: titleField1,
        found2: titleField2,
        value1: titleField1?.value,
        value2: titleField2?.value
    });
    
    data.title = titleField1?.value || titleField2?.value || '';
    data.description = document.getElementById(contentType + '-lesson-description')?.value || 
                       document.getElementById(contentType + '-description')?.value || '';
    data.duration = document.getElementById(contentType + '-lesson-duration')?.value || 
                    document.getElementById(contentType + '-duration')?.value || '';
    
    console.log('Form data extracted:', data); // Debug log
    
    return data;
}

function addContentToChapterContent(html, contentId) {
    if (!currentChapterId) {
        console.error('No chapter selected for content');
        alert('Please select a chapter first.');
        return;
    }
    
    const chapterContent = document.getElementById('content-' + currentChapterId);
    if (!chapterContent) {
        console.error('Chapter content area not found:', 'content-' + currentChapterId);
        alert('Chapter content area not found. Please try again.');
        return;
    }
    
    const contentInner = chapterContent.querySelector('.sikshya-chapter-content-inner');
    if (!contentInner) {
        console.error('Chapter content inner area not found');
        alert('Chapter content area not properly initialized. Please try again.');
        return;
    }
    
    // Remove empty state if it exists
    const emptyState = contentInner.querySelector('.sikshya-chapter-empty');
    if (emptyState) {
        emptyState.remove();
    }
    
    // Add content HTML
    contentInner.insertAdjacentHTML('beforeend', html);
    
    // Update chapter info
    updateChapterInfo(currentChapterId);
    
    console.log('Content added successfully to chapter:', currentChapterId);
}

function updateChapterInfo(chapterId) {
    const chapter = document.getElementById(chapterId);
    const contentItems = chapter.querySelectorAll('.sikshya-lesson-item');
    const contentCount = contentItems.length;
    
    const infoElement = chapter.querySelector('.sikshya-chapter-info span:first-child');
    if (infoElement) {
        infoElement.textContent = `${contentCount} content item${contentCount !== 1 ? 's' : ''}`;
    }
}

// Content editing with modal
function editContentModal(contentId, contentType) {
    // Get current content data
    const contentItem = document.getElementById(contentId);
    const titleElement = contentItem.querySelector('.sikshya-lesson-title-text');
    const currentTitle = titleElement.textContent.trim();
    const currentDescription = contentItem.dataset.description || '';
    const currentDuration = contentItem.dataset.duration || '';
    
    // Load the edit form template
    sikshyaAjax('sikshya_load_form_template', {
        form_type: 'advanced',
        content_type: contentType
    }, function(data) {
        // Create modal wrapper
        const modal = document.createElement('div');
        modal.className = 'sikshya-modal-overlay';
        modal.innerHTML = `
            <div class="sikshya-modal sikshya-modal-full">
                <div class="sikshya-modal-header">
                    <button class="sikshya-modal-close" onclick="closeModal(this)">×</button>
                    <h3 class="sikshya-modal-title">
                        <i class="fas fa-edit"></i>
                        Edit ${contentType.charAt(0).toUpperCase() + contentType.slice(1)} Content
                    </h3>
                    <p class="sikshya-modal-subtitle">Update your ${contentType} content</p>
                </div>
                <div class="sikshya-modal-body">
                    ${data.html}
                </div>
                <div class="sikshya-modal-footer">
                    <button class="sikshya-btn" onclick="closeModal(this)">Cancel</button>
                    <button class="sikshya-btn sikshya-btn-secondary" onclick="saveAsDraft('${contentType}')">Save as Draft</button>
                    <button class="sikshya-btn sikshya-btn-primary" onclick="updateContent('${contentId}', '${contentType}')">Update Content</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        openModal(modal);
        
        // Populate form with current data
        setTimeout(() => {
            populateEditForm(contentType, currentTitle, currentDescription, currentDuration);
        }, 100);
    });
}

function populateEditForm(contentType, title, description, duration) {
    // Populate title field
    const titleField = document.getElementById(contentType + '-lesson-title') || 
                      document.getElementById(contentType + '-title');
    if (titleField) {
        titleField.value = title;
    }
    
    // Populate description field
    const descField = document.getElementById(contentType + '-lesson-description') || 
                     document.getElementById(contentType + '-description');
    if (descField) {
        descField.value = description;
    }
    
    // Populate duration field
    const durationField = document.getElementById(contentType + '-lesson-duration') || 
                         document.getElementById(contentType + '-duration');
    if (durationField) {
        durationField.value = duration;
    }
}

function updateContent(contentId, contentType) {
    const formData = getFormData(contentType);
    
    if (!formData.title) {
        alert('Please enter a title.');
        return;
    }
    
    // Update the content item in the DOM
    const contentItem = document.getElementById(contentId);
    const titleElement = contentItem.querySelector('.sikshya-lesson-title-text');
    
    // Update title
    titleElement.textContent = formData.title;
    
    // Update data attributes
    contentItem.dataset.description = formData.description || '';
    contentItem.dataset.duration = formData.duration || '';
    
    // Update description display if it exists
    const descElement = contentItem.querySelector('.sikshya-lesson-description');
    if (formData.description) {
        if (descElement) {
            descElement.textContent = formData.description;
        } else {
            const newDescElement = document.createElement('div');
            newDescElement.className = 'sikshya-lesson-description';
            newDescElement.textContent = formData.description;
            contentItem.appendChild(newDescElement);
        }
    } else if (descElement) {
        descElement.remove();
    }
    
    // Update duration display
    const durationElement = contentItem.querySelector('.sikshya-lesson-duration');
    if (formData.duration) {
        if (durationElement) {
            durationElement.textContent = formData.duration + ' min';
        } else {
            const actionsElement = contentItem.querySelector('.sikshya-lesson-actions');
            const newDurationElement = document.createElement('span');
            newDurationElement.className = 'sikshya-lesson-duration';
            newDurationElement.textContent = formData.duration + ' min';
            actionsElement.insertBefore(newDurationElement, actionsElement.firstChild);
        }
    } else if (durationElement) {
        durationElement.remove();
    }
    
    // Close modal
    closeModal(document.querySelector('.sikshya-modal-overlay'));
    
    // Show success message
    alert('Content updated successfully!');
}

function deleteContent(button) {
    if (confirm('Are you sure you want to delete this content?')) {
        const contentItem = button.closest('.sikshya-lesson-item');
        const chapterId = contentItem.closest('.sikshya-chapter').id;
        
        contentItem.remove();
        lessonCount--;
        
        // Update chapter info
        updateChapterInfo(chapterId);
        updateProgress();
        
        // Show empty state if no content in chapter
        const chapterContent = document.getElementById('content-' + chapterId);
        const contentInner = chapterContent.querySelector('.sikshya-chapter-content-inner');
        if (contentInner.children.length === 0) {
            contentInner.innerHTML = `
                <div class="sikshya-chapter-empty">
                    <i class="fas fa-plus-circle"></i>
                    <h4>No content yet</h4>
                    <p>Add your first content item to this chapter</p>
                </div>
            `;
        }
    }
}

function editContent(button) {
    const contentItem = button.closest('.sikshya-lesson-item');
    const contentId = contentItem.id;
    const contentType = contentItem.dataset.type;
    
    // Use the new modal editing function
    editContentModal(contentId, contentType);
}

// Utility Functions
function addLesson() {
    showChapterModal();
}

function showEmptyState() {
    const curriculumContent = document.getElementById('curriculum-content');
    curriculumContent.innerHTML = `
        <div class="sikshya-empty-state">
            <i class="fas fa-play-circle"></i>
            <h3>No chapters added yet</h3>
            <p>Start building your course by adding your first chapter below.</p>
        </div>
    `;
}

function updateProgress() {
    const progressFill = document.getElementById('curriculum-progress');
    const totalItems = chapterCount + lessonCount;
    const progress = totalItems > 0 ? Math.min((totalItems / 10) * 100, 100) : 0;
    progressFill.style.width = progress + '%';
}

function previewCourse() {
    alert('Preview functionality will be implemented here.');
}

function saveDraft() {
    alert('Save draft functionality will be implemented here.');
}

function publishCourse() {
    alert('Publish functionality will be implemented here.');
}

function saveAsDraft(contentType) {
    alert('Save as draft functionality will be implemented here.');
}

// File upload handlers
function handleFileUpload(input, type) {
    const file = input.files[0];
    if (!file) return;
    
    // Show upload progress
    const progressContainer = document.getElementById(type + '-upload-progress');
    const progressFill = document.getElementById(type + '-upload-fill');
    const statusText = document.getElementById(type + '-upload-status');
    
    if (progressContainer) {
        progressContainer.style.display = 'block';
        statusText.textContent = 'Uploading...';
        
        // Simulate upload progress (replace with actual upload logic)
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressFill.style.width = progress + '%';
            
            if (progress >= 100) {
                clearInterval(interval);
                statusText.textContent = 'Upload complete!';
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                }, 2000);
            }
        }, 200);
    }
}

// Video-specific handlers
function handleVideoUpload(input) {
    handleFileUpload(input, 'video');
}

function handleThumbnailUpload(input) {
    handleFileUpload(input, 'thumbnail');
}

function handleCaptionsUpload(input) {
    handleFileUpload(input, 'captions');
}

// Audio-specific handlers
function handleAudioUpload(input) {
    handleFileUpload(input, 'audio');
}

function handleCoverUpload(input) {
    handleFileUpload(input, 'cover');
}

// Text lesson handlers
function handleImagesUpload(input) {
    handleFileUpload(input, 'images');
}

function handleAttachmentsUpload(input) {
    handleFileUpload(input, 'attachments');
}

// Assignment handlers
function handleResourcesUpload(input) {
    handleFileUpload(input, 'resources');
}

function handleSampleUpload(input) {
    handleFileUpload(input, 'sample');
}

// Video source toggle
function toggleVideoSource() {
    const source = document.getElementById('video-lesson-source').value;
    const uploadSection = document.getElementById('video-upload-section');
    const urlSection = document.getElementById('video-url-section');
    
    if (source === 'upload') {
        uploadSection.style.display = 'block';
        urlSection.style.display = 'none';
    } else {
        uploadSection.style.display = 'none';
        urlSection.style.display = 'block';
    }
}

// Audio source toggle
function toggleAudioSource() {
    const source = document.getElementById('audio-lesson-source').value;
    const uploadSection = document.getElementById('audio-upload-section');
    const urlSection = document.getElementById('audio-url-section');
    
    if (source === 'upload') {
        uploadSection.style.display = 'block';
        urlSection.style.display = 'none';
    } else {
        uploadSection.style.display = 'none';
        urlSection.style.display = 'block';
    }
}

// Extract video info from URL
function extractVideoInfo() {
    const url = document.getElementById('video-lesson-url').value;
    const previewSection = document.getElementById('video-preview-section');
    const previewTitle = document.getElementById('video-preview-title');
    
    if (url) {
        previewSection.style.display = 'block';
        previewTitle.textContent = 'Video loaded from: ' + url;
    } else {
        previewSection.style.display = 'none';
    }
}

// Extract audio info from URL
function extractAudioInfo() {
    const url = document.getElementById('audio-lesson-url').value;
    const previewSection = document.getElementById('audio-preview-section');
    const previewTitle = document.getElementById('audio-preview-title');
    
    if (url) {
        previewSection.style.display = 'block';
        previewTitle.textContent = 'Audio loaded from: ' + url;
    } else {
        previewSection.style.display = 'none';
    }
}

// Assignment submission type toggle
function toggleSubmissionOptions() {
    const submissionType = document.getElementById('assignment-lesson-submission-type').value;
    const fileOptions = document.getElementById('file-upload-options');
    
    if (submissionType === 'file' || submissionType === 'multiple') {
        fileOptions.style.display = 'block';
    } else {
        fileOptions.style.display = 'none';
    }
}

// Quiz Builder Tab Management
function switchQuizTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.sikshya-quiz-tab-content');
    tabContents.forEach(content => content.classList.remove('active'));
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.sikshya-quiz-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Show selected tab content
    const selectedContent = document.getElementById(tabName + '-tab');
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Add active class to selected tab
    const selectedTab = document.querySelector(`[data-tab="${tabName}"]`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Update question count if switching to questions tab
    if (tabName === 'questions') {
        updateQuestionCount();
    }
    
    // Update overview if switching to quiz tab
    if (tabName === 'quiz') {
        updateQuizOverview();
    }
}

// Update question count in sidebar
function updateQuestionCount() {
    const container = document.getElementById('quiz-questions-container');
    const questions = container.querySelectorAll('.sikshya-question-item');
    const countElement = document.querySelector('.sikshya-question-count');
    
    if (countElement) {
        countElement.textContent = questions.length;
    }
}

// Update quiz overview statistics
function updateQuizOverview() {
    const container = document.getElementById('quiz-questions-container');
    const questions = container.querySelectorAll('.sikshya-question-item');
    
    let totalPoints = 0;
    questions.forEach(question => {
        const points = parseInt(question.dataset.points) || 1;
        totalPoints += points;
    });
    
    // Update total questions
    const totalQuestionsElement = document.getElementById('total-questions');
    if (totalQuestionsElement) {
        totalQuestionsElement.textContent = questions.length;
    }
    
    // Update total points
    const totalPointsElement = document.getElementById('total-points');
    if (totalPointsElement) {
        totalPointsElement.textContent = totalPoints;
    }
    
    // Update estimated time (rough estimate: 2 minutes per question)
    const estimatedTimeElement = document.getElementById('estimated-time');
    if (estimatedTimeElement) {
        const estimatedMinutes = questions.length * 2;
        estimatedTimeElement.textContent = estimatedMinutes + ' min';
    }
}

// Quiz question management
function addQuestion(questionType) {
    const container = document.getElementById('quiz-questions-container');
    const emptyState = container.querySelector('.sikshya-quiz-empty');
    
    if (emptyState) {
        emptyState.remove();
    }
    
    const questionCount = container.querySelectorAll('.sikshya-question-item').length + 1;
    const questionId = 'question-' + Date.now();
    
    // Get question type display name
    const questionTypeNames = {
        'multiple-choice': 'Multiple Choice',
        'true-false': 'True/False',
        'fill-blank': 'Fill in the Blank',
        'essay': 'Essay',
        'matching': 'Matching'
    };
    
    const displayName = questionTypeNames[questionType] || questionType;
    
    // Create a clean question item without form fields
    const questionHtml = `
        <div class="sikshya-question-item" data-question-id="${questionId}" data-question-type="${questionType}" data-points="1">
            <div class="sikshya-question-header" onclick="toggleQuestion(this)">
                <div class="sikshya-question-info">
                <div class="sikshya-question-number">Q${questionCount}</div>
                    <div class="sikshya-question-type">${displayName}</div>
                    <div class="sikshya-question-title">Click "Edit" to add your question</div>
                    <div class="sikshya-question-meta">
                        <span class="sikshya-question-points" title="Points">
                            <i class="fas fa-star"></i> 1 pt
                        </span>
                        <span class="sikshya-question-status" title="Status">
                            <i class="fas fa-clock"></i> Draft
                        </span>
                        <span class="sikshya-question-difficulty" title="Difficulty">
                            <i class="fas fa-signal"></i> Easy
                        </span>
                    </div>
                </div>
                <div class="sikshya-question-actions">
                    <button class="sikshya-icon-btn" onclick="event.stopPropagation(); editQuestion(this)" title="Edit Question">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="sikshya-icon-btn" onclick="event.stopPropagation(); deleteQuestion(this)" title="Delete Question">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="sikshya-icon-btn sikshya-question-toggle" title="Toggle Question">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
                        <div class="sikshya-question-content collapsed">
                <!-- Edit form will be inserted here when expanded -->
            </div>
                    </div>
    `;
    
    container.insertAdjacentHTML('beforeend', questionHtml);
    
    // Update counts
    updateQuestionCount();
    updateQuizOverview();
    
    // Automatically open the edit modal for the new question
    const newQuestionItem = container.querySelector(`[data-question-id="${questionId}"]`);
    if (newQuestionItem) {
        editQuestion(newQuestionItem.querySelector('.sikshya-icon-btn'));
    }
}

// Global variable to store current editing question
let currentEditingQuestion = null;

function toggleQuestion(header) {
    const questionItem = header.closest('.sikshya-question-item');
    const contentContainer = questionItem.querySelector('.sikshya-question-content');
    const toggleIcon = questionItem.querySelector('.sikshya-question-toggle i');
    
    if (contentContainer.classList.contains('collapsed')) {
        // Expand the question and show edit form directly
        contentContainer.classList.remove('collapsed');
        toggleIcon.className = 'fas fa-chevron-up';
        
        // Show edit form directly in the content area
        if (!currentEditingQuestion || currentEditingQuestion !== questionItem) {
            showQuestionEditForm(questionItem);
        }
    } else {
        // Collapse the question
        contentContainer.classList.add('collapsed');
        toggleIcon.className = 'fas fa-chevron-down';
        
        // If currently editing this question, close the edit mode
        if (currentEditingQuestion === questionItem) {
            closeQuestionEdit();
        }
    }
}

function showQuestionEditForm(questionItem) {
    currentEditingQuestion = questionItem;
    
    // Get question data
    const questionId = questionItem.dataset.questionId;
    const questionType = questionItem.dataset.questionType;
    const points = questionItem.dataset.points || 1;
    const questionText = questionItem.querySelector('.sikshya-question-preview-text')?.textContent || 
                        questionItem.querySelector('.sikshya-question-text')?.textContent || 
                        'Click "Edit" to add your question';
    
    // Create inline edit form
    const editFormHtml = `
        <div class="sikshya-question-edit-form">
            <div class="sikshya-question-edit-header">
                <h4><i class="fas fa-edit"></i> Edit Question</h4>
                <button class="sikshya-icon-btn" onclick="closeQuestionEdit()" title="Cancel Edit">
                    <i class="fas fa-times"></i>
                </button>
                    </div>
            <div class="sikshya-question-edit-content">
                <div class="sikshya-form-row-small">
                    <label>Question Text *</label>
                    <textarea id="edit-question-text" placeholder="Enter your question">${questionText}</textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Question Type</label>
                        <select id="edit-question-type" onchange="updateQuestionType()">
                            <option value="multiple-choice" ${questionType === 'multiple-choice' ? 'selected' : ''}>Multiple Choice</option>
                            <option value="true-false" ${questionType === 'true-false' ? 'selected' : ''}>True/False</option>
                            <option value="fill-blank" ${questionType === 'fill-blank' ? 'selected' : ''}>Fill in the Blank</option>
                            <option value="essay" ${questionType === 'essay' ? 'selected' : ''}>Essay</option>
                            <option value="matching" ${questionType === 'matching' ? 'selected' : ''}>Matching</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Points</label>
                        <input type="number" id="edit-question-points" value="${points}" min="1" max="100">
                    </div>
                </div>
                
                <div id="edit-question-options-container">
                    <!-- Options will be dynamically generated based on question type -->
                </div>
                
                <div id="edit-question-answers-container">
                    <!-- Correct answer selection will be dynamically generated -->
                </div>
                
                <div class="sikshya-question-edit-actions">
                    <button class="sikshya-btn sikshya-btn-secondary" onclick="closeQuestionEdit()">Cancel</button>
                    <button class="sikshya-btn sikshya-btn-primary" onclick="saveQuestionEdit()">Save Question</button>
                </div>
            </div>
        </div>
    `;
    
    // Replace question content with edit form
    const contentContainer = questionItem.querySelector('.sikshya-question-content');
    contentContainer.innerHTML = editFormHtml;
    
    // Generate options based on question type
    updateQuestionType();
    
    // Hide the question header actions during edit
    const headerActions = questionItem.querySelector('.sikshya-question-actions');
    if (headerActions) {
        headerActions.style.display = 'none';
    }
}

function editQuestion(button) {
    const questionItem = button.closest('.sikshya-question-item');
    showQuestionEditForm(questionItem);
}

function closeQuestionEdit() {
    if (!currentEditingQuestion) return;
    
    // Clear the question content (no preview needed)
    const contentContainer = currentEditingQuestion.querySelector('.sikshya-question-content');
    contentContainer.innerHTML = '';
    contentContainer.classList.add('collapsed');
    
    // Show the question header actions again
    const headerActions = currentEditingQuestion.querySelector('.sikshya-question-actions');
    if (headerActions) {
        headerActions.style.display = 'flex';
    }
    
    // Update toggle icon
    const toggleIcon = currentEditingQuestion.querySelector('.sikshya-question-toggle i');
    toggleIcon.className = 'fas fa-chevron-down';
    
    currentEditingQuestion = null;
}

function closeQuestionModal() {
    const modalOverlay = document.getElementById('question-edit-modal-overlay');
    closeModal(modalOverlay);
    currentEditingQuestion = null;
}

function updateQuestionType() {
    const questionType = document.getElementById('edit-question-type').value;
    const optionsContainer = document.getElementById('edit-question-options-container');
    const answersContainer = document.getElementById('edit-question-answers-container');
    
    // Clear containers
    optionsContainer.innerHTML = '';
    answersContainer.innerHTML = '';
    
    switch (questionType) {
        case 'multiple-choice':
            generateMultipleChoiceOptions(optionsContainer, answersContainer);
            break;
        case 'true-false':
            generateTrueFalseOptions(optionsContainer, answersContainer);
            break;
        case 'fill-blank':
            generateFillBlankOptions(optionsContainer, answersContainer);
            break;
        case 'essay':
            generateEssayOptions(optionsContainer, answersContainer);
            break;
        case 'matching':
            generateMatchingOptions(optionsContainer, answersContainer);
            break;
    }
}

function generateMultipleChoiceOptions(optionsContainer, answersContainer) {
    const defaultOptions = ['Option A', 'Option B', 'Option C', 'Option D'];
    
    optionsContainer.innerHTML = `
        <div class="sikshya-form-row-small">
            <label>Options</label>
            <div class="sikshya-question-option-editor">
                ${defaultOptions.map((option, index) => `
                    <div class="sikshya-option-item">
                        <input type="text" value="${option}" placeholder="Enter option text">
                        <input type="radio" name="correct_answer" value="${index}" id="correct_${index}">
                        <label for="correct_${index}">Correct</label>
                        <div class="sikshya-option-actions">
                            <button onclick="removeOption(this)" title="Remove Option">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
                <button class="sikshya-add-option-btn" onclick="addOption()">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>
        </div>
    `;
}

function generateTrueFalseOptions(optionsContainer, answersContainer) {
    optionsContainer.innerHTML = `
        <div class="sikshya-form-row-small">
            <label>Correct Answer</label>
            <div class="sikshya-question-option-editor">
                <div class="sikshya-option-item">
                    <input type="radio" name="correct_answer" value="true" id="correct_true">
                    <label for="correct_true">True</label>
                </div>
                <div class="sikshya-option-item">
                    <input type="radio" name="correct_answer" value="false" id="correct_false">
                    <label for="correct_false">False</label>
                </div>
            </div>
        </div>
    `;
}

function generateFillBlankOptions(optionsContainer, answersContainer) {
    optionsContainer.innerHTML = `
        <div class="sikshya-form-row-small">
            <label>Correct Answer</label>
            <input type="text" id="correct_answer_text" placeholder="Enter the correct answer">
        </div>
    `;
}

function generateEssayOptions(optionsContainer, answersContainer) {
    optionsContainer.innerHTML = `
        <div class="sikshya-form-row-small">
            <label>Essay Question</label>
            <p style="color: #7f8c8d; font-size: 14px; margin: 8px 0;">
                Essay questions require manual grading. Students will provide detailed written responses.
            </p>
        </div>
    `;
}

function generateMatchingOptions(optionsContainer, answersContainer) {
    optionsContainer.innerHTML = `
        <div class="sikshya-form-row-small">
            <label>Matching Pairs</label>
            <div class="sikshya-question-option-editor">
                <div class="sikshya-matching-pairs">
                    <div class="sikshya-matching-pair">
                        <input type="text" placeholder="Left item" class="left-item">
                        <i class="fas fa-arrow-right"></i>
                        <input type="text" placeholder="Right item" class="right-item">
                        <button onclick="removeMatchingPair(this)" title="Remove Pair">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <button class="sikshya-add-option-btn" onclick="addMatchingPair()">
                    <i class="fas fa-plus"></i> Add Matching Pair
                </button>
            </div>
        </div>
    `;
}

function addOption() {
    const optionsContainer = document.querySelector('.sikshya-question-option-editor');
    const optionCount = optionsContainer.querySelectorAll('.sikshya-option-item').length;
    const newOption = `
        <div class="sikshya-option-item">
            <input type="text" placeholder="Enter option text">
            <input type="radio" name="correct_answer" value="${optionCount}" id="correct_${optionCount}">
            <label for="correct_${optionCount}">Correct</label>
            <div class="sikshya-option-actions">
                <button onclick="removeOption(this)" title="Remove Option">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    const addButton = optionsContainer.querySelector('.sikshya-add-option-btn');
    addButton.insertAdjacentHTML('beforebegin', newOption);
}

function removeOption(button) {
    const optionItem = button.closest('.sikshya-option-item');
    optionItem.remove();
    
    // Reindex remaining options
    const options = document.querySelectorAll('.sikshya-option-item');
    options.forEach((option, index) => {
        const radio = option.querySelector('input[type="radio"]');
        const label = option.querySelector('label');
        radio.value = index;
        radio.id = `correct_${index}`;
        label.setAttribute('for', `correct_${index}`);
    });
}

function addMatchingPair() {
    const pairsContainer = document.querySelector('.sikshya-matching-pairs');
    const newPair = `
        <div class="sikshya-matching-pair">
            <input type="text" placeholder="Left item" class="left-item">
            <i class="fas fa-arrow-right"></i>
            <input type="text" placeholder="Right item" class="right-item">
            <button onclick="removeMatchingPair(this)" title="Remove Pair">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    pairsContainer.insertAdjacentHTML('beforeend', newPair);
}

function removeMatchingPair(button) {
    const pair = button.closest('.sikshya-matching-pair');
    pair.remove();
}

function saveQuestionEdit() {
    if (!currentEditingQuestion) return;
    
    const questionText = document.getElementById('edit-question-text').value;
    const questionType = document.getElementById('edit-question-type').value;
    const points = document.getElementById('edit-question-points').value;
    
    // Update question data
    currentEditingQuestion.dataset.points = points;
    currentEditingQuestion.dataset.questionType = questionType;
    
    // Update question type badge
    const typeBadge = currentEditingQuestion.querySelector('.sikshya-question-type');
    const questionTypeNames = {
        'multiple-choice': 'Multiple Choice',
        'true-false': 'True/False',
        'fill-blank': 'Fill in the Blank',
        'essay': 'Essay',
        'matching': 'Matching'
    };
    typeBadge.textContent = questionTypeNames[questionType] || questionType;
    
    // Update question title in header
    const questionTitle = currentEditingQuestion.querySelector('.sikshya-question-title');
    if (questionTitle) {
        questionTitle.textContent = questionText;
    }
    
    // Update metadata
    const pointsElement = currentEditingQuestion.querySelector('.sikshya-question-points');
    if (pointsElement) {
        pointsElement.innerHTML = `<i class="fas fa-star"></i> ${points} pt${points > 1 ? 's' : ''}`;
    }
    
    // Update status to "Complete"
    const statusElement = currentEditingQuestion.querySelector('.sikshya-question-status');
    if (statusElement) {
        statusElement.innerHTML = `<i class="fas fa-check-circle"></i> Complete`;
        statusElement.className = 'sikshya-question-status complete';
    }
    
    // Clear the question content (no preview needed)
    const contentContainer = currentEditingQuestion.querySelector('.sikshya-question-content');
    contentContainer.innerHTML = '';
    contentContainer.classList.add('collapsed');
    
    // Show the question header actions again
    const headerActions = currentEditingQuestion.querySelector('.sikshya-question-actions');
    if (headerActions) {
        headerActions.style.display = 'flex';
    }
    
    // Update toggle icon
    const toggleIcon = currentEditingQuestion.querySelector('.sikshya-question-toggle i');
    toggleIcon.className = 'fas fa-chevron-down';
    
    // Clear current editing question
    currentEditingQuestion = null;
    
    // Update counts
    updateQuestionCount();
    updateQuizOverview();
    
    // Show success message
    showNotification('Question updated successfully', 'success');
}

function updateQuestionDisplay(questionItem, questionType) {
    const contentContainer = questionItem.querySelector('.sikshya-question-content');
    const questionText = questionItem.querySelector('.sikshya-question-text')?.textContent || 
                        questionItem.querySelector('.sikshya-question-preview-text')?.textContent || 
                        'Click "Edit" to add your question';
    
    // Update question title in header
    const questionTitle = questionItem.querySelector('.sikshya-question-title');
    if (questionTitle) {
        questionTitle.textContent = questionText;
    }
    
    // Update question metadata
    const points = questionItem.dataset.points || 1;
    const pointsElement = questionItem.querySelector('.sikshya-question-points');
    if (pointsElement) {
        pointsElement.innerHTML = `<i class="fas fa-star"></i> ${points} pt${points > 1 ? 's' : ''}`;
    }
    
    // Update status to "Complete" if question has content
    const statusElement = questionItem.querySelector('.sikshya-question-status');
    if (statusElement && questionText && questionText !== 'Click "Edit" to add your question') {
        statusElement.innerHTML = `<i class="fas fa-check-circle"></i> Complete`;
        statusElement.className = 'sikshya-question-status complete';
    }
    
    // Get question type display name
    const questionTypeNames = {
        'multiple-choice': 'Multiple Choice',
        'true-false': 'True/False',
        'fill-blank': 'Fill in the Blank',
        'essay': 'Essay',
        'matching': 'Matching'
    };
    
    const displayName = questionTypeNames[questionType] || questionType;
    
    // Create a clean preview without form fields
    let previewContent = '';
    
    switch (questionType) {
        case 'multiple-choice':
            previewContent = `
                <div class="sikshya-question-preview">
                    <span class="sikshya-question-preview-text">${questionText}</span>
                    <div class="sikshya-question-preview-info">
                        <span class="sikshya-question-preview-type">${displayName}</span>
                        <span class="sikshya-question-preview-options">4 options</span>
                    </div>
                </div>
            `;
            break;
        case 'true-false':
            previewContent = `
                <div class="sikshya-question-preview">
                    <span class="sikshya-question-preview-text">${questionText}</span>
                    <div class="sikshya-question-preview-info">
                        <span class="sikshya-question-preview-type">${displayName}</span>
                        <span class="sikshya-question-preview-options">True/False</span>
                    </div>
                </div>
            `;
            break;
        case 'fill-blank':
            previewContent = `
                <div class="sikshya-question-preview">
                    <span class="sikshya-question-preview-text">${questionText}</span>
                    <div class="sikshya-question-preview-info">
                        <span class="sikshya-question-preview-type">${displayName}</span>
                        <span class="sikshya-question-preview-options">Text input</span>
                    </div>
                </div>
            `;
            break;
        case 'essay':
            previewContent = `
                <div class="sikshya-question-preview">
                    <span class="sikshya-question-preview-text">${questionText}</span>
                    <div class="sikshya-question-preview-info">
                        <span class="sikshya-question-preview-type">${displayName}</span>
                        <span class="sikshya-question-preview-options">Essay response</span>
                    </div>
                </div>
            `;
            break;
        case 'matching':
            previewContent = `
                <div class="sikshya-question-preview">
                    <span class="sikshya-question-preview-text">${questionText}</span>
                    <div class="sikshya-question-preview-info">
                        <span class="sikshya-question-preview-type">${displayName}</span>
                        <span class="sikshya-question-preview-options">Matching pairs</span>
                    </div>
                </div>
            `;
            break;
        default:
            previewContent = `
                <div class="sikshya-question-preview">
                    <span class="sikshya-question-preview-text">${questionText}</span>
                    <div class="sikshya-question-preview-info">
                        <span class="sikshya-question-preview-type">${displayName}</span>
                    </div>
                </div>
            `;
    }
    
    contentContainer.innerHTML = previewContent;
}

function deleteQuestion(button) {
    const questionItem = button.closest('.sikshya-question-item');
    questionItem.remove();
    
    // Reorder question numbers
    const container = document.getElementById('quiz-questions-container');
    const questions = container.querySelectorAll('.sikshya-question-item');
    
    if (questions.length === 0) {
        // Show empty state
        container.innerHTML = `
            <div class="sikshya-quiz-empty">
                <i class="fas fa-question-circle"></i>
                <h4>No Questions Added Yet</h4>
                <p>Add your first question to get started</p>
            </div>
        `;
    } else {
        // Update question numbers
        questions.forEach((question, index) => {
            const numberElement = question.querySelector('.sikshya-question-number');
            numberElement.textContent = 'Q' + (index + 1);
        });
    }
    
    // Update counts
    updateQuestionCount();
    updateQuizOverview();
    
    // Show notification
    showNotification('Question deleted successfully', 'success');
}

// Quiz save and preview functions
function saveQuiz() {
    // Collect all quiz data
    const quizData = {
        title: document.getElementById('quiz-lesson-title').value,
        description: document.getElementById('quiz-lesson-description').value,
        duration: document.getElementById('quiz-lesson-duration').value,
        difficulty: document.getElementById('quiz-lesson-difficulty').value,
        type: document.getElementById('quiz-lesson-type').value,
        passing_score: document.getElementById('quiz-lesson-passing').value,
        attempts: document.getElementById('quiz-lesson-attempts').value,
        show_results: document.getElementById('quiz-lesson-results').value,
        randomize: document.getElementById('quiz-lesson-randomize').value,
        show_answers: document.getElementById('quiz-lesson-show-answers').value,
        instructions: document.getElementById('quiz-lesson-instructions').value,
        objectives: document.getElementById('quiz-lesson-objectives').value,
        prerequisites: document.getElementById('quiz-lesson-prerequisites').value,
        due_date: document.getElementById('quiz-lesson-due-date').value,
        available_from: document.getElementById('quiz-lesson-available-from').value,
        available_until: document.getElementById('quiz-lesson-available-until').value,
        password: document.getElementById('quiz-lesson-password').value,
        backtrack: document.getElementById('quiz-lesson-backtrack').value,
        progress_bar: document.getElementById('quiz-lesson-progress').value,
        completion_required: document.getElementById('quiz-lesson-completion').value,
        notifications: document.getElementById('quiz-lesson-notifications').value,
        tags: document.getElementById('quiz-lesson-tags').value,
        questions: collectQuestionsData()
    };
    
    // Validate quiz data
    if (!quizData.title.trim()) {
        showNotification('Quiz title is required', 'error');
        return;
    }
    
    if (quizData.questions.length === 0) {
        showNotification('At least one question is required', 'error');
        return;
    }
    
    // Save quiz (this would typically send to server)
    console.log('Saving quiz:', quizData);
    showNotification('Quiz saved successfully', 'success');
}

function previewQuiz() {
    const quizData = {
        title: document.getElementById('quiz-lesson-title').value || 'Untitled Quiz',
        questions: collectQuestionsData()
    };
    
    // Create preview modal
    const previewModal = document.createElement('div');
    previewModal.className = 'sikshya-modal active';
    previewModal.innerHTML = `
        <div class="sikshya-modal-header">
            <h3 class="sikshya-modal-title">Quiz Preview: ${quizData.title}</h3>
            <button class="sikshya-modal-close" onclick="this.closest('.sikshya-modal').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sikshya-modal-body">
            <div class="sikshya-quiz-preview">
                ${generateQuizPreview(quizData)}
            </div>
        </div>
        <div class="sikshya-modal-footer">
            <button class="sikshya-btn sikshya-btn-secondary" onclick="this.closest('.sikshya-modal').remove()">Close</button>
        </div>
    `;
    
    document.body.appendChild(previewModal);
}

function collectQuestionsData() {
    const questions = [];
    const questionItems = document.querySelectorAll('.sikshya-question-item');
    
    questionItems.forEach((item, index) => {
        const questionData = {
            id: item.dataset.questionId,
            number: index + 1,
            type: item.dataset.questionType,
            points: parseInt(item.dataset.points) || 1,
            text: item.querySelector('.sikshya-question-text').textContent,
            options: [],
            correct_answer: null
        };
        
        // Collect options based on question type
        const options = item.querySelectorAll('.sikshya-question-option');
        options.forEach(option => {
            const label = option.querySelector('label').textContent;
            questionData.options.push(label);
        });
        
        questions.push(questionData);
    });
    
    return questions;
}

function generateQuizPreview(quizData) {
    let preview = `
        <div class="sikshya-quiz-preview-header">
            <h2>${quizData.title}</h2>
            <div class="sikshya-quiz-preview-info">
                <span><i class="fas fa-question-circle"></i> ${quizData.questions.length} Questions</span>
                <span><i class="fas fa-clock"></i> Estimated time: ${quizData.questions.length * 2} minutes</span>
            </div>
        </div>
        <div class="sikshya-quiz-preview-questions">
    `;
    
    quizData.questions.forEach((question, index) => {
        preview += `
            <div class="sikshya-quiz-preview-question">
                <div class="sikshya-question-preview-header">
                    <span class="sikshya-question-preview-number">Question ${index + 1}</span>
                    <span class="sikshya-question-preview-type">${question.type.replace('-', ' ')}</span>
                    <span class="sikshya-question-preview-points">${question.points} point${question.points > 1 ? 's' : ''}</span>
                </div>
                <div class="sikshya-question-preview-text">${question.text}</div>
                ${generateQuestionPreviewOptions(question)}
            </div>
        `;
    });
    
    preview += '</div>';
    return preview;
}

function generateQuestionPreviewOptions(question) {
    switch (question.type) {
        case 'multiple-choice':
            return `
                <div class="sikshya-question-preview-options">
                    ${question.options.map(option => `
                        <div class="sikshya-question-preview-option">
                            <input type="radio" disabled>
                            <label>${option}</label>
                        </div>
                    `).join('')}
                </div>
            `;
        case 'true-false':
            return `
                <div class="sikshya-question-preview-options">
                    <div class="sikshya-question-preview-option">
                        <input type="radio" disabled>
                        <label>True</label>
                    </div>
                    <div class="sikshya-question-preview-option">
                        <input type="radio" disabled>
                        <label>False</label>
                    </div>
                </div>
            `;
        case 'fill-blank':
            return `
                <div class="sikshya-question-preview-input">
                    <input type="text" placeholder="Your answer" disabled>
                </div>
            `;
        case 'essay':
            return `
                <div class="sikshya-question-preview-input">
                    <textarea placeholder="Write your answer here..." disabled></textarea>
                </div>
            `;
        case 'matching':
            return `
                <div class="sikshya-question-preview-matching">
                    <div class="sikshya-matching-preview-left">
                        <div class="sikshya-matching-preview-item">Item 1</div>
                        <div class="sikshya-matching-preview-item">Item 2</div>
                        <div class="sikshya-matching-preview-item">Item 3</div>
                    </div>
                    <div class="sikshya-matching-preview-right">
                        <div class="sikshya-matching-preview-item">Match A</div>
                        <div class="sikshya-matching-preview-item">Match B</div>
                        <div class="sikshya-matching-preview-item">Match C</div>
                    </div>
                </div>
            `;
        default:
            return '';
    }
}

// Notification system
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `sikshya-notification sikshya-notification-${type}`;
    notification.innerHTML = `
        <div class="sikshya-notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="sikshya-notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Initialize quiz builder when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize quiz builder if it exists
    if (document.querySelector('.sikshya-quiz-builder')) {
        updateQuestionCount();
        updateQuizOverview();
    }
}); 