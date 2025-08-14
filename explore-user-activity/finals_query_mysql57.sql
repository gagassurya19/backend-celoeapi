DROP VIEW IF EXISTS ActivityCounts;
DROP VIEW IF EXISTS UserCounts;

CREATE VIEW ActivityCounts AS
SELECT
    courseid,
    COUNT(CASE WHEN component = 'mod_resource' THEN 1 END) AS File_Views,
    COUNT(CASE WHEN component = 'mod_page' THEN 1 END) AS Video_Views,
    COUNT(CASE WHEN component = 'mod_forum' THEN 1 END) AS Forum_Views,
    COUNT(CASE WHEN component = 'mod_quiz' THEN 1 END) AS Quiz_Views,
    COUNT(CASE WHEN component = 'mod_assign' THEN 1 END) AS Assignment_Views,
    COUNT(CASE WHEN component = 'mod_url' THEN 1 END) AS URL_Views,
    DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
FROM mdl_logstore_standard_log
WHERE contextlevel = 70
  AND action = 'viewed'
GROUP BY courseid;

CREATE VIEW UserCounts AS
SELECT
    ctx.instanceid AS courseid,
    COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
    COUNT(DISTINCT CASE WHEN ra.roleid = 4 THEN ra.userid END) AS Num_Teachers
FROM mdl_role_assignments ra
JOIN mdl_context ctx ON ra.contextid = ctx.id
WHERE ctx.contextlevel = 50
GROUP BY ctx.instanceid;

SELECT 
    categories.idnumber AS `Course ID`,
    subjects.idnumber AS `ID Number`,
    COALESCE(uc.Num_Teachers, 0) AS `Num Teac`,
    COALESCE(uc.Num_Students, 0) AS `Num Stud`,
    COALESCE(ac.File_Views, 0) AS `File`,
    COALESCE(ac.Video_Views, 0) AS `Video`,
    COALESCE(ac.Forum_Views, 0) AS `Forum`,
    COALESCE(ac.Quiz_Views, 0) AS `Quiz`,
    COALESCE(ac.Assignment_Views, 0) AS `Assignment`,
    COALESCE(ac.URL_Views, 0) AS `URL`,
    (COALESCE(ac.File_Views, 0) + COALESCE(ac.Video_Views, 0) + COALESCE(ac.Forum_Views, 0) + COALESCE(ac.Quiz_Views, 0) + COALESCE(ac.Assignment_Views, 0) + COALESCE(ac.URL_Views, 0)) AS `Sum`,
    ROUND(
        (COALESCE(ac.File_Views, 0) + COALESCE(ac.Video_Views, 0) + COALESCE(ac.Forum_Views, 0) + COALESCE(ac.Quiz_Views, 0) + COALESCE(ac.Assignment_Views, 0) + COALESCE(ac.URL_Views, 0))
        / NULLIF(uc.Num_Students, 0)
        / NULLIF(ac.ActiveDays, 0),
        1
    ) AS `AVG Activity per Student per Day`
FROM mdl_course subjects
LEFT JOIN mdl_course_categories categories ON subjects.category = categories.id
LEFT JOIN ActivityCounts ac ON subjects.id = ac.courseid
LEFT JOIN UserCounts uc ON subjects.id = uc.courseid
WHERE subjects.visible = 1
ORDER BY subjects.id;

DROP VIEW ActivityCounts;
DROP VIEW UserCounts;