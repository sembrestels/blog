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

	// temporarily force tag-cloud display
	echo "<h3>Tagcloud</h3>";
	echo "<div class='tagcloud sidebar'>".display_tagcloud(0, 100, 'tags')."</div>";
	echo "<a href=\"{$vars['url']}mod/tagcloud/tagcloud.php\">All site tags</a>";
?>
