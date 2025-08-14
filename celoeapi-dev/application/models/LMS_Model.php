<?php
    class LMS_Model extends CI_model {

        public function get_courses($course_id)
        {
            $this->db->select('id,fullname,shortname,idnumber');
            $this->db->from('mdl_course');
            $this->db->where_in("idnumber",$course_id);
            $result =  $this->db->get()->result();
            return $result;
        }

        public function get_course($course_id) {
            $this->db->select('id');
            $this->db->from('mdl_course');
            $this->db->where("idnumber", $course_id);
            $this->db->limit(1);
            $result =  $this->db->get()->row_array();
            return $result;
        }

        public function get_course_by_id($course_id) {
            $this->db->from('mdl_course');
            $this->db->where("id", $course_id);
            $this->db->limit(1);
            $result =  $this->db->get()->row_array();
            return $result;
        }
            
        public function get_course_log($course_id, $date_from = '', $date_to = '')
        {           
            $this->db->select('mdl_logstore_standard_log.courseid, mdl_logstore_standard_log.userid, module.name, mdl_logstore_standard_log.action, mdl_logstore_standard_log.target');
            $this->db->join('mdl_course as course','course.id = mdl_logstore_standard_log.courseid');
            $this->db->join('mdl_course_modules as course_module','course_module.course=course.id AND course_module.id =  mdl_logstore_standard_log.contextinstanceid',"LEFT");
            $this->db->join('mdl_modules as module','module.id=course_module.module',"LEFT");
            $this->db->where_in("course.idnumber",$course_id);
            if(!empty($date_from) && !empty($date_to)){
                $this->db->where('mdl_logstore_standard_log.timecreated >=', $date_from);
                $this->db->where('mdl_logstore_standard_log.timecreated <=', $date_to);
            }
            $result = $this->db->get('mdl_logstore_standard_log')->result();
            return $result; 
        }
        
        public function get_course_teachers($course_id){
            $this->db->select('u.id as id, u.username as username, CONCAT(u.firstname," ",u.lastname) as name');
            $this->db->from('mdl_user as u');
            $this->db->join('mdl_role_assignments as ra','ra.userid = u.id');
            $this->db->join('mdl_role as r','r.id = ra.roleid');
            $this->db->join('mdl_context as cxt','cxt.id = ra.contextid');
            $this->db->join('mdl_course as c','c.id = cxt.instanceid');
            $this->db->where('cxt.contextlevel', 50);
            $this->db->where('c.idnumber', $course_id);
            $this->db->where('ra.roleid', 3);
            $result = $this->db->get()->result();
            return $result;
        }
    }
