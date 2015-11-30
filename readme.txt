=== WPMarkdownImporter ===
Contributors: larjen
Donate link: http://exenova.dk/
Tags: Twitter
Requires at least: 4.3.1
Tested up to: 4.3.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress plugin that imports a list of Markdown files into your WordPress blog as posts.

== Description ==

WordPress plugin that imports a list of Markdown files into your WordPress blog as posts.

== Installation ==

1. Download and unzip to your Wordpress plugin folder.
2. Activate plugin.
3. Go to the plugin control panel, and add one URL per line for each Markdown document you want to import.
4. Save the list and configure how you want to import the Markdown documents.

= Special Markdown Syntax =

It is possible to add tags, categories and images to your WordPress post by using the following Markdown comments at the bottom of your document:

    [//]: title (The title as it should appear in your Post, if not set it will use the first line of your document)
    [//]: category (category a)
    [//]: category (category b)
    [//]: category (add as many as you like)
    [//]: start_date (20151112) // will serve as your publishing date, if not set it will be set to current time
    [//]: end_date (20151113) // if not set it will be set to current time
    [//]: excerpt (Excerpt for your post.)
    [//]: tag (tag a)
    [//]: tag (tag b)
    [//]: tag (add as many as you like)
    [//]: thumbnail (http://www.example.com/a.jpg) // only one allowed
    [//]: heroimage (http://www.example.com/b.jpg) // only one allowed
    [//]: image (http://www.example.com/c.jpg) // add as many images as you like
    [//]: image (http://www.example.com/d.jpg|Title|Caption|Alternative Text|Description)
    [//]: image (http://www.example.com/d.jpg|Just a title)

It is important, that these comments are placed at the end of your Markdown document at the beginning of a line, and with the exact spacing as illustrated above.

When importing images to WordPress you may optionally use the pipe character to delimit and add more data to the image.

Any additional comments, will be added as custom fields to your post.

    [//]: meta_data_key_a (meta_data_value_a)
    [//]: meta_data_key_b (meta_data_value_b)
    [//]: meta_data_key_c (meta_data_value_c)
    [//]: meta_data_key_a (meta_data_value_a) // will overwrite the first key

== Frequently Asked Questions ==

= Do I use this at my own risk? =

Yes.

== Screenshots ==

== Changelog ==

= 1.0.2 =
* Now imports images to post.
* When images are detected, each time the post is imported all images are deleted.
* You may specify title, caption, alttext and description when importing images to WordPress.

= 1.0.1 =
* Now only update the document if it is changed, when auto-import is active.

= 1.0.0 =
* Uploaded plugin.

== Upgrade Notice ==
