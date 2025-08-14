<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';
     
class LMSReport extends REST_Controller {

    public function __construct() {
       parent::__construct();
       $this->load->database();
       $this->load->model('LMS_Model');
    }
    
    public function hello_get(){
        echo "world 4";
    }

    public function course_log_post() 
    {     
        $date_from = $this->input->post("date_from", true);
        $date_to = $this->input->post("date_to", true);
        $course_ids = $this->input->post("course_id", true);
        // $course_ids = explode(",", $course_ids);
        $courses = $this->LMS_Model->get_courses($course_ids); 
        $log_data = $this->LMS_Model->get_course_log($course_ids, $date_from, $date_to);  
        $sorted_log = array();
        foreach($log_data as $log){
            if(!array_key_exists($log->courseid,$sorted_log)){
                $sorted_log[$log->courseid] = [];
            }

            if(!array_key_exists($log->userid,$sorted_log[$log->courseid])){
                $log_stat = $this->get_log_stat_object();
                $sorted_log[$log->courseid][$log->userid] = $log_stat;
            }

            $log_stat = $sorted_log[$log->courseid][$log->userid];
            if(array_key_exists($log->name,$log_stat) && $log->action != "viewed"){
                $log_stat[$log->name] += 1;
            }
            $sorted_log[$log->courseid][$log->userid] = $log_stat;
        }     
                
        foreach($courses as $course){
            $log_results = $this->get_log_stat_object();
            $teachers = $this->LMS_Model->get_course_teachers($course->idnumber);
            foreach($teachers as $teacher){
                if(array_key_exists($course->id,$sorted_log)){
                    if(array_key_exists($teacher->id,$sorted_log[$course->id])){
                        $change_array = $sorted_log[$course->id][$teacher->id];
                        foreach($change_array as $module => $count){
                            $log_results[$module] += $count;
                        }
                    }
                }
            }

            $course->changes = $log_results;
        }     
        $this->response([
            'status' => true,
            'data' => $courses
        ], REST_Controller::HTTP_OK); 
    }

    public function teacher_activities_post(){
        $date_from = $this->input->post("date_from", true);
        $date_to = $this->input->post("date_to", true);
        $course_ids = $this->input->post("course_id", true);
        // $course_ids = explode(',', $course_ids);

        $log_data = $this->LMS_Model->get_course_log($course_ids, $date_from, $date_to); 
        
        $sorted_log = array();
        foreach($log_data as $log){
            if(!array_key_exists($log->courseid,$sorted_log)){
                $sorted_log[$log->courseid] = [];
            }

            if(!array_key_exists($log->userid,$sorted_log[$log->courseid])){
                $log_stat = $this->get_log_stat_object();
                $sorted_log[$log->courseid][$log->userid] = $log_stat;
            }

            $log_stat = $sorted_log[$log->courseid][$log->userid];
            if(array_key_exists($log->name,$log_stat)){
                $log_stat[$log->name] += 1;
            }
            if(empty($log->name) && $log->action == "viewed" && $log->target == 'course'){
                $log_stat["viewed"] += 1;
            }
            if(empty($log->name) && $log->action == "deleted"){
                $log_stat["deleted"] += 1;
            }
            $sorted_log[$log->courseid][$log->userid] = $log_stat;
        }   

        $sorted_course = [];
        $courses = $this->LMS_Model->get_courses($course_ids); 
        // var_dump($sorted_log);
        foreach($courses as $course){
            $log_results = [];
            $teachers = $this->LMS_Model->get_course_teachers($course->idnumber);
            foreach($teachers as $teacher){
                $log_teacher = [
                    'username' => $teacher->username,
                    'name' => $teacher->name
                ];


                if(array_key_exists($course->id,$sorted_log)){
                    if(array_key_exists($teacher->id,$sorted_log[$course->id])){
                        $merged_array = array_merge($log_teacher, $sorted_log[$course->id][$teacher->id]);
                    }else{
                        $merged_array = array_merge($log_teacher, $this->get_log_stat_object());
                    }
                }else{
                    $merged_array = array_merge($log_teacher, $this->get_log_stat_object());
                }
                $log_results[] = $merged_array;
            }
            $course->changes = $log_results;
            $sorted_course[] = $course;
        }

        $this->response([
            'status' => true,
            'data' => $sorted_course
        ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
    }

    private function get_log_stat_object()
    {
        $log_stat = array();
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
        $log_stat['groupselect'] = 0;
        $log_stat['hvp'] = 0;
        $log_stat['activequiz'] = 0;
        $log_stat['vpl'] = 0;
        $log_stat['zoom'] = 0;
        $log_stat['h5pactivity'] = 0;
        $log_stat['viewed'] = 0;
        $log_stat['deleted'] = 0;
        return $log_stat;
    }
}
