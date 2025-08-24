-- Fix accessed_count for CP Activity Summary
-- This script fixes the accessed_count field based on available interaction data

USE celoeapi;

-- 1. Fix accessed_count for assignments based on submission_count
UPDATE cp_activity_summary 
SET accessed_count = submission_count 
WHERE activity_type = 'assign' 
AND accessed_count IS NULL 
AND submission_count > 0;

-- 2. Fix accessed_count for quizzes based on attempted_count
UPDATE cp_activity_summary 
SET accessed_count = attempted_count 
WHERE activity_type = 'quiz' 
AND accessed_count IS NULL 
AND attempted_count > 0;

-- 3. Fix accessed_count for resources (keep existing logic)
UPDATE cp_activity_summary 
SET accessed_count = COALESCE(accessed_count, 0) 
WHERE activity_type = 'resource' 
AND accessed_count IS NULL;

-- 4. Set accessed_count = 1 for activities that have any interaction but no access count
UPDATE cp_activity_summary 
SET accessed_count = 1 
WHERE accessed_count IS NULL 
AND (
    submission_count > 0 
    OR attempted_count > 0 
    OR graded_count > 0
);

-- 5. Set accessed_count = 0 for remaining activities with no interaction
UPDATE cp_activity_summary 
SET accessed_count = 0 
WHERE accessed_count IS NULL;

-- 6. Show results
SELECT 
    activity_type,
    COUNT(*) as total_records,
    SUM(CASE WHEN accessed_count IS NOT NULL THEN 1 ELSE 0 END) as with_accessed_count,
    SUM(CASE WHEN accessed_count IS NULL THEN 1 ELSE 0 END) as without_accessed_count,
    AVG(COALESCE(accessed_count, 0)) as avg_accessed_count
FROM cp_activity_summary 
GROUP BY activity_type;

-- 7. Show sample data after fix
SELECT 
    id,
    activity_type,
    activity_name,
    accessed_count,
    submission_count,
    attempted_count,
    graded_count
FROM cp_activity_summary 
ORDER BY id 
LIMIT 10;
