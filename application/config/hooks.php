<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$hook['display_override'][] = array(
    'class'    => 'DebugBar',
    'function' => 'inject_debug_bar',
    'filename' => 'DebugBar.php',
    'filepath' => 'hooks',
    'params'   => array()
);