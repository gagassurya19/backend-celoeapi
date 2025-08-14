<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Swagger Documentation Routes
$route['swagger'] = 'swagger/index';
$route['swagger/(:any)'] = 'swagger/$1';

// API Routes
$route['api/etl/run']['POST'] = 'api/etl/run';
$route['api/etl/status']['GET'] = 'api/etl/status';
$route['api/etl/logs']['GET'] = 'api/etl/logs';

// User Activity ETL API Routes
$route['api/user_activity_etl/status']['GET'] = 'api/user_activity_etl/status';
$route['api/user_activity_etl/run_pipeline']['POST'] = 'api/user_activity_etl/run_pipeline';
$route['api/user_activity_etl/run_activity_counts']['POST'] = 'api/user_activity_etl/run_activity_counts';
$route['api/user_activity_etl/run_user_counts']['POST'] = 'api/user_activity_etl/run_user_counts';
$route['api/user_activity_etl/run_main_etl']['POST'] = 'api/user_activity_etl/run_main_etl';
$route['api/user_activity_etl/results']['GET'] = 'api/user_activity_etl/results';
$route['api/user_activity_etl/results/(:any)']['GET'] = 'api/user_activity_etl/results_date/$1';
$route['api/user_activity_etl/export']['GET'] = 'api/user_activity_etl/export';
$route['api/user_activity_etl/logs']['GET'] = 'api/user_activity_etl/logs';
$route['api/user_activity_etl/clear']['POST'] = 'api/user_activity_etl/clear';
$route['api/user_activity_etl/clean_data']['POST'] = 'api/user_activity_etl/clean_data';
$route['api/user_activity_etl/scheduler']['GET'] = 'api/user_activity_etl/scheduler';
$route['api/user_activity_etl/scheduler/initialize']['POST'] = 'api/user_activity_etl/scheduler_initialize';

// ETL Chart API Routes
$route['api/etl/chart/fetch']['GET'] = 'api/ETL_chart/fetch';
$route['api/etl/chart/logs']['GET'] = 'api/ETL_chart/logs';
$route['api/etl/chart/realtime-logs']['GET'] = 'api/ETL_chart/realtime_logs';
$route['api/etl/chart/stream']['GET'] = 'api/ETL_chart/stream';
$route['api/etl/chart/log']['POST'] = 'api/ETL_chart/log';
$route['api/etl/chart/clear-stuck']['POST'] = 'api/ETL_chart/clear_stuck';

// Analytics API Routes
$route['api/analytics/health']['GET'] = 'api/analytics/health';
$route['api/analytics/health']['OPTIONS'] = 'api/analytics/health_options';
$route['api/analytics/courses']['GET'] = 'api/analytics/courses';
$route['api/analytics/courses']['OPTIONS'] = 'api/analytics/courses_options';
