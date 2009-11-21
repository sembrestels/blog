<?php
/**
 * Elgg metadata
 * Functions to manage object metadata.
 *
 * @package Elgg
 * @subpackage Core
 * @author Curverider Ltd <info@elgg.com>
 * @link http://elgg.org/
 */

/**
 * ElggMetadata
 * This class describes metadata that can be attached to ElggEntities.
 *
 * @author Curverider Ltd <info@elgg.com>
 * @package Elgg
 * @subpackage Core
 */
class ElggMetadata extends ElggExtender {
	/**
	 * Construct a new site object, optionally from a given id value or row.
	 *
	 * @param mixed $id
	 */
	function __construct($id = null) {
		$this->attributes = array();

		if (!empty($id)) {
			// Create from db row
			if ($id instanceof stdClass) {
				$metadata = $id;
			} else {
				$metadata = get_metadata($id);
			}

			if ($metadata) {
				$objarray = (array) $metadata;
				foreach($objarray as $key => $value) {
					$this->attributes[$key] = $value;
				}
				$this->attributes['type'] = "metadata";
			}
		}
	}

	/**
	 * Class member get overloading
	 *
	 * @param string $name
	 * @return mixed
	 */
	function __get($name) {
		return $this->get($name);
	}

	/**
	 * Class member set overloading
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return mixed
	 */
	function __set($name, $value) {
		return $this->set($name, $value);
	}

	/**
	 * Determines whether or not the user can edit this piece of metadata
	 *
	 * @return true|false Depending on permissions
	 */
	function canEdit() {
		if ($entity = get_entity($this->get('entity_guid'))) {
			return $entity->canEditMetadata($this);
		}
		return false;
	}

	/**
	 * Save matadata object
	 *
	 * @return int the metadata object id
	 */
	function save() {
		if ($this->id > 0) {
			return update_metadata($this->id, $this->name, $this->value, $this->value_type, $this->owner_guid, $this->access_id);
		} else {
			$this->id = create_metadata($this->entity_guid, $this->name, $this->value, $this->value_type, $this->owner_guid, $this->access_id);
			if (!$this->id) {
				throw new IOException(sprintf(elgg_new('IOException:UnableToSaveNew'), get_class()));
			}
			return $this->id;
		}
	}

	/**
	 * Delete a given metadata.
	 */
	function delete() {
		return delete_metadata($this->id);
	}

	/**
	 * Get a url for this item of metadata.
	 *
	 * @return string
	 */
	public function getURL() {
		return get_metadata_url($this->id);
	}

	// SYSTEM LOG INTERFACE ////////////////////////////////////////////////////////////

	/**
	 * For a given ID, return the object associated with it.
	 * This is used by the river functionality primarily.
	 * This is useful for checking access permissions etc on objects.
	 */
	public function getObjectFromID($id) {
		return get_metadata($id);
	}
}

/**
 * Convert a database row to a new ElggMetadata
 *
 * @param stdClass $row
 * @return stdClass or ElggMetadata
 */
function row_to_elggmetadata($row) {
	if (!($row instanceof stdClass)) {
		return $row;
	}

	return new ElggMetadata($row);
}

/**
 * Get a specific item of metadata.
 *
 * @param $id int The item of metadata being retrieved.
 */
function get_metadata($id) {
	global $CONFIG;

	$id = (int)$id;
	$access = get_access_sql_suffix("e");
	$md_access = get_access_sql_suffix("m");

	return row_to_elggmetadata(get_data_row("SELECT m.*, n.string as name, v.string as value from {$CONFIG->dbprefix}metadata m JOIN {$CONFIG->dbprefix}entities e on e.guid = m.entity_guid JOIN {$CONFIG->dbprefix}metastrings v on m.value_id = v.id JOIN {$CONFIG->dbprefix}metastrings n on m.name_id = n.id where m.id=$id and $access and $md_access"));
}

/**
 * Removes metadata on an entity with a particular name, optionally with a given value.
 *
 * @param int $entity_guid The entity GUID
 * @param string $name The name of the metadata
 * @param string $value The optional value of the item (useful for removing a single item in a multiple set)
 * @return true|false Depending on success
 */
function remove_metadata($entity_guid, $name, $value = "") {
	global $CONFIG;
	$entity_guid = (int) $entity_guid;
	$name = sanitise_string($name);
	$value = sanitise_string($value);

	$query = "SELECT * from {$CONFIG->dbprefix}metadata WHERE entity_guid = $entity_guid and name_id=" . add_metastring($name);
	if ($value!="") {
		$query .= " and value_id=" . add_metastring($value);
	}

	if ($existing = get_data($query)) {
		foreach($existing as $ex) {
			delete_metadata($ex->id);
		}
		return true;
	}

	return false;
}

/**
 * Create a new metadata object, or update an existing one.
 *
 * @param int $entity_guid
 * @param string $name
 * @param string $value
 * @param string $value_type
 * @param int $owner_guid
 * @param int $access_id
 * @param bool $allow_multiple
 */
function create_metadata($entity_guid, $name, $value, $value_type, $owner_guid, $access_id = ACCESS_PRIVATE, $allow_multiple = false) {
	global $CONFIG;

	$entity_guid = (int)$entity_guid;
	//$name = sanitise_string(trim($name));
	//$value = sanitise_string(trim($value));
	$value_type = detect_extender_valuetype($value, sanitise_string(trim($value_type)));
	$time = time();
	$owner_guid = (int)$owner_guid;
	$allow_multiple = (boolean)$allow_multiple;

	if ($owner_guid==0) {
		$owner_guid = get_loggedin_userid();
	}

	$access_id = (int)$access_id;

	$id = false;

	$existing = get_data_row("SELECT * from {$CONFIG->dbprefix}metadata WHERE entity_guid = $entity_guid and name_id=" . add_metastring($name) . " limit 1");
	if (($existing) && (!$allow_multiple) && (isset($value))) {
		$id = $existing->id;
		$result = update_metadata($id, $name, $value, $value_type, $owner_guid, $access_id);

		if (!$result) {
			return false;
		}
	}
	else if (isset($value)) {
		// Support boolean types
		if (is_bool($value)) {
			if ($value) {
				$value = 1;
			} else {
				$value = 0;
			}
		}

		// Add the metastrings
		$value = add_metastring($value);
		if (!$value) {
			return false;
		}

		$name = add_metastring($name);
		if (!$name) {
			return false;
		}

		// If ok then add it
		$id = insert_data("INSERT into {$CONFIG->dbprefix}metadata (entity_guid, name_id, value_id, value_type, owner_guid, time_created, access_id) VALUES ($entity_guid, '$name','$value','$value_type', $owner_guid, $time, $access_id)");

		if ($id!==false) {
			$obj = get_metadata($id);
			if (trigger_elgg_event('create', 'metadata', $obj)) {
				return true;
			} else {
				delete_metadata($id);
			}
		}

	} else if ($existing) {
		// TODO: Check... are you sure you meant to do this Ben? :)
		$id = $existing->id;
		delete_metadata($id);
	}

	return $id;
}

/**
 * Update an item of metadata.
 *
 * @param int $id
 * @param string $name
 * @param string $value
 * @param string $value_type
 * @param int $owner_guid
 * @param int $access_id
 */
function update_metadata($id, $name, $value, $value_type, $owner_guid, $access_id) {
	global $CONFIG;

	$id = (int)$id;

	if (!$md = get_metadata($id)) {
		return false;
	}
	if (!$md->canEdit()) {
		return false;
	}

	// If memcached then we invalidate the cache for this entry
	static $metabyname_memcache;
	if ((!$metabyname_memcache) && (is_memcache_available())) {
		$metabyname_memcache = new ElggMemcache('metabyname_memcache');
	}

	if ($metabyname_memcache) {
		$metabyname_memcache->delete("{$md->entity_guid}:{$md->name_id}");
	}

	//$name = sanitise_string(trim($name));
	//$value = sanitise_string(trim($value));
	$value_type = detect_extender_valuetype($value, sanitise_string(trim($value_type)));

	$owner_guid = (int)$owner_guid;
	if ($owner_guid==0) {
		$owner_guid = get_loggedin_userid();
	}

	$access_id = (int)$access_id;

	$access = get_access_sql_suffix();

	// Support boolean types (as integers)
	if (is_bool($value)) {
		if ($value) {
			$value = 1;
		} else {
			$value = 0;
		}
	}

	// Add the metastring
	$value = add_metastring($value);
	if (!$value) {
		return false;
	}

	$name = add_metastring($name);
	if (!$name) {
		return false;
	}

	// If ok then add it
	$result = update_data("UPDATE {$CONFIG->dbprefix}metadata set value_id='$value', value_type='$value_type', access_id=$access_id, owner_guid=$owner_guid where id=$id and name_id='$name'");
	if ($result!==false) {
		$obj = get_metadata($id);
		if (trigger_elgg_event('update', 'metadata', $obj)) {
			return true;
		} else {
			delete_metadata($id);
		}
	}

	return $result;
}

/**
 * This function creates metadata from an associative array of "key => value" pairs.
 *
 * @param int $entity_guid
 * @param string $name_and_values
 * @param string $value_type
 * @param int $owner_guid
 * @param int $access_id
 * @param bool $allow_multiple
 */
function create_metadata_from_array($entity_guid, array $name_and_values, $value_type, $owner_guid, $access_id = ACCESS_PRIVATE, $allow_multiple = false) {
	foreach ($name_and_values as $k => $v) {
		if (!create_metadata($entity_guid, $k, $v, $value_type, $owner_guid, $access_id, $allow_multiple)) {
			return false;
		}
	}
	return true;
}

/**
 * Delete an item of metadata, where the current user has access.
 *
 * @param $id int The item of metadata to delete.
 */
function delete_metadata($id) {
	global $CONFIG;

	$id = (int)$id;
	$metadata = get_metadata($id);

	if ($metadata) {
		// Tidy up if memcache is enabled.
		static $metabyname_memcache;
		if ((!$metabyname_memcache) && (is_memcache_available())) {
			$metabyname_memcache = new ElggMemcache('metabyname_memcache');
		}

		if ($metabyname_memcache) {
			$metabyname_memcache->delete("{$metadata->entity_guid}:{$metadata->name_id}");
		}

		if (($metadata->canEdit()) && (trigger_elgg_event('delete', 'metadata', $metadata))) {
			return delete_data("DELETE from {$CONFIG->dbprefix}metadata where id=$id");
		}
	}

	return false;
}

/**
 * Return the metadata values that match your query.
 *
 * @param string $meta_name
 * @return mixed either a value, an array of ElggMetadata or false.
 */
function get_metadata_byname($entity_guid,  $meta_name) {
	global $CONFIG;

	$meta_name = get_metastring_id($meta_name);

	if (empty($meta_name)) {
		return false;
	}

	$entity_guid = (int)$entity_guid;
	$access = get_access_sql_suffix("e");
	$md_access = get_access_sql_suffix("m");

	// If memcache is available then cache this (cache only by name for now since this is the most common query)
	$meta = null;
	static $metabyname_memcache;
	if ((!$metabyname_memcache) && (is_memcache_available())) {
		$metabyname_memcache = new ElggMemcache('metabyname_memcache');
	}
	if ($metabyname_memcache) {
		$meta = $metabyname_memcache->load("{$entity_guid}:{$meta_name}");
	}
	if ($meta) {
		return $meta;
	}

	$result = get_data("SELECT m.*, n.string as name, v.string as value from {$CONFIG->dbprefix}metadata m JOIN {$CONFIG->dbprefix}entities e ON e.guid = m.entity_guid JOIN {$CONFIG->dbprefix}metastrings v on m.value_id = v.id JOIN {$CONFIG->dbprefix}metastrings n on m.name_id = n.id where m.entity_guid=$entity_guid and m.name_id='$meta_name' and $access and $md_access", "row_to_elggmetadata");
	if (!$result) {
		return false;
	}

	// Cache if memcache available
	if ($metabyname_memcache) {
		if (count($result) == 1) {
			$r = $result[0];
		} else {
			$r = $result;
		}
		// This is a bit of a hack - we shorten the expiry on object
		// metadata so that it'll be gone in an hour. This means that
		// deletions and more importantly updates will filter through eventually.
		$metabyname_memcache->setDefaultExpiry(3600);
		$metabyname_memcache->save("{$entity_guid}:{$meta_name}", $r);
	}
	if (count($result) == 1) {
		return $result[0];
	}

	return $result;
}

/**
 * Return all the metadata for a given GUID.
 *
 * @param int $entity_guid
 */
function get_metadata_for_entity($entity_guid) {
	global $CONFIG;

	$entity_guid = (int)$entity_guid;
	$access = get_access_sql_suffix("e");
	$md_access = get_access_sql_suffix("m");

	return get_data("SELECT m.*, n.string as name, v.string as value from {$CONFIG->dbprefix}metadata m JOIN {$CONFIG->dbprefix}entities e ON e.guid = m.entity_guid JOIN {$CONFIG->dbprefix}metastrings v on m.value_id = v.id JOIN {$CONFIG->dbprefix}metastrings n on m.name_id = n.id where m.entity_guid=$entity_guid and $access and $md_access", "row_to_elggmetadata");
}

/**
 * Get the metadata where the entities they are referring to match a given criteria.
 *
 * @param mixed $meta_name
 * @param mixed $meta_value
 * @param string $entity_type The type of entity to look for, eg 'site' or 'object'
 * @param string $entity_subtype The subtype of the entity.
 * @param int $limit
 * @param int $offset
 * @param string $order_by Optional ordering.
 * @param int $site_guid The site to get entities for. Leave as 0 (default) for the current site; -1 for all sites.
 */
function find_metadata($meta_name = "", $meta_value = "", $entity_type = "", $entity_subtype = "", $limit = 10, $offset = 0, $order_by = "", $site_guid = 0) {
	global $CONFIG;

	$meta_n = get_metastring_id($meta_name);
	$meta_v = get_metastring_id($meta_value);

	$entity_type = sanitise_string($entity_type);
	$entity_subtype = get_subtype_id($entity_type, $entity_subtype);
	$limit = (int)$limit;
	$offset = (int)$offset;
	if ($order_by == "") {
		$order_by = "e.time_created desc";
	}

	$order_by = sanitise_string($order_by);
	$site_guid = (int) $site_guid;
	if ($site_guid == 0) {
		$site_guid = $CONFIG->site_guid;
	}

	$where = array();

	if ($entity_type!="") {
		$where[] = "e.type='$entity_type'";
	}

	if ($entity_subtype) {
		$where[] = "e.subtype=$entity_subtype";
	}

	if ($meta_name!="") {
		if (!$meta_v) {
			// The value is set, but we didn't get a value... so something went wrong.
			return false;
		}
		$where[] = "m.name_id='$meta_n'";
	}
	if ($meta_value!="") {
		// The value is set, but we didn't get a value... so something went wrong.
		if (!$meta_v) {
			return false;
		}
		$where[] = "m.value_id='$meta_v'";
	}
	if ($site_guid > 0) {
		$where[] = "e.site_guid = {$site_guid}";
	}

	$query = "SELECT m.*, n.string as name, v.string as value from {$CONFIG->dbprefix}entities e JOIN {$CONFIG->dbprefix}metadata m on e.guid = m.entity_guid JOIN {$CONFIG->dbprefix}metastrings v on m.value_id = v.id JOIN {$CONFIG->dbprefix}metastrings n on m.name_id = n.id where";
	foreach ($where as $w) {
		$query .= " $w and ";
	}
	$query .= get_access_sql_suffix("e"); // Add access controls
	$query .= ' and ' . get_access_sql_suffix("m"); // Add access controls
	$query .= " order by $order_by limit $offset, $limit"; // Add order and limit

	return get_data($query, "row_to_elggmetadata");
}



/**
 * Returns entities based upon metadata.
 *
 * @param array $options Array in format:
 *
 * 	metadata_names => NULL|ARR metadata names
 *
 * 	metadata_values => NULL|ARR metadata values
 *
 * 	metadata_name_value_pairs => NULL|ARR (name = 'name', value => 'value', 'operand' => 'AND', 'case_sensitive' => TRUE) entries
 *
 * 	metadata_name_value_pairs_operator => NULL|STR The operator to use for combining (name = value) OPERATOR (name = value)
 *
 * 	metadata_case_sensitive => BOOL Overall Case sensitive
 *
 * @return array
 */
function elgg_get_entities_from_metadata(array $options = array()) {
	$defaults = array(
		'metadata_names' => NULL,
		'metadata_name' => NULL,
		'metadata_values' => NULL,
		'metadata_value' => NULL,
		'metadata_name_value_pair' => NULL,
		'metadata_name_value_pairs' => NULL,
		'metadata_name_value_pairs_operator' => 'AND',
		'metadata_case_sensitive' => TRUE
	);

	$options = array_merge($defaults, $options);

	$singulars = array('metadata_name', 'metadata_value', 'metadata_name_value_pair');
	$options = elgg_normalise_plural_options_array($options, $singulars);

	$clauses = elgg_get_entity_metadata_where_sql('e', $options['metadata_names'], $options['metadata_values'],
		$options['metadata_name_value_pairs'], $options['metadata_name_value_pairs_operator'], $options['metadata_case_sensitive']);

	if ($clauses) {
		// merge wheres to pass to get_entities()
		if (isset($options['wheres']) && !is_array($options['wheres'])) {
			$options['wheres'] = array($options['wheres']);
		} elseif (!isset($options['wheres'])) {
			$options['wheres'] = array();
		}

		$options['wheres'] = array_merge($options['wheres'], $clauses['wheres']);

		// merge joins to pass to get_entities()
		if (isset($options['joins']) && !is_array($options['joins'])) {
			$options['joins'] = array($options['joins']);
		} elseif (!isset($options['joins'])) {
			$options['joins'] = array();
		}

		$options['joins'] = array_merge($options['joins'], $clauses['joins']);
	}

	return elgg_get_entities($options);
}

/**
 * Returns metadata name and value SQL where for entities.
 * nb: $names and $values are not paired. Use $pairs for this.
 * Pairs default to '=' operand.
 *
 * @param $prefix
 * @param ARR|NULL $names
 * @param ARR|NULL $values
 * @param ARR|NULL $pairs array of names / values / operands
 * @param AND|OR $pair_operator Operator to use to join the where clauses for pairs
 * @param BOOL $case_sensitive
 * @return FALSE|array False on fail, array('joins', 'wheres')
 */
function elgg_get_entity_metadata_where_sql($table, $names = NULL, $values = NULL, $pairs = NULL, $pair_operator = 'AND', $case_sensitive = TRUE) {
	global $CONFIG;

	// short circuit if nothing requested
	// 0 is a valid (if not ill-conceived) metadata name.
	// 0 is also a valid metadata value for FALSE, NULL, or 0
	if ((!$names && $names !== 0)
		&& (!$values && $values !== 0)
		&& (!$pairs && $pairs !== 0)) {
		return '';
	}

	// binary forces byte-to-byte comparision of strings, making
	// it case- and diacritical-mark- sensitive.
	// only supported on values.
	$binary = ($case_sensitive) ? ' BINARY ' : '';

	$access = get_access_sql_suffix('md');

	$return = array (
		'joins' => array (),
		'wheres' => array()
	);

	$wheres = array();

	// get names wheres and joins
	$names_where = '';
	if ($names !== NULL) {
		$return['joins'][] = "JOIN {$CONFIG->dbprefix}metadata md on {$table}.guid = md.entity_guid";
		if (!is_array($names)) {
			$names = array($names);
		}

		$sanitised_names = array();
		foreach ($names as $name) {
			// normalise to 0.
			if (!$name) {
				$name = '0';
			}
			$sanitised_names[] = "'$name'";
		}

		if ($names_str = implode(',', $sanitised_names)) {
			$return['joins'][] = "JOIN {$CONFIG->dbprefix}metastrings msn on md.name_id = msn.id";
			$names_where = "(msn.string IN ($names_str))";
		}
	}

	// get values wheres and joins
	$values_where = '';
	if ($values !== NULL) {
		$return['joins'][] = "JOIN {$CONFIG->dbprefix}metadata md on {$table}.guid = md.entity_guid";

		if (!is_array($values)) {
			$values = array($values);
		}

		$sanitised_values = array();
		foreach ($values as $value) {
			// normalize to 0
			if (!$value) {
				$value = 0;
			}
			$sanitised_values[] = "'$value'";
		}

		if ($values_str = implode(',', $sanitised_values)) {
			$return['joins'][] = "JOIN {$CONFIG->dbprefix}metastrings msv on md.value_id = msv.id";
			$values_where = "({$binary}msv.string IN ($values_str))";
		}
	}

	if ($names_where && $values_where) {
		$wheres[] = "($names_where AND $values_where AND $access)";
	} elseif ($names_where) {
		$wheres[] = "($names_where AND $access)";
	} elseif ($values_where) {
		$wheres[] = "($values_where AND $access)";
	}

	// add pairs
	// pairs must be in arrays.
	if (is_array($pairs)) {
		$array = array(
			'name' => 'test',
			'value' => 5
		);

		$array = array('test' => 5);

		// check if this is an array of pairs or just a single pair.
		if (isset($pairs['name']) || isset($pairs['value'])) {
			$pairs = array($pairs);
		}

		$pair_wheres = array();

		// @todo when the pairs are > 3 should probably split the query up to
		// denormalize the strings table.
		$i = 1;
		foreach ($pairs as $index => $pair) {
			// @todo move this elsewhere?
			// support shortcut 'n' => 'v' method.
			if (!is_array($pair)) {
				$pair = array(
					'name' => $index,
					'value' => $pair
				);
			}

			// @todo The multiple joins are only needed when the operator is AND
			$return['joins'][] = "JOIN {$CONFIG->dbprefix}metadata md{$i} on {$table}.guid = md{$i}.entity_guid";
			$return['joins'][] = "JOIN {$CONFIG->dbprefix}metastrings msn{$i} on md{$i}.name_id = msn{$i}.id";
			$return['joins'][] = "JOIN {$CONFIG->dbprefix}metastrings msv{$i} on md{$i}.value_id = msv{$i}.id";

			// must have at least a name and value
			if (!isset($pair['name']) || !isset($pair['value'])) {
				// @todo should probably return false.
				continue;
			}

			// case sensitivity can be specified per pair.
			// default to higher level setting.
			if (isset($pair['case_sensitive'])) {
				$pair_binary = ($pair['case_sensitive']) ? ' BINARY ' : '';
			} else {
				$pair_binary = $binary;
			}

			if (isset($pair['operand'])) {
				$operand = mysql_real_escape_string($pair['operand']);
			} else {
				$operand = ' = ';
			}

			// if the value is an int, don't quote it because str '15' < str '5'
			// if the operand is IN don't quote it because quoting should be done already.
			//$value = trim(strtolower($operand)) == 'in' ? $pair['value'] : "'{$pair['value']}'";
			if (trim(strtolower($operand)) == 'in' || sanitise_int($pair['value'])) {
				$value = $pair['value'];
			} else {
				$value = "'{$pair['value']}'";
			}

			$access = get_access_sql_suffix("md{$i}");
			$pair_wheres[] = "(msn{$i}.string = '{$pair['name']}' AND {$pair_binary}msv{$i}.string $operand $value AND $access)";
			$i++;
		}

		if ($where = implode (" $pair_operator ", $pair_wheres)) {
			$wheres[] = "($where)";
		}
	}

	if ($where = implode(' OR ', $wheres)) {
		$return['wheres'][] = "($where)";
	}

	return $return;
}

/**
 * Return a list of entities based on the given search criteria.
 *
 * @deprecated 1.7 use elgg_get_entities_from_metadata().
 * @param mixed $meta_name
 * @param mixed $meta_value
 * @param string $entity_type The type of entity to look for, eg 'site' or 'object'
 * @param string $entity_subtype The subtype of the entity.
 * @param int $limit
 * @param int $offset
 * @param string $order_by Optional ordering.
 * @param int $site_guid The site to get entities for. Leave as 0 (default) for the current site; -1 for all sites.
 * @param true|false $count If set to true, returns the total number of entities rather than a list. (Default: false)
 * @param true|false $case_sensitive If set to false this searches for the meta data without case sensitivity. (Default: true)
 *
 * @return int|array A list of entities, or a count if $count is set to true
 */
function get_entities_from_metadata($meta_name, $meta_value = "", $entity_type = "", $entity_subtype = "",
$owner_guid = 0, $limit = 10, $offset = 0, $order_by = "", $site_guid = 0,
$count = FALSE, $case_sensitive = TRUE) {

	elgg_log('get_entities_from_metadata() was deprecated in 1.7 by elgg_get_entities_from_metadata()!', 'WARNING');

	$options = array();

	$options['metadata_names'] = $meta_name;

	if ($meta_value) {
		$options['metadata_values'] = $meta_value;
	}

	if ($entity_type) {
		$options['types'] = $entity_type;
	}

	if ($entity_subtype) {
		$options['subtypes'] = $entity_subtype;
	}

	if ($owner_guid) {
		$options['owner_guid'] = $owner_guid;
	}

	if ($limit) {
		$options['limit'] = $limit;
	}

	if ($offset) {
		$options['offset'] = $offset;
	}

	if ($order_by) {
		$options['order_by'];
	}

	if ($site_guid) {
		$options['site_guid'];
	}

	if ($count) {
		$options['count'] = $count;
	}

	// need to be able to pass false
	$options['metadata_case_sensitive'] = $case_sensitive;

	return elgg_get_entities_from_metadata($options);
}

/**
 * Return a list of entities suitable for display based on the given search criteria.
 *
 * @see elgg_view_entity_list
 *
 * @param mixed $meta_name Metadata name to search on
 * @param mixed $meta_value The value to match, optionally
 * @param string $entity_type The type of entity to look for, eg 'site' or 'object'
 * @param string $entity_subtype The subtype of the entity
 * @param int $limit Number of entities to display per page
 * @param true|false $fullview Whether or not to display the full view (default: true)
 * @param true|false $viewtypetoggle Whether or not to allow users to toggle to the gallery view. Default: true
 * @param true|false $pagination Display pagination? Default: true
 *
 * @return string A list of entities suitable for display
 */
function list_entities_from_metadata($meta_name, $meta_value = "", $entity_type = "", $entity_subtype = "", $owner_guid = 0, $limit = 10, $fullview = true, $viewtypetoggle = true, $pagination = true, $case_sensitive = true ) {
	$offset = (int) get_input('offset');
	$limit = (int) $limit;
	$count = get_entities_from_metadata($meta_name, $meta_value, $entity_type, $entity_subtype, $owner_guid, $limit, $offset, "", 0, true, $case_sensitive );
	$entities = get_entities_from_metadata($meta_name, $meta_value, $entity_type, $entity_subtype, $owner_guid, $limit, $offset, "", 0, false, $case_sensitive );

	return elgg_view_entity_list($entities, $count, $offset, $limit, $fullview, $viewtypetoggle, $pagination);
}

/**
 * @deprecated 1.7.  Use elgg_get_entities_from_metadata().
 * @param $meta_array
 * @param $entity_type
 * @param $entity_subtype
 * @param $owner_guid
 * @param $limit
 * @param $offset
 * @param $order_by
 * @param $site_guid
 * @param $count
 * @param $meta_array_operator
 * @return unknown_type
 */
function get_entities_from_metadata_multi($meta_array, $entity_type = "", $entity_subtype = "",
$owner_guid = 0, $limit = 10, $offset = 0, $order_by = "", $site_guid = 0,
$count = false, $meta_array_operator = 'and') {
	elgg_log('get_entities_from_metadata_multi() was deprecated in 1.7 by elgg_get_entities_from_metadata()!', 'WARNING');

	if (!is_array($meta_array) || sizeof($meta_array) == 0) {
		return false;
	}

	$options = array();

	$options['metadata_name_value_pairs'] = $meta_array;

	if ($entity_type) {
		$options['types'] = $entity_type;
	}

	if ($entity_subtype) {
		$options['subtypes'] = $entity_subtype;
	}

	if ($owner_guid) {
		$options['owner_guid'] = $owner_guid;
	}

	if ($limit) {
		$options['limit'] = $limit;
	}

	if ($offset) {
		$options['offset'] = $offset;
	}

	if ($order_by) {
		$options['order_by'];
	}

	if ($site_guid) {
		$options['site_guid'];
	}

	if ($count) {
		$options['count'] = $count;
	}

	$options['metadata_name_value_pairs_operator'] = $meta_array_operator;

	return elgg_get_entities_from_metadata($options);
}

/**
 * Returns a viewable list of entities based on the given search criteria.
 *
 * @see elgg_view_entity_list
 *
 * @param array $meta_array Array of 'name' => 'value' pairs
 * @param string $entity_type The type of entity to look for, eg 'site' or 'object'
 * @param string $entity_subtype The subtype of the entity.
 * @param int $limit
 * @param int $offset
 * @param string $order_by Optional ordering.
 * @param true|false $fullview Whether or not to display the full view (default: true)
 * @param true|false $viewtypetoggle Whether or not to allow users to toggle to the gallery view. Default: true
 * @param true|false $pagination Display pagination? Default: true
 * @return string List of ElggEntities suitable for display
 */
function list_entities_from_metadata_multi($meta_array, $entity_type = "", $entity_subtype = "", $owner_guid = 0, $limit = 10, $fullview = true, $viewtypetoggle = true, $pagination = true) {
	$offset = (int) get_input('offset');
	$limit = (int) $limit;
	$count = get_entities_from_metadata_multi($meta_array, $entity_type, $entity_subtype, $owner_guid, $limit, $offset, "", $site_guid, true);
	$entities = get_entities_from_metadata_multi($meta_array, $entity_type, $entity_subtype, $owner_guid, $limit, $offset, "", $site_guid, false);

	return elgg_view_entity_list($entities, $count, $offset, $limit, $fullview, $viewtypetoggle, $pagination);
}

/**
 * Clear all the metadata for a given entity, assuming you have access to that metadata.
 *
 * @param int $guid
 */
function clear_metadata($entity_guid) {
	global $CONFIG;

	$entity_guid = (int)$entity_guid;
	if ($entity = get_entity($entity_guid)) {
		if ($entity->canEdit()) {
			return delete_data("DELETE from {$CONFIG->dbprefix}metadata where entity_guid={$entity_guid}");
		}
	}
	return false;
}

/**
 * Clear all annotations belonging to a given owner_guid
 *
 * @param int $owner_guid The owner
 */
function clear_metadata_by_owner($owner_guid) {
	global $CONFIG;

	$owner_guid = (int)$owner_guid;

	$metas = get_data("SELECT id from {$CONFIG->dbprefix}metadata WHERE owner_guid=$owner_guid");
	$deleted = 0;

	foreach ($metas as $id) {
		// Is this the best way?
		if (delete_metadata($id->id)) {
			$deleted++;
		}
	}

	return $deleted;
}

/**
 * Handler called by trigger_plugin_hook on the "export" event.
 */
function export_metadata_plugin_hook($hook, $entity_type, $returnvalue, $params) {
	// Sanity check values
	if ((!is_array($params)) && (!isset($params['guid']))) {
		throw new InvalidParameterException(elgg_echo('InvalidParameterException:GUIDNotForExport'));
	}

	if (!is_array($returnvalue)) {
		throw new InvalidParameterException(elgg_echo('InvalidParameterException:NonArrayReturnValue'));
	}

	$guid = (int)$params['guid'];
	$name = $params['name'];

	$result = get_metadata_for_entity($guid);

	if ($result) {
		foreach ($result as $r) {
			$returnvalue[] = $r->export();
		}
	}

	return $returnvalue;
}

/**
 * Takes in a comma-separated string and returns an array of tags which have been trimmed and set to lower case
 *
 * @param string $string Comma-separated tag string
 * @return array|false An array of strings, or false on failure
 */
function string_to_tag_array($string) {
	if (is_string($string)) {
		$ar = explode(",",$string);
		$ar = array_map('trim', $ar); // trim blank spaces
		$ar = array_map('elgg_strtolower', $ar); // make lower case : [Marcus Povey 20090605 - Using mb wrapper function using UTF8 safe function where available]
		$ar = array_filter($ar, 'is_not_null'); // Remove null values
		return $ar;
	}
	return false;

}

/**
 * Takes a metadata array (which has all kinds of properties) and turns it into a simple array of strings
 *
 * @param array $array Metadata array
 * @return array Array of strings
 */
function metadata_array_to_values($array) {
	$valuearray = array();

	if (is_array($array)) {
		foreach($array as $element) {
			$valuearray[] = $element->value;
		}
	}

	return $valuearray;
}

/**
 * Get the URL for this item of metadata, by default this links to the export handler in the current view.
 *
 * @param int $id
 */
function get_metadata_url($id) {
	$id = (int)$id;

	if ($extender = get_metadata($id)) {
		return get_extender_url($extender);
	}
	return false;
}

/**
 * Mark entities with a particular type and subtype as having access permissions
 * that can be changed independently from their parent entity
 *
 * @param string $type The type - object, user, etc
 * @param string $subtype The subtype; all subtypes by default
 */
function register_metadata_as_independent($type, $subtype = '*') {
	global $CONFIG;
	if (!isset($CONFIG->independents)) {
		$CONFIG->independents = array();
	}
	$CONFIG->independents[$type][$subtype] = true;
}

/**
 * Determines whether entities of a given type and subtype should not change
 * their metadata in line with their parent entity
 *
 * @param string $type The type - object, user, etc
 * @param string $subtype The entity subtype
 * @return true|false
 */
function is_metadata_independent($type, $subtype) {
	global $CONFIG;
	if (empty($CONFIG->independents)) {
		return false;
	}
	if (!empty($CONFIG->independents[$type][$subtype])
		|| !empty($CONFIG->independents[$type]['*'])) {
			return true;
		}
	return false;
}

/**
 * When an entity is updated, resets the access ID on all of its child metadata
 *
 * @param string $event The name of the event
 * @param string $object_type The type of object
 * @param ElggEntity $object The entity itself
 */
function metadata_update($event, $object_type, $object) {
	if ($object instanceof ElggEntity) {
		if (!is_metadata_independent($object->getType(), $object->getSubtype())) {
			global $CONFIG;
			$access_id = (int) $object->access_id;
			$guid = (int) $object->getGUID();
			update_data("update {$CONFIG->dbprefix}metadata set access_id = {$access_id} where entity_guid = {$guid}");
		}
	}
	return true;
}

/**
 * Register a metadata url handler.
 *
 * @param string $function_name The function.
 * @param string $extender_name The name, default 'all'.
 */
function register_metadata_url_handler($function_name, $extender_name = "all") {
	return register_extender_url_handler($function_name, 'metadata', $extender_name);
}

/** Register the hook */
register_plugin_hook("export", "all", "export_metadata_plugin_hook", 2);
/** Call a function whenever an entity is updated **/
register_elgg_event_handler('update','all','metadata_update');

// unit testing
register_plugin_hook('unit_test', 'system', 'metadata_test');
function metadata_test($hook, $type, $value, $params) {
	global $CONFIG;
	$value[] = $CONFIG->path . 'engine/tests/objects/metadata.php';
	return $value;
}