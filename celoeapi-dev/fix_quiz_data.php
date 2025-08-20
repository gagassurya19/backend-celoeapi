<?php
// Script untuk memperbaiki data inconsistency di quiz
require_once 'application/config/database.php';

try {
    // Connect to database
    $dsn = "mysql:host=db;dbname=celoeapi;charset=utf8mb4";
    $pdo = new PDO($dsn, 'moodleuser', 'moodlepass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== FIXING QUIZ DATA INCONSISTENCY ===\n";
    
    // 1. Check if course_id column exists in cp_student_quiz_detail
    $stmt = $pdo->query("DESCRIBE cp_student_quiz_detail");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasCourseId = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'course_id') {
            $hasCourseId = true;
            break;
        }
    }
    
    if (!$hasCourseId) {
        echo "Adding course_id column to cp_student_quiz_detail...\n";
        $pdo->exec("ALTER TABLE cp_student_quiz_detail ADD COLUMN course_id INT AFTER quiz_id");
        echo "Column added successfully!\n";
    } else {
        echo "course_id column already exists.\n";
    }
    
    // 2. Update course_id for existing quiz records
    echo "\nUpdating course_id for existing quiz records...\n";
    
    // Get quiz-course mapping from cp_activity_summary
    $stmt = $pdo->query("SELECT activity_id, course_id FROM cp_activity_summary WHERE activity_type = 'quiz'");
    $quizCourseMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $quizCourseMap[$row['activity_id']] = $row['course_id'];
    }
    
    echo "Found " . count($quizCourseMap) . " quiz-course mappings:\n";
    foreach ($quizCourseMap as $quizId => $courseId) {
        echo "  Quiz $quizId -> Course $courseId\n";
    }
    
    // Update course_id in cp_student_quiz_detail
    $updated = 0;
    foreach ($quizCourseMap as $quizId => $courseId) {
        $stmt = $pdo->prepare("UPDATE cp_student_quiz_detail SET course_id = ? WHERE quiz_id = ?");
        $result = $stmt->execute([$courseId, $quizId]);
        if ($result) {
            $rowCount = $stmt->rowCount();
            $updated += $rowCount;
            echo "  Updated $rowCount records for quiz $quizId (course $courseId)\n";
        }
    }
    
    echo "Total records updated: $updated\n";
    
    // 3. Verify the fix
    echo "\n=== VERIFYING THE FIX ===\n";
    
    // Check quiz data for course 2
    $stmt = $pdo->query("SELECT * FROM cp_student_quiz_detail WHERE course_id = 2");
    $course2Quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Quiz details in course 2: " . count($course2Quizzes) . "\n";
    
    foreach ($course2Quizzes as $quiz) {
        echo "  Quiz: " . $quiz['quiz_id'] . 
             ", Student: " . $quiz['user_id'] . 
             ", Score: " . $quiz['nilai'] . "\n";
    }
    
    // 4. Check consistency between summary and detail
    echo "\n=== CHECKING DATA CONSISTENCY ===\n";
    
    $stmt = $pdo->query("
        SELECT 
            a.activity_id,
            a.course_id,
            a.attempted_count,
            a.graded_count,
            COUNT(d.id) as detail_count
        FROM cp_activity_summary a
        LEFT JOIN cp_student_quiz_detail d ON a.activity_id = d.quiz_id AND a.course_id = d.course_id
        WHERE a.activity_type = 'quiz'
        GROUP BY a.activity_id, a.course_id, a.attempted_count, a.graded_count
    ");
    
    $consistency = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($consistency as $row) {
        $status = ($row['attempted_count'] == $row['detail_count']) ? "✅" : "❌";
        echo "  $status Quiz " . $row['activity_id'] . " (Course " . $row['course_id'] . "): " .
             "Summary shows " . $row['attempted_count'] . " attempts, " .
             "Detail has " . $row['detail_count'] . " records\n";
    }
    
    echo "\n=== FIX COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

