<?php
/**
 * Lesson AJAX Handler
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Ajax;

use Sikshya\Services\LessonService;
use Sikshya\Services\CourseService;
use Sikshya\Constants\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lesson AJAX Handler Class
 * 
 * Handles all lesson-related AJAX operations including:
 * - Creating lessons for all content types
 * - Updating existing lessons
 * - Deleting lessons
 * - Managing lesson meta data
 */
class LessonAjax extends AjaxAbstract
{
    /**
     * Lesson service
     * 
     * @var LessonService
     */
    private LessonService $lessonService;
    
    /**
     * Course service
     * 
     * @var CourseService
     */
    private CourseService $courseService;
    
    /**
     * Constructor
     * 
     * @param \Sikshya\Core\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);
        $this->lessonService = new LessonService();
        $this->courseService = new CourseService();
        $this->initHooks();
    }
    
    /**
     * Initialize AJAX hooks
     * 
     * @return void
     */
    protected function initHooks(): void
    {
        // Lesson CRUD operations
        add_action('wp_ajax_sikshya_save_lesson', [$this, 'handleSaveLesson']);
        add_action('wp_ajax_sikshya_delete_lesson', [$this, 'handleDeleteLesson']);
        add_action('wp_ajax_sikshya_get_lesson', [$this, 'handleGetLesson']);
        add_action('wp_ajax_sikshya_get_lessons', [$this, 'handleGetLessons']);
        
        // Lesson-specific operations
        add_action('wp_ajax_sikshya_upload_lesson_media', [$this, 'handleUploadLessonMedia']);
        add_action('wp_ajax_sikshya_save_lesson_meta', [$this, 'handleSaveLessonMeta']);
        add_action('wp_ajax_sikshya_duplicate_lesson', [$this, 'handleDuplicateLesson']);
        
        // Content type specific handlers
        add_action('wp_ajax_sikshya_save_text_lesson', [$this, 'handleSaveTextLesson']);
        add_action('wp_ajax_sikshya_save_video_lesson', [$this, 'handleSaveVideoLesson']);
        add_action('wp_ajax_sikshya_save_audio_lesson', [$this, 'handleSaveAudioLesson']);
        add_action('wp_ajax_sikshya_save_assignment', [$this, 'handleSaveAssignment']);
        add_action('wp_ajax_sikshya_save_quiz', [$this, 'handleSaveQuiz']);
    }
    
    /**
     * Handle general lesson save (for any content type)
     * 
     * @return void
     */
    public function handleSaveLesson(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }
            
            $content_type = sanitize_text_field($this->getPostData('content_type', 'text'));
            $lesson_id = intval($this->getPostData('lesson_id', 0));
            
            // Route to specific content type handler
            switch ($content_type) {
                case 'text':
                    $this->handleSaveTextLesson();
                    break;
                case 'video':
                    $this->handleSaveVideoLesson();
                    break;
                case 'audio':
                    $this->handleSaveAudioLesson();
                    break;
                case 'assignment':
                    $this->handleSaveAssignment();
                    break;
                case 'quiz':
                    $this->handleSaveQuiz();
                    break;
                default:
                    $this->sendError('Invalid content type');
                    break;
            }
            
        } catch (\Exception $e) {
            $this->logError('Save lesson error', $e);
            $this->sendError('Failed to save lesson: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle text lesson save
     * 
     * @return void
     */
    public function handleSaveTextLesson(): void
    {
        try {
            $data = $this->getTextLessonData();
            $lesson_id = $this->saveLessonData($data, 'text');
            
            $this->sendSuccess([
                'lesson_id' => $lesson_id,
                'message' => 'Text lesson saved successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Save text lesson error', $e);
            $this->sendError('Failed to save text lesson: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle video lesson save
     * 
     * @return void
     */
    public function handleSaveVideoLesson(): void
    {
        try {
            $data = $this->getVideoLessonData();
            $lesson_id = $this->saveLessonData($data, 'video');
            
            $this->sendSuccess([
                'lesson_id' => $lesson_id,
                'message' => 'Video lesson saved successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Save video lesson error', $e);
            $this->sendError('Failed to save video lesson: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle audio lesson save
     * 
     * @return void
     */
    public function handleSaveAudioLesson(): void
    {
        try {
            $data = $this->getAudioLessonData();
            $lesson_id = $this->saveLessonData($data, 'audio');
            
            $this->sendSuccess([
                'lesson_id' => $lesson_id,
                'message' => 'Audio lesson saved successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Save audio lesson error', $e);
            $this->sendError('Failed to save audio lesson: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle assignment save
     * 
     * @return void
     */
    public function handleSaveAssignment(): void
    {
        try {
            $data = $this->getAssignmentData();
            $lesson_id = $this->saveLessonData($data, 'assignment');
            
            $this->sendSuccess([
                'lesson_id' => $lesson_id,
                'message' => 'Assignment saved successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Save assignment error', $e);
            $this->sendError('Failed to save assignment: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle quiz save
     * 
     * @return void
     */
    public function handleSaveQuiz(): void
    {
        try {
            $data = $this->getQuizData();
            $lesson_id = $this->saveLessonData($data, 'quiz');
            
            $this->sendSuccess([
                'lesson_id' => $lesson_id,
                'message' => 'Quiz saved successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logError('Save quiz error', $e);
            $this->sendError('Failed to save quiz: ' . $e->getMessage());
        }
    }
    
    /**
     * Get text lesson data from POST
     * 
     * @return array
     */
    private function getTextLessonData(): array
    {
        return [
            'title' => sanitize_text_field($this->getPostData('title', '')),
            'content' => wp_kses_post($this->getPostData('content', '')),
            'course_id' => intval($this->getPostData('course_id', 0)),
            'duration' => intval($this->getPostData('duration', 0)),
            'difficulty' => sanitize_text_field($this->getPostData('difficulty', 'beginner')),
            'objectives' => wp_kses_post($this->getPostData('objectives', '')),
            'takeaways' => wp_kses_post($this->getPostData('takeaways', '')),
            'resources' => wp_kses_post($this->getPostData('resources', '')),
            'completion' => sanitize_text_field($this->getPostData('completion', 'yes')),
            'comments' => sanitize_text_field($this->getPostData('comments', 'yes')),
            'progress' => sanitize_text_field($this->getPostData('progress', 'yes')),
            'print' => sanitize_text_field($this->getPostData('print', 'yes')),
            'prerequisites' => wp_kses_post($this->getPostData('prerequisites', '')),
            'tags' => sanitize_text_field($this->getPostData('tags', '')),
            'seo' => wp_kses_post($this->getPostData('seo', '')),
            'format' => sanitize_text_field($this->getPostData('format', 'article')),
            'reading_level' => sanitize_text_field($this->getPostData('reading_level', 'basic')),
            'word_count' => intval($this->getPostData('word_count', 0)),
            'language' => sanitize_text_field($this->getPostData('language', 'en')),
            'toc' => sanitize_text_field($this->getPostData('toc', 'auto')),
            'search' => sanitize_text_field($this->getPostData('search', 'yes')),
            'related' => wp_kses_post($this->getPostData('related', '')),
            'status' => sanitize_text_field($this->getPostData('status', 'draft')),
        ];
    }
    
    /**
     * Get video lesson data from POST
     * 
     * @return array
     */
    private function getVideoLessonData(): array
    {
        return [
            'title' => sanitize_text_field($this->getPostData('title', '')),
            'content' => wp_kses_post($this->getPostData('description', '')),
            'course_id' => intval($this->getPostData('course_id', 0)),
            'duration' => intval($this->getPostData('duration', 0)),
            'difficulty' => sanitize_text_field($this->getPostData('difficulty', 'beginner')),
            'video_source' => sanitize_text_field($this->getPostData('video_source', 'upload')),
            'video_url' => esc_url_raw($this->getPostData('video_url', '')),
            'video_file' => sanitize_text_field($this->getPostData('video_file', '')),
            'video_quality' => sanitize_text_field($this->getPostData('video_quality', 'hd')),
            'autoplay' => sanitize_text_field($this->getPostData('autoplay', 'no')),
            'show_controls' => sanitize_text_field($this->getPostData('show_controls', 'yes')),
            'allow_download' => sanitize_text_field($this->getPostData('allow_download', 'no')),
            'transcript' => wp_kses_post($this->getPostData('transcript', '')),
            'subtitles' => wp_kses_post($this->getPostData('subtitles', '')),
            'status' => sanitize_text_field($this->getPostData('status', 'draft')),
        ];
    }
    
    /**
     * Get audio lesson data from POST
     * 
     * @return array
     */
    private function getAudioLessonData(): array
    {
        return [
            'title' => sanitize_text_field($this->getPostData('title', '')),
            'content' => wp_kses_post($this->getPostData('description', '')),
            'course_id' => intval($this->getPostData('course_id', 0)),
            'duration' => intval($this->getPostData('duration', 0)),
            'difficulty' => sanitize_text_field($this->getPostData('difficulty', 'beginner')),
            'audio_source' => sanitize_text_field($this->getPostData('audio_source', 'upload')),
            'audio_url' => esc_url_raw($this->getPostData('audio_url', '')),
            'audio_file' => sanitize_text_field($this->getPostData('audio_file', '')),
            'audio_quality' => sanitize_text_field($this->getPostData('audio_quality', 'high')),
            'autoplay' => sanitize_text_field($this->getPostData('autoplay', 'no')),
            'show_controls' => sanitize_text_field($this->getPostData('show_controls', 'yes')),
            'allow_download' => sanitize_text_field($this->getPostData('allow_download', 'no')),
            'transcript' => wp_kses_post($this->getPostData('transcript', '')),
            'status' => sanitize_text_field($this->getPostData('status', 'draft')),
        ];
    }
    
    /**
     * Get assignment data from POST
     * 
     * @return array
     */
    private function getAssignmentData(): array
    {
        return [
            'title' => sanitize_text_field($this->getPostData('title', '')),
            'content' => wp_kses_post($this->getPostData('description', '')),
            'course_id' => intval($this->getPostData('course_id', 0)),
            'duration' => intval($this->getPostData('duration', 0)),
            'difficulty' => sanitize_text_field($this->getPostData('difficulty', 'beginner')),
            'instructions' => wp_kses_post($this->getPostData('instructions', '')),
            'objectives' => wp_kses_post($this->getPostData('objectives', '')),
            'criteria' => wp_kses_post($this->getPostData('criteria', '')),
            'submission_type' => sanitize_text_field($this->getPostData('submission_type', 'file')),
            'file_types' => $this->getPostData('file_types', []),
            'max_file_size' => intval($this->getPostData('max_file_size', 10)),
            'due_date' => sanitize_text_field($this->getPostData('due_date', '')),
            'points' => intval($this->getPostData('points', 100)),
            'attempts' => intval($this->getPostData('attempts', 1)),
            'status' => sanitize_text_field($this->getPostData('status', 'draft')),
        ];
    }
    
    /**
     * Get quiz data from POST
     * 
     * @return array
     */
    private function getQuizData(): array
    {
        return [
            'title' => sanitize_text_field($this->getPostData('title', '')),
            'content' => wp_kses_post($this->getPostData('description', '')),
            'course_id' => intval($this->getPostData('course_id', 0)),
            'duration' => intval($this->getPostData('duration', 0)),
            'difficulty' => sanitize_text_field($this->getPostData('difficulty', 'beginner')),
            'time_limit' => intval($this->getPostData('time_limit', 0)),
            'passing_score' => intval($this->getPostData('passing_score', 70)),
            'attempts' => intval($this->getPostData('attempts', 1)),
            'randomize_questions' => sanitize_text_field($this->getPostData('randomize_questions', 'no')),
            'show_results' => sanitize_text_field($this->getPostData('show_results', 'yes')),
            'show_correct_answers' => sanitize_text_field($this->getPostData('show_correct_answers', 'no')),
            'status' => sanitize_text_field($this->getPostData('status', 'draft')),
        ];
    }
    
    /**
     * Save lesson data to database
     * 
     * @param array $data
     * @param string $type
     * @return int
     */
    private function saveLessonData(array $data, string $type): int
    {
        // Validate required fields
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Lesson title is required');
        }
        
        if (empty($data['course_id'])) {
            throw new \InvalidArgumentException('Course ID is required');
        }
        
        // Set default values
        $data['type'] = $type;
        $data['status'] = $data['status'] ?? 'draft';
        $data['author_id'] = get_current_user_id();
        
        // Check if updating existing lesson
        $lesson_id = intval($this->getPostData('lesson_id', 0));
        
        if ($lesson_id > 0) {
            // Update existing lesson
            $success = $this->lessonService->updateLesson($lesson_id, $data);
            if (!$success) {
                throw new \Exception('Failed to update lesson');
            }
            return $lesson_id;
        } else {
            // Create new lesson
            $lesson_id = $this->lessonService->createLesson($data);
            if (!$lesson_id) {
                throw new \Exception('Failed to create lesson');
            }
            return $lesson_id;
        }
    }
    
    /**
     * Handle lesson delete
     * 
     * @return void
     */
    public function handleDeleteLesson(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }
            
            $lesson_id = intval($this->getPostData('lesson_id', 0));
            
            if ($lesson_id <= 0) {
                $this->sendError('Invalid lesson ID');
                return;
            }
            
            $success = $this->lessonService->deleteLesson($lesson_id);
            
            if ($success) {
                $this->sendSuccess(null, 'Lesson deleted successfully');
            } else {
                $this->sendError('Failed to delete lesson');
            }
            
        } catch (\Exception $e) {
            $this->logError('Delete lesson error', $e);
            $this->sendError('Failed to delete lesson: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle get lesson
     * 
     * @return void
     */
    public function handleGetLesson(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $lesson_id = intval($this->getPostData('lesson_id', 0));
            
            if ($lesson_id <= 0) {
                $this->sendError('Invalid lesson ID');
                return;
            }
            
            $lesson = $this->lessonService->getLesson($lesson_id);
            
            if ($lesson) {
                $this->sendSuccess(['lesson' => $lesson]);
            } else {
                $this->sendError('Lesson not found');
            }
            
        } catch (\Exception $e) {
            $this->logError('Get lesson error', $e);
            $this->sendError('Failed to get lesson: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle get lessons
     * 
     * @return void
     */
    public function handleGetLessons(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            $course_id = intval($this->getPostData('course_id', 0));
            $args = [
                'posts_per_page' => intval($this->getPostData('posts_per_page', 20)),
                'paged' => intval($this->getPostData('paged', 1)),
                'post_status' => sanitize_text_field($this->getPostData('status', 'any')),
            ];
            
            if ($course_id > 0) {
                $lessons = $this->lessonService->getLessonsByCourse($course_id, $args);
            } else {
                $lessons = $this->lessonService->getAllLessons($args);
            }
            
            $this->sendSuccess(['lessons' => $lessons]);
            
        } catch (\Exception $e) {
            $this->logError('Get lessons error', $e);
            $this->sendError('Failed to get lessons: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle lesson media upload
     * 
     * @return void
     */
    public function handleUploadLessonMedia(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }
            
            // Handle file upload logic here
            $this->sendError('Media upload not implemented yet');
            
        } catch (\Exception $e) {
            $this->logError('Upload lesson media error', $e);
            $this->sendError('Failed to upload media: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle save lesson meta
     * 
     * @return void
     */
    public function handleSaveLessonMeta(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }
            
            $lesson_id = intval($this->getPostData('lesson_id', 0));
            $meta_key = sanitize_text_field($this->getPostData('meta_key', ''));
            $meta_value = $this->getPostData('meta_value', '');
            
            if ($lesson_id <= 0 || empty($meta_key)) {
                $this->sendError('Invalid lesson ID or meta key');
                return;
            }
            
            $success = $this->lessonService->setMeta($lesson_id, $meta_key, $meta_value);
            
            if ($success) {
                $this->sendSuccess(null, 'Lesson meta saved successfully');
            } else {
                $this->sendError('Failed to save lesson meta');
            }
            
        } catch (\Exception $e) {
            $this->logError('Save lesson meta error', $e);
            $this->sendError('Failed to save lesson meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle duplicate lesson
     * 
     * @return void
     */
    public function handleDuplicateLesson(): void
    {
        try {
            if (!$this->verifyNonce('sikshya_lesson_nonce')) {
                $this->sendError('Invalid nonce');
                return;
            }
            
            if (!$this->checkCapability()) {
                $this->sendError('Insufficient permissions');
                return;
            }
            
            $lesson_id = intval($this->getPostData('lesson_id', 0));
            
            if ($lesson_id <= 0) {
                $this->sendError('Invalid lesson ID');
                return;
            }
            
            $lesson = $this->lessonService->getLesson($lesson_id);
            
            if (!$lesson) {
                $this->sendError('Lesson not found');
                return;
            }
            
            // Create duplicate lesson data
            $duplicate_data = [
                'title' => $lesson->post_title . ' (Copy)',
                'content' => $lesson->post_content,
                'course_id' => $this->lessonService->getMeta($lesson_id, 'course_id'),
                'type' => $this->lessonService->getMeta($lesson_id, 'lesson_type'),
                'duration' => $this->lessonService->getMeta($lesson_id, 'duration'),
                'status' => 'draft',
            ];
            
            $new_lesson_id = $this->lessonService->createLesson($duplicate_data);
            
            if ($new_lesson_id) {
                $this->sendSuccess([
                    'lesson_id' => $new_lesson_id,
                    'message' => 'Lesson duplicated successfully'
                ]);
            } else {
                $this->sendError('Failed to duplicate lesson');
            }
            
        } catch (\Exception $e) {
            $this->logError('Duplicate lesson error', $e);
            $this->sendError('Failed to duplicate lesson: ' . $e->getMessage());
        }
    }
}

