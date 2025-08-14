CREATE USER 'celoeapi'@'%' IDENTIFIED BY 'celoeapi';
GRANT ALL PRIVILEGES ON moodle.* TO 'celoeapi'@'%';
FLUSH PRIVILEGES;
