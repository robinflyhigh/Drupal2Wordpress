# Drupal 7 to Wordpress 3.9

## Features

This script supports the migration of the following items:
* Content (Drupal nodes) - nodes of type "article" are migrated into Wordpress as 'post' content type, and any other Drupal node content type is migrated into Wordpress as 'page' content type. All nodes are imported with their original owner user id, timestamp, published or unpublished state. With regards to SEO, Drupal's leading 'content/' prefix for any page is removed.
* Categories - Wordpress 3.9 requires that any blog post is associated with at least one category, and not just a tag, hence the script will create a default 'Blog' category and associate all of the content created into that category.
* Taxonomies
* Comments on Content (up to 11 levels of threaded comments) - only approved comments are imported due to the high level of spam which Drupal sites might endure (in Drupal this means all comments with status 1)
* Users - Drupal's user id 0 (anonymous) and user id 1 (site admin) are ignored. User's basic information is migrated, such as username, e-mail and creation date. Users are migrated with no password, which means in Wordpress that they can't login and must reset their account details (this is due to security reasons).

See also: http://fuzzythinking.davidmullens.com/content/moving-from-drupal-7-to-wordpress-3-3/ to get an insight of the underlying logic.


## Preparing the migration

* The script assumes a fresh Wordpress 3.9 installation with just the administrative user account.
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
