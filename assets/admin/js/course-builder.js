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
    // Check if sikshya_ajax is available
    if (typeof sikshya_ajax === 'undefined') {
        console.error('Sikshya: sikshya_ajax object is not defined!');
        alert('Sikshya AJAX configuration is missing. Please refresh the page.');
        return;
    }
    
    const ajaxData = {
        action: action,
        nonce: sikshya_ajax.nonce,
        ...data
    };

    console.log('Sikshya AJAX Request:', {
        action: action,
        data: ajaxData,
        url: sikshya_ajax.ajax_url
    });

    jQuery.post(sikshya_ajax.ajax_url, ajaxData, function(response) {
        console.log('Sikshya AJAX Raw Response:', response);
        
        if (response.success) {
            callback(response.data);
        } else {
            console.error('Sikshya AJAX Error:', response.data);
            alert('Error: ' + response.data);
        }
    }).fail(function(xhr, status, error) {
        console.error('Sikshya AJAX Failed:', error);
        console.error('XHR Status:', xhr.status);
        console.error('XHR Response:', xhr.responseText);
        alert('Request failed: ' + error);
    });
}

// Tab switching with URL parameter support
function switchTab(tabName) {
    // Prevent default link behavior to avoid hash
    if (event) {
        event.preventDefault();
    }
    
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

    // Update URL with tab parameter and mark as navigated (remove any hash)
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    url.hash = ''; // Remove any hash fragment
    history.replaceState({navigated: true}, null, url.toString());
}

// Initialize tab from URL parameter on page load
function initializeTabFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabName = urlParams.get('tab');
    
    if (tabName) {
        // Check if the tab exists
        const targetContent = document.getElementById(tabName);
        const targetNavLink = document.querySelector(`[data-tab="${tabName}"]`);
        
        if (targetContent && targetNavLink) {
            // Remove active from all tabs
            document.querySelectorAll('.sikshya-nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelectorAll('.sikshya-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activate the correct tab
            targetNavLink.classList.add('active');
            targetContent.classList.add('active');
            
            console.log('Sikshya: Activated tab from URL:', tabName);
        } else {
            console.log('Sikshya: Tab not found:', tabName);
        }
    } else {
        console.log('Sikshya: No tab parameter in URL, using default');
    }
}

// Handle browser back/forward navigation
function handlePopState() {
    initializeTabFromURL();
}

// Add event listener for popstate (browser back/forward)
window.addEventListener('popstate', handlePopState);

// Initialize tab on page load
document.addEventListener('DOMContentLoaded', function() {
      
    initializeTabFromURL();
    
    // Initialize quiz builder if it exists
    if (document.querySelector('.sikshya-quiz-builder')) {
        updateQuestionCount();
        updateQuizOverview();
    }
    
    // Note: Form fields are populated by PHP, not JavaScript
    if (window.sikshyaCourseBuilder && window.sikshyaCourseBuilder.courseData) {
        console.log('Sikshya: Course data available:', window.sikshyaCourseBuilder.courseData);
    } else {
        console.log('Sikshya: No course data available');
    }

    // Initialize content type modal event handlers
    initializeContentTypeModalHandlers();
    
    // Initialize bulk selection functionality
    initializeBulkSelection();
    
});

// Initialize content type modal event handlers
function initializeContentTypeModalHandlers() {
    // Handle content type card clicks (course builder specific)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.sikshya-content-type-card')) {
            e.preventDefault();
            e.stopPropagation(); // Prevent lessons.js handler from firing
            const contentType = e.target.closest('.sikshya-content-type-card').getAttribute('data-content-type');
            if (contentType) {
                // Show content type selection modal in course builder context
                showContentTypeSelectionModal();
            }
        }
    });

    // Handle content type selection in modal
    document.addEventListener('click', function(e) {
        if (e.target.closest('.sikshya-content-type')) {
            e.preventDefault();
            const contentType = e.target.closest('.sikshya-content-type').getAttribute('data-content-type');
            if (contentType) {
                selectContentType(contentType);
            }
        }
    });

    // Handle modal close button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.sikshya-modal-close')) {
            e.preventDefault();
            closeModal(e.target.closest('.sikshya-modal-close'));
        }
    });

    // Handle modal footer button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.sikshya-modal-footer .sikshya-btn-secondary')) {
            e.preventDefault();
            closeModal(e.target.closest('.sikshya-modal-close'));
        }
        
        if (e.target.closest('.sikshya-modal-footer .sikshya-btn-primary')) {
            e.preventDefault();
            e.stopPropagation(); // Prevent lessons.js handler from firing
            
            const button = e.target.closest('.sikshya-btn-primary');
            if (!button.disabled) {
                // Get selected content type
                const selectedType = document.querySelector('.sikshya-content-type.selected');
                if (selectedType) {
                    const contentType = selectedType.getAttribute('data-content-type');
                    if (contentType) {
                        // Load form in modal instead of redirecting
                        showContentFormModal(contentType);
                    }
                }
            }
        }
    });
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
    const duration = document.getElementById('chapter-duration').value;
    const order = document.getElementById('chapter-order').value;
    
    if (!title) {
        alert('Please enter a chapter title.');
        return;
    }
    
    // Get course ID from the form
    const courseIdField = document.querySelector('input[name="course_id"]');
    const courseId = courseIdField ? parseInt(courseIdField.value) || 0 : 0;
    
    console.log('Sikshya: saveChapter called with courseId:', courseId);
    
    // If course_id is 0, we need to save the course first
    if (courseId === 0) {
        console.log('Sikshya: Course ID is 0, saving course first...');
        
        // Get basic course data from the form
        const courseTitle = document.querySelector('input[name="title"]')?.value || 'New Course';
        const courseDescription = document.querySelector('textarea[name="description"]')?.value || '';
        
        console.log('Sikshya: Course data to save:', { title: courseTitle, description: courseDescription });
        
        // Save course as draft first
        sikshyaAjax('sikshya_course_builder_save', {
            title: courseTitle,
            description: courseDescription,
            course_id: 0,
            course_status: 'draft'
        }, function(data) {
            console.log('Sikshya: Course save response:', data);
            
            if (data && data.course_id) {
                console.log('Sikshya: Course saved successfully with ID:', data.course_id);
                
                // Update the course_id in the form
                if (courseIdField) {
                    courseIdField.value = data.course_id;
                }
                
                // Update URL to include course_id
                const url = new URL(window.location);
                url.searchParams.set('course_id', data.course_id);
                window.history.replaceState({}, '', url.toString());
                
                // Now create the chapter with the new course_id
                createChapterWithCourseId(data.course_id, title, duration, order);
            } else {
                console.error('Sikshya: Failed to save course:', data);
                alert('Failed to save course. Please try again.');
            }
        });
    } else {
        console.log('Sikshya: Course already exists, creating chapter directly with courseId:', courseId);
        // Course already exists, create chapter directly
        createChapterWithCourseId(courseId, title, duration, order);
    }
}

function createChapterWithCourseId(courseId, title, duration, order) {
    console.log('Sikshya: Creating chapter with courseId:', courseId, 'title:', title);
    
    sikshyaAjax('sikshya_create_chapter', {
        title: title,
        duration: duration,
        order: order,
        course_id: courseId
    }, function(data) {
        console.log('Sikshya: Chapter creation response data:', data);
        
        if (data && data.html && data.chapter_id) {
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
        } else {
            console.error('Sikshya: Failed to create chapter:', data);
            alert('Failed to create chapter. Please try again.');
        }
    });
}

function addChapterToCurriculum(html, chapterId) {
    console.log('Adding chapter to curriculum:', chapterId);
    console.log('Chapter HTML:', html);
    
    // Hide empty state and show existing curriculum structure
    showCurriculumItems();
    
    // Try to find curriculum items container
    let curriculumItems = document.getElementById('curriculum-items');
    
    // If curriculum-items doesn't exist, we need to create it
    if (!curriculumItems) {
        console.log('Curriculum items container not found, creating it...');
        
        // Find the curriculum builder container
        const curriculumBuilder = document.querySelector('.sikshya-curriculum-builder');
        if (curriculumBuilder) {
            // Create the curriculum-items container
            curriculumItems = document.createElement('div');
            curriculumItems.id = 'curriculum-items';
            curriculumItems.className = 'sikshya-curriculum-items';
            
            // Insert it into the curriculum builder
            curriculumBuilder.appendChild(curriculumItems);
            console.log('Created curriculum-items container');
        } else {
            console.error('Curriculum builder container not found!');
            return;
        }
    }
    
    console.log('Curriculum items container:', curriculumItems);
    
    if (curriculumItems) {
        // Add chapter HTML (from server response)
        curriculumItems.insertAdjacentHTML('beforeend', html);
        console.log('Chapter added successfully');
        
        // Find the added chapter by the correct ID
        const addedChapter = document.getElementById(chapterId);
        console.log('Added chapter element:', addedChapter);
        
        if (addedChapter) {
            // Update the chapter number immediately
            const numberElement = addedChapter.querySelector('.sikshya-chapter-number');
            if (numberElement) {
                numberElement.textContent = chapterCount + 1;
            }
            
            // Update the data-order attribute
            addedChapter.setAttribute('data-order', chapterCount + 1);
        }
        
        chapterCount++;
        console.log('Chapter count updated:', chapterCount);
        
        // Update all chapter numbers
        updateChapterNumbers();
        
        // Add sortable icon to the new chapter
        addSortableIconsToChapters();
        
        // Make the new chapter draggable - try multiple ways to find it
        let newChapter = addedChapter;
        if (!newChapter) {
            // Try to find the chapter by the last added element
            const chapters = document.querySelectorAll('.sikshya-chapter-card');
            newChapter = chapters[chapters.length - 1];
            console.log('Found new chapter by last element:', newChapter);
        }
        
        if (newChapter) {
            newChapter.draggable = true;
            console.log('Made new chapter draggable:', newChapter.draggable);
            console.log('New chapter ID:', newChapter.id);
            
            // Add mouse down event to test responsiveness
            newChapter.addEventListener('mousedown', function(e) {
                console.log('Chapter mousedown event triggered:', e.target);
            });
        }
    } else {
        console.error('Curriculum items container not found!');
    }
}

function updateChapterNumbers() {
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    chapters.forEach((chapter, index) => {
        const numberElement = chapter.querySelector('.sikshya-chapter-number');
        if (numberElement) {
            numberElement.textContent = index + 1;
        }
        // Update the data-order attribute
        chapter.setAttribute('data-order', index + 1);
    });
}

function deleteContent(contentId) {
    if (confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
        const contentElement = document.getElementById(contentId);
        if (contentElement) {
            const chapterCard = contentElement.closest('.sikshya-chapter-card');
            contentElement.remove();
            
            // Update chapter info
            if (chapterCard) {
                updateChapterInfo(chapterCard.id);
            }
        }
    }
}

// Bulk Selection Functions
function selectAllChapters() {
    const selectAllBtn = document.getElementById('select-all-btn');
    const deleteSelectedBtn = document.getElementById('delete-selected-btn');
    const chapterCheckboxes = document.querySelectorAll('.sikshya-chapter-card .sikshya-checkbox');
    
    const isSelectingAll = selectAllBtn.textContent.includes('Select All');
    
    if (isSelectingAll) {
        // Select all chapters
        chapterCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        selectAllBtn.innerHTML = 
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>' +
            '</svg>' +
            'Deselect All';
        deleteSelectedBtn.style.display = 'inline-flex';
    } else {
        // Deselect all chapters
        chapterCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllBtn.innerHTML = 
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
            '</svg>' +
            'Select All';
        deleteSelectedBtn.style.display = 'none';
    }
}

function deleteSelectedChapters() {
    const selectedChapters = document.querySelectorAll('.sikshya-chapter-card .sikshya-checkbox:checked');
    
    if (selectedChapters.length === 0) {
        sikshyaAlert('Please select chapters to delete.', 'warning');
        return;
    }
    
    // This function is now handled by the bulk delete system
    // which uses the same beautiful alert with option blocks
    bulkDeleteSelected();
}

function toggleChapter(chapterId) {
    const chapter = document.getElementById(chapterId);
    const header = chapter.querySelector('.sikshya-chapter-header');
    const content = chapter.querySelector('.sikshya-chapter-content');
    const toggleBtn = chapter.querySelector('.sikshya-chapter-toggle');
    
    // Toggle expanded state
    const isExpanded = header.classList.contains('expanded');
    
    if (isExpanded) {
        // Collapse
        header.classList.remove('expanded');
        content.classList.remove('expanded');
        if (toggleBtn) toggleBtn.classList.remove('expanded');
    } else {
        // Expand
        header.classList.add('expanded');
        content.classList.add('expanded');
        if (toggleBtn) toggleBtn.classList.add('expanded');
    }
    
    // Update chapter info display
    updateChapterInfo(chapterId);
}



function editChapter(chapterId) {
    console.log('editChapter called with chapterId:', chapterId);
    
    // Extract the numeric ID from the chapter ID (e.g., "chapter-123" -> 123)
    const numericId = chapterId.replace('chapter-', '');
    console.log('Numeric chapter ID:', numericId);
    
    // Load chapter data via AJAX
    sikshyaAjax('sikshya_load_chapter_data', {
        chapter_id: numericId
    }, function(data) {
        console.log('Chapter data loaded:', data);
        
        if (data && data.success) {
            const chapterData = data.data;
            console.log('Chapter data object:', chapterData);
            console.log('Chapter title:', chapterData.title);
            console.log('Chapter duration:', chapterData.duration);
            console.log('Chapter order:', chapterData.order);
            
            // Load the edit modal template
            sikshyaAjax('sikshya_load_modal_template', {
                modal_type: 'edit-chapter'
            }, function(modalData) {
                console.log('Modal template loaded:', modalData);
                
                if (modalData && modalData.html) {
                    // Insert modal HTML
                    document.body.insertAdjacentHTML('beforeend', modalData.html);
                    
                    // Get the modal
        const modal = document.querySelector('.sikshya-modal-overlay');
                    console.log('Modal element found:', modal);
        
                    if (modal) {
                        // Add a small delay to ensure modal is fully rendered
        setTimeout(() => {
                            // Populate form fields with chapter data
                            const titleField = modal.querySelector('input[name="title"]');
                            const descriptionField = modal.querySelector('textarea[name="description"]');
                            const durationField = modal.querySelector('input[name="duration"]');
                            const orderField = modal.querySelector('input[name="order"]');
                            
                            console.log('Form fields found:', {
                                titleField: titleField,
                                descriptionField: descriptionField,
                                durationField: durationField,
                                orderField: orderField
                            });
                            
                            if (titleField) {
                                titleField.value = chapterData.title || '';
                                console.log('Title field populated with:', titleField.value);
                            }
                            if (durationField) {
                                durationField.value = chapterData.duration || '';
                                console.log('Duration field populated with:', durationField.value);
                            }
                            if (orderField) {
                                orderField.value = chapterData.order || '';
                                console.log('Order field populated with:', orderField.value);
                            }
                            
                            // Store chapter ID for save operation
                            const chapterIdField = modal.querySelector('input[name="chapter_id"]');
                            if (chapterIdField) {
                                chapterIdField.value = numericId;
                                console.log('Chapter ID field populated with:', chapterIdField.value);
                            }
                            
                            // Set up save button handler
                            const saveButton = modal.querySelector('.sikshya-btn-primary');
                            if (saveButton) {
                                saveButton.onclick = function() {
                                    updateChapter(numericId);
                                };
                                console.log('Save button handler set up');
                            } else {
                                console.error('Save button not found in modal');
                            }
                        }, 100); // 100ms delay
                        
                        // Open the modal
                        openModal(modal);
                        console.log('Modal opened successfully');
                    } else {
                        console.error('Modal element not found after insertion');
                    }
                } else {
                    console.error('Failed to load edit chapter modal template:', modalData);
                }
            });
        } else {
            console.error('Failed to load chapter data:', data);
            alert('Failed to load chapter data. Please try again.');
        }
    });
}

function updateChapter(chapterId) {
    console.log('updateChapter called with chapterId:', chapterId);
    
    // Get form data from the modal
    const modal = document.querySelector('.sikshya-modal-overlay');
    if (!modal) {
        console.error('Modal not found');
        return;
    }
    
    const title = modal.querySelector('input[name="title"]')?.value?.trim();
    const duration = modal.querySelector('input[name="duration"]')?.value?.trim();
    const order = modal.querySelector('input[name="order"]')?.value?.trim();
    
    console.log('Form data:', { title, duration, order });
    
    if (!title) {
        alert('Please enter a chapter title.');
        return;
    }
    
    // Send update request via AJAX
    sikshyaAjax('sikshya_update_chapter', {
        chapter_id: chapterId,
        title: title,
        duration: duration,
        order: order
    }, function(data) {
        console.log('Chapter update response:', data);
        
        if (data && data.success) {
    // Close modal
            closeModal(modal);
            
            // Reload the curriculum tab to show updated data
            const curriculumTab = document.querySelector('[data-tab="curriculum"]');
            if (curriculumTab) {
                curriculumTab.click();
            }
    
    // Show success message
    alert('Chapter updated successfully!');
        } else {
            console.error('Failed to update chapter:', data);
            alert('Failed to update chapter. Please try again.');
        }
    });
}

function deleteChapter(chapterId) {
    // Get chapter info for the message
    const chapterElement = document.getElementById(chapterId);
    const chapterTitle = chapterElement ? chapterElement.querySelector('.sikshya-chapter-title')?.textContent || 'this chapter' : 'this chapter';
    
    const message = `You are about to delete <strong>${chapterTitle}</strong>.<br><br>Choose deletion option:`;
    
    // Create custom confirmation with clickable option blocks
    const customHTML = `
        <div class="sikshya-delete-options">
            <div class="sikshya-delete-option" data-option="chapter-only">
                <input type="radio" name="delete-option" value="chapter-only" checked>
                <div class="sikshya-delete-option-title">Delete Chapter Only</div>
                <div class="sikshya-delete-option-description">Move all content to the next chapter. If no next chapter exists, content will be moved to the previous chapter.</div>
            </div>
            
            <div class="sikshya-delete-option selected" data-option="chapter-content">
                <input type="radio" name="delete-option" value="chapter-content">
                <div class="sikshya-delete-option-title">Delete Chapter + All Content</div>
                <div class="sikshya-delete-option-description">Permanently delete the chapter and all its content. This action cannot be undone.</div>
            </div>
        </div>
    `;
    
    // Store chapter ID for later use
    window.pendingChapterDelete = {
        chapterId: chapterId
    };
    
    // Show custom confirmation
    SikshyaAlert.show({
        type: 'confirm',
        title: 'Delete Chapter',
        message: message + customHTML,
        showCloseButton: false,
        className: 'chapter-delete-confirmation',
        buttons: [
            {
                text: 'Cancel',
                type: 'secondary',
                action: 'cancel'
            },
            {
                text: 'Delete Chapter',
                type: 'danger',
                action: 'confirm',
                callback: function() {
                    confirmIndividualChapterDeletion();
                    return true;
                }
            }
        ]
    });
    
    // Add event listeners for clickable option blocks
    setTimeout(() => {
        const optionBlocks = document.querySelectorAll('.sikshya-delete-option');
        optionBlocks.forEach(block => {
            block.addEventListener('click', function() {
                // Remove selected class from all options
                optionBlocks.forEach(opt => opt.classList.remove('selected'));
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Update the radio button
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
            });
        });
    }, 100);
}

/**
 * Confirm individual chapter deletion based on selected option
 */
function confirmIndividualChapterDeletion() {
    const selectedOption = document.querySelector('input[name="delete-option"]:checked');
    const deleteContent = selectedOption ? selectedOption.value === 'chapter-content' : true;
    
    if (!window.pendingChapterDelete) {
        sikshyaAlert('Error: Chapter deletion data not found.', 'error');
        return;
    }
    
    const chapterId = window.pendingChapterDelete.chapterId;
    const chapter = document.getElementById(chapterId);
    
    if (!chapter) {
        sikshyaAlert('Error: Chapter not found.', 'error');
        return;
    }
    
    if (deleteContent) {
        // Delete chapter and all content
        chapter.remove();
        chapterCount--;
        
        // Show empty state if no chapters
        const curriculumItems = document.getElementById('curriculum-items');
        if (curriculumItems && curriculumItems.children.length === 0) {
            showEmptyState();
        }
        updateProgress();
        
        sikshyaAlert('Chapter and all content deleted successfully.', 'success');
    } else {
        // Delete chapter only - move content to next/previous chapter
        const nextChapter = chapter.nextElementSibling;
        const previousChapter = chapter.previousElementSibling;
        const targetChapter = nextChapter || previousChapter;
        
        if (targetChapter) {
            // Move all lessons to target chapter
            const lessons = chapter.querySelectorAll('.sikshya-lesson-item');
            const targetLessonsContainer = targetChapter.querySelector('.sikshya-chapter-lessons');
            
            if (targetLessonsContainer) {
                lessons.forEach(lesson => {
                    targetLessonsContainer.appendChild(lesson);
                });
            }
        }
        
        // Remove the chapter
        chapter.remove();
        chapterCount--;
        
        // Show empty state if no chapters
        const curriculumItems = document.getElementById('curriculum-items');
        if (curriculumItems && curriculumItems.children.length === 0) {
            showEmptyState();
        }
        updateProgress();
        
        sikshyaAlert('Chapter deleted successfully. Content moved to adjacent chapter.', 'success');
    }
    
    // Clear pending data
    window.pendingChapterDelete = null;
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

// Alias for course builder context
function showContentTypeSelectionModal() {
    showContentTypeModal();
}

function selectContentType(type) {
    console.log('selectContentType called with type:', type); // Debug log
    
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
    console.log('currentContentType set to:', currentContentType); // Debug log
}

// Content type information for modal headers
function getContentTypeInfo(contentType) {
    const contentTypes = {
        'text': {
            title: 'Create Text Lesson',
            subtitle: 'Add rich text content with formatting and media',
            icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
        },
        'video': {
            title: 'Create Video Lesson',
            subtitle: 'Upload video content with descriptions and transcripts',
            icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>'
        },
        'audio': {
            title: 'Create Audio Lesson',
            subtitle: 'Add audio content with transcripts and notes',
            icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>'
        },
        'quiz': {
            title: 'Create Quiz',
            subtitle: 'Build interactive assessments with questions and answers',
            icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        },
        'assignment': {
            title: 'Create Assignment',
            subtitle: 'Set up student submissions and project requirements',
            icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>'
        }
    };
    
    return contentTypes[contentType] || {
        title: `Create ${contentType.charAt(0).toUpperCase() + contentType.slice(1)}`,
        subtitle: `Add ${contentType} content to your course`,
        icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>'
    };
}

function proceedToContentForm() {
    console.log('proceedToContentForm called, currentContentType:', currentContentType); // Debug log
    
    if (!currentContentType) {
        console.error('No content type selected!'); // Debug log
        return;
    }
    
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
        
        // Create modal wrapper with improved design
        const modal = document.createElement('div');
        modal.className = 'sikshya-modal-overlay';
        
        // Get content type display name and icon
        const contentTypeInfo = getContentTypeInfo(contentType);
        
        modal.innerHTML = `
            <div class="sikshya-modal sikshya-modal-extra-large">
                <div class="sikshya-modal-header">
                    <button class="sikshya-modal-close" onclick="closeModal(this)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <div class="sikshya-modal-header-content">
                        <div class="sikshya-modal-title-wrapper">
                            <div class="sikshya-modal-icon">
                                ${contentTypeInfo.icon}
                            </div>
                            <h3 class="sikshya-modal-title">${contentTypeInfo.title}</h3>
                        </div>
                        <p class="sikshya-modal-subtitle">${contentTypeInfo.subtitle}</p>
                    </div>
                </div>
                <div class="sikshya-modal-body">
                    ${data.html || '<p>No form content loaded</p>'}
                </div>
                <div class="sikshya-modal-footer">
                    <button class="sikshya-btn sikshya-btn-secondary" onclick="closeModal(this)">Cancel</button>
                    <button class="sikshya-btn sikshya-btn-secondary" onclick="saveAsDraft('${contentType}')">Save as Draft</button>
                    <button class="sikshya-btn sikshya-btn-primary" onclick="saveContent('${contentType}')">Add to Chapter</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        openModal(modal);
        
        // Initialize tab functionality for lesson forms
        initializeLessonFormTabs(modal);
        
        console.log('Modal created and opened for:', contentType); // Debug log
    });
}

// Initialize lesson form tabs
function initializeLessonFormTabs(modalElement) {
    console.log('Initializing lesson form tabs for modal:', modalElement); // Debug log
    
    // Set course_id automatically for lesson forms in course builder
    const courseId = window.sikshyaCourseBuilder?.courseId;
    if (courseId) {
        // Add hidden course_id field to all lesson forms
        const lessonForms = modalElement.querySelectorAll('form');
        lessonForms.forEach(form => {
            // Check if course_id field already exists
            let courseIdField = form.querySelector('input[name="course_id"]');
            if (!courseIdField) {
                // Create hidden course_id field
                courseIdField = document.createElement('input');
                courseIdField.type = 'hidden';
                courseIdField.name = 'course_id';
                courseIdField.value = courseId;
                form.appendChild(courseIdField);
            } else {
                // Update existing course_id field
                courseIdField.value = courseId;
            }
        });
        
        // Listen for lesson save events from lessons.js
        initializeLessonEventListener();
    }
    
    // Handle tab button clicks
    modalElement.addEventListener('click', function(e) {
        if (e.target.closest('.sikshya-tab-btn')) {
            console.log('Tab button clicked in course builder modal'); // Debug log
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            
            const tabBtn = e.target.closest('.sikshya-tab-btn');
            const tabId = tabBtn.getAttribute('data-tab');
            
            console.log('Tab ID:', tabId); // Debug log
            
            if (tabId) {
                // Remove active class from all tabs
                modalElement.querySelectorAll('.sikshya-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked tab
                tabBtn.classList.add('active');
                
                // Hide all tab panels
                modalElement.querySelectorAll('.sikshya-tab-panel').forEach(panel => {
                    panel.classList.remove('active');
                });
                
                // Show selected tab panel using ID (matching lessons.js approach)
                const targetPanel = modalElement.querySelector('#' + tabId);
                console.log('Target panel found:', targetPanel); // Debug log
                
                if (targetPanel) {
                    targetPanel.classList.add('active');
                    console.log('Added active class to panel:', tabId); // Debug log
                } else {
                    console.error('Target panel not found for ID:', tabId); // Debug log
                }
            }
        }
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
    
    // Use the save handler from course-builder-save.js
    if (window.SikshyaCourseBuilderSave) {
        window.SikshyaCourseBuilderSave.saveContent(contentType, formData, function(success, response) {
            if (success) {
        // Close modal
        closeModal(document.querySelector('.sikshya-modal-overlay'));
        
        // Update progress
        updateProgress();
        
        lessonCount++;
            } else {
                console.error('Failed to save content:', response);
                alert('Failed to save content. Please try again.');
            }
    });
    } else {
        console.error('SikshyaCourseBuilderSave not available');
        alert('Save handler not available. Please refresh the page.');
    }
}

function createDefaultChapterAndAddContent(contentType, formData) {
    // Get course ID from the form
    const courseIdField = document.querySelector('input[name="course_id"]');
    const courseId = courseIdField ? parseInt(courseIdField.value) || 0 : 0;
    
    // If course_id is 0, we need to save the course first
    if (courseId === 0) {
        // Get basic course data from the form
        const courseTitle = document.querySelector('input[name="title"]')?.value || 'New Course';
        const courseDescription = document.querySelector('textarea[name="description"]')?.value || '';
        
        // Save course as draft first
        sikshyaAjax('sikshya_course_builder_save', {
            title: courseTitle,
            description: courseDescription,
            course_id: 0,
            course_status: 'draft'
        }, function(data) {
            console.log('Course save response:', data);
            
            if (data && data.course_id) {
                // Update the course_id in the form
                if (courseIdField) {
                    courseIdField.value = data.course_id;
                }
                
                // Update URL to include course_id
                const url = new URL(window.location);
                url.searchParams.set('course_id', data.course_id);
                window.history.replaceState({}, '', url.toString());
                
                // Now create the default chapter and content with the new course_id
                createDefaultChapterAndContentWithCourseId(data.course_id, contentType, formData);
            } else {
                console.error('Failed to save course:', data);
                alert('Failed to save course. Please try again.');
            }
        });
    } else {
        // Course already exists, create default chapter and content directly
        createDefaultChapterAndContentWithCourseId(courseId, contentType, formData);
    }
}

function createDefaultChapterAndContentWithCourseId(courseId, contentType, formData) {
    // Create a default chapter first
    const defaultChapterData = {
        title: 'Chapter 1',
        description: 'Introduction to the course',
        duration: '',
        order: 1,
        course_id: courseId
    };
    
    sikshyaAjax('sikshya_create_chapter', defaultChapterData, function(data) {
        console.log('Default chapter created:', data);
        
        // Set as current chapter
        currentChapterId = data.chapter_id;
        
        // Now create the content using the save handler
        if (window.SikshyaCourseBuilderSave) {
            window.SikshyaCourseBuilderSave.saveContent(contentType, formData, function(success, response) {
                if (success) {
            // Close modal
            closeModal(document.querySelector('.sikshya-modal-overlay'));
            
            // Update progress
            updateProgress();
            
            lessonCount++;
                } else {
                    console.error('Failed to save content:', response);
                    alert('Failed to save content. Please try again.');
                }
        });
        } else {
            console.error('SikshyaCourseBuilderSave not available');
            alert('Save handler not available. Please refresh the page.');
        }
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
    
    // Get additional fields based on content type
    if (contentType === 'text') {
        data.content = document.getElementById('text-lesson-content')?.value || '';
        data.objectives = document.getElementById('text-lesson-objectives')?.value || '';
        data.takeaways = document.getElementById('text-lesson-takeaways')?.value || '';
        data.resources = document.getElementById('text-lesson-resources')?.value || '';
        data.difficulty = document.getElementById('text-lesson-difficulty')?.value || 'beginner';
        data.completion = document.getElementById('text-lesson-completion')?.value || 'yes';
        data.comments = document.getElementById('text-lesson-comments')?.value || 'yes';
        data.progress = document.getElementById('text-lesson-progress')?.value || 'yes';
        data.print = document.getElementById('text-lesson-print')?.value || 'yes';
        data.prerequisites = document.getElementById('text-lesson-prerequisites')?.value || '';
        data.tags = document.getElementById('text-lesson-tags')?.value || '';
        data.seo = document.getElementById('text-lesson-seo')?.value || '';
        data.format = document.getElementById('text-lesson-format')?.value || 'article';
        data.reading_level = document.getElementById('text-lesson-reading-level')?.value || 'basic';
        data.word_count = document.getElementById('text-lesson-word-count')?.value || '';
        data.language = document.getElementById('text-lesson-language')?.value || 'en';
        data.toc = document.getElementById('text-lesson-toc')?.value || 'auto';
        data.search = document.getElementById('text-lesson-search')?.value || 'yes';
        data.related = document.getElementById('text-lesson-related')?.value || '';
    }
    
    console.log('Form data extracted:', data); // Debug log
    
    return data;
}

function addContentToChapterContent(html, contentId) {
    console.log('addContentToChapterContent called with HTML:', html); // Debug log
    console.log('addContentToChapterContent called with contentId:', contentId); // Debug log
    
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
    
    // Find the lesson list container
    const lessonList = contentInner.querySelector('.sikshya-lesson-list');
    if (lessonList) {
        // Add content HTML to the lesson list
        lessonList.insertAdjacentHTML('beforeend', html);
        console.log('Content added to lesson list');
    } else {
        // Fallback: add to the content inner if lesson list not found
        const addContentButton = contentInner.querySelector('.sikshya-add-lesson');
        if (addContentButton) {
            addContentButton.insertAdjacentHTML('beforebegin', html);
        } else {
            // Fallback: add to the end if button not found
            contentInner.insertAdjacentHTML('beforeend', html);
        }
        console.log('Content added to fallback location');
    }
    
    // Update chapter info
    updateChapterInfo(currentChapterId);
    
    console.log('Content added successfully to chapter:', currentChapterId);
}

function updateChapterInfo(chapterId) {
    const chapter = document.getElementById(chapterId);
    if (!chapter) return;
    
    // Count different types of content
    const allContent = chapter.querySelectorAll('.sikshya-lesson-card');
    let lessonCount = 0;
    let quizCount = 0;
    let assignmentCount = 0;
    
    allContent.forEach(item => {
        const contentType = item.getAttribute('data-type');
        switch(contentType) {
            case 'text':
            case 'video':
            case 'audio':
            case 'lesson':
                lessonCount++;
                break;
            case 'quiz':
                quizCount++;
                break;
            case 'assignment':
                assignmentCount++;
                break;
            default:
                lessonCount++; // Default to lesson
        }
    });
    
    console.log('Chapter content counts:', { chapterId, lessonCount, quizCount, assignmentCount });
    
    // Update chapter content summary in header
    updateChapterContentSummary(chapter, lessonCount, quizCount, assignmentCount);
    
    // Show/hide empty state
    const emptyState = chapter.querySelector('.sikshya-chapter-empty');
    const contentInner = chapter.querySelector('.sikshya-chapter-content-inner');
    const lessonList = contentInner ? contentInner.querySelector('.sikshya-lesson-list') : null;
    
    if (lessonList && lessonList.children.length > 0) {
        if (emptyState) emptyState.style.display = 'none';
    } else {
        if (emptyState) emptyState.style.display = 'block';
    }
}

function updateChapterContentSummary(chapter, lessonCount, quizCount, assignmentCount) {
    // Create or update content summary in chapter header
    let summaryElement = chapter.querySelector('.sikshya-chapter-content-summary');
    
    if (!summaryElement) {
        // Create summary element if it doesn't exist
        const chapterMain = chapter.querySelector('.sikshya-chapter-main');
        if (chapterMain) {
            summaryElement = document.createElement('div');
            summaryElement.className = 'sikshya-chapter-content-summary';
            chapterMain.appendChild(summaryElement);
        }
    }
    
    // Calculate total content
    const totalContent = lessonCount + quizCount + assignmentCount;
    
    if (summaryElement) {
        // Always show badges, even with zero counts
        summaryElement.innerHTML = `
            <div class="sikshya-chapter-meta">
                <span class="sikshya-chapter-lessons"><span class="lesson-count">${lessonCount}</span> lessons</span>
                <span class="sikshya-chapter-quizzes"><span class="quiz-count">${quizCount}</span> quizzes</span>
                <span class="sikshya-chapter-assignments"><span class="assignment-count">${assignmentCount}</span> assignments</span>
            </div>
        `;
    }
    
    // Show/hide empty state
    const emptyState = chapter.querySelector('.sikshya-chapter-empty');
    const contentInner = chapter.querySelector('.sikshya-chapter-content-inner');
    const lessonList = contentInner ? contentInner.querySelector('.sikshya-lesson-list') : null;
    
    if (lessonList && lessonList.children.length > 0) {
        if (emptyState) emptyState.style.display = 'none';
    } else {
        if (emptyState) emptyState.style.display = 'block';
    }
    
    console.log('Chapter updated:', { lessonCount, quizCount, assignmentCount, totalContent });
}

// Content editing with modal
function editContentModal(contentId, contentType) {
    // Get current content data
    const contentItem = document.getElementById(contentId);
    const titleElement = contentItem.querySelector('.sikshya-lesson-title');
    const currentTitle = titleElement ? titleElement.textContent.trim() : '';
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
        // Get content type icon
        const contentTypeIcons = {
            'text': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'video': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
            'audio': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>',
            'quiz': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'assignment': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>'
        };
        
        const contentTypeIcon = contentTypeIcons[contentType] || contentTypeIcons['text'];
        
        modal.innerHTML = 
            '<div class="sikshya-modal sikshya-modal-full">' +
                '<div class="sikshya-modal-header">' +
                    '<button class="sikshya-modal-close" onclick="closeModal(this)">' +
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                    '<div class="sikshya-modal-header-content">' +
                        '<div class="sikshya-modal-title-wrapper">' +
                            '<div class="sikshya-modal-icon">' + contentTypeIcon + '</div>' +
                            '<h3 class="sikshya-modal-title">Edit ' + contentType.charAt(0).toUpperCase() + contentType.slice(1) + ' Content</h3>' +
                        '</div>' +
                        '<p class="sikshya-modal-subtitle">Update your ' + contentType + ' content details</p>' +
                    '</div>' +
                '</div>' +
                '<div class="sikshya-modal-body">' +
                    data.html +
                '</div>' +
                '<div class="sikshya-modal-footer">' +
                    '<button class="sikshya-btn" onclick="closeModal(this)">Cancel</button>' +
                    '<button class="sikshya-btn sikshya-btn-secondary" onclick="saveAsDraft(\'' + contentType + '\')">Save as Draft</button>' +
                    '<button class="sikshya-btn sikshya-btn-primary" onclick="updateContent(\'' + contentId + '\', \'' + contentType + '\')">Update Content</button>' +
                '</div>' +
            '</div>';
        
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
    const titleElement = contentItem.querySelector('.sikshya-lesson-title');
    
    // Update title
    if (titleElement) {
        titleElement.textContent = formData.title;
    }
    
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
function addContent(chapterId) {
    if (chapterId) {
        currentChapterId = chapterId;
        showContentTypeModal();
    } else {
        showChapterModal();
    }
}

// Simple UI State Management
function showCurriculumItems() {
    console.log('Showing curriculum items...');
    
    const emptyState = document.getElementById('curriculum-empty-state');
    let curriculumItems = document.getElementById('curriculum-items');
    const bulkActions = document.getElementById('bulk-actions');
    
    console.log('Empty state element:', emptyState);
    console.log('Curriculum items element:', curriculumItems);
    
    if (emptyState) {
        emptyState.style.display = 'none';
        console.log('Hidden empty state');
    }
    
    // If curriculum-items doesn't exist, create it
    if (!curriculumItems) {
        console.log('Curriculum items container not found, creating it...');
        const curriculumBuilder = document.querySelector('.sikshya-curriculum-builder');
        if (curriculumBuilder) {
            curriculumItems = document.createElement('div');
            curriculumItems.id = 'curriculum-items';
            curriculumItems.className = 'sikshya-curriculum-items';
            curriculumBuilder.appendChild(curriculumItems);
            console.log('Created curriculum-items container');
        }
    }
    
    if (curriculumItems) {
        curriculumItems.style.display = 'block';
        console.log('Showed curriculum items');
    }
    
    // Show bulk actions when content exists
    if (bulkActions) {
        bulkActions.style.display = 'flex';
        console.log('Showed bulk actions');
    }
}

function showEmptyState() {
    const emptyState = document.getElementById('curriculum-empty-state');
    const curriculumItems = document.getElementById('curriculum-items');
    const bulkActions = document.getElementById('bulk-actions');
    
    if (emptyState) {
        emptyState.style.display = 'block';
    }
    if (curriculumItems) {
        curriculumItems.style.display = 'none';
        curriculumItems.innerHTML = '';
    }
    
    // Hide bulk actions when no content
    if (bulkActions) {
        bulkActions.style.display = 'none';
    }
    
    // Reset counters
    chapterCount = 0;
    lessonCount = 0;
}

function updateProgress() {
    const progressFill = document.getElementById('curriculum-progress');
    const totalItems = chapterCount + lessonCount;
    const progress = totalItems > 0 ? Math.min((totalItems / 10) * 100, 100) : 0;
    if (progressFill) {
        progressFill.style.width = progress + '%';
    }
}





function previewCourse() {
    alert('Preview functionality will be implemented here.');
}

// Form submission functions
function saveDraft() {
    document.getElementById('course-status-field').value = 'draft';
    submitCourseForm();
}

function publishCourse() {
    if (validateCourseForm()) {
        document.getElementById('course-status-field').value = 'publish';
        submitCourseForm();
    }
}

function submitCourseForm() {
    const form = document.getElementById('sikshya-course-builder-form');
    const formData = new FormData(form);
    
    // Add curriculum data
    const curriculumData = collectCurriculumData();
    formData.append('curriculum_data', JSON.stringify(curriculumData));
    
    // Show loading state
    showLoadingState();
    
    // Submit via AJAX
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        if (data.success) {
            showNotification('Course saved successfully!', 'success');
            if (data.data.redirect) {
                window.location.href = data.data.redirect;
            }
        } else {
            showNotification(data.data.message || 'Error saving course', 'error');
        }
    })
    .catch(error => {
        hideLoadingState();
        showNotification('Error saving course: ' + error.message, 'error');
        console.error('Error:', error);
    });
}

function validateCourseForm() {
    const title = document.querySelector('input[name="title"]');
    const description = document.querySelector('textarea[name="description"]');
    
    if (!title || !title.value.trim()) {
        showNotification('Please enter a course title', 'error');
        switchTab('course');
        title.focus();
        return false;
    }
    
    if (!description || !description.value.trim()) {
        showNotification('Please enter a course description', 'error');
        switchTab('course');
        description.focus();
        return false;
    }
    
    return true;
}

function collectCurriculumData() {
    const curriculum = [];
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    
    chapters.forEach((chapter, chapterIndex) => {
        const chapterData = {
            id: chapter.getAttribute('data-chapter-id'),
            title: chapter.querySelector('.sikshya-chapter-title')?.textContent || '',
            order: chapterIndex + 1,
            lessons: []
        };
        
        const lessons = chapter.querySelectorAll('.sikshya-lesson-item');
        lessons.forEach((lesson, lessonIndex) => {
            const lessonData = {
                id: lesson.getAttribute('data-lesson-id'),
                type: lesson.getAttribute('data-type'),
                title: lesson.querySelector('.sikshya-lesson-title')?.textContent || '',
                duration: lesson.getAttribute('data-duration') || '',
                order: lessonIndex + 1
            };
            chapterData.lessons.push(lessonData);
        });
        
        curriculum.push(chapterData);
    });
    
    return curriculum;
}

function showLoadingState() {
    const buttons = document.querySelectorAll('.sikshya-header-actions button');
    buttons.forEach(btn => {
        btn.disabled = true;
        if (btn.querySelector('svg')) {
            btn.querySelector('svg').style.display = 'none';
        }
        if (btn.textContent.includes('Save') || btn.textContent.includes('Publish')) {
            btn.innerHTML = '<div class="sikshya-spinner"></div> Saving...';
        }
    });
}

function hideLoadingState() {
    const buttons = document.querySelectorAll('.sikshya-header-actions button');
    buttons.forEach(btn => {
        btn.disabled = false;
    });
    // Restore original button content - would need to be improved for production
    location.reload();
}

function showNotification(message, type = 'info') {
    // Use toast system if available, otherwise fallback to old notification
    if (window.SikshyaToast) {
        switch (type) {
            case 'success':
                SikshyaToast.successMessage(message);
                break;
            case 'error':
                SikshyaToast.errorMessage(message);
                break;
            case 'warning':
                SikshyaToast.warningMessage(message);
                break;
            default:
                SikshyaToast.infoMessage(message);
                break;
        }
    } else {
        // Fallback to old notification system
        const notification = document.createElement('div');
        notification.className = `sikshya-notification sikshya-notification-${type}`;
        notification.innerHTML = `
            <div class="sikshya-notification-content">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
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
    // Use toast system if available, otherwise fallback to old notification
    if (window.SikshyaToast) {
        switch (type) {
            case 'success':
                SikshyaToast.successMessage(message);
                break;
            case 'error':
                SikshyaToast.errorMessage(message);
                break;
            case 'warning':
                SikshyaToast.warningMessage(message);
                break;
            default:
                SikshyaToast.infoMessage(message);
                break;
        }
    } else {
        // Fallback to old notification system
        const notification = document.createElement('div');
        notification.className = `sikshya-notification sikshya-notification-${type}`;
        notification.innerHTML = `
            <div class="sikshya-notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
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
}

// Tab switching function for dynamic course builder
function switchTab(tabId) {
    // Remove active class from all tabs
    document.querySelectorAll('.sikshya-nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to clicked tab
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    
    // Hide all tab content
    document.querySelectorAll('.sikshya-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedContent = document.getElementById(tabId);
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Update active tab
    if (window.sikshyaCourseBuilder) {
        window.sikshyaCourseBuilder.activeTab = tabId;
    }
}

// Conditional field functions
function togglePricing(select) {
    const pricingFields = document.getElementById('pricing-fields');
    if (select.value === 'free') {
        pricingFields.style.display = 'none';
    } else {
        pricingFields.style.display = 'block';
    }
}

function togglePasswordField(select) {
    const passwordField = document.getElementById('password-field');
    if (select.value === 'password_protected') {
        passwordField.style.display = 'block';
    } else {
        passwordField.style.display = 'none';
    }
}

function toggleCertificateSettings(checkbox) {
    const certificateSettings = document.getElementById('certificate-settings');
    if (checkbox.checked) {
        certificateSettings.style.display = 'block';
    } else {
        certificateSettings.style.display = 'none';
    }
}

// Permalink functions
function togglePermalinkEdit() {
    const display = document.getElementById('permalink-display');
    const edit = document.getElementById('permalink-edit');
    const editBtn = document.getElementById('edit-permalink-btn');
    
    if (display.style.display !== 'none') {
        display.style.display = 'none';
        edit.style.display = 'flex';
        editBtn.style.display = 'none';
        document.getElementById('permalink-input').focus();
    } else {
        display.style.display = 'flex';
        edit.style.display = 'none';
        editBtn.style.display = 'inline-block';
    }
}

function savePermalink() {
    const input = document.getElementById('permalink-input');
    const slug = input.value.trim();
    const slugDisplay = document.getElementById('permalink-slug');
    
    if (slug) {
        slugDisplay.textContent = slug;
        togglePermalinkEdit();
    } else {
        // If slug is empty, revert to original
        cancelPermalinkEdit();
    }
}

function cancelPermalinkEdit() {
    const input = document.getElementById('permalink-input');
    const slugDisplay = document.getElementById('permalink-slug');
    const originalSlug = slugDisplay.textContent || 'course-slug';
    
    input.value = originalSlug;
    togglePermalinkEdit();
}

// Auto-generate slug from title
function generateSlugFromTitle() {
    const titleInput = document.querySelector('input[name="title"]');
    const slugInput = document.getElementById('permalink-input');
    const slugDisplay = document.getElementById('permalink-slug');
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // Remove special characters
                .replace(/\s+/g, '-') // Replace spaces with hyphens
                .replace(/-+/g, '-') // Replace multiple hyphens with single
                .trim('-'); // Remove leading/trailing hyphens
            
            slugInput.value = slug;
            slugDisplay.textContent = slug;
        });
    }
}

// Initialize permalink functionality
document.addEventListener('DOMContentLoaded', function() {
    generateSlugFromTitle();
    
    // Initialize permalink display
    const slugDisplay = document.getElementById('permalink-slug');
    const slugInput = document.getElementById('permalink-input');
    
    if (slugDisplay && slugInput) {
        // Set initial display
        if (!slugDisplay.textContent.trim()) {
            slugDisplay.textContent = 'course-slug';
        }
        
        // Handle Enter key in permalink input
        slugInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                savePermalink();
            }
        });
    }
    
});

// Feature tabs functionality for empty state
function initializeFeatureTabs() {
    const tabs = document.querySelectorAll('.sikshya-feature-tab');
    const contents = document.querySelectorAll('.sikshya-feature-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const feature = this.getAttribute('data-feature');
            
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            const targetContent = document.querySelector(`[data-content="${feature}"]`);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
}

// Initialize on page load (update the existing DOMContentLoaded)
document.addEventListener('DOMContentLoaded', function() {
    initializeTabFromURL();
    initializeFeatureTabs();
    addSortableIconsToChapters();
    initializeChapterSorting();
    
    // Load curriculum if course_id exists
            initializeCurriculumOnPageLoad();
    
    // Add global drag event debugging
    document.addEventListener('dragstart', function(e) {
        console.log('Global drag start detected:', e.target);
        console.log('Target draggable:', e.target.draggable);
        console.log('Target classList:', e.target.classList);
    });
    
    document.addEventListener('dragover', function(e) {
        console.log('Global drag over detected:', e.target);
    });
    
    // Test if any element is draggable
    setTimeout(() => {
        const chapters = document.querySelectorAll('.sikshya-chapter-card');
        chapters.forEach((chapter, index) => {
            console.log(`Chapter ${index + 1} draggable:`, chapter.draggable);
        });
    }, 2000);
    
    // Initialize quiz builder if it exists
    if (document.querySelector('.sikshya-quiz-builder')) {
        updateQuestionCount();
        updateQuizOverview();
    }
    
    // Add event listeners for buttons
    document.addEventListener('click', function(e) {
        // Tab switching
        if (e.target.closest('.sikshya-nav-link')) {
            e.preventDefault();
            const tabLink = e.target.closest('.sikshya-nav-link');
            const tabId = tabLink.getAttribute('data-tab');
            if (tabId) {
                switchTab(tabId);
            }
        }
        
        // Preview course
        if (e.target.closest('#preview-course-btn') || e.target.closest('#sidebar-preview-btn')) {
            e.preventDefault();
            previewCourse();
        }
    });
    
    // Course data is now loaded directly in PHP template, no need for AJAX loading
    console.log('Sikshya: Course data loaded directly in PHP template');
});

// Initialize curriculum functionality on page load
function initializeCurriculumOnPageLoad() {
    console.log('Sikshya: Initializing curriculum functionality...');
    
    // Check if we have chapters or empty state
    const curriculumItems = document.getElementById('curriculum-items');
    if (curriculumItems) {
        // We have chapters, update chapter count
        const chapters = document.querySelectorAll('.sikshya-chapter-card');
        chapterCount = chapters.length;
        console.log('Sikshya: Found chapters:', chapterCount);
        
        // Initialize chapter functionality
        addSortableIconsToChapters();
        initializeChapterSorting();
        updateChapterNumbers();
                } else {
        // We have empty state, ensure curriculum-items container exists for future chapters
        console.log('Sikshya: Empty curriculum state, ensuring container structure');
        
        const curriculumContainer = document.querySelector('.sikshya-curriculum-builder');
        if (curriculumContainer) {
            // Create curriculum-items container if it doesn't exist
            let curriculumItems = document.getElementById('curriculum-items');
            if (!curriculumItems) {
                curriculumItems = document.createElement('div');
                curriculumItems.id = 'curriculum-items';
                curriculumItems.className = 'sikshya-curriculum-items';
                curriculumItems.style.display = 'none'; // Hidden initially
                curriculumContainer.appendChild(curriculumItems);
                console.log('Sikshya: Created curriculum-items container for future chapters');
            }
        }
    }
}

// Populate form with course data
function populateCourseForm(courseData) {
    console.log('Populating form with course data:', courseData);
    
    // Basic course information
    if (courseData.title) {
        const titleField = document.querySelector('input[name="title"]');
        console.log('Sikshya: Title field found:', titleField);
        if (titleField) {
            titleField.value = courseData.title;
            console.log('Sikshya: Set title field value to:', courseData.title);
        } else {
            console.log('Sikshya: Title field not found');
        }
    }
    
    if (courseData.description) {
        const descField = document.querySelector('textarea[name="description"]');
        console.log('Sikshya: Description field found:', descField);
        if (descField) {
            descField.value = courseData.description;
            console.log('Sikshya: Set description field value to:', courseData.description);
        } else {
            console.log('Sikshya: Description field not found');
        }
    }
    
    if (courseData.slug) {
        const slugDisplay = document.getElementById('permalink-slug');
        if (slugDisplay) {
            slugDisplay.textContent = courseData.slug;
        }
    }
    
    // Course meta fields
    if (courseData.meta) {
        console.log('Sikshya: Processing meta fields:', courseData.meta);
        Object.keys(courseData.meta).forEach(key => {
            const field = document.querySelector(`[name="${key}"]`);
            console.log('Sikshya: Meta field', key, 'found:', field);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = courseData.meta[key] === '1' || courseData.meta[key] === true;
                    console.log('Sikshya: Set checkbox field', key, 'to:', field.checked);
                } else {
                    field.value = courseData.meta[key];
                    console.log('Sikshya: Set field', key, 'value to:', courseData.meta[key]);
                }
            } else {
                console.log('Sikshya: Meta field', key, 'not found in DOM');
            }
        });
    }
    
    // Trigger any necessary UI updates
    if (courseData.title) {
        // Update permalink if title was set
        const titleInput = document.querySelector('input[name="title"]');
        if (titleInput) {
            titleInput.dispatchEvent(new Event('input'));
        }
    }
    
    console.log('Sikshya: Form populated successfully');
    console.log('Sikshya: All form fields after population:', {
        title: document.querySelector('input[name="title"]')?.value,
        description: document.querySelector('textarea[name="description"]')?.value,
        short_description: document.querySelector('input[name="short_description"]')?.value,
        category: document.querySelector('select[name="category"]')?.value,
        difficulty: document.querySelector('select[name="difficulty"]')?.value,
        duration: document.querySelector('input[name="duration"]')?.value,
        language: document.querySelector('select[name="language"]')?.value
    });
}

// Show loading state
function showLoadingState() {
    const form = document.getElementById('sikshya-course-builder-form');
    if (form) {
        form.classList.add('loading');
    }
    
    // Show loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'sikshya-loading-overlay';
    loadingOverlay.className = 'sikshya-loading-overlay';
    loadingOverlay.innerHTML = `
        <div class="sikshya-loading-spinner">
            <div class="sikshya-spinner"></div>
            <p>Loading course data...</p>
        </div>
    `;
    document.body.appendChild(loadingOverlay);
}

// Hide loading state
function hideLoadingState() {
    const form = document.getElementById('sikshya-course-builder-form');
    if (form) {
        form.classList.remove('loading');
    }
    
    // Remove loading overlay
    const loadingOverlay = document.getElementById('sikshya-loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.remove();
    }
}

// Add sortable icons to chapters
function addSortableIconsToChapters() {
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    console.log('Found chapters:', chapters.length);
    
    chapters.forEach((chapter, index) => {
        const header = chapter.querySelector('.sikshya-chapter-header');
        if (header && !header.querySelector('.sikshya-sortable-icon')) {
            const sortableIcon = document.createElement('div');
            sortableIcon.className = 'sikshya-sortable-icon';
            sortableIcon.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="8" cy="6" r="1.5"></circle>
                    <circle cx="16" cy="6" r="1.5"></circle>
                    <circle cx="8" cy="12" r="1.5"></circle>
                    <circle cx="16" cy="12" r="1.5"></circle>
                    <circle cx="8" cy="18" r="1.5"></circle>
                    <circle cx="16" cy="18" r="1.5"></circle>
                </svg>
            `;
            header.insertBefore(sortableIcon, header.firstChild);
            console.log('Added sortable icon to chapter', index + 1);
        }
    });
}

// Initialize chapter sorting functionality
function initializeChapterSorting() {
    const curriculumItems = document.getElementById('curriculum-items');
    if (!curriculumItems) return;
    
    let draggedElement = null;
    let draggedIndex = null;
    let isDragging = false;
    
    // Use event delegation for dynamic content
    document.addEventListener('dragstart', function(e) {
        if (e.target.closest('.sikshya-chapter-card') && e.target.closest('#curriculum-items')) {
            console.log('Drag start event triggered');
            
            draggedElement = e.target.closest('.sikshya-chapter-card');
            draggedIndex = Array.from(curriculumItems.children).indexOf(draggedElement);
            isDragging = true;
            
            console.log('Dragging chapter:', draggedIndex + 1);
            
            // Add visual feedback
            draggedElement.classList.add('dragging');
            curriculumItems.classList.add('drag-active');
            
            // Set drag data
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'chapter-drag');
            e.dataTransfer.setData('application/json', JSON.stringify({
                chapterId: draggedElement.id,
                index: draggedIndex
            }));
            
            // Create a simple drag image
            const dragImage = document.createElement('div');
            dragImage.style.cssText = `
                position: absolute;
                top: -1000px;
                left: -1000px;
                width: 200px;
                height: 60px;
                background: #3b82f6;
                color: white;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
                z-index: 9999;
                pointer-events: none;
            `;
            dragImage.textContent = 'Moving Chapter...';
            document.body.appendChild(dragImage);
            e.dataTransfer.setDragImage(dragImage, 100, 30);
            
            // Clean up drag image
            setTimeout(() => {
                if (document.body.contains(dragImage)) {
                    document.body.removeChild(dragImage);
                }
            }, 1000);
        }
    });
    
    document.addEventListener('dragend', function(e) {
        console.log('Drag end event triggered');
        
        // Clean up visual states
        if (draggedElement) {
            draggedElement.classList.remove('dragging');
        }
        
        // Remove all drag-over states
        document.querySelectorAll('.sikshya-chapter-card.drag-over').forEach(card => {
            card.classList.remove('drag-over');
            card.removeAttribute('data-drop-position');
        });
        
        // Remove drag-active class from curriculum container
        if (curriculumItems) {
            curriculumItems.classList.remove('drag-active');
        }
        
        // Reset state
        draggedElement = null;
        draggedIndex = null;
        isDragging = false;
    });
    
    document.addEventListener('dragover', function(e) {
        if (!isDragging || !draggedElement) return;
        
        // Always prevent default to allow drop
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const chapterCard = e.target.closest('.sikshya-chapter-card');
        const curriculumArea = e.target.closest('#curriculum-items');
        
        // Only allow dropping in curriculum area
        if (!curriculumArea) {
            e.dataTransfer.dropEffect = 'none';
            return;
        }
        
        if (chapterCard && chapterCard !== draggedElement) {
            const rect = chapterCard.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            
            // Remove previous drag-over states
            document.querySelectorAll('.sikshya-chapter-card.drag-over').forEach(card => {
                card.classList.remove('drag-over');
                card.removeAttribute('data-drop-position');
            });
            
            if (e.clientY < midY) {
                chapterCard.classList.add('drag-over');
                chapterCard.setAttribute('data-drop-position', 'above');
            } else {
                chapterCard.classList.add('drag-over');
                chapterCard.setAttribute('data-drop-position', 'below');
            }
        } else {
            // Remove all drag-over states when not over a chapter
            document.querySelectorAll('.sikshya-chapter-card.drag-over').forEach(card => {
                card.classList.remove('drag-over');
                card.removeAttribute('data-drop-position');
            });
        }
    });
    
    document.addEventListener('dragleave', function(e) {
        const chapterCard = e.target.closest('.sikshya-chapter-card');
        if (chapterCard && chapterCard.closest('#curriculum-items')) {
            chapterCard.classList.remove('drag-over');
            chapterCard.removeAttribute('data-drop-position');
        }
    });
    
    document.addEventListener('drop', function(e) {
        if (!isDragging || !draggedElement) return;
        
        e.preventDefault();
        
        const chapterCard = e.target.closest('.sikshya-chapter-card');
        const curriculumArea = e.target.closest('#curriculum-items');
        
        // Only allow dropping in curriculum area
        if (!curriculumArea) {
            console.log('Drop outside curriculum area - cancelled');
            return;
        }
        
        // Remove all drag-over states
        document.querySelectorAll('.sikshya-chapter-card.drag-over').forEach(card => {
            card.classList.remove('drag-over');
            card.removeAttribute('data-drop-position');
        });
        
        if (chapterCard && chapterCard !== draggedElement) {
            // Drop on another chapter
            const rect = chapterCard.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            
            if (e.clientY < midY) {
                // Drop above
                curriculumItems.insertBefore(draggedElement, chapterCard);
                console.log('Chapter reordered - dropped above');
            } else {
                // Drop below
                curriculumItems.insertBefore(draggedElement, chapterCard.nextSibling);
                console.log('Chapter reordered - dropped below');
            }
        } else {
            // Drop in empty space - add to end
            curriculumItems.appendChild(draggedElement);
            console.log('Chapter moved to end');
        }
        
        // Update chapter order numbers
        updateChapterOrderNumbers();
        
        // Save new order to server
        saveChapterOrder();
        
        // Show success feedback
        showDragSuccessFeedback();
    });
    
    // Make all chapters draggable
    makeChaptersDraggable();
}

function makeChaptersDraggable() {
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    console.log('Making chapters draggable:', chapters.length);
    chapters.forEach((chapter, index) => {
        chapter.draggable = true;
        console.log('Set draggable for chapter', index + 1, ':', chapter.draggable);
        
        // Add mouse down event to test responsiveness
        chapter.addEventListener('mousedown', function(e) {
            console.log('Mouse down on chapter', index + 1);
            if (e.target.closest('.sikshya-sortable-icon')) {
                console.log('Mouse down on sortable icon of chapter', index + 1);
            }
        });
        
        // Also add a click handler to test if the element is responsive
        chapter.addEventListener('click', function(e) {
            if (e.target.closest('.sikshya-sortable-icon')) {
                console.log('Sortable icon clicked on chapter', index + 1);
            }
        });
    });
}

function updateChapterOrderNumbers() {
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    chapters.forEach((chapter, index) => {
        const numberBadge = chapter.querySelector('.sikshya-chapter-number');
        if (numberBadge) {
            numberBadge.textContent = index + 1;
        }
        // Update data-order attribute
        chapter.setAttribute('data-order', index + 1);
    });
}

function saveChapterOrder() {
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    const chapterOrder = Array.from(chapters).map(chapter => ({
        id: chapter.getAttribute('data-chapter-id'),
        order: chapter.getAttribute('data-order')
    }));
    
    // Send to server via AJAX
    sikshyaAjax('sikshya_update_chapter_order', {
        chapter_order: chapterOrder
    }, function(response) {
        console.log('Chapter order updated:', response);
    });
}

function showDragSuccessFeedback() {
    // Create a temporary success indicator
    const feedback = document.createElement('div');
    feedback.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    feedback.textContent = '✓ Chapter reordered successfully';
    document.body.appendChild(feedback);
    
    // Animate in
    setTimeout(() => {
        feedback.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 2 seconds
    setTimeout(() => {
        feedback.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (document.body.contains(feedback)) {
                document.body.removeChild(feedback);
            }
        }, 300);
    }, 2000);
}

// Quiz builder initialization is now handled in the main DOMContentLoaded listener above 

// ========================================
// BULK SELECTION AND DELETION FUNCTIONALITY
// ========================================

/**
 * Initialize bulk selection functionality
 */
function initializeBulkSelection() {
    // Handle checkbox changes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('sikshya-checkbox')) {
            updateBulkSelection();
        }
    });
    
    // Handle select all functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('sikshya-select-all')) {
            toggleSelectAll();
        }
    });
    
    // Handle bulk action buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('[data-action]')) {
            const button = e.target.closest('[data-action]');
            const action = button.dataset.action;
            
            switch(action) {
                case 'bulk-delete':
                    e.preventDefault();
                    bulkDeleteSelected();
                    break;
                case 'clear-selection':
                    e.preventDefault();
                    clearBulkSelection();
                    break;
            }
        }
    });
}

/**
 * Update bulk selection state
 */
function updateBulkSelection() {
    const checkboxes = document.querySelectorAll('.sikshya-checkbox:not(.sikshya-select-all)');
    const checkedBoxes = document.querySelectorAll('.sikshya-checkbox:checked:not(.sikshya-select-all)');
    const selectedCount = document.getElementById('selected-count');
    const selectAllCheckbox = document.getElementById('select-all-chapters');
    
    
    // Update select all checkbox state
    if (selectAllCheckbox && checkboxes.length > 0) {
        if (checkedBoxes.length === checkboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length > 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    }
    
    // Update selected count
    if (selectedCount) {
        selectedCount.textContent = checkedBoxes.length;
    }
    
    // Update individual item selection states
    checkboxes.forEach(checkbox => {
        const item = checkbox.closest('.sikshya-chapter-card, .sikshya-lesson-item');
        if (item) {
            if (checkbox.checked) {
                item.classList.add('sikshya-selected');
            } else {
                item.classList.remove('sikshya-selected');
            }
        }
    });
}

/**
 * Clear all bulk selections
 */
function clearBulkSelection() {
    const checkboxes = document.querySelectorAll('.sikshya-checkbox:checked');
    const selectAllCheckbox = document.getElementById('select-all-chapters');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
    
    updateBulkSelection();
}

/**
 * Toggle select all functionality
 */
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.sikshya-checkbox:not(.sikshya-select-all)');
    const selectAllCheckbox = document.getElementById('select-all-chapters');
    
    if (selectAllCheckbox) {
        const shouldSelectAll = selectAllCheckbox.checked;
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = shouldSelectAll;
        });
        
        updateBulkSelection();
    }
}

/**
 * Bulk delete selected items
 */
function bulkDeleteSelected() {
    const checkedBoxes = document.querySelectorAll('.sikshya-checkbox:checked');
    
        if (checkedBoxes.length === 0) {
            sikshyaAlert('Please select items to delete.', 'warning');
            return;
        }
    
    // Check if any chapters are selected
    const chapterItems = Array.from(checkedBoxes).filter(checkbox => 
        checkbox.closest('.sikshya-chapter-card')
    );
    
    if (chapterItems.length > 0) {
        // Show enhanced confirmation for chapters
        showChapterDeleteConfirmation(chapterItems, checkedBoxes);
            } else {
                // Regular confirmation for lessons only
                const message = `Are you sure you want to delete ${checkedBoxes.length} lessons? This action cannot be undone.`;
                
                sikshyaConfirm(message, {
                    title: 'Delete Lessons',
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    confirmType: 'danger'
                }).then(result => {
                    if (result.action === 'confirm') {
                        const itemsToDelete = [];
                        
                        checkedBoxes.forEach(checkbox => {
                            const item = checkbox.closest('.sikshya-lesson-item');
                            if (item) {
                                const itemId = item.getAttribute('data-lesson-id');
                                itemsToDelete.push({
                                    id: itemId,
                                    type: 'lesson',
                                    element: item
                                });
                            }
                        });
                        
                        deleteBulkItems(itemsToDelete);
                    }
                });
            }
}

/**
 * Show chapter deletion confirmation with options
 */
function showChapterDeleteConfirmation(chapterItems, allCheckedBoxes) {
    const chapterCount = chapterItems.length;
    const lessonCount = allCheckedBoxes.length - chapterCount;
    
    const message = `You are about to delete <strong>${chapterCount} chapter${chapterCount > 1 ? 's' : ''}</strong>${lessonCount > 0 ? ` and <strong>${lessonCount} lesson${lessonCount > 1 ? 's' : ''}</strong>` : ''}.<br><br>Choose deletion option:`;
    
            // Create custom confirmation with clickable option blocks
            const customHTML = `
                <div class="sikshya-delete-options">
                    <div class="sikshya-delete-option" data-option="chapter-only">
                        <input type="radio" name="delete-option" value="chapter-only" checked>
                        <div class="sikshya-delete-option-title">Delete Chapter Only</div>
                        <div class="sikshya-delete-option-description">Move all content to the next chapter. If no next chapter exists, content will be moved to the previous chapter.</div>
                    </div>
                    
                    <div class="sikshya-delete-option selected" data-option="chapter-content">
                        <input type="radio" name="delete-option" value="chapter-content">
                        <div class="sikshya-delete-option-title">Delete Chapter + All Content</div>
                        <div class="sikshya-delete-option-description">Permanently delete the chapter and all its content. This action cannot be undone.</div>
                    </div>
                </div>
            `;
    
    // Store selected items for later use
    window.pendingBulkDelete = {
        chapterItems: chapterItems,
        allCheckedBoxes: allCheckedBoxes
    };
    
            // Show custom confirmation
            SikshyaAlert.show({
                type: 'confirm',
                title: 'Delete Chapters',
                message: message + customHTML,
                showCloseButton: false,
                className: 'chapter-delete-confirmation',
                buttons: [
                    {
                        text: 'Cancel',
                        type: 'secondary',
                        action: 'cancel'
                    },
                    {
                        text: 'Delete Selected',
                        type: 'danger',
                        action: 'confirm',
                        callback: function() {
                            confirmChapterDeletion();
                            return true;
                        }
                    }
                ]
            });
            
            // Add event listeners for clickable option blocks
            setTimeout(() => {
                const optionBlocks = document.querySelectorAll('.sikshya-delete-option');
                optionBlocks.forEach(block => {
                    block.addEventListener('click', function() {
                        // Remove selected class from all options
                        optionBlocks.forEach(opt => opt.classList.remove('selected'));
                        
                        // Add selected class to clicked option
                        this.classList.add('selected');
                        
                        // Update the radio button
                        const radio = this.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                        }
                    });
                });
            }, 100);
}


/**
 * Confirm chapter deletion based on selected option
 */
function confirmChapterDeletion() {
    const selectedOption = document.querySelector('input[name="delete-option"]:checked');
    if (!selectedOption) return;
    
    const { chapterItems, allCheckedBoxes } = window.pendingBulkDelete;
    
    // Prepare items to delete
    const itemsToDelete = [];
    
    // Add chapters
    chapterItems.forEach(checkbox => {
        const item = checkbox.closest('.sikshya-chapter-card');
        if (item) {
            const itemId = item.getAttribute('data-chapter-id');
            itemsToDelete.push({
                id: itemId,
                type: 'chapter',
                element: item,
                deleteContent: selectedOption.value === 'chapter-content'
            });
        }
    });
    
    // Add lessons if any
    const lessonItems = Array.from(allCheckedBoxes).filter(checkbox => 
        checkbox.closest('.sikshya-lesson-item')
    );
    
    lessonItems.forEach(checkbox => {
        const item = checkbox.closest('.sikshya-lesson-item');
        if (item) {
            const itemId = item.getAttribute('data-lesson-id');
            itemsToDelete.push({
                id: itemId,
                type: 'lesson',
                element: item
            });
        }
    });
    
    // Delete items via AJAX
    deleteBulkItems(itemsToDelete);
    
    // Clean up
    delete window.pendingBulkDelete;
}

/**
 * Delete bulk items via AJAX
 */
function deleteBulkItems(items) {
    const chaptersToDelete = items.filter(item => item.type === 'chapter').map(item => item.id);
    const lessonsToDelete = items.filter(item => item.type === 'lesson').map(item => item.id);
    
    // Show loading state
    const deleteBtn = document.getElementById('bulk-delete-btn');
    if (deleteBtn) {
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Deleting...';
        deleteBtn.disabled = true;
    }
    
    // Prepare data
    const data = {
        action: 'sikshya_bulk_delete_items',
        chapters: chaptersToDelete,
        lessons: lessonsToDelete,
        nonce: sikshyaAjax.nonce || ''
    };
    
    // Send AJAX request
    sikshyaAjax('sikshya_bulk_delete_items', data, function(response) {
        if (response.success) {
            // Remove items from DOM
            items.forEach(item => {
                item.element.remove();
            });
            
            // Clear selection
            clearBulkSelection();
            
                    // Show success message
                    sikshyaAlert(`${items.length} items deleted successfully.`, 'success');
            
            // Update curriculum counts
            updateCurriculumCounts();
        } else {
            sikshyaAlert('Error deleting items: ' + (response.data || 'Unknown error'), 'error');
        }
    }, function(error) {
        sikshyaAlert('Error deleting items: ' + error, 'error');
    }, function() {
        // Restore button state
        if (deleteBtn) {
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        }
    });
}

/**
 * Update curriculum counts after deletion
 */
function updateCurriculumCounts() {
    // Update chapter numbers
    const chapters = document.querySelectorAll('.sikshya-chapter-card');
    chapters.forEach((chapter, index) => {
        const numberElement = chapter.querySelector('.sikshya-chapter-number');
        if (numberElement) {
            numberElement.textContent = index + 1;
        }
    });
    
    // Update lesson counts in chapters
    chapters.forEach(chapter => {
        const lessonCount = chapter.querySelectorAll('.sikshya-lesson-item').length;
        const lessonCountElement = chapter.querySelector('.lesson-count');
        if (lessonCountElement) {
            lessonCountElement.textContent = lessonCount;
        }
    });
}
