<?php

class WPMarkdownImporterAdmin extends WPMarkdownImporter {

    static function plugin_menu() {
        add_management_page("WPMarkdown Importer", "WPMarkdown Importer", 'activate_plugins', 'WPMarkdownImporterAdmin', array('WPMarkdownImporterAdmin', 'plugin_options'));
    }

    /*
     * Writes the import file
     */
    static function write_file($file_contents){
        
        // now add file to cache
        $fh = fopen(self::$import_file, 'w') or die();
        fwrite($fh, $file_contents);
        fclose($fh);

    }

    /*
     * Deactivates import
     */
    static function activate_import() {
        if (get_option(self::$plugin_name."_ACTIVE") == false) {
            self::start_schedule();
            self::add_message("Import of Markdown files has been activated.");
        }
        update_option(self::$plugin_name."_ACTIVE", true);
    }
    
    /*
     * Activates import
     */
    static function deactivate_import() {
        if (get_option(self::$plugin_name."_ACTIVE") == true) {
            self::clear_schedule();
            self::add_message("Import of Markdown files has been deactivated.");
        }
        update_option(self::$plugin_name."_ACTIVE", false);
    }
    
    /*
     * Remove all imported markdown posts from WordPress
     */
    static function purge_markdowns() {

        global $table_prefix;
        global $wpdb;

        $sql = 'SELECT DISTINCT post_id FROM ' . $table_prefix . 'postmeta WHERE meta_key = "WPMarkdownImporter"';
        $rows = $wpdb->get_results($sql, 'ARRAY_A');
        foreach ($rows as $row) {
            wp_delete_post($row["post_id"], true);
        }

        self::add_message("All Markdown imported posts have been deleted.");
    }
    
    /*
     * Plugin admin page
     */
    static function plugin_options() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if (isset($_POST[self::$plugin_name."_PURGE_MARKDOWNS"])) {
            self::purge_markdowns();
        }
        
        // save all settings
        $optionsArr = array(self::$plugin_name."_IMPORT_AS");
        foreach ($optionsArr as $value) {
            if (isset($_POST[$value])) {
                update_option($value, $_POST[$value]);
            }
        }
        
        // update the import.txt file table
        if (isset($_POST[self::$plugin_name . "_IMPORT_FILE"])) {
            
            // write this file to disk
            self::write_file($_POST[self::$plugin_name . "_IMPORT_FILE"]);
            
            // then import them again
            self::add_imports_from_file();
        }

        // read the file into memory
        $import_file_contents = file_get_contents(self::$import_file);
        
        if (isset($_POST["ACTIVE"])) {
            if ($_POST["ACTIVE"] == 'activated') {
                self::activate_import();
            }
            if ($_POST["ACTIVE"] == 'deactivated') {
                self::deactivate_import();
            }
        }
        
        $force_import_checked = "";
        if (isset($_POST[self::$plugin_name."_FORCE_IMPORT"])) {
            do_action('WPMarkdownImporter_import');
            $force_import_checked = ' checked="checked"';
        }
        
        // debug
        if (self::$debug) {
            echo '<pre>';
            echo 'get_option(self::$plugin_name . "_MESSAGES")=' . print_r(get_option(self::$plugin_name . "_MESSAGES")) . PHP_EOL;
            echo 'get_option(self::$plugin_name . "_URLS_TO_PROCESS")=' . print_r(get_option(self::$plugin_name . "_URLS_TO_PROCESS")) . PHP_EOL;
            echo 'get_option(self::$plugin_name . "_IMPORT_AS")=' . print_r(get_option(self::$plugin_name."_IMPORT_AS")) . PHP_EOL;
            echo '</pre>';
        }

        $messages = get_option(self::$plugin_name . "_MESSAGES");

        while (!empty($messages)) {
            $message = array_shift($messages);
            echo '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"><p><strong>' . $message . '</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Afvis denne meddelelse.</span></button></div>';
        }

        // since the messages has been shown, purge them.
        update_option(self::$plugin_name . "_MESSAGES", []);

        // remaining URLs to parse
        $remaining_urls_to_parse = count(get_option(self::$plugin_name . "_URLS_TO_PROCESS"));
        
        
        // print the admin page
        echo '<div class="wrap">';
        echo '<h2>'.self::$plugin_name.'</h2>';
        echo '<p>This plugin will attempt to read in all of your Markdown documents into WordPress as posts, it will do this either every 5 minutes.</p>';
        
        echo '<form method="post" action="">';
        
        echo '<table class="form-table"><tbody>';

        echo '<tr valign="top"><th scope="row">Remaining documents:</th><td><p>' . $remaining_urls_to_parse . '</p></td></tr>';
        
        echo '<tr valign="top"><th scope="row">All documents have been read:</th><td><p>' . (get_option(self::$plugin_name . "_IMPORTED_ALL_MARKDOWN_DOCUMENTS") == false)? 'Yes':'No'.'</p></td></tr>';

        echo '<tr valign="top"><th scope="row">Activate import</th><td><fieldset><legend class="screen-reader-text"><span>Activate</span></legend>';

        if (get_option(self::$plugin_name.'_ACTIVE') == true) {
            echo '<label for="ACTIVE"><input checked="checked" id="ACTIVE" name="ACTIVE" type="radio" value="activated"> Import of Markdown documents is active.</label><br /><legend class="screen-reader-text"><span>Dectivate</span></legend><label for="DEACTIVE"><input id="DEACTIVE" name="ACTIVE" type="radio" value="deactivated"> Import of Markdown documents is deactivated.</label>';
        } else {
            echo '<label for="ACTIVE"><input id="ACTIVE" name="ACTIVE" type="radio" value="activated"> Import of Markdown documents is active.</label><br /><legend class="screen-reader-text"><span>Dectivate</span></legend><label for="DEACTIVE"><input checked="checked" id="DEACTIVE" name="ACTIVE" type="radio" value="deactivated"> Import of Markdown documents is deactivated.</label>';
        }

        echo '<p class="description">When activated this plugin will import one Markdown file every 5 minutes. When the end is reached, it will start again from the top.</p>';
        echo '</fieldset></td></tr>';
        
        echo '<tr valign="top"><th scope="row"><label for="TWITTER_CATEGORY">Import Markdown posts as</label></th><td>';

        wp_dropdown_users(array('hide_empty' => 0, 'name' => self::$plugin_name.'_IMPORT_AS', 'orderby' => 'name', 'selected' => get_option(self::$plugin_name.'_IMPORT_AS')));

        echo '</td></tr>';
        
        echo '<tr valign="top"><th scope="row">Delete all imported posts</th><td><fieldset><legend class="screen-reader-text"><span>Delete posts</span></legend><label for="PURGE_MARKDOWNS"><input id="PURGE_MARKDOWNS" name="'.self::$plugin_name.'_PURGE_MARKDOWNS" type="checkbox"></label><p class="description">Will delete all Markdown imported posts from blog.</p></fieldset></td></tr>';
        
        echo '<tr valign="top"><th scope="row">Force import</th><td><fieldset><legend class="screen-reader-text"><span>Force import</span></legend><label for="FORCE_IMPORT"><input id="FORCE_IMPORT" name="'.self::$plugin_name.'_FORCE_IMPORT" type="checkbox" ' . $force_import_checked . '></label><p class="description">Force import of the next Markdown document on the list.</p></fieldset></td></tr>';
        echo '</tbody></table>';        
        
        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>';
        echo '</form>';
        
        echo '<form method="post" action="">';

        echo '<h3 class="title">Update import URLs</h3>';
        echo '<p>This is the list of URL endpoints that points to Markdown documents the plugin will import. When you change and save these endpoints, the plugin will attempt to parse all of your Markdown documents again.</p>';
        echo '<textarea name="'.self::$plugin_name .'_IMPORT_FILE" style="width: 50%;height: 400px;">' . $import_file_contents . '</textarea>';
        echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Update import URLs"></p>';
    
        echo '</form>';
        echo '</div>';
    }
    
    
    

}

// register wp hooks
add_action('admin_menu', 'WPMarkdownImporterAdmin::plugin_menu');

// add action to import next file
add_action('WPMarkdownImporter_import', 'WPMarkdownImporter::import_next_markdown_file');

