<?php
	
	require_once("php-mysql.php");

	$DB_HOSTNAME	= 'localhost';

	$DB_WP_USERNAME	= 'root';
	$DB_WP_PASSWORD	= 'root';
	$DB_WORDPRESS	= 'robin_wordpress';

	$DB_DP_USERNAME	= 'root';
	$DB_DP_PASSWORD	= 'root';
	$DB_DRUPAL		= 'robin_drupal';

	$DB_WORDPRESS_PREFIX = 'wp_';
	$DB_DRUPAL_PREFIX	 = '';

	$drupal_connection = array(
		"host" => "localhost",
		"username" => $DB_DP_USERNAME,
		"password" => $DB_DP_PASSWORD,
		"database" => $DB_DRUPAL
	);

	$wordpress_connection = array(
		"host" => "localhost",
		"username" => $DB_WP_USERNAME,
		"password" => $DB_WP_PASSWORD,
		"database" => $DB_WORDPRESS
	);

	$dc = new DB($drupal_connection);
	$wc = new DB($wordpress_connection);
	
	$dcheck = $dc->check();	
	if (!$dcheck)
	{
		echo "This $DB_DRUPAL service is AVAILABLE";
		die();
	}

	$wcheck = $wc->check();	
	if (!$wcheck)
	{
		echo "This $DB_WORDPRESS service is AVAILABLE";
		die();
	}

	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."comments");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."links");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."postmeta");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."posts");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."term_relationships");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."term_taxonomy");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."terms");

	//Tags
	$drupal_tags = $dc->results("SELECT DISTINCT d.tid, d.name, REPLACE(LOWER(d.name), ' ', '_') as slug FROM ".$DB_DRUPAL_PREFIX."taxonomy_term_data d INNER JOIN ".$DB_DRUPAL_PREFIX."taxonomy_term_hierarchy h USING(tid) WHERE (1) ORDER BY d.tid");	
	foreach($drupal_tags as $dt)
	{
		$wc->query("REPLACE INTO ".$DB_WORDPRESS_PREFIX."terms (term_id, name, slug) VALUES ('%s','%s','%s')", $dt['tid'], $dt['name'], $dt['slug']);
	}

	$drupal_taxonomy = $dc->results("SELECT DISTINCT d.tid 'term_id', 'post_tag' , d.description 'description', h.parent 'parent' FROM ".$DB_DRUPAL_PREFIX."taxonomy_term_data d INNER JOIN ".$DB_DRUPAL_PREFIX."taxonomy_term_hierarchy h USING(tid) WHERE (1)  ORDER BY d.tid");
	foreach($drupal_taxonomy as $dt)
	{
		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."term_taxonomy (term_id, taxonomy, description, parent) VALUES ('%s','%s','%s','%s')", $dt['term_id'], $dt['post_tag'], $dt['description'], $dt['parent']);
	}

	//Post
	$drupal_posts = $dc->results("SELECT DISTINCT n.nid 'id', n.uid 'post_author', FROM_UNIXTIME(n.created) 'post_date', r.body_value 'post_content', n.title 'post_title', r.body_summary 'post_excerpt', n.type 'post_type', IF(n.status = 1, 'publish', 'private') 'post_status' FROM ".$DB_DRUPAL_PREFIX."node n, ".$DB_DRUPAL_PREFIX."field_data_body r WHERE n.vid = r.entity_id");
	foreach($drupal_posts as $dp)
	{
		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."posts (id, post_author, post_date, post_content, post_title, post_excerpt, post_type, post_status) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s')", $dp['id'], $dp['post_author'], $dp['post_date'], $dp['post_content'], $dp['post_title'], $dp['post_excerpt'], $dp['post_type'], $dp['post_status']);
	}

	$drupal_post_tags = $dc->results("SELECT DISTINCT node.nid, taxonomy_term_data.tid FROM (".$DB_DRUPAL_PREFIX."taxonomy_index taxonomy_index INNER JOIN ".$DB_DRUPAL_PREFIX."taxonomy_term_data taxonomy_term_data ON (taxonomy_index.tid = taxonomy_term_data.tid)) INNER JOIN ".$DB_DRUPAL_PREFIX."node node ON (node.nid = taxonomy_index.nid)"); 
	foreach($drupal_post_tags as $dpt)
	{
		$wordpress_term_tax = $wc->row("SELECT DISTINCT term_taxonomy.term_taxonomy_id FROM ".$DB_WORDPRESS_PREFIX."term_taxonomy term_taxonomy  WHERE (term_taxonomy.term_id = ".$dpt['tid'].")"); 
		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."term_relationships (object_id, term_taxonomy_id) VALUES ('%s','%s')", $dpt['nid'], $wordpress_term_tax['term_taxonomy_id']);
	}	

	$wc->query("UPDATE ".$DB_WORDPRESS_PREFIX."posts SET post_type = 'post' WHERE post_type IN ('blog')");

	$wc->query("UPDATE ".$DB_WORDPRESS_PREFIX."term_taxonomy tt SET `count` = ( SELECT COUNT(tr.object_id) FROM ".$DB_WORDPRESS_PREFIX."term_relationships tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id )");	

	$drupal_url = $dc->results("SELECT url_alias.source, url_alias.alias FROM ".$DB_DRUPAL_PREFIX."url_alias url_alias WHERE (url_alias.source LIKE 'node%')");
	foreach($drupal_url as $du)
	{
		$update = $wc->query("UPDATE ".$DB_WORDPRESS_PREFIX."posts SET post_name = '%s' WHERE ID = '%s'",$du['alias'],str_replace('node/','',$du['source']));
	}

	function po($obj)
	{
		echo "<pre>";
		print_r($obj);
		echo "</pre>";
	}	
?>