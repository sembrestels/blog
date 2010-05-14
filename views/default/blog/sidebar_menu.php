<?php
/**
 * Blog sidebar menu.
 *
 * @package Blog
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Curverider Ltd
 * @copyright Curverider Ltd 2008-2010
 * @link http://elgg.org/
 */

// a few cases to consider:
// 1. looking at all posts
// 2. looking at a user's post
// 3. looking at your posts

/*
Logged in or not doesn't matter unless you're looking at your blog.
	Does it matter then on the side bar?

All blogs:
	Archives

Owned blogs;
	Archives
*/

$loggedin_user = get_loggedin_user();
$page_owner = page_owner_entity();

// include a view for plugins to extend
echo elgg_view("blogs/sidebar", array("object_type" => 'blog'));

// fetch & display latest comments on all blog posts
$comments = get_annotations(0, "object", "blog", "generic_comment", "", 0, 4, 0, "desc");
echo elgg_view('annotation/latest_comments', array('comments' => $comments));


// only show archives for users or groups.
// This is a limitation of the URL schema.
if ($page_owner) {
	$dates = blog_get_blog_months($user);

	if ($dates) {
		echo "<h3>".elgg_echo('blog:archives')."</h3>";

		echo '<ul>';
		foreach($dates as $date) {
			$date = $date->yearmonth;

			$timestamplow = mktime(0,0,0,substr($date,4,2),1,substr($date,0,4));
			$timestamphigh = mktime(0,0,0,((int) substr($date,4,2)) + 1,1,substr($date,0,4));

			if (!isset($page_owner)) $page_owner = page_owner_entity();
			$link = $CONFIG->wwwroot . 'pg/blog/' . $page_owner->username . '/archive/' . $timestamplow . '/' . $timestamphigh;
			//add_submenu_item(sprintf(elgg_echo('date:month:' . substr($date,4,2)), substr($date, 0, 4)), $link, 'filter');
			$month = sprintf(elgg_echo('date:month:' . substr($date,4,2)), substr($date, 0, 4));
			echo "<li><a href=\"$link\" title=\"$month\">$month</a><li>";
		}

		echo '</ul>';
	}
}

// tag-cloud display
echo display_tagcloud(0, 50, 'tags');
?>