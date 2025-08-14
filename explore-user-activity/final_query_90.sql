SELECT 
	faculty.id AS `Faculty Study ID`,
	program.id AS `Program Study ID`,
	subjects.idnumber AS `Subject ID`,
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
		/ NULLIF(ac.active_days, 0),
		1
	) AS `AVG Activity per Student per Day`
FROM mdl_course subjects
LEFT JOIN mdl_course_categories program
	ON subjects.category = program.id
	AND program.depth = 2
LEFT JOIN mdl_course_categories faculty
	ON faculty.id = program.parent
	AND faculty.depth = 1
LEFT JOIN celoeapi.activity_counts_etl ac ON subjects.id = ac.courseid
LEFT JOIN celoeapi.user_counts_etl uc ON subjects.id = uc.courseid
WHERE subjects.visible = 1 and subjects.idnumber is not null and subjects.idnumber != ''
ORDER BY subjects.id;