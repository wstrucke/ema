<?php
 /* Caching Module
  * Copyright 2011 William Strucke, wstrucke@gmail.com
  * All Rights Reserved
  *
  * Revision 1.0.0, Aug-02-2011/Oct-10-2011
  * William Strucke, wstrucke@gmail.com
  *
  * Objects with embeddable methods (filaments) should always implement the following functions:
  * 
  
  public function cache_expire_table($t)
  /* given a table (t) that has been modified, return an array of match arguments to be expired
   *   from the cache
   *
   * this function should only be called from the cache module
   *
   * returns an array of one or more key->value pairs or false to decline the request
   *
   *
  {
  	$match = array();
  	switch($t) {
  		default: return false;
  	}
  	return $match;
  }
  
  public function cache_expire_interval($method = '')
  /* given a method name return the interval in seconds in which it should be refreshed by the system cache
   *  the cache should always start counting at midnight
   *
   * return an integer; -1 means 'do not cache'
   *
   * for reference:
   *    1 hour        3600 seconds
   *    6 hours       21600 seconds
   *    12 hours      43200 seconds
   *    1 day         86400 seconds
   *    5 days        432000 seconds
   *    1 week        604800 seconds
   *    1 month       2592000 seconds
   *
   * a value of 0 means never automatically refresh until the cache is expired
   *
   *
  {
  	switch($method) {
  		default: return 0;
  	}
  }
  
  *
  * Please note: the current schema for this extension only supports function names with a maximum of 256 characters
  *              This can be easily changed if needed.
  *
  */
  
class cache_basic extends standard_extension
{
  # public / directly accessible variables
  #public $var;                        // (type) description
  
  # variables to be shared on demand
  #protected $var3;                    // (type) description
  
  # internal variables
  protected $capture;                 // (bool) is capture enabled for this request
  protected $known_tables;            // (array) list of tables->objects registered by other objects for auto-expiration
  protected $maximum_age;             // (integer) the maximum content age in seconds before auto-expiration
  protected $minimum_verify_time;     // (integer) the minimum number of seconds between requests for a valid
                                      //           iteration of the verify count (default 1800 -> 30 minutes)
  protected $mode;                    // (integer) running mode: 0->new_capture,1->verification,2->using_cache
  protected $request_hash;            // (string) the hash for the current request
  protected $verification;            // (integer) 0 to disable verification, otherwise the number of times
                                      //           to build a request before approving it
  protected $verify_map;              // (array) zero or more elements in the cache_content_map during verification
  
  # database version
  public $schema_version='0.1.3';   // the schema version to match the registered schema
  
  protected $_debug_prefix = 'cache'; // object debug output prefix
  public $_name = 'Cache';            // the module name
  public $_version = '1.0.0';         // the loaded object's version string
  
  /* code */
  
  public function __clone()
  /* handle clone processes
   *
   */
  {
  }
  
  public function &__get($item)
  {
  }

  public function __set($field, $value)
  {
  }
    
  public function _construct()
  /* initialize the object
   *
   */
  {
  	$this->capture = false;
  	$this->known_tables = array();
  	$this->maximum_age = 604800;
  	$this->minimum_verify_time = 60; // CHANGE ME (1800)
  	$this->mode = -1;
  	$this->verification = 2;
  	$this->verify_map = array();
  }
  
  public function admin($option = false)
  /* administration interface
   *
   */
  {
  	return $this->_content('index');
  }
  
  public function disapprove($request)
  /* disapprove the specified request hash
   *
   * used to force a cache refresh/verification on this or the next next
   *
   */
  {
  	$this->_tx->db->update('cache_content', array('request'), array($request), array('approved'), array(false));
  	return;
  }
  
  public function expire($args = false)
  /* expire all cached content matching args
   *
   * valid args:
   *   approved      (bool) true or false
   *   last_verify   (string|integer) expire everything with a unixtime earlier than or equal to this
   *   authenticated (bool) true or false
   *   pageid        (string) request page id / cms element id
   *   uri           (string) match uri -- this will expire *everything* matching this uri
   *   uri_args      (string) match uri_args -- this will expire *everything* matching the args uri
   *   table         (string) for known tables, expire the cache for related items
   *                          these are not implicitely known and require the owner object to register them.
   *
   */
  {
  	$this->_debug_start();
  	
  	# handle auto-expiration from database modules
  	if (array_key_exists('table', $args)) {
  		$this->_debug('expire called from db plugin for table ' . $args['table']);
  		$new_args = false;
  		if (array_key_exists($args['table'], $this->known_tables)) {
	  		# expire by registered tables
	  		$obj = $this->_tx->{$this->known_tables[$args['table']]};
	  		if (is_object($obj)&&method_exists($obj, 'cache_expire_table')) {
	  			$new_args = $obj->cache_expire_table($args['table']);
	  		}
	  	}
	  	if ($new_args === false) return $this->_return(false, 'nothing to update');
	  	$args = $new_args;
  	}
  	
  	# initialize match array
  	$match = array();
  	
  	foreach($args as $k=>$v) {
  		switch(strtolower($k)){
  			case 'approved': $match['approved'] = (bool)$v; continue 2; break;
  			case 'last_verify': $match['last_verify'] = "<= $v"; continue 2; break;
  			case 'authenticated': $match['authenticated'] = (bool)$v; continue 2; break;
  			default:
  				if ($v == '%') {
  					$match[strtolower($k)] = $v;
  				} else {
  					$match[strtolower($k)] = '%' . $v . '%';
  				}
  				break;
  		}
  	}
  	
  	# expire the cache
  	$list = $this->_tx->db->select('cache_content', array_keys($match), array_values($match), array('request'));
  	if (!db_qcheck($list, true)) return $this->_return(false);
  	
  	$list = array_multi_reduce($list, 'request');
  	#$this->_tx->db->delete('cache_content', array_keys($match), array_values($match));
  	#for ($i=0;$i<count($list);$i++){
  	#	$this->_tx->db->delete('cache_content_map', array('request_id'), array($list[$i]));
  	#}
  	$this->_tx->db->update('cache_content', array_keys($match), array_values($match), array('approved'), array(false));
  	
  	return $this->_return(true);
  }
  
  public function expire_all()
  /* expire all cached content
   *
   */
  {
  	$this->_tx->db->delete('cache_content', array(1), array(1));
  	$this->_tx->db->delete('cache_content_map', array(1), array(1));
  	$this->_tx->db->delete('cache_method', array(1), array(1));
  	$this->_tx->db->delete('cache_output', array(1), array(1));
  }
  
  public function hash($args = false)
  /* get the hash for a request
   *
   * if request data is provided, use it otherwise use the current request
   *
   * returns the hash
   *
   * the hash can be based upon:
   *  - is a user logged in (y/n)
   *  - the cms request id
   *  - the request_uri
   *  - the remote ip
   *  - the remote console type (e.g. mobile, etc...)
   *  - get/request variables and settings
   *
   * always exclude post
   * always exclude xml requests (?unless explicitely allowed?)
   * sometimes exclude logged in users (?)
   * be congnicent of pages that are *mostly* static with some dynamic content
   *
   */
  {
  	# build the hash string
  	#echo "Remote IP: " . $_SERVER['REMOTE_ADDR'] . "<br />";
  	#echo "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "<br />";
  	$hs = $this->_tx->cms->schema_version . '~';
  	if ($this->_tx->security->authenticated) { $hs .= '1~'; } else { $hs .= '0~'; }
  	$hs .= $this->_tx->get->userid . '~';
  	$hs .= $this->_tx->get->content_type . '~';
  	$hs .= $this->_tx->get->pageid . '~';
  	$hs .= $this->_tx->get->request_string . '~';
  	if ($this->_tx->get->mech_override) { $hs .= '1~'; } else { $hs .= '0~'; }
  	if ($this->_tx->get->fuse) { $hs .= '1~'; } else { $hs .= '0~'; }
  	if (is_array($this->_tx->get->fuse_args)) { $hs .= '1~'; } else { $hs .= '0~'; }
  	$hs .= $_SERVER['REQUEST_URI'];
  	$this->_debug("Building hash from string: `$hs`");
  	return md5($hs);
  }
  
  public function register_table($name)
  /* interface for an object/extension to register tables it wants to
   *  enable auto-expiration for on changes
   *
   */
  {
  	# get the requesting object
  	$dbg = debug_backtrace();
  	$module = $dbg[1]['class'];
  	# register the table
  	if (!array_key_exists($name, $this->known_tables)) $this->known_tables[$name] = $module;
  }
  
  protected function replace_methods(&$stream)
  /* find and replace any ema method calls in the data stream
   *
   */
  {
  	# identify any methods in the stream
  	$list = $this->_tx->cms->parse_shared_methods($stream);
  	
  	# replace any found function calls
  	for ($i=0;$i<count($list);$i++) {
  		$stream = str_replace($list[$i][0], $this->_tx->cms->call_shared_method($list[$i][1],$list[$i][2],$list[$i][3]), $stream);
  	}
  	
  	return true;
  }
  
  protected function replace_vars(&$stream)
  /* find and replace any ema shared variables in the data stream
   *
   * alters the data stream
   *
   */
  {
  	# identify any variables in the stream
		$variables = $this->_tx->cms->parse_shared_data($stream);
		
		# replace any found variables
		for ($i=0;$i<count($variables);$i++) {
			$output = $this->_tx->cms->call_shared_data($variables[$i][1],$variables[$i][2]);
			$stream = str_replace($variables[$i][0], "$output", $stream);
		}
		
		return true;
  }
  
  public function mode_new()
  /* put the cache in new capture mode
   *
   */
  {
  	$this->_debug('no existing cache for this request');
		$this->capture = true;
		$this->mode = 0;
		$this->_tx->set->process_filaments = false;
		$this->_tx->set->process_variables = false;
		return;
  }
  
  public function mode_verify()
  /* put the cache in verify mode
   *
   */
  {
  	$this->_debug('cache needs verification');
  	$this->capture = true;
  	$this->mode = 1;
  	$this->_tx->set->process_filaments = false;
  	$this->_tx->set->process_variables = false;
  	# get the content map for this request
  	$list = $this->_tx->db->select('cache_content_map', array('request_id'), array($this->request_hash), array('cache_output_id'), true, 'assembly_order');
  	if (db_qcheck($list, true)) $this->verify_map = array_multi_reduce($list, 'cache_output_id');
  	# clear the map
  	$this->_tx->db->delete('cache_content_map', array('request_id'), array($this->request_hash));
  	return;
  }
  
  public function output_hash($str)
  /* given an arbitrary string return a hash of the output
   *
   * this could just be a simple md5() call but we'll wrap it around
   *  this function to enable a future changes
   *
   */
  {
  	return md5($str);
  }
  
  public function prune_output_cache()
  /* check each id in the cache_output table to ensure there is at least one reference to it
   *   either cache_method or cache_output_map
   *
   * delete all entries without references
   *
   */
  {
  	// to be completed
  }
  
  protected function update_cache(&$components, $simple_keys, $methods, $max_age)
  /* update the cache tables with the compiled output
   *
   * called from this::unload()
   *
   * - components is an array of string pieces representing the final output
   * - simple_keys is an array of one or more keys representing the basic (non-filament) output
   * - methods is an array of the methods contained in the non-simple keys
   * - max_age is the maximum combined content age in seconds before it must be re-validated
   * - $this->mode 0 is new capture, 1 is updating existing capture
   *
   * returns true or false
   *
   */
  {
  	$this->_debug_start($this->mode);
  	
  	# preset verified flag
  	$verified = true;
  	
  	for($i=0;$i<count($components);$i++) {
  		# get the output hash
 			$hout = $this->output_hash($components[$i]);
 			# pre-set the cache value for this component to the actual output
 			$cache = $components[$i];
 			$method_id = null;
 			# update method table as needed
  		if (! in_array($i, $simple_keys)) { $cache = $this->update_method_cache($method_id, $methods, $hout, $components[$i], $i); }
  		# cache_output is implicitely deduplicated by mysql due to id being the primary key - use this::prune_output_cache to clean it up
 			$this->_tx->db->insert('cache_output', array('id', 'output'), array($hout, $cache));
 			# check the mode
 			if (($this->mode == 1)&&((!array_key_exists($i, $this->verify_map))||($this->verify_map[$i] != $hout))) $verified = false;
 			
 			$this->_debug("Check for position $i");
 			if (!array_key_exists($i, $this->verify_map)) { $this->_debug('Array Key DNE'); } else {
 				$this->_debug('Array checks out!');
 				if ($this->verify_map[$i] != $hout) {
 					$this->_debug("HOUT != VERIFYMAP!<br />\r\nHOUT = `$hout`, VERIFY = `" . $this->verify_map[$i] . '`');
 					if ($this->_debug_mode >= 0) {
 						$tempq = $this->_tx->db->select('cache_output', array('id'), array($this->verify_map[$i]), array('output'));
 						echo "<br />\r\nCompiled Output:<br />\r\n" . $components[$i] . "\r\n\r\n";
 						echo "<br />\r\nCached Output:<br />\r\n" . $tempq[0]['output'] . "\r\n\r\n";
 						echo "<br />\r\n";
 					}
 				} else { $this->_debug('HOUT checks out!'); }
 			}
 			$this->_debug('after validation verified = ' . b2s($verified));
 			
  		# add the content map
  		$this->_tx->db->insert(
  			'cache_content_map',
  			array('request_id', 'cache_output_id', 'time', 'assembly_order', 'cache_method_id'),
  			array($this->request_hash, $hout, strval(time()), $i, $method_id)
  			);
  	}
  	
  	# preset approval
  	$approval = false;
  	$c = 0;
  	$d = 0;
  	
  	# check the mode for verification
  	if ($this->mode == 1) {
  		$this->_debug('verify mode');
  		$q = $this->_tx->db->select('cache_content', array('request'), array($this->request_hash), array('count', 'time', 'duration'));
  		if (db_qcheck($q)) { $c = intval($q[0]['count']); $t = intval($q[0]['time']); $d = intval($q[0]['duration']); } else { $t = time(); }
  		if ($verified) {
  			$c++;
  			if ($c >= $this->verification) { $approval = true; $t = time(); }
  		} else {
  			$c = 0;
  		}
  	} elseif ($this->verification == 0) {
  		$approval = true;
  	}
  	$this->_debug("after verification count = $c and approval = " . b2s($approval));
  	
  	$this->_debug('================================================================');
  	$this->_debug('Request hash: ' . $this->request_hash);
  	$this->_debug('Verification count: ' . $c);
  	$this->_debug('Approved: ' . b2s($approval));
  	//$this->_debug('Last Verified: ' . );
  	$this->_debug('Compile Time in ms: ' . $this->_tx->get->exec_time);
  	$this->_debug('Maximum Content Age: ' . $max_age);
  	$this->_debug('Authenticated: ' . b2s($this->_tx->get->authenticated));
  	$this->_debug('User ID: ' . $this->_tx->get->userid);
  	$this->_debug('================================================================');
  	
  	# set the duration to the average
  	if ($c > 0) {
  		$d = (($d + $this->_tx->get->exec_time) / 2);
  		$this->_debug('setting average duration: ' . $d);
  	} else {
  		$d = $this->_tx->get->exec_time;
  	}
  	
  	if ($this->mode == 0) {
  		# get the active user id (if any)
  		$uid = $this->_tx->get->userid;
  		if (!is_string($uid)) $uid = null;
  		# add the record to the content meta table
  		$this->_tx->db->insert(
  			'cache_content',
  			array('request', 'time', 'count', 'approved', 'last_verify', 'duration', 'authenticated', 'pageid', 'uri', 'uri_args', 'uid', 'max_age'),
  			array($this->request_hash, strval(time()), 0, $approval, 0, $d, $this->_tx->get->authenticated, $this->_tx->get->pageid, $this->_tx->get->request_string, $this->_tx->get->arg_string, $uid, $max_age)
  			);
  	} else {
  		# update the content meta table
  		$this->_tx->db->update(
  			'cache_content', array('request'), array($this->request_hash),
  			array('count', 'approved', 'last_verify', 'duration', 'max_age'),
  			array($c, $approval, strval(time()), $d, $max_age)
  			);
  	}
  	
  	return $this->_return(true);
  }
  
  protected function update_method_cache(&$new_id, &$list, $hash, $output, $id)
  /* called from this::update_cache to update a method cache
   *
   * list is the method list provided by this::unload which in turn comes from cms::filaments
   *   format is: array( 0=>match, 1=>object_name, 2=>function_name, 3=>args*, real_method=>name, interval=>#, streamKey=>id* )
   *
   * where 'id' is the array key from the component list provided to this::update_cache
   *
   * alters the input list by removing the matching key
   *
   * returns the value to cache (if interval is negative, returns the function call instead of the output)
   *
   */
  {
  	# find the specified id in the array
  	foreach ($list as $i=>$meta) {
  		if ((!array_key_exists('streamKey', $meta))||($meta['streamKey'] != $id)) continue;
  		if ($meta['interval'] < 0) $output = $list[$i][0];
  		$this->_tx->db->insert(
  				'cache_method',
  				array('request_id', 'call', 'extension', 'method', 'cache_output_id', 'update_interval'),
  				array($this->request_hash, $meta[0], $meta[1], $meta['real_method'], $hash, $meta['interval'])
  				);
  		$new_id = $this->_tx->db->insert_id();
  		unset($list[$i]);
  	}
  	return $output;
  }
  
  protected function verify_method_refresh($method_id, $age, $output_id)
  /* given a method id, verify it is not expired
   *
   * if it is expired, update the table and refresh it
   *
   * return either the new content or current content
   *
   */
  {
  	$this->_debug_start($method_id . ', ' . $age);
  	# get the unix time for now
  	$now = time();
  	# get the unix time for midnight today which will be used to check refresh intervals
  	#  - i believe this mignight is in UTC when we really mean to use midnight local time.
  	#    this brings up an interesting point -- for things that display dates or times but are cached,
  	#    i.e. the calendar extension -- it probably wants to output the current date or time for the
  	#    *end user* which would be different depending on his or her time zone... this should most definitely
  	#    be reconsidered and addressed in the future.  It may end up being that extension that depend
  	#    specifically on something client side implement their own caching mechanism or, in the least,
  	#    are more involved.  This could establish 'active' caching, whereas the goal of this
  	#    extension 'as is' is 'passive' caching which does not require any input or implicit
  	#    action by any other extension.
  	$midnight = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
  	
  	# get the method meta data
  	$meta = $this->_tx->db->select('cache_method', array('id'), array($method_id), array('call', 'update_interval'));
  	if (!db_qcheck($meta)) return '';
  	
  	# check the age of the stored method
  	if (intval($age) < ($midnight - intval($meta[0]['update_interval']))) {
  		# needs to be updated
  		$this->_debug('refreshing function call due to age');
  		$cache = $meta[0]['call'];
  		$this->replace_methods($cache);
  		$hout = $this->output_hash($cache);
  		# cache_output is implicitely deduplicated by mysql due to id being the primary key - use this::prune_output_cache to clean it up
 			$this->_tx->db->insert('cache_output', array('id', 'output'), array($hout, $cache));
  		$this->_tx->db->update(
  			'cache_content_map',
  			array('request_id', 'cache_output_id', 'cache_method_id'),
  			array($this->request_hash, $output_id, $method_id),
  			array('time', 'cache_output_id'),
  			array(strval(time()), $hout));
  	} else {
  		# good to go
  		$tmp = $this->_tx->db->select('cache_output', array('id'), array($output_id), array('output'), true);
  		if (!db_qcheck($tmp)) return '';
  		$cache = $tmp[0]['output'];
  	}
  	
  	return $this->_return($cache);
  }
  
  /*================================================================================
   *
   *                                   G E A R S
   *
   *==============================================================================*/
  
  public function postoptimize()
  /* build the request hash and check for a cached version
   *
   * this function should also intercept content updates via the cms
   *   or template manager and automatically expire the cache, preventing
   *   the need for either of those modules to know about this one.
   *
   * verification is necessarily complex. the entire concept of caching
   *   in this type of system is the antithesis of a dynamic web site.
   *   the entire reason the site isn't built in vanilla html is to
   *   allow for dynamic content and features, but that is exactly
   *   what also makes is very slow, especially under load.  caching
   *   is a good solution but it has to be highly intelligent.
   *
   * that means that the site and site modules (as well as the admin)
   *   must be able to expire the cache for specific items (or everything)
   *   as appropriate. if the client recognizes that the site isn't
   *   changing appropriately than caching is broken.  eventually
   *   the cache system should recognize when specific sections of the
   *   page change around otherwise static content and handle it
   *   accordingly.  with our templating system that should be
   *   the usual case for embedded items.
   *
   */
  {
  	# never cache posts
  	if (count($_POST) > 0) { $this->_debug('no cache on post'); return; }
  	
  	# never cache downloads or xml requests
  	if ($this->_tx->get->content_type != 'html') { $this->_debug('no cache on non-html output'); return; }
  	
  	# never cache 404s
  	if ($this->_tx->get->page_404 == $this->_tx->get->pageid) { $this->_debug('no cache on 404s'); return; }
  	
  	# get the request hash
  	$this->request_hash = $this->hash();
  	
  	# lookup this request
  	$r = $this->_tx->db->select(
  		'cache_content',
  		array('request'),
  		array($this->request_hash),
  		array('time', 'count', 'approved', 'last_verify', 'duration', 'authenticated', 'uid', 'pageid', 'uri', 'cache_duration', 'returned', 'max_age'));
  	
  	# if there is no result, set the capture flag and exit
  	if ((!db_qcheck($r))||(bit2bool($r[0]['authenticated'])!=$this->_tx->security->authenticated)){
  		$this->_debug('no cache for this request');
  		return $this->mode_new();
  	}
  	
  	# get the last check time for this request
  	(intval($r[0]['last_verify']) == 0) ? $vtime = intval($r[0]['time']) : $vtime = intval($r[0]['last_verify']);
  	
  	# get the unix time for now
  	$now = time();
  	# get the unix time for midnight today which will be used to check refresh intervals
  	#  - i believe this mignight is in UTC when we really mean to use midnight local time.
  	#    this brings up an interesting point -- for things that display dates or times but are cached,
  	#    i.e. the calendar extension -- it probably wants to output the current date or time for the
  	#    *end user* which would be different depending on his or her time zone... this should most definitely
  	#    be reconsidered and addressed in the future.  It may end up being that extension that depend
  	#    specifically on something client side implement their own caching mechanism or, in the least,
  	#    are more involved.  This could establish 'active' caching, whereas the goal of this
  	#    extension 'as is' is 'passive' caching which does not require any input or implicit
  	#    action by any other extension.
  	$midnight = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
  	# get the time for midnight the day of the request
  	$rmidnight = mktime(0, 0, 0, date('n', $vtime), date('j', $vtime), date('Y', $vtime));
  	
  	# get the maximum age for this request
  	if (is_null($r[0]['max_age'])) { $rmax = $this->maximum_age; } else { $rmax = intval($r[0]['max_age']); }
  	
  	# get the check/comparison time for aging
  	if ($rmax < 86400) {
  		# if maximum age is less than one day, do a normal comparison
  		$ctime = $vtime;
  	} else {
  		# max age is 24 hours or more, check from request midnight for expiration
  		$ctime = $rmidnight;
  	}
  	
  	# verify the age of the content
  	if (($ctime + $rmax) < $now) {
  		$this->_debug('cache is aged, setting verify mode');
  		$this->disapprove($this->request_hash);
  		return $this->mode_verify();
  	}
  	
  	# (temporary) allow manual cache expiration
  	if (array_key_exists('expire_cache', $_GET)) {
  		$this->_debug('forcing cache expiration by special request');
  		$this->disapprove($this->request_hash);
  		return $this->mode_verify();
  	}
  	
  	# if there is a result and it's approved, use it
  	if (bit2bool($r[0]['approved'])) {
  		# load the content list
  		$list = $this->_tx->db->select(
  			'cache_content_map', array('request_id'), array($this->request_hash),
  			array('cache_output_id', 'time', 'cache_method_id'), true, 'assembly_order'
  			);
  		if (!db_qcheck($list, true)){ $this->_debug('Error loading cache!');  return; }
  		$cache = '';
  		$aged = false;
  		for ($i=0;$i<count($list);$i++) {
  			# check if the item was a module reference
  			if (!is_null($list[$i]['cache_method_id'])) {
  				# verify the refresh timing on the method
  				$c = $this->verify_method_refresh($list[$i]['cache_method_id'], $list[$i]['time'], $list[$i]['cache_output_id']);
  			} else {
  				# load the content
		  		$tmp = $this->_tx->db->select('cache_output', array('id'), array($list[$i]['cache_output_id']), array('output'), true);
		  		if (!db_qcheck($tmp)) { $this->_debug('Error loading cache item!'); continue; }
		  		$c = $tmp[0]['output'];
  			}
	  		$cache .= $c;
  		}
  		# identify and replace any variables in the stream
			$this->replace_vars($cache);
			# identify and replace any non-cached methods in the stream
			$this->replace_methods($cache);
  		# send the content, log the time, and unregister all remaining gears
  		echo $cache;
  		$time = $this->_tx->get->exec_time;
  		$this->_debug("Total exec time: $time");
  		$this->_debug('Cache saved ' . ($r[0]['duration'] - $time) . ' ms on this request');
  		$this->mode = 2;
  		$this->_tx->_exclude('*');
  		# update the average duration
  		if (is_null($r[0]['cache_duration'])||($r[0]['cache_duration']==0)) {
  			$d = $this->_tx->get->exec_time;
  		} else {
  			$d = ((intval($r[0]['cache_duration']) + $this->_tx->get->exec_time) / 2);
  		}
  		$this->_tx->db->update(
  			'cache_content', array('request'), array($this->request_hash),
  			array('returned', 'cache_duration'), array(intval($r[0]['returned'])+1, $d));
  		return;
  	} elseif ($vtime <= ($now - $this->minimum_verify_time)) {
  		# request is cached but not approved, enable capture to verify the cache before marking it approved
  		$this->_debug('request is cached but not approved -- setting verify mode');
  		return $this->mode_verify();
  	}
  	$this->_debug('not using cache');
  	return;
  }
  
  public function postoutput()
  /* set running mode silent for the cms when we're in active cache mode
   *
   */
  {
  	# never cache 404s
  	if ($this->_tx->get->page_404 == $this->_tx->get->pageid) {
  		$this->_debug('no cache on 404s');
  		$this->mode = -1;
  		return;
  	}
  	
  	if ($this->mode >= 0) {
  		# active caching mode, ask the cms to go silent and let us output the requested content
  		if ($this->_tx->cms->set_mode('silent') === false) {
  			$this->_debug('An error occurred, unable to put the cms in silent output mode, aborting capture');
  			$this->capture = false;
  		}
  	}
  }
  
  public function postunload()
  /* save captured output into the cache
   *
   */
  {
  	if ($this->capture) {
  		# load up the methods used on this request
  		$list = '';
  		$methods = $this->_tx->get->filaments;
  		$method_out = array();
  		$combined_interval = 0;
  		for ($i=0;$i<count($methods);$i++) {
  			# get the lowest non-zero refresh interval
  			$obj =& $this->_tx->$methods[$i][1];
  			$interval = 0;
  			# ema allows for the function name to have arguments included, check and remove them as needed
  			$pos = strpos($methods[$i][2], '(');
  			$real_method = $methods[$i][2];
  			# remove the arguments if they were found
  			if ($pos !== false) $real_method = substr($methods[$i][2], 0, $pos);
  			# get the cache expire interval for the method
  			if (method_exists($obj, 'cache_expire_interval')) {
  				$interval = $obj->cache_expire_interval($real_method);
  				$this->_debug('cache expire interval for ' . $methods[$i][1] . '::' . $real_method . " = $interval seconds");
  			} else {
  				$this->_debug('no cache expire interval for ' . $methods[$i][1] . '::' . $real_method);
  			}
  			if (($interval > 0)&&(($combined_interval == 0)||($interval < $combined_interval))) $combined_interval = $interval;
  			
  			# temporarily create a comma deliminated list of called methods for debugging purposes
  			$list .= $methods[$i][1] . '::' . $real_method . ', ';
  			$methods[$i]['real_method'] = $real_method;
  			$methods[$i]['interval'] = $interval;
  			
  			# call the actual function to get the results
  			$tmp = $this->_tx->cms->call_shared_method($methods[$i][1],$methods[$i][2],$methods[$i][3]);
  			# CYA in case the method returns some type of object
  			$method_out[$i] = "$tmp";
  		}
  		
  		# get the contents of the output buffer
  		#$stream = array($this->_tx->output->get());
  		$html =& $this->_tx->get->html;
  		$stream = array("$html");
  		$streamKeys = array(0);
  		$this->_debug('Stored Output Size: ' . strlen($stream[0]));
  		
  		# two loops are required since filament calls are allowed to alter the output
  		#  doing this in one loop means we miss alterations to other parts of the html output, such as
  		#  the header (javascript/css additions), metadata, or other 'neat' functionality enabled
  		#  by the unique ema html object structure
  		for ($i=0;$i<count($methods);$i++) {
  			# split the stream around this method
  			#   doing it like this since we can't assume the methods are provided in the order they appear in the stream
  			#   ...though I also wrote the cms module so I think they actually *do* appear in that order, but its better
  			#   to be safe in this case.
  			$match = false;
  			for($j=0;$j<count($streamKeys);$j++) {
  				$pos = strpos($stream[$streamKeys[$j]], $methods[$i][0]);
  				if ($pos === false) continue;
  				$match = true;
  				# split the stream around this function call
  				$before = substr($stream[$streamKeys[$j]], 0, $pos);
  				$after = '';
  				if (strlen($stream[$streamKeys[$j]]) > ($pos+strlen($methods[$i][0]))) {
	  				$after = @substr($stream[$streamKeys[$j]], $pos+strlen($methods[$i][0]));
	  			}
  				# replace the output in the data stream so this page load looks correct
	  			array_splice($stream, $streamKeys[$j], 1, array($before, $method_out[$i], $after));
	  			$streamKeys[] = (count($streamKeys)*2);
	  			$methods[$i]['streamKey'] = $streamKeys[$j]+1;
	  			continue 2;
  			}
  			if ($match === false) $this->_debug('<strong>Critical Error</strong>: Failed to locate the function call in the output stream!');
  		}
  		
  		# validate combined interval value
  		if ($combined_interval == 0) $combined_interval = null;
  		
  		# update the cache
  		$this->update_cache($stream, $streamKeys, $methods, $combined_interval);
  		
  		# combine for output
  		$stream = implode('', $stream);
  		
  		$this->_debug('Output hash: ' . $this->output_hash($stream));
  		$this->_debug('Embedded Methods: ' . $list);
  		
  		# identify and replace any variables in the stream
			$this->replace_vars($stream);
			
  		# send the output
  		echo $stream;
  	}
  }
  
}
?>