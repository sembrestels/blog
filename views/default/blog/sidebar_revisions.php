<?php
/**
 * Blog sidebar menu showing revisions
 *
 * @package Blog
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Curverider Ltd
 * @copyright Curverider Ltd 2008-2010
 * @link http://elgg.org/
 */

//If editing a post, show the previous revisions and drafts.
$blog = isset($vars['entity']) ? $vars['entity'] : FALSE;

if (elgg_instanceof($blog, 'object', 'blog') && $blog->canEdit()) {
	$owner = $blog->getOwnerEntity();
	$revisions = array();

	if ($auto_save_annotations = $blog->getAnnotations('blog_auto_save', 1)) {
		$revisions[] = $auto_save_annotations[0];
	}

	// count(FALSE) == 1!  AHHH!!!
	if ($saved_revisions = $blog->getAnnotations('blog_revision', 10, 0, 'time_created DESC')) {
		$revision_count = count($saved_revisions);
	} else {
		$revision_count = 0;
	}

	$revisions = array_merge($revisions, $saved_revisions);

	if ($revisions) {
		echo '<h3>' . elgg_echo('blog:revisions') . '</h3>';

		$n = count($revisions);
		echo '<ul class="blog_revisions">';

		$load_base_url = "{$vars['url']}pg/blog/{$owner->username}/edit/{$blog->getGUID()}/";

		// show the "published revision"
		if ($blog->status == 'published') {
			$load = elgg_view('output/url', array(
				'href' => $load_base_url,
				'text' => elgg_echo('load')
			));

			$time = friendly_time($blog->publish_date);

			echo '<li>
			' . elgg_echo('blog:status:published') . ": $time $load
			</li>";
		}

		foreach ($revisions as $revision) {
			$time = friendly_time($revision->time_created);
			$load = elgg_view('output/url', array(
				'href' => $load_base_url . $revision->id,
				'text' => elgg_echo('load')
			));

			if ($revision->name == 'blog_auto_save') {
				$name = elgg_echo('blog:auto_saved_revision');
			} else {
				$name = elgg_echo('blog:revision') . " $n";
			}

			$text = "$name: $time $load";
			$class = 'class="auto_saved"';

			$n--;

			echo <<<___END
<li $class>
$text
</li>

___END;
		}

		echo '</ul>';
	}
}