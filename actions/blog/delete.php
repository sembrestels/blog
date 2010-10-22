<?php
/**
 * Delete blog entity
 *
 * @package Blog
 */

$blog_guid = get_input('guid');
$blog = get_entity($blog_guid);

if (elgg_instanceof($blog, 'object', 'blog') && $blog->canEdit()) {
	$container = get_entity($blog->container_guid);
	if ($blog->delete()) {
		system_message(elgg_echo('blog:message:deleted_post'));
		forward("pg/blog/$container->username/read/");
	} else {
		register_error(elgg_echo('blog:error:cannot_delete_post'));
	}
} else {
	register_error(elgg_echo('blog:error:post_not_found'));
}

forward($_SERVER['HTTP_REFERER']);