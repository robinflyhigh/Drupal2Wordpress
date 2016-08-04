<?php
  require_once("php-mysql.php");

  //Database Host Name
  $DB_HOSTNAME	= 'localhost';

  //Drupal Database Name, Username and Password
  $DB_DP_DATABASE = getenv('DB_DP_DATABASE');
  $DB_DP_USERNAME	= getenv('DB_DP_USERNAME');
  $DB_DP_PASSWORD	= getenv('DB_DP_PASSWORD');

  //Wordpress Database Name, Username and Password
  $DB_WP_DATABASE	= getenv('DB_WP_DATABASE');
  $DB_WP_USERNAME	= getenv('DB_WP_USERNAME');
  $DB_WP_PASSWORD	= getenv('DB_WP_PASSWORD');

  //Check if we have databases
  if (empty($DB_DP_DATABASE) or empty($DB_WP_DATABASE)) {
    echo "Error: Missing database name variables\n"; die();
  }
  //Check if we have usernames
  if (empty($DB_DP_USERNAME) or empty($DB_WP_USERNAME)) {
    echo "Error: Missing username variables\n"; die();
  }

  //Table Prefix
  $DB_DP_DATABASE_PREFIX	 = '';
  $DB_WP_DATABASE_PREFIX = 'wp_';

  //Create Connection Array for Drupal and Wordpress
  $drupal_connection	= array("host" => "localhost","username" => $DB_DP_USERNAME,"password" => $DB_DP_PASSWORD,"database" => $DB_DP_DATABASE);
  $wordpress_connection	= array("host" => "localhost","username" => $DB_WP_USERNAME,"password" => $DB_WP_PASSWORD,"database" => $DB_WP_DATABASE);

  //Create Connection for Drupal and Wordpress
  $dc = new DB($drupal_connection);
  $wc = new DB($wordpress_connection);

  //Check if database connection is fine
  $dcheck = $dc->check();
  if (!$dcheck){
    echo "Error: The $DB_DP_DATABASE service is UNAVAILABLE\n"; die();
  }

  $wcheck = $wc->check();
  if (!$wcheck){
    echo "Error: The $DB_WP_DATABASE service is UNAVAILABLE\n"; die();
  }

  message('Database connection successful');

  //Empty the current worpdress Tables
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."comments");
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."links");
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."postmeta");
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."posts");
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."term_relationships");
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."term_taxonomy");
  $wc->query("TRUNCATE TABLE ".$DB_WP_DATABASE_PREFIX."terms");

  message('Wordpress Table Truncated');

  //Get all drupal Tags and add it into worpdress terms table
  $drupal_tags = $dc->results("SELECT DISTINCT d.tid, d.name, REPLACE(LOWER(d.name), ' ', '_') AS slug FROM ".$DB_DP_DATABASE_PREFIX."taxonomy_term_data d INNER JOIN ".$DB_DP_DATABASE_PREFIX."taxonomy_term_hierarchy h ON (d.tid = h.tid) ORDER BY d.tid ASC");

  foreach($drupal_tags as $dt)
  {
    $wc->query("REPLACE INTO ".$DB_WP_DATABASE_PREFIX."terms (term_id, name, slug) VALUES ('%s','%s','%s')", $dt['tid'], $dt['name'], $dt['slug']);
  }

   //Update worpdress term_taxonomy table
   $drupal_taxonomy = $dc->results("SELECT DISTINCT d.tid AS term_id, 'post_tag' AS post_tag, d.description AS description, h.parent AS parent FROM ".$DB_DP_DATABASE_PREFIX."taxonomy_term_data d INNER JOIN ".$DB_DP_DATABASE_PREFIX."taxonomy_term_hierarchy h ON (d.tid = h.tid) ORDER BY 'term_id' ASC");

  foreach($drupal_taxonomy as $dt)
  {
    $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."term_taxonomy (term_id, taxonomy, description, parent) VALUES ('%s','%s','%s','%s')", $dt['term_id'], $dt['post_tag'], $dt['description'], $dt['parent']);
  }

  message('Tags Updated');

  //Update worpdress category for a new Blog entry (as catgegory) which
  //is a must for a post to be displayed well
  //Insert a fake new category named Blog
  $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."terms (name, slug) VALUES ('%s','%s')", 'Blog', 'blog');

  //Then query to get this entry so we can attach it to content we create
  $blog_term_id = 0;
  $row = $wc->row("SELECT term_id FROM ".$DB_WP_DATABASE_PREFIX."terms WHERE name = '%s' AND slug = '%s'", 'Blog', 'blog');
  if (!empty($row['term_id'])) {
    $blog_term_id = $row['term_id'];

    $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."term_taxonomy (term_id, taxonomy) VALUES ('%d','%s')", $blog_term_id, 'category');
  }

  message('Category Updated');

  //Get all posts from Drupal and add it into Wordpress posts table
  $drupal_posts = $dc->results("SELECT DISTINCT n.nid AS id, n.uid AS post_author, FROM_UNIXTIME(n.created) AS post_date, r.body_value AS post_content, n.title AS post_title, r.body_summary AS post_excerpt, n.type AS post_type,  IF(n.status = 1, 'publish', 'draft') AS post_status FROM ".$DB_DP_DATABASE_PREFIX."node n, ".$DB_DP_DATABASE_PREFIX."field_data_body r WHERE (n.nid = r.entity_id)");

  $post_type = 'page';

  foreach($drupal_posts as $dp)
  {
    //Wordpress basicially has 2 core post_type options, similar to Drupal -
    //either a blog-style content, where in Drupal is referred to as 'article'
    //and in Wordpress this is 'post', and the page-style content which is
    //referred to as 'page' content type in both platforms.

    //For the sake of supporting out of the box seamless migration we will
    //assume that any Drupal 'article' content type should be a Wordpress
    //'post' type and anything else will be set to 'page'

    if ($dp['post_type'] === 'article')
      $post_type = 'post';
    else
      $post_type = 'page';

    $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."posts (id, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_type, post_status) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s')", $dp['id'], $dp['post_author'], $dp['post_date'], $dp['post_date'], $dp['post_content'], $dp['post_title'], $dp['post_excerpt'], $post_type, $dp['post_status']);

    //Attach all posts to the Blog category we created earlier
    if ($blog_term_id !== 0) {
      //Attach all posts the terms/tags
      $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."term_relationships (object_id, term_taxonomy_id) VALUES ('%s','%s')", $dp['id'], $blog_term_id);
    }

  }

  message('Posts Updated');

  //Add relationship for post and tags
  $drupal_post_tags = $dc->results("SELECT DISTINCT node.nid, taxonomy_term_data.tid FROM (".$DB_DP_DATABASE_PREFIX."taxonomy_index taxonomy_index INNER JOIN ".$DB_DP_DATABASE_PREFIX."taxonomy_term_data taxonomy_term_data ON (taxonomy_index.tid = taxonomy_term_data.tid)) INNER JOIN ".$DB_DP_DATABASE_PREFIX."node node ON (node.nid = taxonomy_index.nid)");

  foreach($drupal_post_tags as $dpt)
  {
    $wordpress_term_tax = $wc->row("SELECT DISTINCT term_taxonomy.term_taxonomy_id FROM ".$DB_WP_DATABASE_PREFIX."term_taxonomy term_taxonomy WHERE (term_taxonomy.term_id = ".$dpt['tid'].")");

    //Attach all posts the terms/tags
    $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."term_relationships (object_id, term_taxonomy_id) VALUES ('%s','%s')", $dpt['nid'], $wordpress_term_tax['term_taxonomy_id']);
  }

  message('Tags & Posts Relationships Updated');

  //Update the post type for worpdress
  $wc->query("UPDATE ".$DB_WP_DATABASE_PREFIX."posts SET post_type = 'post' WHERE post_type IN ('blog')");

  message('Posted Type Updated');

  //Count the total tags
  $wc->query("UPDATE ".$DB_WP_DATABASE_PREFIX."term_taxonomy tt SET count = ( SELECT COUNT(tr.object_id) FROM ".$DB_WP_DATABASE_PREFIX."term_relationships tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id )");

  message('Tags Count Updated');

  //Get the url alias from drupal and use it for the Post Slug
  $drupal_url = $dc->results("SELECT url_alias.source, url_alias.alias FROM ".$DB_DP_DATABASE_PREFIX."url_alias url_alias WHERE (url_alias.source LIKE 'node%')");

  foreach($drupal_url as $du)
  {
    $update = $wc->query("UPDATE ".$DB_WP_DATABASE_PREFIX."posts SET post_name = '%s' WHERE ID = '%s'",
      //Make sure we import without Drupal's leading 'content/' in the URL
      str_replace('content/', '', $du['alias']),
      str_replace('node/','',$du['source'])
    );
  }

  message('URL Alias to Slug Updated');

  //Move the comments and their replies - 11 Levels
  //Ensure we import only approved comments (c.status = 1)
  //as otherwise we might be importing a ton of spam from a Drupal site
  $drupal_comments = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = 0) AND c.status = 1");

  foreach($drupal_comments as $duc)
  {
    $insert = $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID,comment_author,comment_author_email,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$duc['comment_ID'],$duc['comment_post_ID'],$duc['comment_author'],$duc['comment_author_email'],$duc['comment_author_url'],$duc['comment_author_IP'],$duc['comment_date'],$duc['comment_date'],$duc['comment_content'],'1','0');

    $drupal_comments_level1 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$duc['comment_ID'].") AND c.status = 1");

    foreach($drupal_comments_level1 as $dcl1)
    {
      $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID,comment_author,comment_author_email,comment_author_url,comment_author_IP,comment_date,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl1['comment_ID'],$dcl1['comment_post_ID'],$dcl1['comment_author'],$dcl1['comment_author_email'],$dcl1['comment_author_url'],$dcl1['comment_author_IP'],$dcl1['comment_date'],$dcl1['comment_date'],$dcl1['comment_content'],'1',$duc['comment_ID']);

      $drupal_comments_level2 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author,c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl1['comment_ID'].") AND c.status = 1");

      foreach ($drupal_comments_level2 as $dcl2)
      {
        $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID,comment_author,comment_author_email,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl2['comment_ID'],$dcl2['comment_post_ID'],$dcl2['comment_author'],$dcl2['comment_author_email'],$dcl2['comment_author_url'],$dcl2['comment_author_IP'],$dcl2['comment_date'],$dcl2['comment_date'],$dcl2['comment_content'],'1',$dcl1['comment_ID']);

        $drupal_comments_level3 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl2['comment_ID'].") AND c.status = 1");

        foreach ($drupal_comments_level3 as $dcl3)
        {
          $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl3['comment_ID'],$dcl3['comment_post_ID'],$dcl3['comment_author'],$dcl3['comment_author_email'],$dcl3['comment_author_url'],$dcl3['comment_author_IP'],$dcl3['comment_date'],$dcl3['comment_date'],$dcl3['comment_content'],'1',$dcl2['comment_ID']);

          $drupal_comments_level4 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl3['comment_ID'].") AND c.status = 1");

          foreach ($drupal_comments_level4 as $dcl4)
          {
            $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl4['comment_ID'],$dcl4['comment_post_ID'],$dcl4['comment_author'],$dcl4['comment_author_email'],$dcl4['comment_author_url'],$dcl4['comment_author_IP'],$dcl4['comment_date'],$dcl4['comment_date'],$dcl4['comment_content'],'1',$dcl3['comment_ID']);

            $drupal_comments_level5 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl4['comment_ID'].") AND c.status = 1");

            foreach ($drupal_comments_level5 as $dcl5)
            {
              $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl5['comment_ID'],$dcl5['comment_post_ID'],$dcl5['comment_author'],$dcl5['comment_author_email'],$dcl5['comment_author_url'],$dcl5['comment_author_IP'],$dcl5['comment_date'],$dcl5['comment_date'],$dcl5['comment_content'],'1',$dcl4['comment_ID']);

              $drupal_comments_level6 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl5['comment_ID'].") AND c.status = 1");

              foreach ($drupal_comments_level6 as $dcl6)
              {
                $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl6['comment_ID'],$dcl6['comment_post_ID'],$dcl6['comment_author'],$dcl6['comment_author_email'],$dcl6['comment_author_url'],$dcl6['comment_author_IP'],$dcl6['comment_date'],$dcl6['comment_date'],$dcl6['comment_content'],'1',$dcl5['comment_ID']);

                $drupal_comments_level7 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl6['comment_ID'].") AND c.status = 1");

                foreach ($drupal_comments_level7 as $dcl7)
                {
                  $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl7['comment_ID'],$dcl7['comment_post_ID'],$dcl7['comment_author'],$dcl7['comment_author_email'],$dcl7['comment_author_url'],$dcl7['comment_author_IP'],$dcl7['comment_date'],$dcl7['comment_date'],$dcl7['comment_content'],'1',$dcl6['comment_ID']);

                  $drupal_comments_level8 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl7['comment_ID'].") AND c.status = 1");

                  foreach ($drupal_comments_level8 as $dcl8)
                  {
                    $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl8['comment_ID'],$dcl8['comment_post_ID'],$dcl8['comment_author'],$dcl8['comment_author_email'],$dcl8['comment_author_url'],$dcl8['comment_author_IP'],$dcl8['comment_date'],$dcl8['comment_date'],$dcl8['comment_content'],'1',$dcl7['comment_ID']);

                    $drupal_comments_level9 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl8['comment_ID'].") AND c.status = 1");

                    foreach ($drupal_comments_level9 as $dcl9)
                    {
                      $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl9['comment_ID'],$dcl9['comment_post_ID'],$dcl9['comment_author'],$dcl9['comment_author_email'],$dcl9['comment_author_url'],$dcl9['comment_author_IP'],$dcl9['comment_date'],$dcl9['comment_date'],$dcl9['comment_content'],'1',$dcl8['comment_ID']);

                      $drupal_comments_level10 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl9['comment_ID'].") AND c.status = 1");

                      foreach ($drupal_comments_level10 as $dcl10)
                      {
                        $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl10['comment_ID'],$dcl10['comment_post_ID'],$dcl10['comment_author'],$dcl10['comment_author_email'],$dcl10['comment_author_url'],$dcl10['comment_author_IP'],$dcl10['comment_date'],$dcl10['comment_date'],$dcl10['comment_content'],'1',$dcl9['comment_ID']);

                        echo $dcl10['comment_ID'] . '<br />';

                        $drupal_comments_level11 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl10['comment_ID'].") AND c.status = 1");

                        foreach ($drupal_comments_level11 as $dcl11)
                        {
                          $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl11['comment_ID'],$dcl11['comment_post_ID'],$dcl11['comment_author'],$dcl11['comment_author_email'],$dcl11['comment_author_url'],$dcl11['comment_author_IP'],$dcl11['comment_date'],$dcl11['comment_date'],$dcl11['comment_content'],'1',$dcl10['comment_ID']);

                          $drupal_comments_level12 = $dc->results("SELECT DISTINCT c.cid AS comment_ID, c.nid AS comment_post_ID, c.name AS comment_author, c.mail AS comment_author_email, c.homepage AS comment_author_url, c.hostname AS comment_author_IP, FROM_UNIXTIME(c.created) AS comment_date, field_data_comment_body.comment_body_value AS comment_content FROM ".$DB_DP_DATABASE_PREFIX."comment c INNER JOIN ".$DB_DP_DATABASE_PREFIX."field_data_comment_body field_data_comment_body ON (c.cid = field_data_comment_body.entity_id) WHERE (c.pid = ".$dcl11['comment_ID'].") AND c.status = 1");

                          foreach ($drupal_comments_level12 as $dcl12)
                          {
                            $wc->query("INSERT INTO  ".$DB_WP_DATABASE_PREFIX."comments (comment_ID,comment_post_ID ,comment_author ,comment_author_email ,comment_author_url ,comment_author_IP ,comment_date ,comment_date_gmt, comment_content ,comment_approved,comment_parent)VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",$dcl12['comment_ID'],$dcl12['comment_post_ID'],$dcl12['comment_author'],$dcl12['comment_author_email'],$dcl12['comment_author_url'],$dcl12['comment_author_IP'],$dcl12['comment_date'],$dcl12['comment_date'],$dcl12['comment_content'],'1',$dcl11['comment_ID']);

                            echo '<br />' . $dcl12['comment_ID'] . '<br />';
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  message('Comments Updated - 11 Level');

  //Update Comment Counts in Wordpress
  $wc->query("UPDATE ".$DB_WP_DATABASE_PREFIX."posts SET comment_count = ( SELECT COUNT(comment_post_id) FROM ".$DB_WP_DATABASE_PREFIX."comments WHERE ".$DB_WP_DATABASE_PREFIX."posts.id = ".$DB_WP_DATABASE_PREFIX."comments.comment_post_id )");

  //Update wordpress users
  //From Drupal we're getting the essential user details, including their
  //user id so we can maintain the same posts ownership when we migrate
  //content over to Wordpress.
  //
  //* Special edge-case: we're skipping the administrative user migration
  //* Special edge-case: passwords are intentionally left blank as this
  //  forces user expiration in Wordpress
  $drupal_users = $dc->results("SELECT u.uid, u.name, u.mail, FROM_UNIXTIME(u.created) AS created, u.access FROM ".$DB_DP_DATABASE_PREFIX."users u WHERE u.uid != 1 AND u.uid != 0");
  foreach($drupal_users as $du)
  {
    $wc->query("INSERT INTO ".$DB_WP_DATABASE_PREFIX."users
      (`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_registered`, `display_name`)
      VALUES
      ('%s','%s','%s','%s','%s','%s','%s')", $du['uid'], $du['name'], '', $du['name'], $du['mail'], $du['created'], $du['name']);
  }

  message('Users Updated');

  message('Cheers !!');

  /*
    TO DO - Skipped coz didnt have much comment and Users, if you need then share you database and shall work upon and fix it for you.
    1.) Update Users/Authors
  */

  //Preformat the Object for Debuggin Purpose
  function po($obj){
    echo "<pre>";
    print_r($obj);
    echo "</pre>";
  }

  function message($msg){
    echo "<hr>$msg</hr>";
    func_flush();
  }

  function func_flush($s = NULL)
  {
    if (!is_null($s))
      echo $s;

    if (preg_match("/Apache(.*)Win/S", getenv('SERVER_SOFTWARE')))
      echo str_repeat(" ", 2500);
    elseif (preg_match("/(.*)MSIE(.*)\)$/S", getenv('HTTP_USER_AGENT')))
      echo str_repeat(" ", 256);

    if (function_exists('ob_flush'))
    {
      //for PHP >= 4.2.0
      @ob_flush();
    }
    else
    {
      //for PHP < 4.2.0
      if (ob_get_length() !== FALSE)
        ob_end_flush();
    }
    flush();
  }
