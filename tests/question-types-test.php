<?php
/**
 * Question Types Test
 * 
 * This file tests all question types to ensure they work properly
 * 
 * @package Sikshya\Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SikshyaQuestionTypesTest
{
    private $questionTypeService;
    private $quizModel;

    public function __construct()
    {
        $this->questionTypeService = new \Sikshya\Services\QuestionTypeService();
        $this->quizModel = new \Sikshya\Models\Quiz();
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "<h2>🧪 Sikshya Question Types Test Results</h2>\n";
        
        $this->testQuestionTypeService();
        $this->testQuestionTypeValidation();
        $this->testQuestionTypeConversion();
        $this->testQuizScoring();
        $this->testQuestionTypeFeatures();
        
        echo "<h3>✅ All tests completed!</h3>\n";
    }

    /**
     * Test QuestionTypeService functionality
     */
    private function testQuestionTypeService()
    {
        echo "<h3>📋 Testing QuestionTypeService</h3>\n";
        
        // Test getting all question types
        $types = $this->questionTypeService->getAllQuestionTypes();
        $expectedTypes = ['multiple_choice', 'true_false', 'fill_blank', 'essay', 'matching'];
        
        foreach ($expectedTypes as $type) {
            if (isset($types[$type])) {
                echo "✅ {$type} - " . $types[$type]['label'] . "\n";
            } else {
                echo "❌ {$type} - Missing\n";
            }
        }
        
        // Test individual type methods
        $this->testIndividualTypeMethods();
    }

    /**
     * Test individual question type methods
     */
    private function testIndividualTypeMethods()
    {
        echo "<h4>🔍 Testing Individual Type Methods</h4>\n";
        
        $testCases = [
            'multiple_choice' => [
                'supports_options' => true,
                'supports_single_answer' => true,
                'auto_gradable' => true,
                'requires_text_input' => false
            ],
            'true_false' => [
                'supports_options' => true,
                'supports_single_answer' => true,
                'auto_gradable' => true,
                'requires_text_input' => false
            ],
            'fill_blank' => [
                'supports_options' => false,
                'supports_single_answer' => true,
                'auto_gradable' => true,
                'requires_text_input' => true
            ],
            'essay' => [
                'supports_options' => false,
                'supports_single_answer' => false,
                'auto_gradable' => false,
                'requires_text_input' => true
            ],
            'matching' => [
                'supports_options' => true,
                'supports_single_answer' => false,
                'supports_multiple_answers' => true,
                'auto_gradable' => true,
                'requires_text_input' => false
            ]
        ];
        
        foreach ($testCases as $type => $expected) {
            echo "<strong>Testing {$type}:</strong>\n";
            
            foreach ($expected as $method => $expectedValue) {
                $actualValue = $this->questionTypeService->$method($type);
                $status = $actualValue === $expectedValue ? '✅' : '❌';
                echo "  {$status} {$method}: Expected {$expectedValue}, Got {$actualValue}\n";
            }
        }
    }

    /**
     * Test question type validation
     */
    private function testQuestionTypeValidation()
    {
        echo "<h3>✅ Testing Question Type Validation</h3>\n";
        
        $testCases = [
            [
                'type' => 'multiple_choice',
                'data' => [
                    'question_text' => 'What is 2+2?',
                    'options' => ['3', '4', '5', '6'],
                    'correct_answer' => '4',
                    'points' => 1
                ],
                'expected_valid' => true
            ],
            [
                'type' => 'true_false',
                'data' => [
                    'question_text' => 'The sky is blue.',
                    'options' => ['True', 'False'],
                    'correct_answer' => 'True',
                    'points' => 1
                ],
                'expected_valid' => true
            ],
            [
                'type' => 'fill_blank',
                'data' => [
                    'question_text' => 'The capital of France is _____.',
                    'correct_answer' => 'Paris',
                    'points' => 1
                ],
                'expected_valid' => true
            ],
            [
                'type' => 'essay',
                'data' => [
                    'question_text' => 'Explain the theory of relativity.',
                    'points' => 5
                ],
                'expected_valid' => true
            ],
            [
                'type' => 'matching',
                'data' => [
                    'question_text' => 'Match the countries with their capitals.',
                    'options' => [
                        'left' => ['France', 'Germany', 'Italy'],
                        'right' => ['Paris', 'Berlin', 'Rome']
                    ],
                    'correct_answer' => ['France' => 'Paris', 'Germany' => 'Berlin', 'Italy' => 'Rome'],
                    'points' => 3
                ],
                'expected_valid' => true
            ],
            [
                'type' => 'invalid_type',
                'data' => [
                    'question_text' => 'This should fail.',
                    'points' => 1
                ],
                'expected_valid' => false
            ]
        ];
        
        foreach ($testCases as $testCase) {
            $result = $this->questionTypeService->validateQuestionData($testCase['type'], $testCase['data']);
            $status = $result['valid'] === $testCase['expected_valid'] ? '✅' : '❌';
            echo "{$status} {$testCase['type']}: " . ($result['valid'] ? 'Valid' : 'Invalid');
            
            if (!$result['valid'] && !empty($result['errors'])) {
                echo " (Errors: " . implode(', ', $result['errors']) . ")";
            }
            echo "\n";
        }
    }

    /**
     * Test question type conversion
     */
    private function testQuestionTypeConversion()
    {
        echo "<h3>🔄 Testing Question Type Conversion</h3>\n";
        
        $conversionTests = [
            'multiple-choice' => 'multiple_choice',
            'true-false' => 'true_false',
            'fill-blank' => 'fill_blank',
            'essay' => 'essay',
            'matching' => 'matching'
        ];
        
        foreach ($conversionTests as $frontend => $database) {
            // Test frontend to database conversion
            $converted = $this->questionTypeService->convertFrontendToDatabase($frontend);
            $status1 = $converted === $database ? '✅' : '❌';
            echo "{$status1} Frontend '{$frontend}' → Database '{$converted}'\n";
            
            // Test database to frontend conversion
            $convertedBack = $this->questionTypeService->convertDatabaseToFrontend($database);
            $status2 = $convertedBack === $frontend ? '✅' : '❌';
            echo "{$status2} Database '{$database}' → Frontend '{$convertedBack}'\n";
        }
    }

    /**
     * Test quiz scoring with different question types
     */
    private function testQuizScoring()
    {
        echo "<h3>📊 Testing Quiz Scoring</h3>\n";
        
        // Create a mock quiz with different question types
        $mockQuizId = 999;
        $mockQuestions = [
            [
                'id' => 1,
                'question_type' => 'multiple_choice',
                'question_text' => 'What is 2+2?',
                'options' => ['3', '4', '5', '6'],
                'correct_answer' => '4',
                'points' => 1
            ],
            [
                'id' => 2,
                'question_type' => 'true_false',
                'question_text' => 'The sky is blue.',
                'options' => ['True', 'False'],
                'correct_answer' => 'True',
                'points' => 1
            ],
            [
                'id' => 3,
                'question_type' => 'fill_blank',
                'question_text' => 'The capital of France is _____.',
                'correct_answer' => 'Paris',
                'points' => 2
            ],
            [
                'id' => 4,
                'question_type' => 'essay',
                'question_text' => 'Explain gravity.',
                'points' => 5
            ]
        ];
        
        // Mock the getQuestions method
        $this->mockQuizQuestions($mockQuizId, $mockQuestions);
        
        // Test different answer scenarios
        $testScenarios = [
            [
                'name' => 'Perfect Score',
                'answers' => [
                    1 => '4',
                    2 => 'True',
                    3 => 'Paris'
                ],
                'expected_score' => 100
            ],
            [
                'name' => 'Partial Score',
                'answers' => [
                    1 => '4',
                    2 => 'False',
                    3 => 'London'
                ],
                'expected_score' => 25
            ],
            [
                'name' => 'No Answers',
                'answers' => [],
                'expected_score' => 0
            ]
        ];
        
        foreach ($testScenarios as $scenario) {
            $score = $this->quizModel->calculateScore($mockQuizId, $scenario['answers']);
            $status = $score === $scenario['expected_score'] ? '✅' : '❌';
            echo "{$status} {$scenario['name']}: Expected {$scenario['expected_score']}%, Got {$score}%\n";
        }
    }

    /**
     * Test question type features
     */
    private function testQuestionTypeFeatures()
    {
        echo "<h3>🎯 Testing Question Type Features</h3>\n";
        
        // Test auto-gradable types
        $autoGradableTypes = $this->questionTypeService->getAutoGradableTypes();
        echo "✅ Auto-gradable types: " . implode(', ', array_keys($autoGradableTypes)) . "\n";
        
        // Test manual grading types
        $manualGradingTypes = $this->questionTypeService->getManualGradingTypes();
        echo "✅ Manual grading types: " . implode(', ', array_keys($manualGradingTypes)) . "\n";
        
        // Test default options
        foreach (['multiple_choice', 'true_false', 'matching'] as $type) {
            $defaultOptions = $this->questionTypeService->getDefaultOptions($type);
            echo "✅ {$type} default options: " . json_encode($defaultOptions) . "\n";
        }
        
        // Test question type options for dropdown
        $dropdownOptions = $this->questionTypeService->getQuestionTypeOptions();
        echo "✅ Dropdown options: " . implode(', ', array_values($dropdownOptions)) . "\n";
    }

    /**
     * Mock quiz questions for testing
     */
    private function mockQuizQuestions($quizId, $questions)
    {
        // This is a simple mock - in a real test environment, you'd use a proper mocking framework
        // For now, we'll just store the questions in a static property or use a different approach
        add_post_meta($quizId, '_sikshya_quiz_questions', $questions, true);
    }
}

// Run the tests if this file is accessed directly
if (isset($_GET['run_question_tests']) && current_user_can('manage_options')) {
    $test = new SikshyaQuestionTypesTest();
    $test->runAllTests();
} 