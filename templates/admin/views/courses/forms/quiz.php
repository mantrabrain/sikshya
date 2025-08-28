<?php
/**
 * Advanced Quiz Form Template with Tabs
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-quiz-builder">
    <div class="sikshya-quiz-content">
        <!-- Left Sidebar with Tabs -->
        <div class="sikshya-quiz-sidebar">
            <div class="sikshya-quiz-tabs">
                <button class="sikshya-quiz-tab active" data-tab="quiz" onclick="switchQuizTab('quiz')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Quiz</span>
                </button>
                <button class="sikshya-quiz-tab" data-tab="questions" onclick="switchQuizTab('questions')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Questions</span>
                    <span class="sikshya-question-count">0</span>
                </button>
                <button class="sikshya-quiz-tab" data-tab="settings" onclick="switchQuizTab('settings')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Settings</span>
                </button>
            </div>
        </div>

        <!-- Right Content Area -->
        <div class="sikshya-quiz-main">
            <!-- Quiz Tab Content -->
            <div class="sikshya-quiz-tab-content active" id="quiz-tab">
                <div class="sikshya-form-section">
                    <h4 class="sikshya-form-section-title">Basic Information</h4>
                    
                    <div class="sikshya-form-row-small">
                        <label>Quiz Title *</label>
                        <input type="text" id="quiz-lesson-title" placeholder="Enter quiz title" required>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Description</label>
                        <textarea id="quiz-lesson-description" placeholder="Brief description of this quiz"></textarea>
                    </div>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Time Limit (minutes)</label>
                            <input type="number" id="quiz-lesson-duration" placeholder="30" min="1">
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Difficulty Level</label>
                            <select id="quiz-lesson-difficulty">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="sikshya-form-section">
                    <h4 class="sikshya-form-section-title">Quiz Overview</h4>
                    
                    <div class="sikshya-quiz-overview">
                        <div class="sikshya-overview-card">
                            <div class="sikshya-overview-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="sikshya-overview-content">
                                <h5>Total Questions</h5>
                                <span class="sikshya-overview-value" id="total-questions">0</span>
                            </div>
                        </div>
                        
                        <div class="sikshya-overview-card">
                            <div class="sikshya-overview-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </div>
                            <div class="sikshya-overview-content">
                                <h5>Total Points</h5>
                                <span class="sikshya-overview-value" id="total-points">0</span>
                            </div>
                        </div>
                        
                        <div class="sikshya-overview-card">
                            <div class="sikshya-overview-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="sikshya-overview-content">
                                <h5>Estimated Time</h5>
                                <span class="sikshya-overview-value" id="estimated-time">0 min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Tab Content -->
            <div class="sikshya-quiz-tab-content" id="questions-tab">
                <div class="sikshya-form-section">
                    <div class="sikshya-questions-header">
                        <h4 class="sikshya-form-section-title">Quiz Questions</h4>
                        <div class="sikshya-question-actions">
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('multiple-choice')" title="Choose one correct answer from multiple options">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                                Multiple Choice
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('true-false')" title="Choose between True or False">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                True/False
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('fill-blank')" title="Fill in the missing word or phrase">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Fill in the Blank
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('essay')" title="Write a detailed response">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Essay
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('matching')" title="Match items from two columns">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                Matching
                            </button>
                        </div>
                    </div>
                    
                    <div id="quiz-questions-container">
                        <div class="sikshya-quiz-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--sikshya-gray-400);">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h4>No Questions Added Yet</h4>
                            <p>Add your first question to get started</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab Content -->
            <div class="sikshya-quiz-tab-content" id="settings-tab">
                <div class="sikshya-form-section">
                    <h4 class="sikshya-form-section-title">Quiz Settings</h4>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Quiz Type</label>
                            <select id="quiz-lesson-type">
                                <option value="graded">Graded Quiz</option>
                                <option value="practice">Practice Quiz</option>
                                <option value="survey">Survey</option>
                            </select>
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Passing Score (%)</label>
                            <input type="number" id="quiz-lesson-passing" placeholder="70" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Attempts Allowed</label>
                            <select id="quiz-lesson-attempts">
                                <option value="1">1 Attempt</option>
                                <option value="2">2 Attempts</option>
                                <option value="3">3 Attempts</option>
                                <option value="unlimited">Unlimited</option>
                            </select>
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Show Results</label>
                            <select id="quiz-lesson-results">
                                <option value="immediate">Immediately</option>
                                <option value="after_submit">After Submit</option>
                                <option value="never">Never</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Randomize Questions</label>
                            <select id="quiz-lesson-randomize">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Show Correct Answers</label>
                            <select id="quiz-lesson-show-answers">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="sikshya-form-section">
                    <h4 class="sikshya-form-section-title">Quiz Instructions</h4>
                    
                    <div class="sikshya-form-row-small">
                        <label>Instructions for Students</label>
                        <textarea id="quiz-lesson-instructions" placeholder="Provide clear instructions for students taking this quiz"></textarea>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Learning Objectives</label>
                        <textarea id="quiz-lesson-objectives" placeholder="What will this quiz assess?"></textarea>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Prerequisites</label>
                        <textarea id="quiz-lesson-prerequisites" placeholder="What should students know before taking this quiz?"></textarea>
                    </div>
                </div>

                <div class="sikshya-form-section">
                    <h4 class="sikshya-form-section-title">Additional Settings</h4>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Due Date</label>
                            <input type="datetime-local" id="quiz-lesson-due-date">
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Available From</label>
                            <input type="datetime-local" id="quiz-lesson-available-from">
                        </div>
                    </div>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Available Until</label>
                            <input type="datetime-local" id="quiz-lesson-available-until">
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Require Password</label>
                            <input type="text" id="quiz-lesson-password" placeholder="Leave empty for no password">
                        </div>
                    </div>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Allow Backtracking</label>
                            <select id="quiz-lesson-backtrack">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Show Progress Bar</label>
                            <select id="quiz-lesson-progress">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="sikshya-form-grid-2">
                        <div class="sikshya-form-row-small">
                            <label>Require Completion</label>
                            <select id="quiz-lesson-completion">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        
                        <div class="sikshya-form-row-small">
                            <label>Send Notifications</label>
                            <select id="quiz-lesson-notifications">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Tags</label>
                        <input type="text" id="quiz-lesson-tags" placeholder="Enter tags separated by commas">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Question editing is now inline - no modal needed --> 