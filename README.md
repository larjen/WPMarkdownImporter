# WPMarkdownImporter

WordPress plugin that imports a list of Markdown files into your WordPress blog as posts.

This plugin uses the [Parsedown](https://github.com/erusev/parsedown) engine.

## Installation

1. Download and unzip to your Wordpress plugin folder.
2. Activate plugin.

### Special Markdown Syntax

It is possible to add tags, categories and images to your WordPress post by using the following Markdown comments at the bottom of your document:

    [//]: title (The title as it should appear in your Post, if not set it will use the first line of your document)
    [//]: category (category a)
    [//]: category (category b)
    [//]: category (add as many as you like)
    [//]: start_date (20151112)
    [//]: end_date (20151113)
    [//]: excerpt (Excerpt for your post.)
    [//]: tag (tag a)
    [//]: tag (tag b)
    [//]: tag (add as many as you like)
    [//]: thumbnail (http://www.example.com/a.jpg) // only one allowed
    [//]: heroimage (http://www.example.com/b.jpg) // only one allowed
    [//]: image (http://www.example.com/c.jpg) // add as many images as you like
    [//]: image (http://www.example.com/d.jpg)
    [//]: image (http://www.example.com/d.jpg)

It is important, that these comments are placed at the end of your Markdown document at the beginning of a line, and with the exact spacing as illustrated above.

Any additional comment names, will be added as meta data to your post.

    [//]: meta_data_key_a (meta_data_value_a)
    [//]: meta_data_key_b (meta_data_value_b)
    [//]: meta_data_key_c (meta_data_value_c)
    [//]: meta_data_key_a (meta_data_value_a) // will overwrite the first key
    
## Changelog

### 1.0.2
* Now imports images to post.
* When images are detected, each time the post is imported all images are deleted.

### 1.0.1
* Now only update the document if it is changed, when auto-import is active.

### 1.0.0
* Uploaded plugin.

[//]: title (WPMarkDownImporter)
[//]: category (work)
[//]: start_date (20151112)
[//]: end_date (#)
[//]: excerpt (WordPress plugin that imports a list of Markdown files into your WordPress blog as posts.)
[//]: tag (WordPress)
[//]: tag (PHP)
[//]: tag (Markdown)
[//]: tag (GitHub)
[//]: tag (Work)
[//]: url_github (https://github.com/larjen/WPMarkdownImporter)
[//]: url_demo (#) 
[//]: url_wordpress (https://wordpress.org/plugins/WPMarkdownImporter/)
[//]: url_download (https://github.com/larjen/WPMarkdownImporter/archive/master.zip)
[//]: thumbnail (http://www.exenova.dk/download/a.jpg)
[//]: heroimage (http://www.exenova.dk/download/b.jpg)
[//]: image (http://www.exenova.dk/download/c.jpg)
[//]: image (http://www.exenova.dk/download/d.jpg)
[//]: arbitrary_meta_data_key (arbitrary_meta_data_value)