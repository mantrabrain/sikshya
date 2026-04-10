<?php

namespace Sikshya\Services;

/**
 * Question Type Service
 *
 * Handles all question type operations and validation
 *
 * @package Sikshya\Services
 */
class QuestionTypeService
{
    /**
     * Supported question types
     */
    public const QUESTION_TYPES = [
        'multiple_choice' => [
            'label' => 'Multiple Choice',
            'description' => 'Choose one correct answer from multiple options',
            'supports_options' => true,
            'supports_single_answer' => true,
            'supports_multiple_answers' => false,
            'auto_gradable' => true,
            'requires_text_input' => false,
            'icon' => 'fas fa-list-ul'
        ],
        'multiple_response' => [
            'label' => 'Multiple Response',
            'description' => 'Select all correct answers',
            'supports_options' => true,
            'supports_single_answer' => false,
            'supports_multiple_answers' => true,
            'auto_gradable' => true,
            'requires_text_input' => false,
            'icon' => 'fas fa-tasks'
        ],
        'true_false' => [
            'label' => 'True/False',
            'description' => 'Choose between True or False',
            'supports_options' => true,
            'supports_single_answer' => true,
            'supports_multiple_answers' => false,
            'auto_gradable' => true,
            'requires_text_input' => false,
            'icon' => 'fas fa-toggle-on'
        ],
        'short_answer' => [
            'label' => 'Short Answer',
            'description' => 'Brief text response (auto-graded; use | for accepted alternatives)',
            'supports_options' => false,
            'supports_single_answer' => true,
            'supports_multiple_answers' => false,
            'auto_gradable' => true,
            'requires_text_input' => true,
            'icon' => 'fas fa-i-cursor'
        ],
        'fill_blank' => [
            'label' => 'Fill in the Blank',
            'description' => 'Fill in the missing word or phrase (use | for multiple accepted answers)',
            'supports_options' => false,
            'supports_single_answer' => true,
            'supports_multiple_answers' => false,
            'auto_gradable' => true,
            'requires_text_input' => true,
            'icon' => 'fas fa-pencil-alt'
        ],
        'ordering' => [
            'label' => 'Ordering',
            'description' => 'Put items in the correct sequence',
            'supports_options' => true,
            'supports_single_answer' => false,
            'supports_multiple_answers' => false,
            'auto_gradable' => true,
            'requires_text_input' => false,
            'icon' => 'fas fa-sort'
        ],
        'essay' => [
            'label' => 'Essay',
            'description' => 'Write a detailed response',
            'supports_options' => false,
            'supports_single_answer' => false,
            'supports_multiple_answers' => false,
            'auto_gradable' => false,
            'requires_text_input' => true,
            'icon' => 'fas fa-file-alt'
        ],
        'matching' => [
            'label' => 'Matching',
            'description' => 'Match each left column item to the correct right column item',
            'supports_options' => false,
            'supports_single_answer' => false,
            'supports_multiple_answers' => true,
            'auto_gradable' => true,
            'requires_text_input' => false,
            'icon' => 'fas fa-link'
        ]
    ];

    /**
     * Get all question types
     *
     * @return array
     */
    public function getAllQuestionTypes(): array
    {
        return self::QUESTION_TYPES;
    }

    /**
     * Get question type by key
     *
     * @param string $type
     * @return array|null
     */
    public function getQuestionType(string $type): ?array
    {
        return self::QUESTION_TYPES[$type] ?? null;
    }

    /**
     * Check if question type exists
     *
     * @param string $type
     * @return bool
     */
    public function isValidQuestionType(string $type): bool
    {
        return isset(self::QUESTION_TYPES[$type]);
    }

    /**
     * Get question type label
     *
     * @param string $type
     * @return string
     */
    public function getQuestionTypeLabel(string $type): string
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['label'] : 'Unknown';
    }

    /**
     * Get question type description
     *
     * @param string $type
     * @return string
     */
    public function getQuestionTypeDescription(string $type): string
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['description'] : '';
    }

    /**
     * Get question type icon
     *
     * @param string $type
     * @return string
     */
    public function getQuestionTypeIcon(string $type): string
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['icon'] : 'fas fa-question';
    }

    /**
     * Check if question type supports options
     *
     * @param string $type
     * @return bool
     */
    public function supportsOptions(string $type): bool
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['supports_options'] : false;
    }

    /**
     * Check if question type supports single answer
     *
     * @param string $type
     * @return bool
     */
    public function supportsSingleAnswer(string $type): bool
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['supports_single_answer'] : false;
    }

    /**
     * Check if question type supports multiple answers
     *
     * @param string $type
     * @return bool
     */
    public function supportsMultipleAnswers(string $type): bool
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['supports_multiple_answers'] : false;
    }

    /**
     * Check if question type is auto-gradable
     *
     * @param string $type
     * @return bool
     */
    public function isAutoGradable(string $type): bool
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['auto_gradable'] : false;
    }

    /**
     * Check if question type requires text input
     *
     * @param string $type
     * @return bool
     */
    public function requiresTextInput(string $type): bool
    {
        $questionType = $this->getQuestionType($type);
        return $questionType ? $questionType['requires_text_input'] : false;
    }

    /**
     * Validate question data based on type
     *
     * @param string $type
     * @param array $data
     * @return array Validation result
     */
    public function validateQuestionData(string $type, array $data): array
    {
        $errors = [];

        if (!$this->isValidQuestionType($type)) {
            $errors[] = 'Invalid question type';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check required fields
        if (empty($data['question_text'])) {
            $errors[] = 'Question text is required';
        }

        // Check options for question types that support them
        if ($this->supportsOptions($type) && $type !== 'matching') {
            if (empty($data['options']) || !is_array($data['options'])) {
                $errors[] = 'Options are required for this question type';
            } else {
                $validOptions = array_filter(
                    $data['options'],
                    static function ($option) {
                        return $option !== null && $option !== '' && trim((string) $option) !== '';
                    }
                );

                if (count($validOptions) < 2) {
                    $errors[] = 'At least 2 options are required';
                }
            }
        }

        if ($type === 'matching') {
            $raw = $data['correct_answer'] ?? '';
            $dec = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
            if (
                !is_array($dec)
                || empty($dec['matching']['left'])
                || empty($dec['matching']['right'])
                || empty($dec['matching']['map'])
                || !is_array($dec['matching']['map'])
            ) {
                $errors[] = 'Matching pairs are incomplete';
            }
        }

        // Check correct answer
        if ($this->isAutoGradable($type) && $type !== 'essay') {
            if ($type === 'multiple_response') {
                $raw = $data['correct_answer'] ?? '';
                $arr = is_string($raw) ? json_decode($raw, true) : $raw;
                if (!is_array($arr) || count($arr) < 1) {
                    $errors[] = 'Select at least one correct option';
                }
            } elseif ($type === 'ordering') {
                $raw = $data['correct_answer'] ?? '';
                $arr = is_string($raw) ? json_decode($raw, true) : $raw;
                if (!is_array($arr) || count($arr) < 2) {
                    $errors[] = 'Ordering answer is invalid';
                }
            } elseif ($type === 'matching') {
                /* validated above */
            } elseif (!array_key_exists('correct_answer', $data)) {
                $errors[] = 'Correct answer is required for auto-gradable questions';
            } else {
                $ca = $data['correct_answer'];
                if ($ca === '' || $ca === null) {
                    $errors[] = 'Correct answer is required for auto-gradable questions';
                }
            }
        }

        // Validate points
        if (isset($data['points'])) {
            $points = (int) $data['points'];
            if ($points < 1) {
                $errors[] = 'Points must be at least 1';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get default options for question type
     *
     * @param string $type
     * @return array
     */
    public function getDefaultOptions(string $type): array
    {
        switch ($type) {
            case 'multiple_choice':
                return [
                    'Option A',
                    'Option B',
                    'Option C',
                    'Option D'
                ];

            case 'true_false':
                return [
                    'True',
                    'False'
                ];

            case 'matching':
                return [
                    'left' => ['Item 1', 'Item 2', 'Item 3'],
                    'right' => ['Match A', 'Match B', 'Match C']
                ];

            case 'ordering':
                return ['First step', 'Second step', 'Third step'];

            case 'multiple_response':
                return ['Option A', 'Option B', 'Option C', 'Option D'];

            case 'fill_blank':
            case 'short_answer':
            case 'essay':
            default:
                return [];
        }
    }

    /**
     * Get question type options for select dropdown
     *
     * @return array
     */
    public function getQuestionTypeOptions(): array
    {
        $options = [];

        foreach (self::QUESTION_TYPES as $key => $type) {
            $options[$key] = $type['label'];
        }

        return $options;
    }

    /**
     * Get auto-gradable question types
     *
     * @return array
     */
    public function getAutoGradableTypes(): array
    {
        return array_filter(self::QUESTION_TYPES, function ($type) {
            return $type['auto_gradable'];
        });
    }

    /**
     * Get manual grading question types
     *
     * @return array
     */
    public function getManualGradingTypes(): array
    {
        return array_filter(self::QUESTION_TYPES, function ($type) {
            return !$type['auto_gradable'];
        });
    }

    /**
     * Convert question type from frontend format to database format
     *
     * @param string $frontendType
     * @return string
     */
    public function convertFrontendToDatabase(string $frontendType): string
    {
        $mapping = [
            'multiple-choice' => 'multiple_choice',
            'multiple-response' => 'multiple_response',
            'true-false' => 'true_false',
            'fill-blank' => 'fill_blank',
            'short-answer' => 'short_answer',
            'ordering' => 'ordering',
            'essay' => 'essay',
            'matching' => 'matching'
        ];

        return $mapping[$frontendType] ?? $frontendType;
    }

    /**
     * Convert question type from database format to frontend format
     *
     * @param string $databaseType
     * @return string
     */
    public function convertDatabaseToFrontend(string $databaseType): string
    {
        $mapping = [
            'multiple_choice' => 'multiple-choice',
            'multiple_response' => 'multiple-response',
            'true_false' => 'true-false',
            'fill_blank' => 'fill-blank',
            'short_answer' => 'short-answer',
            'ordering' => 'ordering',
            'essay' => 'essay',
            'matching' => 'matching'
        ];

        return $mapping[$databaseType] ?? $databaseType;
    }
}
