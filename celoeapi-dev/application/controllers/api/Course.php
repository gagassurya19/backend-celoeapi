<?php
    use Restserver\Libraries\REST_Controller;
    defined('BASEPATH') OR exit('No direct script access allowed');

    require APPPATH . 'libraries/REST_Controller.php';
    require APPPATH . 'libraries/Format.php';

    class Course extends REST_Controller {

        function __construct()
        {
            parent::__construct();
            $this->load->database();
            $this->load->model('Course_Model', 'm_Course');
        }

        // Get Data
        public function index_get() 
        {      
            $courses = $this->m_Course->get_courses(); 

            $log_data = $this->m_Course->get_course_log();  
            $sorted_log = array();
            foreach($log_data as $log){
                if(!array_key_exists($log->courseid,$sorted_log)){
                    $log_stat = $this->get_log_stat_object();
                    $sorted_log[$log->courseid] = $log_stat;
                }
                $log_stat = $sorted_log[$log->courseid];
                if(array_key_exists($log->name,$log_stat)){
                    $log_stat[$log->name] += 1;
                }
                $sorted_log[$log->courseid] = $log_stat;
            }     
                    
            foreach($courses as $course){
                if(array_key_exists($course->id,$sorted_log)){
                    $course->changes = $sorted_log[$course->id];
                }else{
                    $course->changes = $this->get_log_stat_object();
                }
            }     
            $this->response([
                'status' => true,
                'data' => $courses
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        private function get_log_stat_object()
        {
            $log_stat = array();
            $log_stat['quiz'] = 0;
            $log_stat['resource'] = 0;
            $log_stat['assign'] = 0;
            $log_stat['assignment'] = 0;
            $log_stat['book'] = 0;
            $log_stat['chat'] = 0;
            $log_stat['choice'] = 0;
            $log_stat['data'] = 0;
            $log_stat['feedback'] = 0;
            $log_stat['folder'] = 0;
            $log_stat['forum'] = 0;
            $log_stat['glossary'] = 0;
            $log_stat['groupselect'] = 0;
            $log_stat['hvp'] = 0;
            $log_stat['imscp'] = 0;
            $log_stat['label'] = 0;
            $log_stat['lesson'] = 0;
            $log_stat['lti'] = 0;
            $log_stat['page'] = 0;
            $log_stat['quiz'] = 0;
            $log_stat['resource'] = 0;
            $log_stat['scorm'] = 0;
            $log_stat['survey'] = 0;
            $log_stat['url'] = 0;
            $log_stat['wiki'] = 0;
            $log_stat['workshop'] = 0;
            return $log_stat;
        }
    
    }