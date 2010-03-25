<?php
/**
 * Blog helper functions
 *
 * @package Blog
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Curverider Ltd
 * @copyright Curverider Ltd 2008-2010
 * @link http://elgg.org/
 */


/**
 * Returns HTML for a blog post.
 *
 * @param int $guid of a blog entity.
 * @return string html
 */
function blog_get_page_content_read($owner_guid = NULL, $guid = NULL) {
	$content = elgg_view('page_elements/content_header', array('context' => $context, 'type' => 'blog'));

	if ($guid) {
		$blog = get_entity($guid);

		if (elgg_instanceof($blog, 'object', 'blog') && $blog->status == 'final') {
			elgg_push_breadcrumb($blog->title, $blog->getURL());
			$content .= elgg_view_entity($blog, TRUE);
		} else {
			$content .= elgg_echo('blog:error:post_not_found');
		}
	} else {
		$options = array(
			'type' => 'object',
			'subtype' => 'blog',
			'full_view' => FALSE,
			'order_by_metadata' => array('name'=>'publish_date', 'direction'=>'DESC', 'as'=>'int')
		);

		if ($owner_guid) {
			$options['owner_guid'] = $owner_guid;
		}

		// show all posts for admin or users looking at their own blogs
		// show only published posts for other users.
		if (!(isadminloggedin() || (isloggedin() && $owner_guid == get_loggedin_userid()))) {
			$options['metadata_name_value_pairs'] = array(
				array('name' => 'status', 'value' => 'published'),
				array('name' => 'publish_date', 'operand' => '<', 'value' => time())
			);
		}

		$content .= elgg_list_entities_from_metadata($options);
	}

	return array('content' => $content);
}

/**
 * Returns HTML to edit a blog post.
 *
 * @param int $guid
 * @param int annotation id optional revision to edit
 * @return string html
 */
function blog_get_page_content_edit($guid, $revision = NULL) {
	$vars = array();
	if ($guid) {
		$blog = get_entity((int)$guid);

		if (elgg_instanceof($blog, 'object', 'blog') && $blog->canEdit()) {
			$vars['entity'] = $blog;

			if ($revision) {
				$revision = get_annotation((int)$revision);
				$vars['revision'] = $revision;

				if (!$revision || !($revision->entity_guid == $guid)) {
					$content = elgg_echo('blog:error:revision_not_found');
				}
			}

			elgg_push_breadcrumb($blog->title, $blog->getURL());
			elgg_push_breadcrumb(elgg_echo('edit'));

			$content = elgg_view('blog/forms/edit', $vars);
			$sidebar = elgg_view('blog/sidebar_revisions', array('entity' => $blog));
			//$sidebar .= elgg_view('blog/sidebar_related');
		} else {
			$content = elgg_echo('blog:error:post_not_found');
		}
	} else {
		elgg_push_breadcrumb(elgg_echo('blog:new'));
		$content = elgg_view('blog/forms/edit', $vars);
		//$sidebar = elgg_view('blog/sidebar_related');
	}

	return array('content' => $content, 'sidebar' => $sidebar);
}

/**
 * Show blogs with publish dates between $lower and $upper
 *
 * @param unknown_type $owner_guid
 * @param unknown_type $lower
 * @param unknown_type $upper
 */
function blog_get_page_content_archive($owner_guid, $lower, $upper) {
	$now = time();

	$content = elgg_view('page_elements/content_header', array('context' => $context, 'type' => 'blog'));

	if ($lower) {
		$lower = (int)$lower;
	}

	if ($upper) {
		$upper = (int)$upper;
	}

	$options = array(
		'type' => 'object',
		'subtype' => 'blog',
		'full_view' => FALSE,
		'order_by_metadata' => array('name'=>'publish_date', 'direction'=>'DESC', 'as'=>'int'),
	);

	if ($owner_guid) {
		$options['owner_guid'] = $owner_guid;
	}

	// admin / owners can see any posts
	// everyone else can only see published posts
	if (!(isadminloggedin() || (isloggedin() && $owner_guid == get_loggedin_userid()))) {
		if ($upper > $now) {
			$upper = $now;
		}

		$options['metadata_name_value_pairs'] = array(
			array('name' => 'status', 'value' => 'published')
		);
	}

	if ($lower) {
		$options['metadata_name_value_pairs'][] = array(
			'name' => 'publish_date',
			'operand' => '>',
			'value' => $lower
		);
	}

	if ($upper) {
		$options['metadata_name_value_pairs'][] = array(
			'name' => 'publish_date',
			'operand' => '<',
			'value' => $upper
		);
	}

	$content .= elgg_list_entities_from_metadata($options);

	return array(
		'content' => $content
	);
}

/**
 * Returns an appropriate excerpt for a blog.
 *
 * @param string $text
 * @return string
 */
function blog_make_excerpt($text) {
	return substr(strip_tags($text), 0, 300);
}

/**
 * Returns a list of years and months for all blogs optionally for a user.
 * Very similar to get_entity_dates() except uses a metadata field.
 *
 * @param mixed $user_guid
 */
function blog_get_blog_months($user_guid = NULL, $container_guid = NULL) {
	global $CONFIG;

	$subtype = get_subtype_id('blog');

	$q = "SELECT DISTINCT EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(mdv.string)) AS yearmonth
		FROM {$CONFIG->dbprefix}entities e, {$CONFIG->dbprefix}metadata, {$CONFIG->dbprefix}metastrings mdn, {$CONFIG->dbprefix}metastrings mdv
		WHERE e.guid = {$CONFIG->dbprefix}metadata.entity_guid
		AND {$CONFIG->dbprefix}metadata.name_id = mdn.id
		AND {$CONFIG->dbprefix}metadata.value_id = mdv.id
		AND mdn.string = 'publish_date'";

	if ($user_guid) {
		$user_guid = (int)$user_guid;
		$q .= " AND e.owner_guid = $user_guid";
	}

	if ($container_guid) {
		$container_guid = (int)$container_guid;
		$q .= " AND e.container_guid = $container_guid";
	}

	$q .= ' AND ' . get_access_sql_suffix('e');

	return get_data($q);
}

/**
 * Extended class to override the time_created
 */
class ElggBlog extends ElggObject {
	protected function initialise_attributes() {
		parent::initialise_attributes();

		// override the default file subtype.
		$this->attributes['subtype'] = 'blog';
	}

	/**
	 * Override the value returned for time_created
	 */
	public function __get($name) {
		if ($name == 'time_created') {
			$name = 'time_created';
		}

		return $this->get($name);
	}
}