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
    <!-- Quiz Builder Header -->
    <div class="sikshya-quiz-header">
        <h3><i class="fas fa-edit"></i> Quiz Builder</h3>
        <div class="sikshya-quiz-actions">
            <button class="sikshya-btn sikshya-btn-secondary" onclick="previewQuiz()">
                <i class="fas fa-eye"></i> Preview
            </button>
            <button class="sikshya-btn sikshya-btn-primary" onclick="saveQuiz()">
                <i class="fas fa-save"></i> Save Quiz
            </button>
        </div>
    </div>

    <div class="sikshya-quiz-content">
        <!-- Left Sidebar with Tabs -->
        <div class="sikshya-quiz-sidebar">
            <div class="sikshya-quiz-tabs">
                <button class="sikshya-quiz-tab active" data-tab="quiz" onclick="switchQuizTab('quiz')">
                    <i class="fas fa-info-circle"></i>
                    <span>Quiz</span>
                </button>
                <button class="sikshya-quiz-tab" data-tab="questions" onclick="switchQuizTab('questions')">
                    <i class="fas fa-question-circle"></i>
                    <span>Questions</span>
                    <span class="sikshya-question-count">0</span>
                </button>
                <button class="sikshya-quiz-tab" data-tab="settings" onclick="switchQuizTab('settings')">
                    <i class="fas fa-cogs"></i>
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
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="sikshya-overview-content">
                                <h5>Total Questions</h5>
                                <span class="sikshya-overview-value" id="total-questions">0</span>
                            </div>
                        </div>
                        
                        <div class="sikshya-overview-card">
                            <div class="sikshya-overview-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="sikshya-overview-content">
                                <h5>Total Points</h5>
                                <span class="sikshya-overview-value" id="total-points">0</span>
                            </div>
                        </div>
                        
                        <div class="sikshya-overview-card">
                            <div class="sikshya-overview-icon">
                                <i class="fas fa-clock"></i>
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
                                <i class="fas fa-list-ul"></i> Multiple Choice
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('true-false')" title="Choose between True or False">
                                <i class="fas fa-toggle-on"></i> True/False
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('fill-blank')" title="Fill in the missing word or phrase">
                                <i class="fas fa-pencil-alt"></i> Fill in the Blank
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('essay')" title="Write a detailed response">
                                <i class="fas fa-file-alt"></i> Essay
                            </button>
                            <button class="sikshya-btn sikshya-btn-secondary" onclick="addQuestion('matching')" title="Match items from two columns">
                                <i class="fas fa-link"></i> Matching
                            </button>
                        </div>
                    </div>
                    
                    <div id="quiz-questions-container">
                        <div class="sikshya-quiz-empty">
                            <i class="fas fa-question-circle"></i>
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
                    <h4 class="sikshya-form-section-title">Advanced Options</h4>
                    
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