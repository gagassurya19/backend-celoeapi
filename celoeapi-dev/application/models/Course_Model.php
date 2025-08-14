<?php
    class Course_Model extends CI_Model {

        public function __construct()
        {
            parent::__construct();
            $this->load->database();
        }

        public function get_courses()
        {
            $this->db->select('id,fullname,shortname');
            $this->db->from('course');
            return $this->db->get()->result();
        }
            
        public function get_course_log()
        {           
            $this->db->select('logcourse.courseid, module.name');
            $this->db->from('logstore_standard_log as logcourse');
            $this->db->join('course as course','course.id = logcourse.courseid');
            $this->db->join('course_modules as course_module','course_module.course = course.id AND course_module.id = logcourse.objectid');
            $this->db->join('modules as module','module.id = course_module.module');
            $result = $this->db->get()->result();
            return $result; 
        }
        
    }