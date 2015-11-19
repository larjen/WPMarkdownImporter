<?php

// require oAuth
require_once('parsedown-1.6.0/Parsedown.php');

class WPMarkdownImporter {

    static $debug = true;
    static $plugin_name = "WPMarkdownImporter";
    //static $import_file = "".__DIR__."".DIRECTORY_SEPARATOR."import.txt";
    static $newline_separator = "\r\n";

    /*
     * tasks to run when the user activates the plugin
     */

    static function activation() {
        update_option(self::$plugin_name . "_MESSAGES", []);
        update_option(self::$plugin_name . "_URLS_TO_PROCESS", []);
        update_option(self::$plugin_name . "_ACTIVE", false);
        update_option(self::$plugin_name . "_IMPORTED_ALL_MARKDOWN_DOCUMENTS", false);
        update_option(self::$plugin_name . "_URLS_TO_PROCESS", self::get_imports_from_file());

        
        self::add_message("Activated the plugin.");
    }

    /*
     * When deactivating the plugin clean up
     */

    static function deactivation() {
        self::clear_schedule();
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
        $schedules[self::$plugin_name . '_every5Minutes'] = array('interval' => 60 * 5, 'display' => 'Every 5 minutes');
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
     * This function parses the import file and returns an array of files to parse
     * the database, or false if no array can be traversed
     */

    static function get_imports_from_file() {

        // get filehandler
        $fh = fopen(__DIR__ . DIRECTORY_SEPARATOR . "import.txt", "r");

        if ($fh) {

            // start with a new empty array
            $urls_to_process = [];

            while (($line = fgets($fh)) !== false) {
                // add line to array
                if (trim($line) != "") {
                    array_push($urls_to_process, trim($line));
                }
            }
            fclose($fh);

            // add it back to the database
            return $urls_to_process;
        } else {
            // error opening the file.
            self::add_message("There was a problem opening the file " . __DIR__ . DIRECTORY_SEPARATOR . "import.txt" . ".");
        
            return false;
        }
    }
    
    /*
     * Check to see if URI exists
     */

    static function uri_exists($uri){
        $headers = get_headers($uri);
        return stripos($headers[0],"200 OK")?true:false;
    }
    
    /* Import media from url
     *
     * @param string $file_url URL of the existing file from the original site
     * @param int $post_id The post ID of the post to which the imported media is to be attached
     *
     * @return boolean True on success, false on failure
     */

    static function fetch_media($file_url, $post_id, $markdown, $is_featured, $image_meta_data) {
        
        // in order for this to run in background as a scheduled task, we need access
        // to these files
        require_once(ABSPATH . 'wp-load.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        
        error_log("Importing:       " . $file_url . " to post " . $post_id);


        global $wpdb;

        if (!$post_id) {
            self::add_message("No valid post id given when trying to attach images.");
            return false;
        }

        if (self::uri_exists($file_url)) { 
            
            error_log("Verified exists: " . $file_url . " to post " . $post_id);

            $artDir = 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'importedmedia' . DIRECTORY_SEPARATOR . md5($markdown) . DIRECTORY_SEPARATOR;

            //if the directory doesn't exist, create it
            if (!file_exists(ABSPATH . $artDir)) {
                mkdir(ABSPATH . $artDir);
            }

            //create a unique name for the file
            $filenameArr = explode(".", $file_url);
            $ext = array_pop($filenameArr);
            $new_filename = md5($file_url . $markdown) . "." . $ext;


            copy($file_url, ABSPATH . $artDir . $new_filename);

            $siteurl = get_option('siteurl');
            $file_info = getimagesize(ABSPATH . $artDir . $new_filename);

            //create an array of attachment data to insert into wp_posts table
            $artdata = array();
            $artdata = array(
                'post_author' => get_option(self::$plugin_name . "_IMPORT_AS"),
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql'),
                'post_title' => $file_url,
                'post_status' => 'inherit',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_name' => sanitize_title_with_dashes(str_replace("_", "-", $new_filename)),
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql'),
                'post_parent' => $post_id,
                'post_type' => 'attachment',
                'guid' => $siteurl . '/' . $artDir . $new_filename,
                'post_mime_type' => $file_info['mime'],
                'post_excerpt' => '',
                'post_content' => ''
            );

            $uploads = wp_upload_dir();
            $save_path = $uploads['basedir'] . DIRECTORY_SEPARATOR . 'importedmedia' . DIRECTORY_SEPARATOR . md5($markdown) . DIRECTORY_SEPARATOR . $new_filename;

            //insert the database record
            $attach_id = wp_insert_attachment($artdata, $save_path, $post_id);

            $attach_data = [];
            
            //generate metadata and thumbnails
            if ($attach_data = wp_generate_attachment_metadata($attach_id, $save_path)) {
                
                
                // Give an absolute path to the image location of each image.
                $attach_data["sizes"]["thumbnail"]["file"] =  $siteurl . '/' . $artDir . $attach_data["sizes"]["thumbnail"]["file"];
                $attach_data["sizes"]["medium"]["file"] =  $siteurl . '/' . $artDir  . $attach_data["sizes"]["medium"]["file"];
                $attach_data["sizes"]["post-thumbnail"]["file"] =  $siteurl . '/' . $artDir  . $attach_data["sizes"]["post-thumbnail"]["file"];
                if (isset($attach_data["sizes"]["large"])){
                    $attach_data["sizes"]["large"]["file"] =  $siteurl . '/' . $artDir  . $attach_data["sizes"]["large"]["file"];
                }
                $attach_data["file"] =  $siteurl . '/' . $artDir . $new_filename;
                
                // add something to attach data
//                print_r($attach_data["sizes"]);
                
                wp_update_attachment_metadata($attach_id, $attach_data);
            }

            // insert a key that makes it possible to wipe the post
            add_post_meta($attach_id, 'WPMarkdownImporter', 'true', true);

            // add meta data to the post from the array passed
            foreach ($image_meta_data as $key => $value){
                // insert a key that makes it possible to wipe the post
                add_post_meta($attach_id, $key, $value, true);
    
            }
            
            //optional make it the featured image of the post it's attached to
            if ($is_featured) {
                $rows_affected = $wpdb->insert($wpdb->prefix . 'postmeta', array('post_id' => $post_id, 'meta_key' => '_thumbnail_id', 'meta_value' => $attach_id));
            }
        } else {
            self::add_message("The image at ".$file_url." could not be found.");
            return false;
        }
        return true;
    }

    /*
     * Adds images found in metadata to the post
     */

    static function add_images_to_post($post_id, $images, $markdown, $type) {

        var_dump($images);
        // Add meta tags to post
        foreach ($images as $key) {

            if ($type == "image") {
                self::fetch_media($key, $post_id, $markdown, false, array("type"=>"image"));
            }

            if ($type == "thumbnail") {
                self::fetch_media($key, $post_id, $markdown, true, array("type"=>"thumbnail"));
            }

            if ($type == "heroimage") {
                self::fetch_media($key, $post_id, $markdown, true, array("type"=>"heroimage"));
            }
            
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

        $sql = 'SELECT DISTINCT post_id FROM ' . $table_prefix . 'postmeta WHERE meta_key = "WPMarkdownImporterUri" AND meta_value = "' . $uri . '"';
        $rows = $wpdb->get_results($sql, 'ARRAY_A');
        $number_of_rows = count($rows);

        if ($number_of_rows == 0) {

            // post does not exist
            return false;
        } else {

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
     * Check to see if post was altered
     */

    static function post_is_altered($post_id, $markdown) {

        // disable this check for dev purposes
        return true;

        $stored_hashkey = get_post_meta($post_id, 'WPMarkdownImporterHash', true);

        if ($stored_hashkey == md5($markdown)) {
            return false;
        }
        return true;
    }

    /*
     * Imports images to a post
     */

    static function import_images_to_post($post_id, $meta_data, $markdown) {

        // add image files to post
        if (isset($meta_data["image"])){
            self::add_images_to_post($post_id, $meta_data["image"], $markdown, "image");
        }
        unset($meta_data["image"]);

        // add thumbnail files to post
        if (isset($meta_data["thumbnail"])){
            self::add_images_to_post($post_id, $meta_data["thumbnail"], $markdown, "thumbnail");
        }
        unset($meta_data["thumbnail"]);

        return $meta_data;
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

        if ($post_id === false) {

            // post does not exist, insert the post
            $post_id = wp_insert_post($post);

            // add images to the post
            $meta_data = self::import_images_to_post($post_id, $meta_data, $markdown);

            // add metadata to the post
            self::add_meta_data_to_post($post_id, $meta_data, $uri, $markdown);
        } else {

            // only update the post if it was altered
            if (self::post_is_altered($post_id, $markdown)) {

                // post exists
                $post["ID"] = $post_id;

                wp_update_post($post);

                // add images to the post
                $meta_data = self::import_images_to_post($post_id, $meta_data, $markdown);

                // add metadata to the post
                self::add_meta_data_to_post($post_id, $meta_data, $uri, $markdown);
            }
        }

        return true;
    }

    /*
     * Add metadata to post
     */

    static function add_meta_data_to_post($post_id, $meta_data, $uri, $markdown) {

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

        add_post_meta($post_id, 'WPMarkdownImporterHash', md5($markdown), true);
        add_post_meta($post_id, 'WPMarkdownImporter', 'true', true);
        add_post_meta($post_id, 'WPMarkdownImporterUri', $uri, true);
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
            self::add_message("The Markdown document at ".$uri." could not be found.");
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

        // if there are no more URLS to process decide what to do
        if (count($urls_to_process) == 0) {

            // no more markdown files to import
            update_option(self::$plugin_name . "_IMPORTED_ALL_MARKDOWN_DOCUMENTS", true);

            // if import is active then fetch all files again
            if (get_option(self::$plugin_name . "_ACTIVE") == true) {
                
                $urls_to_process = self::get_imports_from_file();
                
                // if the urls could be read and there are more than one
                if ($urls_to_process == false || count($urls_to_process) == 0){

                    self::add_message("There was a problem with reading the list of files to parse.");
                    return true;
                }
                
                
            } else {
                self::add_message("You have successfully imported all of the markdown documents on the list.");
                return true;
            }
        }

        // now import then next one
        $next_uri = array_shift($urls_to_process);

        if (self::import_this_markdown_file($next_uri) === true) {

            // the document was imported, update the remaining documents
            // to be updated
            // if auto_import enabled and we have reached the end then reimport
            // the documents to parse

            update_option(self::$plugin_name . "_URLS_TO_PROCESS", $urls_to_process);

            // give a message depending on if we reached bottom of the list of markdown documents.
            if (get_option(self::$plugin_name . "_ACTIVE") == true && count($urls_to_process) == 0) {
                self::add_message("Successfully imported " . $next_uri . " to WordPress, now starting importing from top.");
            } else {
                self::add_message("Successfully imported " . $next_uri . " to WordPress.");
            }
        } else {

            // there was an error push the document to the end of the list
            array_push($urls_to_process, $next_uri);
            update_option(self::$plugin_name . "_URLS_TO_PROCESS", $urls_to_process);

            self::add_message("There was a problem importing " . $next_uri . " to WordPress. The import has been postponed, and will be retried again.");
        }
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

}

// add schedule to import every minute
add_filter('cron_schedules', 'WPMarkdownImporter::additional_schedule');

// add action to import next update
add_action('WPMarkdownImporter_scheduled_import', 'WPMarkdownImporter::import_next_markdown_file_scheduled');
