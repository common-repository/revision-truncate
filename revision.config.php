<?php	/*
		Plugin Name: Revision Truncate!
		Plugin URI:  http://www.big8software.com/products/wordpress
		Description: Fastest plugin available for managing the number of revisions in your database.  Just input the number of revisions you would like to keep on each post the delete the rest.  You also get statistics on how many revisions are in your database and how many revisions you currently maintain.  Cleaning out the revisions on posts is an important part of keeping your system performance acceptable.  
		Version:     0.6
		Author:      Big8Software, Inc.
		Author URI:  http://www.big8software.com/
	*/

	add_action('plugins_loaded', 'b8RevisionTruncateInstall');
	add_action('admin_menu',     'b8RevisionTruncateAdminMenu');

	define('B8_RT_META_KEY',   'b8_revision_truncate');
	define('B8_RT_META_VALUE', '10;0');
	define('B8_RT_FILENAME',   'revision-truncate/revision.config.php');

	function b8RevisionTruncateInstall()
	{	// get $wpdb
		global $wpdb;

		// activate plugin
		if (true == $_REQUEST['activate'])
		{
			if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", B8_RT_META_KEY)) < 1)
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (meta_key, meta_value) VALUES (%s, %s)", 
								B8_RT_META_KEY, B8_RT_META_VALUE));
		}
		// deactivate plugin
		elseif (('deactivate' == $_REQUEST['action']) && (B8_RT_FILENAME == $_REQUEST['plugin']))
		{
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", B8_RT_META_KEY));
		}
	}

	function b8RevisionTruncateAdminMenu()
	{
		add_options_page('Revision Truncate! Options', 'Revision Truncate!', 'manage_options', __FILE__, 'b8RevisionTruncateConfigure');
	}

	function b8RevisionTruncateConfigure()
	{	// get $wpdb
		global $wpdb;

		// update configuration if the action is submit and it comes from the config.php file.
		if (('Truncate!' == $_REQUEST['submit']) && (B8_RT_FILENAME == $_REQUEST['page']))
		{
			$revs    = (ctype_digit($_REQUEST['versions']) ? intval($_REQUEST['versions']) : 10);
			$rd_last = ((1 == $_REQUEST['last']) ? 1 : 0);
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s", B8_RT_META_KEY, "$revs;$rd_last" ));
			b8RevisionTruncateRun($revs);
		}

		// display configuration
		$total_revisions  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'revision'));
						$max_revisions = 0;
		
		$max_revisions  = $wpdb->get_var($wpdb->prepare("SELECT MAX(revisions) 
								FROM (
SELECT post_parent, COUNT(*) AS revisions FROM {$wpdb->posts} 
					 								WHERE post_type = 'revision'  
								AND LOCATE('autosave', post_name) = 0 GROUP BY post_parent) AS MIN_TABLE"));

						$revisions_option = $wpdb->get_var($wpdb->prepare("SELECT SUBSTRING_INDEX(meta_value, ';', 1) 
								FROM {$wpdb->postmeta} WHERE meta_key  = %s LIMIT 1", B8_RT_META_KEY));

// HTML BEGIN*********************************************************************************************************************
		echo <<<EOD
<div class="wrap">
	<h2>Revision Truncate!</h2>
	This plugin truncates old revisions from published posts by number of revisons.<br />
	Just enter how many revisions you would like to keep and it will remove older content.<br />
	This is really helpful in cleaning up systems with lots of revision data.<br /><br />
	Plugin address from <a href="http://www.big8software.com/products/wordpress">Revision Truncate!</a><br />
	Author Homepage <a href="http://www.big8software.com">www.big8software.com</a><br /><br /><br />
</div>
<form method="post">
	<div class="wrap">
		<h2>Configure Revision Truncate!</h2>
		Truncate revisions from published posts greater than a maximum of
		<input type="text" name="versions" value="$revs" size="2" /> versions(s)<br />
		You currently have a total of $total_revisions revision(s) in your database.<br />
		The maximum number of revisions for any post is $max_revisions revision(s).<br />
		<div class="wrap submit">
			<input name="submit" value="Truncate!" type="submit" />
			<input name="reset"  value="Reset"     type="reset"  />
		</div>
	</div>
	<div class="wrap">
		<br />
		<h2>Note!</h2>
		Make sure you have a backup of your database before truncating the revisions.<br />
		You will not be able to restore old revision without this backup.<br />
	</div>
</form>
EOD;
// HTML END*********************************************************************************************************************
		}

	function b8RevisionTruncateRun($revisions)
	{	// get $wpdb
		global $wpdb;
	
		// create a table to store the ids we'll be removing
		$wpdb->query('CREATE TEMPORARY TABLE revtest (id INT(11) DEFAULT NULL) ENGINE=MyISAM');
		// look for posts having a revision level lower than $revs (e.g., with 100 revisions, pull back rows 0-90)
		// look for posts having a revision level lower than 
		$wpdb->query($wpdb->prepare("INSERT INTO revtest
						     SELECT id
						     FROM {$wpdb->posts} AS x
						     WHERE post_type  = %s
						     AND   LOCATE(%s, post_name) = 0
						     AND   IF(post_name LIKE %s, 1, 
CONVERT(SUBSTRING_INDEX(post_name, %s, -1) USING utf8))
  <=
					(SELECT MAX( 	   
IF(post_name LIKE %s, 1, 
CONVERT(SUBSTRING_INDEX(post_name, %s, -1) USING utf8)) 
 - %d)
					 FROM {$wpdb->posts}
					 WHERE post_parent  = x.post_parent
					 AND   post_type             = %s
					 AND   LOCATE(%s, post_name) = 0)",
					'revision', 'autosave', '%revision', '-', '%revision', '-', $revisions, 'revision', 'autosave'));
		// remove the unwanted revisions
		$wpdb->query("DELETE FROM {$wpdb->posts} WHERE id IN (SELECT id FROM revtest)");
		// optimize wp_posts table
		$wpdb->query("OPTIMIZE TABLE {$wpdb->posts}");
	}
?>
