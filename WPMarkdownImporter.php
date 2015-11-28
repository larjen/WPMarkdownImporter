<?php

/*
  Plugin Name: WPMarkdownImporter
  Plugin URI: https://github.com/larjen/WPMarkdownImporter
  Description: WordPress plugin that imports a list of Markdown files into your WordPress blog as posts.
  Author: Lars Jensen
  Version: 1.0.2
  Author URI: http://exenova.dk/
 */

include_once(__DIR__ . DIRECTORY_SEPARATOR . "includes". DIRECTORY_SEPARATOR . "main.php");

if (is_admin()) {
    
    // include admin ui
    include_once(__DIR__ . DIRECTORY_SEPARATOR . "includes". DIRECTORY_SEPARATOR . "admin.php");

    // register activation and deactivation
    register_activation_hook(__FILE__, 'WPMarkdownImporter::activation');
    register_deactivation_hook(__FILE__, 'WPMarkdownImporter::deactivation');
    
}
