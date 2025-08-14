<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	https://codeigniter.com/user_guide/general/hooks.html
|
*/

// CORS Hook - Centralized CORS handling for all endpoints (CI3 compatible)
$hook['pre_controller'] = array(
    'class'    => 'Cors',
    'function' => 'handle_cors',
    'filename' => 'cors.php',
    'filepath' => 'hooks'
);
