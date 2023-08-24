<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Template {

    protected $_CI;

    function __construct() {
        $this->_CI = &get_instance();
    }

    function display($template, $file, $data = null) {
        // function display($template, $file, $data = null) {
		// $js  = $template.'/js/'.$file;
		// $css = $template.'/css/'.$file;
		       	
		// $data['_css'] 	  = $this->_CI->load->view($css, $data, true);
		// $data['_header']  = $this->_CI->load->view('template/header', $data, true);  
		// $data['_sidebar'] = $this->_CI->load->view('template/sidebar', $data, true);      	
        // $data['_content'] = $this->_CI->load->view($template.'/'.$file, $data, true);		
		// $data['_footer']  = $this->_CI->load->view('template/footer', $data, true);	    	
		// $data['_js'] 	  = $this->_CI->load->view($js, $data, true);     
        $this->_CI->load->view('dashboard/templates/header', $data);
        $this->_CI->load->view('dashboard/templates/sidebar', $data);
        $this->_CI->load->view('dashboard/templates/topbar', $data);
        $this->_CI->load->view('dashboard/'. $template .'/'. $file, $data);
        // $this->_CI->load->view('dashboard/'. $template .'/'. $file, $data);
        $this->_CI->load->view('dashboard/templates/footer', $data);

               // $this->load->view('templates/header', $data);
            // $this->load->view('templates/sidebar', $data);
            // $this->load->view('templates/topbar', $data);
            // $this->load->view('dashboard/genre/index', $data);
            // $this->load->view('templates/footer');
    }

    function futures( $data = null) {
    
        $this->_CI->load->view('dashboard/templates/header', $data);
        $this->_CI->load->view('dashboard/templates/sidebar', $data);
        $this->_CI->load->view('dashboard/templates/topbar', $data);
        $this->_CI->load->view('new-feature', $data);
        // $this->_CI->load->view('dashboard/'. $template .'/'. $file, $data);
        $this->_CI->load->view('dashboard/templates/footer', $data);

    }

    function displayWebsite($path, $data = null) {
    
        $this->_CI->load->view('home/layouts/header', $data);
        $this->_CI->load->view('home/layouts/topbar', $data);
        $this->_CI->load->view('home/'.$path, $data);
        $this->_CI->load->view('home/layouts/footer', $data);

    }

}
