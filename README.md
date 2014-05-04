# Drupal 7 to Wordpress 3.9

## Features

This script supports the migration of the following items:
* Content (Drupal nodes)
* Taxonomies
* Comments on Content (up to 11 levels of threaded comments)
* Users

See also: http://fuzzythinking.davidmullens.com/content/moving-from-drupal-7-to-wordpress-3-3/ to get an insight of the underlying logic.


## Preparing the migration

* The script itself needs to be configured settings the proper values in the .php file.
* Additionally, you need to perform a basic wp install (just copy the wp files, configure wp-config.php and point your browser to the root URL of your instance, complete the basic setup, and run your Drupal2WordPress script.


# Contributors

* Liran Tal (http://www.enginx.com)
* versvs (http://www.versvs.net)
* Ali Sadattalab (http://twitter.com/xbox3000)



# To Do

* [LiranTal] Support importing the media resources that were uploaded to Drupal or referrenced to a Drupal install
* [LiranTal] Code clean up with the comments 11 levels foreach() nesting (should possibly be replaced with a recursive function)

# Changelog

* [versvs] Script built upon the foundation of robinflyhigh's work.
* [versvs] fixed some details regarding post type and post status after migration, and also improved it so it can migrate up to 11 levels of nested comments.
