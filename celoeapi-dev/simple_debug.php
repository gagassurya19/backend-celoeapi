<?php
// Simple debug script untuk memeriksa database
try {
    // Connect to celoeapi database
    $dsn = "mysql:host=localhost;port=3302;dbname=celoeapi;charset=utf8mb4";
    $pdo = new PDO($dsn, 'moodleuser', 'moodlepass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CELOEAPI DATABASE CONNECTION SUCCESS ===\n";
    
    // Check cp_ tables
    $tables = [
        'cp_activity_summary',
        'cp_student_quiz_detail', 
        'cp_student_assignment_detail',
        'cp_student_profile',
        'cp_course_summary',
        'cp_student_resource_access'
    ];
    
    foreach ($tables as $table) {
        echo "\n--- Table: $table ---\n";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns: " . count($columns) . "\n";
        
        // Check row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Row count: " . $row['count'] . "\n";
        
        // Show sample data
        if ($row['count'] > 0) {
            $stmt = $pdo->query("SELECT * FROM $table LIMIT 3");
            $sample = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Sample data:\n";
            foreach ($sample as $i => $data) {
                echo "  Row " . ($i+1) . ": " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
    
    // Check specific quiz data for course 2
    echo "\n=== CHECKING QUIZ DATA FOR COURSE 2 ===\n";
    
    // Check activity summary
    $stmt = $pdo->query("SELECT * FROM cp_activity_summary WHERE course_id = 2 AND activity_type = 'quiz'");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Quiz activities in course 2: " . count($activities) . "\n";
    foreach ($activities as $activity) {
        echo "  Quiz ID: " . $activity['activity_id'] . 
             ", Attempted: " . $activity['attempted_count'] . 
             ", Graded: " . $activity['graded_count'] . "\n";
    }
    
    // Check student quiz detail
    $stmt = $pdo->query("SELECT * FROM cp_student_quiz_detail WHERE course_id = 2 LIMIT 5");
    $quizDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Student quiz details in course 2: " . count($quizDetails) . "\n";
    if (count($quizDetails) > 0) {
        foreach ($quizDetails as $detail) {
            echo "  Student: " . $detail['user_id'] . 
                 ", Quiz: " . $detail['activity_id'] . 
                 ", Score: " . $detail['score'] . "\n";
        }
    }
    
    // Check Moodle database connection
    echo "\n=== CHECKING MOODLE DATABASE ===\n";
    try {
        $moodleDsn = "mysql:host=localhost;port=3302;dbname=moodle;charset=utf8mb4";
        $moodlePdo = new PDO($moodleDsn, 'moodleuser', 'moodlepass');
        $moodlePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "Moodle connection: SUCCESS\n";
        
        // Check quiz data in Moodle
        $stmt = $moodlePdo->query("SELECT id, course, name FROM mdl_quiz WHERE course = 2");
        $moodleQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Quizzes in Moodle course 2: " . count($moodleQuizzes) . "\n";
        foreach ($moodleQuizzes as $quiz) {
            echo "  Quiz ID: " . $quiz['id'] . ", Name: " . $quiz['name'] . "\n";
        }
        
        // Check quiz attempts
        $stmt = $moodlePdo->query("SELECT COUNT(*) as count FROM mdl_quiz_attempts qa JOIN mdl_quiz q ON qa.quiz = q.id WHERE q.course = 2");
        $attempts = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Total quiz attempts in course 2: " . $attempts['count'] . "\n";
        
    } catch (Exception $e) {
        echo "Moodle connection failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
