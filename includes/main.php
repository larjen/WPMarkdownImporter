<?php

// require oAuth
require_once('parsedown-1.6.0/Parsedown.php');

class WPMarkdownImporter {

    static $debug = false;
    static $plugin_name = "WPMarkdownImporter";
    static $import_file = __DIR__ . DIRECTORY_SEPARATOR . "import.txt";
    static $newline_separator = "\r\n";

    /*
     * tasks to run when the user activates the plugin
     */

    static function activation() {
        update_option(self::$plugin_name . "_MESSAGES", []);
        update_option(self::$plugin_name . "_URLS_TO_PROCESS", []);
        update_option(self::$plugin_name . "_ACTIVE", false);
        update_option(self::$plugin_name . "_IMPORTED_ALL_MARKDOWN_DOCUMENTS", false);

        self::add_message("Activated the plugin.");
        self::add_imports_from_file();
    }

    /*
     * When deactivating the plugin clean up
     */

    static function deactivation() {
        delete_option(self::$plugin_name . "_MESSAGES");
        delete_option(self::$plugin_name . "_URLS_TO_PROCESS");
        delete_option(self::$plugin_name . "_ACTIVE");
        delete_option(self::$plugin_name . "_IMPORT_AS");
    }

    /*
     * Adds a message to the log
     */

    static function add_message($message) {
        $messages = get_option(self::$plugin_name . "_MESSAGES");
        array_push($messages, date("Y-m-d H:i:s") . " - " . $message);

        // keep the amount of messages below 10
        if (count($messages) > 10) {
            $temp = array_shift($messages);
        }

        update_option(self::$plugin_name . "_MESSAGES", $messages);
    }

    /*
     * Additional schedule every 5 minutes
     */

    static function additional_schedule($schedules) {
        // interval in seconds
        $schedules[self::$plugin_name . '_every5Minutes'] = array('interval' => 60, 'display' => 'Every 5 minutes');
        return $schedules;
    }

    /*
     * Start schedule
     */

    static function start_schedule() {
        // unschedule previous schedule
        self::clear_schedule();

        //gives the unix timestamp for today's date + 60 minutes
        $start = time() + (5 * 60);

        // start schedule
        wp_schedule_event($start, self::$plugin_name . '_every5Minutes', self::$plugin_name . '_scheduled_import');
    }

    /*
     * Clear schedule
     */

    static function clear_schedule() {
        // unschedule previous schedule
        wp_clear_scheduled_hook(self::$plugin_name . '_scheduled_import');
    }

    /*
     * Scheduled import of next markdown file
     */

    static function import_next_markdown_file_scheduled() {

        // if the plugin is activated then import the next markdown file
        if (get_option(self::$plugin_name . "_ACTIVE") == true) {
            self::import_next_markdown_file();
        }
    }

    /*
     * This function parses the import file and adds it to an array of URLs in
     * the database
     */

    static function add_imports_from_file() {

        // get filehandler
        $fh = fopen(self::$import_file, "r");

        if ($fh) {

            // now get the array from database
            $urls_to_process = get_option(self::$plugin_name . "_URLS_TO_PROCESS");

            while (($line = fgets($fh)) !== false) {
                // add line to array
                if (rtrim($line) != "") {
                    array_push($urls_to_process, rtrim($line));
                }
            }
            fclose($fh);

            // add it back to the database
            update_option(self::$plugin_name . "_URLS_TO_PROCESS", $urls_to_process);
        } else {
            // error opening the file.
            self::add_message("There was a problem opening the file " . self::$import_file . ".");
        }
    }

    /*
     * Creates an array of metadata found in the markdown file and returns it
     * as an array
     */

    static function get_metadata($markdown) {

        $meta_data = [];
        $line = strtok($markdown, self::$newline_separator);

        while ($line !== false) {

            $line_start = substr($line, 0, 6);

            if ($line_start == "[//]: ") {

                $meta_arr = explode(" (", $line, 2);
                $key = substr($meta_arr[0], 6);
                $value = substr(rtrim($meta_arr[1]), 0, -1);

                //error_log("key:'".$key."'");
                //error_log("value:'".$value."'");
                // add as array in the meta array
                if (!isset($meta_data[$key])) {
                    $meta_data[$key] = [];
                }
                array_push($meta_data[$key], $value);
            }

            $line = strtok(self::$newline_separator);
        }

        //print_r($meta_data);

        return $meta_data;
    }

    /*
     * Checks to see if there is already a post with this MarkDown in the database
     * If there is already a post for the given URI then return the ID, otherwise
     * return false.
     */

    static function post_exists($uri) {
        
        global $table_prefix;
        global $wpdb;

        $sql = 'SELECT DISTINCT post_id FROM ' . $table_prefix . 'postmeta WHERE meta_key = "WPMarkdownImporterUri" AND meta_value = "'.$uri.'"';
        $rows = $wpdb->get_results($sql, 'ARRAY_A');
        $number_of_rows = count($rows);

        if ($number_of_rows == 0){
            
            // post does not exist
            return false;
            
        }else{
            
            // post exists return the ID
            return $rows[0]["post_id"];
        }
    }

    /*
     * Strip the first line of markdown
     */

    static function stripFirstLine($text) {
        return substr($text, strpos($text, "\n") + 1);
    }

    /*
     * inserts a markdown file as a post in wordpress
     */

    static function insert_markdown_as_post($markdown, $uri) {

        // extract all metadata from the $markdown file
        $meta_data = self::get_metadata($markdown);

        $tags = [];
        // sanitize tags from Markdown
        if (isset($meta_data["tag"])) {
            foreach ($meta_data["tag"] as $key => $tag) {
                // if wpTagSanitizer is installed, use it to sanitize the tags
                if (is_callable('WPTagSanitizer::sanitizeTag')) {
                    $tag = WPTagSanitizer::sanitizeTag($tag);
                }
                array_push($tags, $tag);
            }
            unset($meta_data["tag"]);
        }

        $categories = [];
        // sanitize categories from Markdown
        if (isset($meta_data["category"])) {
            foreach ($meta_data["category"] as $key => $cat) {
                // if wpTagSanitizer is installed, use it to sanitize the tags
                if (is_callable('WPTagSanitizer::sanitizeTag')) {
                    $cat = WPTagSanitizer::sanitizeTag($cat);
                }
                array_push($categories, $cat);
            }
            unset($meta_data["category"]);
        }

        $category_ids = [];
        // get an array of ids for found categories
        foreach ($categories as $cat_name) {

            $term = term_exists($cat_name, 'category');

            if ($term !== 0 && $term !== null) {

                // the term id must be pushed to the array of ids
                array_push($category_ids, $term["term_id"]);
            } else {

                // create a new category and add id to array of ids
                $new_cat = array(
                    'cat_name' => $cat_name
                );

                // Create the category
                $new_cat_id = wp_insert_category($new_cat);

                array_push($category_ids, $new_cat_id);
            }
        }

        // First line is the title, this is redundant so delete the line
        $markdown = self::stripFirstLine($markdown);

        // now parse the content
        $Parsedown = new Parsedown();
        $content = $Parsedown->text($markdown);

        
        
        // build the post
        $post = array(
            'post_title' => $meta_data["title"][0],
            'comment_status' => 'closed', // 'closed' means no comments.
            'ping_status' => 'closed', // 'closed' means pingbacks or trackbacks turned off
            'post_author' => get_option(self::$plugin_name . "_IMPORT_AS"), //The user ID number of the author.
            'post_content' => $content, //The full text of the post.
            'post_excerpt' => $meta_data["excerpt"][0],
            'post_date' => date("Y-m-d H:i:s", strtotime($meta_data["start_date"][0])), //The time post was made.
            'post_date_gmt' => gmdate("Y-m-d H:i:s", strtotime($meta_data["start_date"][0])), //The time post was made, in GMT.
            'post_status' => 'publish', //Set the status of the new post. 
            'post_type' => 'post', //You may want to insert a regular post, page, link, a menu item or some custom post type
            'post_category' => $category_ids, //Categories
            'tags_input' => $tags //For tags.
        );

        // check to see if we need to update or create a new post
        $post_id = self::post_exists($uri);

        if ($post_id === false){
            
            // post does not exist, insert the post
            $post_id = wp_insert_post($post);
            
        }else{
            
            // post exists
            $post["ID"]=$post_id;
            wp_update_post( $post );
        }

        // Add meta tags to post
        foreach ($meta_data as $key => $array) {
            $value = '';

            foreach ($array as $innerKey => $innerValue) {
                $value = $innerValue . "," . $value;
            }

            if ($value != '') {
                $value = substr($value, 0, -1);
                add_post_meta($post_id, $key, $value, true);
            }
        }

        add_post_meta($post_id, 'WPMarkdownImporter', 'true', true);
        add_post_meta($post_id, 'WPMarkdownImporterUri', $uri, true);

        return true;
    }

    /*
     * Imports this markdown document, returns true or false depending on
     * the successfull import
     */

    static function import_this_markdown_file($uri) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $markdown = curl_exec($ch);

        // get status code
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        //error_log("error:".$error);
        //error_log("statuscode:".$status_code);
        //error_log("output:".$markdown);

        curl_close($ch);

        if ($status_code != 200) {
            return false;
        }

        // now insert the contents into WordPress
        self::insert_markdown_as_post($markdown, $uri);

        return true;
    }

    /*
     * Import the next markdown file
     */

    static function import_next_markdown_file() {

        // get the first item from the list

        $urls_to_process = get_option(self::$plugin_name . "_URLS_TO_PROCESS");

        if (count($urls_to_process) == 0) {

            // no more markdown files to import
            update_option(self::$plugin_name . "_IMPORTED_ALL_MARKDOWN_DOCUMENTS", true);
        } else {

            $next_uri = array_shift($urls_to_process);

            if (self::import_this_markdown_file($next_uri) === true) {

                // the document was imported, update the remaining documents
                // to be updated

                update_option(self::$plugin_name . "_URLS_TO_PROCESS", $urls_to_process);
                self::add_message("Successfully imported " . $next_uri . " to WordPress.");
            } else {

                // there was an error push the document to the end of the list

                array_push($urls_to_process, $next_uri);
                update_option(self::$plugin_name . "_URLS_TO_PROCESS", $urls_to_process);

                self::add_message("There was a problem importing " . $next_uri . " to WordPress.");
            }
        }
    }

}

// add schedule to import every minute
add_filter('cron_schedules', 'WPMarkdownImporter::additional_schedule');

// add action to import next update
add_action('WPMarkdownImporter_scheduled_import', 'WPMarkdownImporter::import_next_markdown_file_scheduled');
