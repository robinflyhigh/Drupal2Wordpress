Drupal 7 to Wordpress 3.x
=======

* Script built upon the foundation of robinflyhigh's work. I fixed some details regarding post type and post status after migration, and I also improved it so It can migrate up to 11 levels of nested comments.
* See also: http://fuzzythinking.davidmullens.com/content/moving-from-drupal-7-to-wordpress-3-3/ to get an insight of the underlying logic.

Contributors
=======

* versvs (http://www.versvs.net)
* Ali Sadattalab (http://twitter.com/xbox3000)

What it does
=======

* This script migrates content, taxonomies, and comments from a Drupal 7 MySQL schema to a WordPress 3.x database schema.

What it does not
=======

* It does not migrate users.


Preparing the migration
=======

* The script itself needs to be configured settings the proper values in the .php file.
* Additionally, you need to perform a basic wp install (just copy the wp files, configure wp-config.php and point your browser to the root URL of your instance, complete the basic setup, and run your Drupal2WordPress script.


To Do
=======

* Implement user migrations.
