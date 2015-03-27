<?php
 /* Content Management System Extension for ema
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Oct-13-2008/Jan-18-2009/Jul-05-2009
  * William Strucke, wstrucke@gmail.com
  *
  * to do:
  * - implement multiple dynamic home pages
  * - need to distinguish between failures in session foreign functions
  *   and embedded foreign functions
  * - gracefully handle session function failures
  * - revisit need for local references to transmission variables (e.g. db, etc...)
  *   some may make sense if they are used often, others may not.
  * - mechanism support needs to be recoded to work for pages compiled from snips
  * - code caching -- template should only be used for edits, not at run time
  * - support for mobile devices
  *
  */
	
class cms_manager extends standard_extension
{
  # object declaration
  protected $css;                 // shared css document object
  protected $db;                  // shared database object
  protected $outs;                // user output manager
  protected $template;            // shared template object
  
  # standard extension variables
  protected $_debug_prefix = 'cms';
  public $_name = 'Content Management System Extension';
  public $_version = '1.0.0';
  
  # published variables
  public $content_type;           // the active page's content type
  public $doctype;                // the active page's doctype
  public $err;                    // internal error code to be shared, if one is encountered
  public $html;                   // html element containing all output
  public $html_ref;               // xml object containing complete html reference
  public $link_rewrite;           // if apache mod_rewrite then true
  public $mech_item_code;         // the requested page component request code
  public $mech_override;          // boolean value telling postoutput() to use a mech or full output
  public $mech_request_code;      // the output mechanism request code
  public $pageid;                 // the active page
  public $ssl;                    // true if the site is being accessed over ssl
  public $title;                  // the active page's title
  public $type;                   // the active page's type (e.g. thread, circuit, link, fuse, etc...)
  
  # published settings
  public $administrator_menu;     // (bool) if true always display the admin menu for admin users
  public $content_request_code;   // (string) the variable used in urls to select a page or content
  public $default_home;           // (string) the site's default home page content id
  public $dev_mode;               // (bool) is the site is in global development mode?
  public $domain;                 // (string) the local domain name
  public $download_request_code;  // (string) the variable used in urls to specify a file or file id
  public $filaments;              // (array) zero or more elements representing the filaments
                                  //         in use on the active request. this field is not populated
                                  //         until the BODY gear is executed.
                                  //
                                  //         elements are in the form of:
                                  //           { 0=>match, 1=>object_name, 2=>function_name, 3=>args* }
                                  //
                                  //         *: args are either an array or arguments or false
                                  //
  public $page_401;               // (string) the id of the page to be used when access is denied
  public $page_404;               // (string) the id of the page to be used when content is not found
  public $process_filaments;      // (bool) if false *do not* process filaments in the output -- this setting
                                  //        is made available for caching mechanisms to replace cms functionality
  public $process_variables;      // (bool) if false *do not* process variables in the output -- see above
  public $ps;                     // (string) the path separator character
  public $site_title;             // (string) the configured site's page title
  public $variables;              // (array) zero or more variables found in the active request body
                                  //         this field is not populated until the BODY gear is executed.
                                  //
                                  //         elements are in the form of:
                                  //           { 0=>match, 1=>var_name, 2=>conditional }
                                  //
  
  # database version
  public $schema_version='0.4.24'; // the schema version to match the registered schema
  
  # internal variables
  protected $arg_string;          // any raw arg string after the request (from the URL or POST data)
  protected $cache;               // internal cache (not site cache)
  protected $cache_last_modified; // the last modified element id for use with cache_expire_table
  protected $fuse;                // fuse array (provides=>object, function=>function_name)
  protected $fuse_args;           // optional argument array to be passed to a request fuse
  protected $message;             // message queue array
  protected $message_type;        // the message type to output (notice|error)
  protected $request_string;      // the selected request string (from the URL or POST data)
  protected $running_mode;        // the active request's running mode (either "active" or "silent")
  protected $template_id;         // the site's active/default template id
  
  protected function _construct()
  /* initialize cms_manager class
   *
   */
  {
  	# initialize local variables
  	$this->cache_last_modified = false;
  	$this->html_ref =& $this->_tx->my_xml_document_2;
  	$this->fuse = false;
  	$this->fuse_args = false;
  	$this->mech_override = false;
  	$this->message = array();
  	$this->message_type = 'notice';
  	
  	$this->domain = $_SERVER['HTTP_HOST'];
  	
  	# try to remove the port from the domain name if one exists
  	$test = strpos($this->domain, ':');
  	if ($test !== false) { $this->domain = substr($this->domain, 0, $test); }
  	
  	$this->administrator_menu = true;
  	$this->cache = array();
  	$this->content_request_code = 'r';
  	$this->default_home = '';
  	$this->dev_mode = false;
  	$this->download_request_code = '';
  	$this->filaments = array();
  	$this->link_rewrite = false;
  	$this->mech_item_code = '';
  	$this->mech_request_code = '';
  	$this->page_401 = '';
  	$this->page_404 = '';
  	$this->pageid = '';
  	$this->process_filaments = true;
  	$this->process_variables = true;
  	$this->ps = '/';
  	$this->site_title = '';
  	$this->ssl = false;
  	$this->variables = array();
  	
  	$tmp1 = 'William Strucke';
  	$tmp2 = 'wstrucke@gmail.com';
  	$tmp3 = date('Y-M-d H:i');
  	
  	# publish data
  	$this->_tx->_publish('admin_name', $tmp1);
  	$this->_tx->_publish('admin_email', $tmp2);
  	$this->_tx->_publish('arg_string', $this->arg_string);
  	$this->_tx->_publish('content_request_code', $this->content_request_code);
  	$this->_tx->_publish('content_type', $this->content_type);
  	$this->_tx->_publish('cms_error', $this->err);
  	$this->_tx->_publish('date', $tmp3);
  	$this->_tx->_publish('default_home', $this->default_home);
  	$this->_tx->_publish('dev_mode', $this->dev_mode);
  	$this->_tx->_publish('doctype', $this->doctype);
  	$this->_tx->_publish('domain', $this->domain);
  	$this->_tx->_publish('download_request_code', $this->download_request_code);
  	$this->_tx->_publish('filaments', $this->filaments);
  	$this->_tx->_publish('fuse', $this->fuse);
  	$this->_tx->_publish('fuse_args', $this->fuse_args);
  	$this->_tx->_publish('html', $this->html);
  	$this->_tx->_publish('html_ref', $this->html_ref);
  	$this->_tx->_publish('link_rewrite', $this->link_rewrite);
  	$this->_tx->_publish('mech_item_code', $this->mech_item_code);
  	$this->_tx->_publish('mech_override', $this->mech_override);
  	$this->_tx->_publish('mech_request_code', $this->mech_request_code);
  	$this->_tx->_publish('page_401', $this->page_401);
  	$this->_tx->_publish('page_404', $this->page_404);
  	$this->_tx->_publish('pageid', $this->pageid);
  	$this->_tx->_publish('process_filaments', $this->process_filaments);
  	$this->_tx->_publish('process_variables', $this->process_variables);
  	$this->_tx->_publish('ps', $this->ps);
  	$this->_tx->_publish('request_string', $this->request_string);
  	$this->_tx->_publish('site_title', $this->site_title);
  	$this->_tx->_publish('ssl', $this->ssl);
  	$this->_tx->_publish('default_template_id', $this->template_id);
  	$this->_tx->_publish('title', $this->title);
  	$this->_tx->_publish('type', $this->type);
  	
  	$tmp = 'Marching and Athletic Bands';
  	
  	$this->_tx->_publish('site_name', $tmp);
  	
  	$this->_debug('<strong>WARNING:</strong> client extensions are not coded in the engine yet!!');
  }
  
  public function cache_expire_table($t)
  /* given a table (t) that has been modified, return an array of match arguments to be expired
   *   from the cache
   *
   * this function should only be called from the cache module
   *
   * returns an array of one or more key->value pairs or false to decline the request
   *
   */
  {
  	$match = array();
  	switch($t) {
  		case 'cms_element':
  			if ($this->cache_last_modified === false) return false;
  			$match['pageid'] = $this->cache_last_modified;
  			break;
  		case 'cms_navigation': return false;
  		case 'cms_settings': return false;
  		default: return false;
  	}
  	$this->cache_last_modified = false;
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
   */
  {
  	switch($method) {
  		case 'generate_admin_menu': return -1;
  		case 'generate_menu': return -1;
  		default: return 0;
  	}
  }
  
  public function check_request_id($request_string = false)
  /* check for and validate any requested page id
   *
   * if no page was requested, set the default to the home page
   *
   * if the home page does not exist (yet), set to the cms management page (code: ampersand)
   *
   */
  {
  	$this->_debug_start();
  	
  	# set the request string
  	if (($request_string === false) && array_key_exists($this->content_request_code, $_REQUEST)) {
  		$request_string = $_REQUEST[$this->content_request_code];
  		$request_local = false;
  	} else {
  	$request_local = true;
  	}
  	
  	$this->_debug('Request String: ' . @$request_string);
  	
  	# clear page id and result strings before processing
  	$this->arg_string = '';
  	$this->pageid = '';
  	$this->request_string = '';
  	
  	# retrieve the requested page id or alias
  	if ($request_string !== false) {
  		# convert the request into an array based on the path seperator
  		$rEach = explode($this->ps, $request_string);
  		$rqst = array($rEach[0]);
  		
  		# now create a stepped check array from the component paths
  		for ($i=1;$i<count($rEach);$i++) $rqst[$i] = $rqst[$i - 1] . $this->ps . $rEach[$i];
  		
  		# reverse the array to check the most specific first
  		$rqst = array_reverse($rqst);
  		
  		# check for each id
  		for ($i=0;$i<count($rqst);$i++) {
  			$result = $this->db->query(
  				array('cms_navigation'=>'element_id', 'cms_element'=>'id'),
  				array('cms_navigation,path', 'cms_element,enabled'),
  				array($rqst[$i], true),
  				array('cms_navigation,path', 'cms_navigation,element_id', 'cms_navigation,template_id',
  					'cms_element,type', 'cms_element,function', 'cms_element,module', 'cms_element,title',
  					'cms_element,content_type', 'cms_element,link_uri', 'cms_navigation,path_is_outdated')
  				);
  			$this->_debug('result count ' . count($result) . ' at check ' . $i);
  			if (db_qcheck($result, true)) break;
  		}
  		
  		if (is_array($result)) {
  		
  			# set the URL strings
  			if (array_key_exists($i, $rqst)) $this->request_string = $rqst[$i];
  			if (strlen($request_string) > (strlen($this->request_string)+1)) {
  				if (strlen($this->request_string) > 0) {
  					$this->arg_string = substr($request_string, strlen($this->request_string)+1);
  				} else {
  					$this->arg_string = $request_string;
  				}
  			}
  			
  			# remove top layer array if necessary; accounts for different functions provided the result data
  			if (count($result) == 1) $result = $result[0];
  			
  			# check for outdated path
  			if (@$result['path_is_outdated'] == '1') {
  				if (@strlen($result['link_uri']) > 0) {
  					$extra = ' to use the ' . l('new address', $result['link_uri']) . '.';
  				} else {
  					$extra = '.';
  				}
  				$this->message('The link you used to access this page is outdated. Please update your bookmarks' . $extra);
  			}
  			
  			# special case for aliases of internal links
  			if (@strlen($result['link_uri'])!=0) {
  				if ($request_local === false) {
  					$this->_debug('handling special case alias-of-link');
  					return $this->check_request_id($result['link_uri']);
  				} else {
  					$this->_debug('a severe error occurred: invalid nested link');
  					$result = array('element_id'=>null,'title'=>null,'content_type'=>'html','type'=>null);
  				}
  			}
  			
  			$this->_debug('setting page to requested page');
  			if (array_key_exists('element_id', $result)) $this->pageid = $result['element_id'];
  			if (array_key_exists('title', $result)) $this->title = $result['title'];
  			if (array_key_exists('content_type', $result)) $this->content_type = $result['content_type'];
  			if (array_key_exists('type', $result)) $this->type = $result['type'];
  			
  			if (! @is_null($result['template_id'])) {
  				$this->_debug('the requested page uses a custom template');
  				$this->load_template($result['template_id']);
  			}
  		}
  	}
  	
  	if (isset($_REQUEST[$this->mech_item_code]) && isset($_REQUEST[$this->mech_request_code])) {
  		switch(strtolower($_REQUEST[$this->mech_request_code])) {
  			case 'xml':
  				$this->content_type = 'text/xml; charset=utf-8';
  				$this->mech_override = true;
  				break;
  			default:
  				$this->_debug('mech request code was set but invalid// ignoring...');
  				break;
  		}
  	}
  	
  	if ($this->pageid == '') {
  		$this->_debug('overriding to default home');
  		# use the default home page (based on criteria / filters)
  		$this->pageid = $this->default_home;
  		
  		// NOTE -- ADD FILTERS HERE TO ALLOW MORE THAN ONE DEFAULT HOME PAGE BASED ON VARIOUS CRITERIA
  		$this->_debug('<em><strong>WARNING:</strong> Multiple dynamic home pages are not implemented yet!</em>');
  	}
  	
  	if ($this->pageid == '') {
  		# no home page exists -- use our success page instead
  		$this->pageid = -1;
  	}
  	
  	return $this->_return(true, 'set pageid: ' . $this->pageid . ', content_type: ' . $this->content_type);
  }
  
  private function check_ssl()
  /* determine whether or not this is being accessed from ssl
   *
   */
  {
  	if (strtolower(substr($_SERVER['PHP_SELF'], 0, 5)) == "https") return true;
  	return false;
  }
  
  public function clean_up_after_template($template_id)
  /* hook function for the template::delete_template function since
   *  we are using template_ids in this object's tables
   *
   * this function will handle any clean up required in cms
   *  tables after a template is deleted from the system
   *
   */
  {
  	$this->_debug_start("Template_ID: $template_id");
  	
  	# sanity check
  	if (strlen($template_id) == 0) return $this->_return(false);
  	
  	# update the cache to expire
  	$this->cache_last_modified = '%';
  	
  	# clear this template id from the cms_element table
  	$this->_tx->db->update(
  		'cms_element',
  		array('template_id'),
  		array($template_id),
  		array('template_id'),
  		array('null'));
  	
  	return $this->_return(true);
  }
  
  protected function construct_page()
  /* retrieve and reconstruct page to output using the loaded template
   *
   * returns the completed page or false on error
   *
   */
  {
  	$this->_debug_start();
  	
  	# preset contents
  	$contents = '';
  	
  	# check if this is a fuse or standard element
  	if (is_array($this->pageid)) {
  		# fuse
  		$contents = $this->process_fuse($this->pageid);
  	} else {
  		# standard element
  		$contents = $this->process_element($this->pageid);
  	}
  	
  	return $this->_retByRef($contents);
  }
  
  protected function check_element($args)
  /* verify whether or not an element already exists with the given args
   *
   * this will be called from create_element to ensure install
   *   scripts do not duplicate existing elements
   *
   */
  {
  	$search = array();
  	foreach($args as $field=>$value){
  		$search[$field] = $value;
  	}
  	$check = $this->_tx->db->query('cms_element', array_keys($search), array_values($search));
  	return db_qcheck($check);
  }
  
  public function create_element($args)
  /* create a new element from $args array
   *
   * requires:
   *   type               : the type of element ('thread', 'circuit', 'fuse', 'filament', 'link')
   *   name               : the element name OR
   *   load_from_request  : true (default false)
   *                        this option instructs the function to load all data from the request object
   *
   * optional:
   *   created_by         : the name of the user or 'SYSTEM'
   *   title              : a local page title
   *   link_title         : the title to use when linked
   *   link_uri           : the address for a link
   *   description        : description of the element
   *   template_id        : id of the template to use
   *   enabled            : enabled true or false
   *   ssl_required       : should ssl be required to load the page (T/F)?
   *   locked             : are edits prohibited (T/F)?
   *   function           : the name of the function for a fuse
   *   module             : the name of the module which owns the element
   *   content_type       : the type of content (defaults to 'html')
   *   skip_security      : true to skip security checks/sets (defaults to false)
   *
   * returns the id of the new element or false (on error)
   *
   */
  {
  	$this->_debug_start();
  	
  	# set valid element types
  	$valid_types = array('thread', 'circuit', 'fuse', 'filament', 'link');
  	
  	# set load from request flag
  	if (array_key_exists('load_from_request', $args) && ($args['load_from_request'] === true)) {
  		$load_from_request = true;
  	} else {
  		$load_from_request = false;
  	}
  	
  	# set skip_security flag then remove it from args (since args are used implicitely in db queries)
  	if (array_key_exists('skip_security', $args)) {
  		$SKIP_SECURITY=$args['skip_security'];
  		unset($args['skip_security']);
  	} else {
  		$SKIP_SECURITY=false;
  	}
  	
  	# verify required data is provided and valid
  	if (! is_array($args)) return $this->_return(false, 'E1');
  	if ((! array_key_exists('type', $args))||(! in_array(strtolower((string)$args['type']), $valid_types))) return $this->_return(false, 'E2');
  	if ( ((! array_key_exists('name', $args))||(strlen((string)$args['name'])==0)) && (! $load_from_request) ) {
  		return $this->_return(false, 'E3');
  	}
  	if (($args['type'] == 'link') &&
  		(
  			($load_from_request && ((!array_key_exists('link_uri', $_REQUEST) || (strlen($_REQUEST['link_uri']) == 0))))
  			||
  			(!$load_from_request && ((!array_key_exists('link_uri', $args)) || (strlen($args['link_uri']) == 0)))
  		))
  	{
  		return $this->_return(false, 'E4');
  	}
  	if ($this->check_element($args)) return $this->_return(true, 'identical element already exists');
  	
  	# prepare our master field list
  	$fieldlist = $this->field_list('cms_element');
  	array_remove($fieldlist, 'id', true);
  	array_remove($fieldlist, 'shared', true);
  	array_remove($fieldlist, 'created_by', true);
  	array_remove($fieldlist, 'updated_by', true);
  	array_remove($fieldlist, 'modified', true);
  	array_remove($fieldlist, 'type', true);
  	array_remove($fieldlist, 'module', true);
  	if ($args['type'] != 'link') array_remove($fieldlist, 'content', true);
  	
  	# set the id
  	$id = uniqid();
  	
  	# set the creator
  	array_key_exists('created_by', $args) ? $creator = $args['created_by'] : $creator = $this->_tx->get->userid;
  	
  	# set the owner object
  	if (array_key_exists('module', $args)) {
  		$module = $args['module'];
  	} else {
  		$dbg = debug_backtrace(false);
  		(array_key_exists('class', $dbg[1])) ? $module = $dbg[1]['class'] : $module = 'SYSTEM';
  	}
  	
  	# create the arrays that will be passed to the mysql object
  	$saveFields = array('id', 'type', 'created_by', 'module');
  	$saveValues = array($id, $args['type'], $creator, $module);
  	
  	# set a pointer to the data source
  	(array_key_exists('load_from_request', $args)&&($args['load_from_request'] === true)) ? $d =& $_REQUEST : $d =& $args;
  	
  	# build the insert arrays
  	for($i=0;$i<count($fieldlist);$i++) {
  		if ( (array_key_exists($fieldlist[$i], $d)) && (strlen($d[$fieldlist[$i]]) > 0) ) {
  			# set the value based on the field type
  			switch($this->field_type('cms_element', $fieldlist[$i])) {
  				case 'string': $saveValues[] = (string)$this->_tx->db->escape($d[$fieldlist[$i]]); break;
  				case 'integer': $saveValues[] = intval($d[$fieldlist[$i]]); break;
  				case 'boolean': $saveValues[] = (bool)$d[$fieldlist[$i]]; break;
  				//case 'timestamp': $saveValues[] = "'" . $this->_tx->db->escape($d[$fieldlist[$i]]) . "'"; break;
  				default: $saveValues[] = "field type error"; break;
  			}
  			$saveFields[] = $fieldlist[$i];
  		}
  	}
  	
  	# insert the new record into the database
  	$result = $this->_tx->db->insert('cms_element', $saveFields, $saveValues);
  	if (! db_qcheck($result)) return $this->_return(false, 'E4');
  	
  	# FOR NOW -- grant public access to the new element if the security system is enabled
  	#  YES -- $this->_has() should return the same result as is_object(), but in this instance
  	#  the CMS object can be called from the security object which can result in unintentional
  	#  recusion.  Modern versions of PHP detect this and block the behavior, resulting in 
  	#  $this->_has() returning true but then actually calling the object terminating the code,
  	#  thus we have to check both cases.
  	if (($SKIP_SECURITY === false)&&($this->_has('security'))&&(@is_object($this->_tx->security))) {
  		$this->_tx->security->grant_access('PUBLIC', "content_id.$id");
  	}
  	
  	# for links, automatically create a path entry
  	if ($args['type'] == 'link') $this->create_path(array('element_id'=>$id));
  	
  	return $this->_return($id, 'S');
  }
  
  
  protected function create_html_root()
  /* create the html root object exactly one time
   *
   */
  {
  	if (is_object($this->html)) return;
  	$this->_debug('creating html root');
  	# create the root html document element used to output the page
		$this->html = new html_element($this->_tx, array('tag'=>'html','css'=>&$this->css,'html'=>&$this->html_ref,'_debug_mode'=>$this->_debug_mode,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match));
  }
  
  public function create_path($args)
  /* create a new path from $args array
   *
   * requires:
   *   element_id         : the element id to link to
   *
   * optional:
   *   path               : the new, unique path
   *   menu_setting       : true if the path should appear in the menu (default false)
   *   path_is_outdated   : true if the path should be marked as outdated (default false)
   *   force              : true if an existing path should be overwritten
   *
   * returns true or false (on error)
   *
   */
  {
  	$this->_debug_start();
  	
  	# verify required data is provided and valid
  	if (! is_array($args)) return $this->_return(false, 'Args must be an array');
  	if (! array_key_exists('element_id', $args)) return $this->_return(false, 'An element id is required');
  	
  	# enforce no spaces in paths
  	if (array_key_exists('path', $args)) {
  		$path = trim(str_replace(' ', '_', $args['path']));
  	} else {
  		$path = null;
  	}
  	
  	# set the menu setting
  	if (array_key_exists('menu_setting', $args)&&($args['menu_setting']=='true')) {
  		$menu_setting = true;
  	} else {
  		$menu_setting = false;
  	}
  	
  	# set the outdated setting
  	if (array_key_exists('path_is_outdated', $args)&&($args['path_is_outdated']=='true')) {
  		$legacy_setting = true;
  	} else {
  		$legacy_setting = false;
  	}
  	
  	# set the nav level
  	$nav_level = 0;
  	
  	# check for overwrite
  	if (array_key_exists('force', $args)&&($args['force']==true)) {
  		$this->_tx->db->delete('cms_navigation', array('path'), array($path));
  	}
  	
  	# insert the new record into the database
  	$result = $this->_tx->db->insert(
  		'cms_navigation',
  		array('path', 'element_id', 'menu_visible', 'nav_level', 'path_is_outdated'),
  		array($path, $args['element_id'], $menu_setting, $nav_level, $legacy_setting)
  		);
  	
  	if (db_qcheck($result)) return $this->_return(true, 'Success');
  	return $this->_return(false, 'Error inserting new path record');
  }
  
  public function delete_element($args)
  /* delete the specified element
   *
   * only the module who created an element can delete it
   *
   * in the future... support a wildcard match to delete all owned elements by a module
   *
   * $args should be an array
   *
   * requires:
   *   id                 : the element id
   *
   * optional:
   *   delete_shared      : true to override shared setting and force deletion (default false)
   *   delete_foreign     : true to override module ownership and force deletion (default false)
   *                        only the local cms object can set this option
   *
   * returns true or false (on error)
   *
   */
  {
  	# verify required data is provided and valid
  	if (! is_array($args)) return false;
  	if ((! array_key_exists('id', $args))||(! is_string($args['id']))) return false;
  	if (strlen($args['id']) == 0) return false;
  	
  	# get the requesting object
  	$dbg = debug_backtrace();
  	$module = $dbg[1]['class'];
  	
  	# set delete foreign flag
  	if (($module == get_class($this))&&(array_key_exists('delete_foreign', $args))&&($args['delete_foreign']===true)) {
  		$delete_foreign = true;
  	} else {
  		$delete_foreign = false;
  	}
  	
  	# set delete shared flag
  	if ((array_key_exists('delete_shared', $args))&&($args['delete_shared']===true)) {
  		$delete_shared = true;
  	} else {
  		$delete_shared = false;
  	}
  	
  	# get the element data
  	$ele = $this->_tx->db->query('cms_element', array('id'), array($args['id']), array('module', 'shared'));
  	if (! db_qcheck($ele)) return false; // element does not exist
  	
  	# validate flags
  	if ((! $delete_foreign)&&($ele[0]['module']!==$module)) return false;
  	if ((! $delete_shared)&&($ele[0]['shared'])) return false;
  	
  	# update the cache to expire
  	$this->cache_last_modified = $args['id'];
  	
  	# delete the element
  	if ($this->_tx->db->delete('cms_element', array('id'), array($args['id']))) {
  		# delete path mappings
  		$this->_tx->db->delete('cms_navigation', array('element_id'), array($args['id']));
  	} else {
  		return false;
  	}
  	
  	return true;
  }
  
  public function delete_path($path)
  /* delete the specified path
   *
   * requires:
   *   path               : the new, unique path
   *
   * returns true or false (on error)
   *
   */
  {
  	# delete the path from the database
  	$result = $this->_tx->db->delete('cms_navigation', array('path'), array($path));
  	if (db_qcheck($result)) return true;
  	return false;
  }
  
		public function dev_drop_tables()
		/* drops the tables for all modules
		 *
		 * enable during development only!!
		 *
		 */
		{
			if ($this->dev_mode) {
				$tableList = array('calendar_events', 'calendar_groups', 'calendar_locations', 'calendar_recurring_events',
						'calendar_settings', 'cms_element', 'cms_navigation', 'cms_settings', 'files', 'files_index',
						'map_template_resource', 'map_template_elements', 'template_elements', 'template_resource', 'templates');
				foreach ($tableList as $table) $this->_tx->db->queryFull("DROP TABLE IF EXISTS `$table`");
				$this->_tx->db->queryFull("DELETE FROM `modules` WHERE 1=1");
				return "ALL MODULE TABLES HAVE BEEN DROPPED. Please " . l('click here to reload', '') . '.';
			} else {
				return "Not in development mode, sorry.";
			}
		}
		
		protected function encode_foreign_reference($group, $function)
		/* given a module group and function name, encode a reference that
		 *  can be inserted into an element
		 *
		 * this will double as the ID for fuses and filaments
		 *
		 * reference is in the form of ##_module_name::function_## 
		 *
		 */
		{
			# sanity check
			if ( (strlen($group) == 0) || (strlen($group) == 0) ) return '';
			
			# return the encoded reference
			return '##_' . $group . '::' . $function . '_##';
		}
		
		protected function extract_child_ids($text, &$list)
		/* given a snip of text, identify and extract any child ids
		 *
		 * extracted ids will be returned in an array. if no result is found, an empty array will be returned
		 *
		 */
		{
			$this->_debug_start();
						
			# create empty array to return
			$list = array();
			
			# protect against empty string
			if (strlen($text) > 8)
			{
				preg_match_all("/##__([0-9]+)__##/i", $text, $match);
				
				if (is_array($match)) $list = $match[1];
			}
			
			return $this->_return(true);
		}
		
		protected function field_list($table, $values_as_types = false)
		/* return an array of fields for the specified table
		 *
		 * if values as types is true, return in the format of "field"=>"type"
		 *
		 */
		{
			switch($table) {
				case 'cms_element':
					$list = array('id'=>'string', 'name'=>'string', 'shared'=>'boolean',
							'type'=>'string', 'title'=>'string', 'link_title'=>'string',
							'description'=>'string', 'template_id'=>'string',
							'created_by'=>'string', 'updated_by'=>'string',
							'modified'=>'datetime', 'enabled'=>'boolean',
							'ssl_required'=>'boolean', 'locked'=>'boolean', 'content'=>'string',
							'module'=>'string', 'function'=>'string', 'content_type'=>'string',
							'link_uri'=>'string', 'link_is_external'=>'boolean');
					break;
				case 'cms_navigation':
					$list = array('id'=>'integer','path'=>'string', 'element_id'=>'string',
							'menu_visible'=>'boolean', 'nav_level'=>'integer',
							'parent_id'=>'string','parent_order'=>'integer',
							'template_id'=>'integer','path_is_outdated'=>'boolean');
					break;
				default: return array(); break;
			}
			
			if (! $values_as_types) $list = array_keys($list);
			
			return $list;
		}
		
		protected function field_type($table, $field)
		/* return the field type for the specified table and field
		 *
		 */
		{
			$list = $this->field_list($table, true);
			return $list[$field];
		}
		
		public function generate_admin_menu()
		/* generate the administration menu
		 *
		 */
		{
			$arr = array(
				array('id'=>'admin', 'path'=>'admin', 'nav_level'=>0, 'link_title'=>'Site Administration', 'class'=>'left', 'parent_id'=>null),
				array('path'=>'admin/cms', 'nav_level'=>1, 'link_title'=>'Content', 'class'=>'left', 'parent_id'=>'admin'),
				array('path'=>'admin/template', 'nav_level'=>1, 'link_title'=>'Display/Templates', 'class'=>'left', 'parent_id'=>'admin'),
				array('path'=>'admin/file', 'nav_level'=>1, 'link_title'=>'Files', 'class'=>'left', 'parent_id'=>'admin'),
				array('path'=>'admin/accounts_db', 'nav_level'=>1, 'link_title'=>'Accounts (DB)', 'class'=>'left', 'parent_id'=>'admin'),
				array('path'=>'admin/security', 'nav_level'=>1, 'link_title'=>'Security/Permissions', 'class'=>'left', 'parent_id'=>'admin')
				);
			return $arr;
		}
		
		public function generate_menu($start_level = 0, $end_level = false, $parent_id = false, $class = false)
		/* generate the menu system
		 *
		 */
		{
			if ($end_level === false) {
				$end_level = 20;
			}
			
			# make sure integers are integers
			$start_level = intval($start_level);
			if ($end_level !== false) $end_level = intval($end_level);
			
			# validate empty parent_id
			#if (($parent_id !== false)&&(strlen($parent_id)==0)) $parent_id = false;
			
			# create the selected array
			#  there should be zero or one path selected for each nav level output
			$selected = array();
			$path = $this->request_string;
			
			# attempt to work out the selection structure in the menu system, starting with what nav_level the current
			#  request lies at all the way back to the top level
			#
			# now that i think about it, i'm not sure what situation we would be in where
			#  the code calling this function would actually know the parent_id it was
			#  requesting a menu for; the template system supposed to be generic enough
			#  to not have different code for different 'sections' of the site; i think
			#  we'll be removing the "parent_id" argument completely sooner or later
			#
			# if the current path is not in the menu, use the root for a top level menu and the home page for level 2
			#
			# try to match the current path to a place in the nav menu
			$r = $this->_tx->db->query('cms_navigation', array('path'), array($path), array('id', 'path', 'parent_id', 'nav_level'));
			/* debug */ $c = 0;
			while ((! db_qcheck($r, true))&&(strlen($path)>0)) {
				/* debug */ if ($c == 1000) { die('MAX LOOP COUNT 1 EXCEEDED'); }
				$pos = strrpos($path, '/');
				if ($pos !== false) {
					$path = substr($path, 0, $pos);
				} else {
					# no results since we're out of virtual folders
					$path = '';
				}
			}
			# if there is a result then we know the last level and the one above it. (in demon voice: ) FINISH THEM
			if (db_qcheck($r, true)) {
				$selected[$r[0]['nav_level']] = $r[0]['id'];
				if ($r[0]['nav_level'] > 0) $selected[$r[0]['nav_level']-1] = $r[0]['parent_id'];
				# continue moving upwards until we get to the root (0)
				/* debug */ $c = 0;
				while (! array_key_exists(0, $selected)) {
					/* debug */ if ($c == 1000) { die('MAX LOOP COUNT 2 EXCEEDED'); }
					reset($selected);
					$r = $this->_tx->db->query('cms_navigation', array('id'), array(current($selected)), array('id', 'path', 'parent_id', 'nav_level'));
					if (! db_qcheck($r, true)) break;
					$selected[$r[0]['nav_level']] = $r[0]['id'];
					if ($r[0]['nav_level'] > 0) $selected[$r[0]['nav_level']-1] = $r[0]['parent_id'];
					ksort($selected);
				}
			} else {
				# there was no result; use the first top level menu item as the default
				$r = $this->_tx->db->query('cms_navigation', array('nav_level'), array(0), array('id', 'path'), true, array('parent_order'));
				if (db_qcheck($r, true)) $selected[0] = $r[0]['id'];
			}
			
			# define the query search criteria
			$search_keys = array('cms_navigation,nav_level', 'cms_navigation,nav_level', 'cms_navigation,menu_visible', 'cms_element,enabled');
			$search_values = array('>= ' . $start_level, '<= ' . $end_level, true, true);
			
			# optionally include parent_id if limiting
			if (array_key_exists(($start_level-1), $selected)) {
				$search_keys[] = 'cms_navigation,parent_id';
				$search_values[] = $selected[$start_level-1];
			}
			
			$r = $this->_tx->db->query(
				array('cms_navigation'=>'element_id', 'cms_element'=>'id'),
				$search_keys,
				$search_values,
				array('cms_navigation,id', 'cms_navigation,path', 'cms_navigation,menu_visible', 'cms_navigation,element_id', 'cms_navigation,nav_level',
					'cms_element,name', 'cms_element,link_title', 'cms_navigation,parent_id', 'cms_element,type', 'cms_element,link_uri',
					'cms_element,link_is_external', 'cms_element,description'),
				true,
				array('cms_navigation,nav_level','cms_navigation,parent_order')
				);
			
			if (! db_qcheck($r, true)) return false;
			
			# set the class for this menu
			if ($class == false) {
				if ($start_level == 0) { $class = 'navigation'; } else { $class = 'sub_navigation'; }
			}
			
			# create the html root object if needed
			$this->create_html_root();
			
			$ul = $this->html->_new('ul');
			$ul->add_class($class);
			
			$elements = array();
			$recovery = array();
			$security_check = $this->_has('security');
			
			# until we work out the administration menu and setup menu structure, always include a link
			if (($security_check)&&($this->_tx->security->access('admin'))) $r = array_merge($r, $this->generate_admin_menu());
			
			for ($i=0;$i<count($r);$i++) {
				# validate access first
				if (($security_check)&&(! @is_null($r[$i]['element_id']))&&(! $this->_tx->security->access('content_id.' . $r[$i]['element_id']))) continue;
				if (strlen($r[$i]['link_title']) == 0) {
					$title = $r[$i]['name'];
				} else {
					$title = $r[$i]['link_title'];
				}
				# set the link
				if (@$r[$i]['type'] == 'link') {
					if ($r[$i]['link_is_external']) {
						$url = $r[$i]['link_uri'];;
					} else {
						$url = url($r[$i]['link_uri']);
					}
				} else {
					$url = url($r[$i]['path']);
				}
				
				if ($r[$i]['nav_level'] == $start_level) {
					@$elements[$r[$i]['id']] =& $ul->cc('li');
					@$elements[$r[$i]['id']]->cc('a')->sa('href',$url)->set_value($title);
					if (@$r[$i]['id'] == @$selected[$start_level]) $elements[$r[$i]['id']]->add_class('selected');
					if (array_key_exists('class', $r[$i])&&(strlen($r[$i]['class'])>0)) @$elements[$r[$i]['id']]->add_class($r[$i]['class']);
					if (@strlen($r[$i]['description'])>0) @$elements[$r[$i]['id']]->set_attribute('title', $r[$i]['description']);
				} else {
					if (@array_key_exists($r[$i]['parent_id'], $elements)) {
						@$elements[$r[$i]['id']] =& $elements[$r[$i]['parent_id']]->ul->cc('li');
						@$elements[$r[$i]['id']]->cc('a')->sa('href',$url)->set_value($title);
						if (@$r[$i]['id'] == @$selected[$r[$i]['nav_level']]) @$elements[$r[$i]['id']]->add_class('selected');
						if (@strlen($r[$i]['description'])>0) @$elements[$r[$i]['id']]->set_attribute('title', $r[$i]['description']);
					} else {
						$recovery[$r[$i]['id']] = array(0=>$r[$i]['parent_id']);
						$recovery[$r[$i]['id']][1] = $this->html->_new('ul');
						$recovery[$r[$i]['id']][1]->cc('li')->cc('a')->sa('href',$url)->set_value($title);
						if ($r[$i]['id'] == @$selected[$r[$i]['nav_level']]) $recovery[$r[$i]['id']][1]->li->add_class('selected');
						if (@strlen($r[$i]['class'])>0) $recovery[$r[$i]['id']][1]->add_class($r[$i]['class']);
						if (@strlen($r[$i]['description'])>0) @$recovery[$r[$i]['id']][1]->set_attribute('title', $r[$i]['description']);
					}
				}
			}
			
			foreach($recovery as $key=>&$ele) {
				if (array_key_exists($ele[0], $elements)) $elements[$ele[0]]->add_child($ele[1]);
			}
			
			return $ul;
		}
		
		public function get_args()
		/* return zero or more arguments provided in the path, not including the request id
		 *
		 */
		{
			return $this->arg_string;
		}
		
		protected function get_page_settings()
		/* retrieve the settings for the selected page 
		 *
		 */
		{
			$this->_debug_start();
			
			# set the currently unimplemented variables
			#$this->doctype='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
			$this->doctype='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
			
			if ( (! is_array($this->pageid)) && ($this->pageid !== -1) && (strlen($this->pageid) > 0) ) {
				# retrieve the page meta-data from the database
				$result = $this->db->query(
						'cms_element',
						array('id'),
						array($this->pageid),
						array('type','title','content_type','template_id','ssl_required','module','function'));
				
				if (db_qcheck($result)) {
					# set variables
					$this->title = $result[0]['title'];
					$this->content_type = $result[0]['content_type'];
					$this->type = $result[0]['type'];
					
					$this->_debug('set content_type: ' . $this->content_type);
					
					if ($result[0]['ssl_required'] == true) {
						# not implemented yet
						$this->_debug('<em><strong>Warning:</strong> SSL was required for this page 
														but https overrides are not implemented in this version of the cms module.</em>');
					}
					
					if (strlen($result[0]['function']) > 0) {
						$this->_debug('Modern Fuse Selected');
						$this->_debug('Args: ' . $this->arg_string);
						$this->fuse = array('provides'=>$result[0]['module'], 'function'=>$result[0]['function']);
						if (strlen($this->arg_string) > 0) $this->fuse_args = explode($this->ps, $this->arg_string);
					}
					
					if (strlen($result[0]['template_id']) > 0) {
						$this->_debug('the requested page uses a custom template');
						$this->load_template($result[0]['template_id']);
					}
				}
			} elseif ($this->pageid === -1) {
				# success page
				$this->title = $this->_version;
				$this->content_type = 'html';
				$this->type = 'thread';
			}
			
			# set mode silent for downloads, xml, etc...
			switch($this->content_type) {
				case 'download':
					$this->running_mode = 'silent';
					break;
				case 'text':
					$this->running_mode = 'silent';
					break;
				case 'xml':
					$this->running_mode = 'silent';
					break;
				default:
					$this->_debug('using default running mode for content type ' . $this->content_type);
					$this->running_mode = 'active';
					break;
			}
			
			# check to see if the page uses any extensions
			if ($this->type == 'circuit') {
				# page uses one or more extensions
				/* ... not implemented yet ... */
				$this->_debug('<strong>WARNING:</strong> Page extension use is not implemented yet!');
				/* maybe give the engine a shared array called 'load_queue' that can be passed and altered
						by the cms and other objects.
						on each gear change, check the load queue and process & remove any listed items */
			}
			
			return $this->_return(true);
		}
		
		public function call_shared_data($variable, $conditional = false)
		/* given a variable return the evaluated value
		 *
		 * supports conditional variables per parse_shared_data syntax
		 *
		 */
		{
			$value = $this->_tx->get->$variable;
			if (@strlen($value) == 0) return '';
			
			if ($conditional !== false) {
				return @str_replace('#__' . $variable . '__#', $value, $conditional);
			}
			
			return $value;
		}
		
		public function call_shared_method($object_name, $method, $args = false)
		/* given a data stream and object/method data, return the result of the call
		 *
		 * if the function call is invalid, returns 'ERROR'
		 *
		 */
		{
			# handle the special case where *this* is the object
			if (($object_name == 'cms')||($object_name == 'cms_manager')) {
				$obj =& $this;
			} else {
				# get the object
				$obj =& $this->_tx->$object_name;
			}
			
			# call the function or error
			if ($this->_tx->_matchFilament($object_name, $method) && is_object($obj) && method_exists($obj, $method)) {
				if ($args !== false) {
					$fn_result = call_user_func_array(array($obj, $method), $args);
				} else {
					$fn_result = call_user_func(array($obj, $method));
				}
			} else {
				$fn_result = "<strong>ERROR: $object_name, $method</strong>";
			}
			
			return $fn_result;
		}
		
		protected function load_configuration()
		/* load the stored configuration (if any) for this web site
		 *
		 */
		{
			$this->_debug_start();
			
			# retrieve all rows from the database
			$result = $this->db->query('cms_settings', '', '', array('option','value'), true);
			
			# preset settings array
			$settings = array();
			
			# since all option values are unique, set the option values to keys and value values to the values
			if (is_array($result) && @is_array($result[0])) {
				for ($i=0;$i<count($result);$i++) {
					$settings[$result[$i]['option']] = $result[$i]['value'];
				}
			}
			
			if ((!array_key_exists('htaccess', $settings)) || (array_key_exists('htaccess', $settings) && ($settings['htaccess'] == '1'))) {
				# attempt to write the htaccess file if it does not exist
				#  this should be done via configuration, dynamic, and possibly in the access module
				if (! file_exists($this->_tx->get->site_root . '.htaccess')) $this->write_htaccess();
				
				# now set our navigation variable depending on the existance of this file
				if (file_exists($this->_tx->get->site_root . '.htaccess')) $this->link_rewrite = true;
			} else {
				$this->link_rewrite = true;
			}
			
			/* SET CMS CONFIGURATION VARIABLES HERE */
			if (isset($settings['administrator_menu'])&&($settings['administrator_menu'] == false)) {
				$this->administrator_menu = false;
			}
			if (isset($settings['default_home'])) $this->default_home = $settings['default_home'];
			if (isset($settings['dev_mode'])&&($settings['dev_mode'] == true)) {
				$this->dev_mode = true;
			}
			if (isset($settings['content_request_code'])) $this->content_request_code = $settings['content_request_code'];
			if (isset($settings['template_id'])) $this->template_id = $settings['template_id'];
			if (isset($settings['download_request_code'])) $this->download_request_code = $settings['download_request_code'];
			if (isset($settings['mech_item_code'])) $this->mech_item_code = $settings['mech_item_code'];
			if (isset($settings['mech_request_code'])) $this->mech_request_code = $settings['mech_request_code'];
			if (isset($settings['site_title'])) $this->site_title = $settings['site_title'];
			
			# check for initial setup
			if (strlen($this->content_request_code) == 0) $this->content_request_code = 'setup_request';
			if ($this->dev_mode) { $this->dev_mode = true; } else { $this->dev_mode = false; }
			if (strlen($this->download_request_code) == 0) $this->download_request_code = 'fid';
			if (strlen($this->mech_item_code) == 0) $this->mech_item_code = 'mit';
			if (strlen($this->mech_request_code) == 0) $this->mech_request_code = 'mech';
			if (strlen($this->site_title) == 0) $this->site_title = '';
			if (strlen($this->template_id) == 0) $this->template_id = '0';
			
			# development mode options
			if ($this->dev_mode) {
				if (array_key_exists('dev_debug', $_REQUEST)) $this->_debug_mode = (int)$_REQUEST['dev_debug'];
			}
			
			# debug output
			$this->_debug('Site Template ID: ' . $this->template_id);
			$this->_debug('Content Request Code: ' . $this->content_request_code);
			$this->_debug('Download Request Code: ' . $this->download_request_code);
			
			return $this->_return(true);
		}
		
		protected function load_template($id)
		/* load a template adhering to the current state and settings
		 *
		 */
		{
			if ($this->dev_mode && array_key_exists('dev_template', $_REQUEST)) {
				if ($this->template->load($_REQUEST['dev_template'])) return true;
			}
			$this->template->load($id);
		}
		
		public function message($string, $type = 'notice')
		/* queue a message
		 *
		 * optional type (notice|error)
		 *
		 */
		{
			# validate the type
			switch (@strtolower($type)) {
				case 'notice': $type = 'note'; break;
				case 'note': $type = 'note'; break;
				case 'error': $type = 'alert'; break;
				default: $type = 'alert'; break;
			}
			# validate the string
			if ((!is_string($string))||(strlen($string) == 0)) return false;
			# add the message for output and set the type
			$this->message_type = $type;
			$this->message[] = $string;
			return true;
		}
		
		public function output_message()
		/* output any queued messages
		 *
		 */
		{
			if (count($this->message)==0) return true;
			# get the html object
			if (!is_object($this->html)) return false;
			$html =& $this->html->body;
			# set the message string
			$string = '';
			for($i=0;$i<count($this->message);$i++) {
				if (strlen($string)>0) $string.="<br />";
				$string .= $this->message[$i];
			}
			# create the message box
			$msgBox = $html->_new('div')->add_class('message_box')->add_class($this->message_type)->set_value($string);
			# attempt to place the message box in the right spot
			$ele =& $html->match_children('class', 'contentBox', false);
			if (is_object($ele)) { $ele->ac($msgBox, 'top'); return true; }
			$ele =& $html->match_children('id', 'content', false);
			if (is_object($ele)) { $ele->ac($msgBox, 'top'); return true; }
			$ele =& $html->match_children('id', 'header', false);
			if (is_object($ele)) { $ele->ac($msgBox, 'after'); return true; }
			$html->ac($msgBox, 'top');
			return true;
		}
		
		protected function output_array($a, $output_level = 0)
		/* send each element from array $a to the standard output object
		 *
		 * output_level is passed directly to the standard output object
		 *
		 */
		{
			if (is_array($a))
			{
				foreach($a as $str)
				{
					$this->outs->_send($str, $output_level);
				}
			} else {
				if (strlen($a) > 0) $this->outs->_send($a, $output_level);
			}
			return true;
		}
		
		public function parse_ema_commands(&$stream)
		/* scan stream for any ema commands and execute them
		 *
		 * supports commands in the form of:
		 *   ##emc:function_name[:arguments]##
		 *
		 */
		{
			# check for commands
			preg_match_all("/\#\#emc:([^\#\:]*)(?::([^\#\:]*)){0,1}\#\#/", $stream, $list);
			# replace matches
			for ($i=0;$i<count($list[0]);$i++) {
				$stream = str_replace($list[0][$i], call_user_func($list[1][$i], $list[2][$i]), $stream);
			}
			return true;
		}
		
		public function parse_shared_data(&$stream)
		/* scan stream for any shared variable placeholders
		 *
		 * updates variable list; does not alter the data stream
		 *
		 * supports conditional variables in the form of:
		 *   (#__var__# something else)
		 * where the parenthesis will never be output
		 #   and "something else" will only be output if
		 #   the shared variable "var" is not a zero length string
		 *
		 */
		{
			$list = array();
			
			# check for conditionals
			preg_match_all("/\(([^#()]*\#__([^\#]*)__\#[^#()]*)\)/", $stream, $conditionals);
			
			for ($i=0;$i<count($conditionals[0]);$i++) {
				$list[] = array($conditionals[0][$i], $conditionals[2][$i], $conditionals[1][$i]);
			}
			
			# check for standard matches
			preg_match_all("/\#__([^\#]*)__\#/", $stream, $match);
			
			# update the variable list
			for ($i=0;$i<count($match[0]);$i++) {
				$list[] = array($match[0][$i], $match[1][$i], false);
			}
			
			return $list;
		}
		
		public function parse_shared_methods(&$stream)
		/* scan stream for any embedded shared methods (filaments)
		 *
		 * does not alter the stream, returns results in the defined format:
		 *   { 0=>match, 1=>object_name, 2=>function_name, 3=>args* }
		 *
		 */
		{
			$r = array();
			
			# get the matches
			preg_match_all("/\#\#_([^\#]*)_\#\#/", $stream, $match);
			
			# replace matches
			for ($i=0;$i<count($match[0]);$i++) {
				# init args
				$args = false;
				# get the function data
				$fn_call = explode('::', $match[1][$i]);
				if (count($fn_call) != 2) continue;
				$this->_debug('parse_shared_methods executing ' . $fn_call[0] . '::' . $fn_call[1]);
				# check for arguments
				$p1 = strpos($fn_call[1], '(');
				$p2 = strpos($fn_call[1], ')');
				if (($p1 !== false) && ($p2 !== false) && ($p1 < $p2)) {
					if ($p1+1 == $p2) {
						# special case (no args, just empty parenthesis)
						$fn_call[1] = substr($fn_call[1], 0, strlen($fn_call[1])-2);
					} else {
						# found arguments
						$this->_debug('arguments were provided');
						$list = substr($fn_call[1], $p1+1, $p2-$p1-1);
						$this->_debug("arguments: $list");
						$fn_call[1] = substr($fn_call[1], 0, $p1);
						# process args
						$args = explode(',', $list);
					}
				}
				# publish the result
				$r[] = array($match[0][$i], $fn_call[0], $fn_call[1], $args);
			}
			
			return $r;
		}
		
		protected function parse_template_element(&$template_html, &$element, $instance_number = 1)
		/* replace the specified template element instance number with the provided element
		 *
		 */
		{
			# for templates without any elements (empty templates, like the default 'Silent')
			#  just set the contents and return
			if (strlen($template_html) == 0) {
				$template_html = $element;
				return false;
			}
			
			# match any fixed areas
			preg_match_all("/\<\~\>(.*)\<\/\~\>/", $template_html, $match);
			
			# insert matches
			for ($i=0;$i<count($match[0]);$i++) {
				$element .= $match[1][$i];
			}
			
			# make the switch
			$template_html = preg_replace("/\<\%" . $instance_number . "\>(.*)\<\/\%" . $instance_number . "\>/", $element, $template_html);
			
			return true;
		}
		
		protected function process_element($id)
		/* load, compile, and return the specified element
		 *
		 */
		{
			$this->_debug_start("id = $id");
			
			# retrieve element from the database
			$result = $this->db->query('cms_element', array('id'), array($id), array('content', 'process_php', 'process_ema'));
			if (! db_qcheck($result)) return $this->_return(false, 'error: the specified element id was not found.');
			
			if ($result[0]['process_php']) {
				# process any php in the code -- this should be highly protected but we need to get the site online ASAP
				ob_start();
				eval(" ?> " . $result[0]['content'] . "<?php ");
				$content = ob_get_contents();
				ob_end_clean();
			} else {
				$content = $result[0]['content'];
			}
			
			if ($result[0]['process_ema']) {
				# extract any children from the content
				$this->extract_child_ids($content, $children);
				
				# since extract children always returns an array (empty or otherwise), attempt to process each child element now
				foreach($children as &$child) {
					# replace child id in snip with actual child element
					str_replace("##__$child__##", $this->process_element($child), $content);
				}
				
				# identify any methods in the stream
				$list = $this->parse_shared_methods($content);
				
				# conditionally update the output
				if ($this->process_filaments !== false) {
					for ($i=0;$i<count($list);$i++) {
						$content = @str_replace($list[$i][0], $this->call_shared_method($list[$i][1],$list[$i][2],$list[$i][3]), $content);
					}
				}
				
				# add to the registered request filaments
				$this->filaments = array_merge($this->filaments, $list);
				
				# look for any shared data placeholders
				$this->parse_shared_data($content);
			}
			
			# issue warning until we code for scope, new scope, and the proper use of the tag (it needs attributes)
			$this->_debug('<strong>WARNING:</strong> Coding has only been partially completed for this function!');
			
			return $this->_return($content);
		}
		
		protected function process_fuse($farr)
		/* given a fuse array from the transmission, get and return the fuse content
		 *
		 */
		{
			if (! is_array($farr)) return false;
			
			$this->_debug('process_fuse executing ' . $farr['provides'] . '::' . $farr['function']);
			
			# get the fuse content
			if (is_array($this->fuse_args)) {
				$r = call_user_func_array(array($this->_tx->$farr['provides'], $farr['function']), $this->fuse_args);
			} else {
				$r = call_user_func(array($this->_tx->$farr['provides'], $farr['function']));
			}
			
			if ($r === false) $this->_debug('Error executing fuse');
			return $r;
		}
		
		public function set_mode($mode)
		/* change the request running mode
		 *
		 * this command is only valid during the following global states:
		 *   postoutput
		 *
		 */
		{
			if ((!is_string($mode))||(($mode != 'active')&&($mode != 'silent'))) return false;
			if ($this->_tx->get->gear !== STATE_POSTOUTPUT) return false;
			$this->_debug('changing the running mode by special request. new mode: ' . $mode);
			$this->running_mode = $mode;
			return true;
		}
		
		public function admin($page = false, $option = false)
		/* module administration interface
		 *
		 */
		{
			switch(@strtolower($page)) {
				case 'modules': $page = 'modules'; break;
				default: $page = 'content'; break;
			}
			return $this->_content($page);
		}
		
		public function siteadmin($module = false, $option = false)
		/* site administration interface
		 *
		 * if a module is provided, load that module's admin page
		 *
		 */
		{
			if (($module !== false)&&(method_exists($this->_tx->{$module}, 'admin'))) {
				return $this->_tx->{$module}->admin($option);
			}
			
			# retrieve a list of all enabled extensions
			$extList = $this->_tx->_getExtensions(true);
			$extByGroup = array();
			$ul =& $this->html->_new('ul');
			$ul->add_class('extension_list');
			
			for($i=0;$i<count($extList);$i++) {
				$p = strpos($extList[$i], '_');
				$group = substr($extList[$i], 0, $p);
				$ext = substr($extList[$i], $p+1);
				if (! @is_array($extByGroup[$group])) $extByGroup[$group] = array();
				$extByGroup[$group][] = $ext;
			}
			
			ksort($extByGroup);
			
			foreach($extByGroup as $group=>$extensions) {
				$li =& $ul->cc('li');
				$li->span->set_value($group);
				for($i=0;$i<count($extensions);$i++) {
					$div =& $li->cc('div');
					$div->set_value("$extensions[$i]");
					$module = $group . '_' . $extensions[$i];
					if (method_exists($this->_tx->{$module}, 'admin')) {
						$div->a->set_attribute('href', url("admin/$module"))->set_value('Manage');
					}
				}
			}
			
			return "$ul";
		}
  
  /*================================================================================
   *
   *                                   G E A R S
   *
   *==============================================================================*/
  
		public function initialize()
		/* initialize the cms settings and begin pre-processing
		 *
		 */
		{
			$this->_debug_start();
			
			# determine whether or not the client is accessing with https
			$this->ssl = $this->check_ssl();
			
			# load the site's configuration
			$this->load_configuration();
			
			# check for any requested page id
			$this->check_request_id();
			
			# cache the selected page id
			$this->cache['pageid'] = $this->pageid;
			
			# register with the system cache
			if ($this->_has('cache')&&(@is_object($this->_tx->cache))) {
				$this->_tx->cache->register_table('cms_element');
				$this->_tx->cache->register_table('cms_navigation');
				$this->_tx->cache->register_table('cms_settings');
			}
			
			return $this->_return(true);
		}
		
		public function optimize()
		/* optimize output based on approved navigation path
		 *
		 */
		{
			# using the page id, retrieve the settings for this page to determine the content type, etc.
			$this->get_page_settings();
			
			# send the header(s) to the web-browser
			if ( ($this->_debug_mode < 0) && (strlen($this->content_type) > 0) ) {
				switch($this->content_type) {
					case 'html': @header('Content-Type: text/html; charset=utf-8'); break;
					case 'xml': @header('Content-Type: text/xml; charset=utf-8'); break;
					case 'css': @header('Content-Type: text/css'); break;
					case 'javascript': @header('Content-Type: text/javascript'); break;
					case 'text': @header('Content-Type: text/plain'); break;
					case 'download': break;
					default: header('Content-Type: ' . $this->content_type); break;
				}
			}
			
			# if this is an approved download, abort futher cms processing
			if (is_array($this->fuse)) {
				$this->_debug('pageid: fuse');
				# stop processing here if we are only outputting a fuse contents
				if ($this->running_mode == 'silent') {
					$contents = $this->process_fuse($this->fuse);
					if ($contents === false) {
						$this->pageid = $this->page_404;
						$this->running_mode = 'active';
					} else {
						$this->_tx->_exclude('*');
						$this->outs->_send($contents);
					}
				}
			} else {
				$this->_debug('pageid: ' . $this->pageid);
			}
		}
		
		public function preoutput()
		/* output html headers, content-type, etc...
		 *
		 */
		{
			$this->_debug_start();
			
			# load the specified template
			if ($this->template->status() == 'unloaded') $this->load_template($this->template_id);
			
			# create the html element
			$this->create_html_root();
			
			# set the html object settings
			$this->html->set_doctype($this->doctype);
			$this->html->set_attribute('xmlns', 'http://www.w3.org/1999/xhtml');
			$this->html->head;
			
			@header('Cache-Control: no-store, no-cache, must-revalidate');
			@header('Cache-Control: post-check=0, pre-check=0');
			@header('Pragma: no-cache');
			@header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT', true, 200);
			
			# start building html, depending on the page type
			if ($this->running_mode == 'active') {
				# add meta tags (if any)
				foreach ($this->template->meta_tags as &$tmp) $this->html->head->append_value($tmp);
				
				$css = $this->template->get_css();
				
				# add css (if any)
				foreach ($css as &$tmp) {
					$this->html->head->cc('link')->sas(array('rel'=>'stylesheet','type'=>'text/css','href'=>$tmp));
				}
				
				# add javascript (if any)
				foreach ($this->template->javascript as &$tmp) {
					$this->html->head->cc('script')->sas(array('type'=>'text/javascript','src'=>$tmp));
				}
				
				# set the site title if necessary
				if (strlen($this->site_title) == 0) $this->site_title = $this->title;
				
				unset($tmp, $ele);
			}
			
			return $this->_return(true);
		}
		
		public function head()
		/* output header element CONTENTS
		 *
		 * actual "<head>" and "</head>" tags are sent by the
		 *	preoutput and body functions respectively.
		 *	This allows for the engine to call various
		 *	registered "head" functions in any order without
		 *	having contents positioned outside the head tags.
		 *
		 */
		{
			$this->_debug_start();
			
			# process sitle title for variables
			$this->parse_shared_data($this->site_title);
			
			# continue sending html, depending on the page type
			if ($this->running_mode == 'active') {
				$this->html->head->title->set_value($this->site_title);
			}
			
			return $this->_return(true);
		}
		
		public function body()
		/* output body element
		 *
		 */
		{
			$this->_debug_start();
			
			$timer = new timer();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 0'); $timer->start();
			
			# get the template html
			$stream = $this->_tx->template->get_html();
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 1'); $timer->start();
			
			# process any commands in the stream
			$this->parse_ema_commands($stream);
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 2'); $timer->start();
			
			# identify any variables in the stream
			$this->variables = $this->parse_shared_data($stream);
			
			# conditionally update the output
			if ($this->process_variables !== false) {
				for ($i=0;$i<count($this->variables);$i++) {
					$stream = str_replace($this->variables[$i][0], $this->call_shared_data($this->variables[$i][1],$this->variables[$i][2]), $stream);
				}
			}
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 3'); $timer->start();
			
			# identify any methods in the stream
			$this->filaments = $this->parse_shared_methods($stream);
			
			# conditionally update the output
			if ($this->process_filaments !== false) {
				for ($i=0;$i<count($this->filaments);$i++) {
					$stream = @str_replace($this->filaments[$i][0], $this->call_shared_method($this->filaments[$i][1],$this->filaments[$i][2],$this->filaments[$i][3]), $stream);
				}
			}
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 4'); $timer->start();
			
			# check if this is a fuse or standard element
			if (is_array($this->fuse)) {
				# fuse
				$contents = $this->process_fuse($this->fuse);
			} elseif (is_array($this->pageid)) {
				# legacy fuse
				$this->_debug('<strong>LEGACY FUSE:</strong> Please update the providing module');
				$contents = $this->process_fuse($this->pageid);
			} elseif ($this->pageid === -1) {
				# success page
				$contents = $this->_content('success');
			} else {
				# standard element
				$contents = $this->process_element($this->pageid);
			}
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 5'); $timer->start();
			
			# handle failures
			if ($contents === false) {
				$this->pageid = $this->page_404;
				$contents = $this->_content('404');
				$this->mech_override = false;
			}
			
			$this->parse_template_element($stream, $contents);
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			$this->_debug('<strong>CMS TIMER:</strong> Starting BODY 6'); $timer->start();
			
			# insert the output into the document body element
			if ( ($this->mech_override) || ($this->running_mode == 'active') ) {
				$this->_debug('override or active');
				$this->html->body->restore($stream);
				$this->html->body->set_id('body');
			} else {
				switch ($this->content_type) {
					case 'text':
						echo $stream;
					default:
						$this->outs->_send($stream);
						break;
				}
			}
			
			$timer->stop(); $this->_debug('<strong>CMS TIMER:</strong> Total Time: ' . $timer->retrieve()); $timer->reset();
			
			return $this->_return(true);
		}
		
		public function postoutput()
		/* set message/post html output code
		 *
		 */
		{
			# handle any queued user notifications
			$this->output_message();
		}
		
		public function unload()
		/* send the data to output
		 *
		 */
		{
			$this->_debug_start('running mode: ' . $this->running_mode);
			
			# send the data
			if (($this->running_mode == 'active')&&(!$this->mech_override)) {
				# enable live output
				$this->outs->_toggle(true);
				# send the compiled web page
				$this->outs->_send("$this->html");
			}
			
			return $this->_return(true);
		}
		
		public function write_htaccess()
		/* write an htaccess file to use apache's mod_rewrite
		 *
		 */
		{
			$this->_debug('writing htaccess');
			# update our content_request_code
			$file = str_replace('##_content_request_code_##', $this->content_request_code, buffer(dirname(__FILE__) . '/content/htaccess.php'));
			
			$handle = @fopen($this->_tx->get->site_root . '.htaccess', 'w');
			if ($handle === false) return false;
			$result = fwrite($handle, $file, strlen($file));
			@fclose($handle);
			
			return $result;
		}
		
		public function xml_create_element($type = 'thread')
		/* create a new element of the specified type
		 *
		 */
		{
			# validate input
			if (! array_key_exists('name', $_REQUEST)) return xml_error('A name is required');
			
			# prepare the xml_3 object that we will return
			$this->_tx->_preRegister('xml_3', array('_tag'=>'cms_element'));
			
			# try to create the element
			$id = $this->create_element(array('type'=>$type,'load_from_request'=>true));
			if ($id === false) return xml_error('An error occurred attempting to create the element');
			
			# get the insert id and return to output
			$this->_tx->xml_3->id = $id;
			$this->_tx->xml_3->type = $type;
			$this->_tx->xml_3->content = '';
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_create_path()
		/* create a new navigation path
		 *
		 */
		{
			# get input variables
			$path = @$_REQUEST['path'];
			$element_id = @$_REQUEST['element_id'];
			$menu_setting = @$_REQUEST['menu_setting'];
			$legacy_setting = @$_REQUEST['path_is_outdated'];
			$nav_level = 1;
			
			# validate input
			if (is_null($path) || (strlen($path) == 0)) return xml_error('A path is required');
			if (is_null($element_id) || (strlen($element_id) == 0)) return xml_error('An element id is required');
			if (is_null($menu_setting) || (strtolower($menu_setting) != 'true')) $menu_setting = 'false';
			if (is_null($legacy_setting) || (strtolower($legacy_setting) != 'true')) $legacy_setting = 'false';
			
			# verify the element_id exists and load type
			$ele_meta = $this->_tx->db->query('cms_element', array('id'), array($element_id), array('type'));
			if (!db_qcheck($ele_meta)) return xml_error('The specified element does not exist');
			
			if (! $this->create_path(array('path'=>$path,'element_id'=>$element_id,'menu_setting'=>$menu_setting,'path_is_outdated'=>$legacy_setting))) {
				return xml_error('Error creating path');
			}
			
			return xml_response('success');
		}
		
		public function xml_delete_element($id)
		/* deletes an element
		 *
		 */
		{
			# check that id *appears* valid
			if (strlen((string)$id) == 0) return xml_error('Error: no id specified');
			
			# try to protect against the most basic error
			if (strpos((string)$id, '*') !== false) return xml_error('Error: invalid id provided');
			
			# get this element first
			$ele = $this->_tx->db->query('cms_element', array('id'), array((string)$id), array('shared'));
			if (! db_qcheck($ele)) return xml_error('Error: invalid id provided [' . $id . ']');
			if ($ele[0]['shared']) return xml_error('Error: can not delete a shared element');
			
			# execute the query
			if (! $this->delete_element(array('id'=>$id))) return xml_error('Error deleting element');
			return xml_response();
		}
		
		public function xml_delete_path()
		/* deletes a path (alias)
		 *
		 * gets the id from the arg_string variable
		 *
		 */
		{
			# get the id
			$id = (string)$this->arg_string;
			# check that id *appears* valid
			if (strlen($id) == 0) return xml_error('Error: no path specified');
			
			# sql injection protection
			$id = $this->_tx->db->escape($id);
			if (strpos($id, '*') !== false) return xml_error('Error: invalid path provided');
			
			# get this path first
			$p = $this->_tx->db->query('cms_navigation', array('id'), array($id), array('path'));
			
			if (! db_qcheck($p)) return xml_error('Error: invalid path provided [' . $id . ']');
			
			# execute the query
			if (! $this->_tx->db->delete('cms_navigation', array('id'), array($id))) return xml_error('Error deleting path');
			
			return xml_response(htmlentities($id));
		}
		
		public function xml_get_element_content($id = '', $type = '')
		/* gets content for threads and circuits
		 *
		 */
		{
			$this->_debug('FUNCTION <strong>xml_get_element_content</strong>');
			$id = (string)$id;
			if (strlen($id) == 0) { return xml_error('Error: no id specified.'); }
			
			$search = array('id'=>$id);
			if (strlen($type) > 0) $search['type'] = $type;
			
			$id = $this->_tx->db->escape($id);
			$type = $this->_tx->db->escape($type);
			$result = $this->_tx->db->query(
					'cms_element',
					array_keys($search),
					array_values($search),
					array('id', 'type', 'content'));
			
			if (! db_qcheck($result, true)) { return xml_error('Error: query returned an error.'); }
			
			/*
			<element>
				<id></id>
				<type></type>
				<content></content>
			</element>
			*/
			
			$this->_tx->_preRegister('xml_3', array('_tag'=>'cms_element'));
			
			foreach ($result[0] as $k=>$v) { $this->_tx->xml_3->$k = $v; }
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_get_element_info($id = '001', $type = 'circuit')
		/* 
		 * gets any element information except content
		 * get data from database, make sure data is valid, store the info in some sort of object, return it
		 */
		{
			$id = (string)$id;
			if (strlen($id) == 0) { return xml_error('Error: no id specified.'); }
			$fields = $this->field_list('cms_element');
			
			$id = $this->_tx->db->escape($id);
			$type = $this->_tx->db->escape($type);
			$result = $this->_tx->db->query('cms_element',array('id', 'type'),array($id, $type),$fields);
			if (! db_qcheck($result, true)) { return xml_error('Error: query returned an error.'); }
			
			$this->_tx->_preRegister('xml_3', array('_tag'=>'cms_element'));
			foreach ($result[0] as $k=>$v) { $this->_tx->xml_3->$k = htmlentities(stripslashes($v)); }
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_elements($list = false, $filter = false)
		/* output all registered elements as xml
		 *
		 * a provided comma seperated list can limit which element
		 *  types are returned
		 *
		 * if a list is not provided all element types
		 *  will be returned
		 *
		 * optional name filter to match element names
		 *
		 */
		{
			$this->_debug('xml_list_elements');
			
			if ($list == false) {
				$type = array('alias', 'circuit', 'filament', 'file', 'fuse', 'link', 'thread');
			} else {
				$type = explode(',', $list);
			}
			
			# pre-register our primary xml tag with the transmission
			$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
			
			# compile the list of elements
			# looking for threads, circuits, fuses, filaments, aliases, links, and files
			
			if ($filter) {
				$search_keys = array('name');
				$search_values = array('%' . $filter . '%');
			} else {
				$search_keys = '';
				$search_values = '';
			}
			
		/*
			<cms_element>
				<element name="some name" id="5" type="filament">
					<extended_permissions>0</extended_permissions>
					<file_info mime_type="jpeg" name="something.jpg" size="40k" />
					<last_modified by="strucke.1">2010-02-22 19:04:00</last_modified>
					<locked>0</locked>
					<in_menu>1</in_menu>
					<permissions>
						<delete>1</delete>
						<edit_content>1</edit_content>
						<edit_info>1</edit_info>
						<lock>1</lock>
						<set_permissions>1</set_permissions>
					</permissions>
				</element>
			</cms_element>
		*/
			
			# load elements
			for ($i=0;$i<count($type);$i++){
				switch($type[$i]){
					case 'alias':
						/*
						$fields = array('path', 'element_id', 'menu_visible', 'nav_level');
						$data = $this->_tx->db->query('cms_navigation', $search_keys, $search_values, $fields, true, array('path'));
						if ($data === false) $data = array();
						foreach($data as $record) {
							$c =& $this->_tx->xml_3->_cc('element')->_sas(array(
								'name'=>htmlentities($record['path']),
								'id'=>htmlentities($this->encode_foreign_reference('alias', $record['path'])),
								'type'=>'alias'
								));
							$c->_cc('extended_permissions', '0');
							$c->_cc('last_modified')->_sa('by', '');
							$c->_cc('locked', '0');
							$c->_cc('in_menu', b2s($record['menu_visible']));
							$c->_cc('permissions');
						}
						*/
						break;
					case 'circuit':
						$fields = array('id', 'name', 'shared', 'type', 'updated_by', 'modified', 'locked');
						if ($filter) {
							$data = $this->_tx->db->query('cms_element', array('type', $search_keys[0]), array('circuit', $search_values[0]), $fields, true, array('name'));
						} else {
							$data = $this->_tx->db->query('cms_element', array('type'), array('circuit'), $fields, true, array('name'));
						}
						if ($data === false) $data = array();
						# load data into arrays
						for ($j=0;$j<count($data);$j++) {
							$c =& $this->_tx->xml_3->_cc('element')->_sas(array(
								'name'=>htmlentities(stripslashes($data[$j]['name'])),
								'id'=>htmlentities($data[$j]['id']),
								'type'=>'circuit'
								));
							$c->extended_permissions = 0;
							$c->_cc('last_modified', $data[$j]['modified'])->_sa('by', $data[$j]['updated_by']);
							$c->locked = $data[$j]['locked'];
							$c->in_menu = 0;
							$c->permissions->delete = 1; // static until permissions are implemented
							$c->permissions->edit_content = 1;
							$c->permissions->edit_info = 1;
							$c->permissions->lock = 1;
							$c->permissions->set_permissions = 1;
						}
						break;
					case 'filament':
						$filaments = $this->_tx->_getFilaments();
						if (! is_array($filaments)) $filaments = array();
						# process filaments
						for ($j=0;$j<count($filaments['keys']);$j++) {
							if ($filter) { if (stripos($filaments['keys'][$j], $filter) === false) { continue; } }
							$tmp = new xml_object($this->_tx, array('_tag'=>'element'));
							$tmp->_setAttribute('name', $filaments['keys'][$j]);
							$tmp->_setAttribute('id', htmlentities($this->encode_foreign_reference($filaments['values'][$j], $filaments['keys'][$j])));
							$tmp->_setAttribute('type', 'filament');
							$this->_tx->xml_3->_addChild($tmp);
						}
						break;
					case 'file':
						$data = $this->_tx->file->list_files();
						if (! is_array($data)) $data = array();
						for ($j=0;$j<count($data);$j++) {
							if ($filter) { if (stripos($data[$j]['name'], $filter) === false) { continue; } }
							$c =& $this->_tx->xml_3->_cc('element')->_sas(array(
								'name'=>htmlentities($data[$j]['name']),
								'id'=>htmlentities($data[$j]['id']),
								'type'=>'file'
								));
							$c->extended_permissions = 0;
							$c->_cc('last_modified', $data[$j]['updated'])->_sa('by', '');
							$c->locked = 0;
							$c->in_menu = 0;
							$c->permissions->delete = 1; // static until permissions are implemented
							$c->permissions->edit_content = 1;
							$c->permissions->edit_info = 1;
							$c->permissions->lock = 1;
							$c->permissions->set_permissions = 1;
						}
						break;
					case 'fuse':
						$fields = array('id', 'name', 'shared', 'type', 'updated_by', 'modified', 'locked');
						if ($filter) {
							$data = $this->_tx->db->query('cms_element', array('type', $search_keys[0]), array('fuse', $search_values[0]), $fields, true, array('name'));
						} else {
							$data = $this->_tx->db->query('cms_element', array('type'), array('fuse'), $fields, true, array('name'));
						}
						if ($data === false) $data = array();
						# load data into arrays
						for ($j=0;$j<count($data);$j++) {
							$c =& $this->_tx->xml_3->_cc('element')->_sas(array(
								'name'=>htmlentities(stripslashes($data[$j]['name'])),
								'id'=>htmlentities($data[$j]['id']),
								'type'=>'fuse'
								));
							$c->extended_permissions = 0;
							$c->_cc('last_modified')->_sa('by', $data[$j]['updated_by'])->_setValue($data[$j]['modified']);
							$c->locked = $data[$j]['locked'];
							$c->in_menu = 0;
							$c->permissions->delete = 1; // static until permissions are implemented
							$c->permissions->edit_content = 1;
							$c->permissions->edit_info = 1;
							$c->permissions->lock = 1;
							$c->permissions->set_permissions = 1;
						}
						break;
					case 'link':
						$fields = array('id', 'name', 'shared', 'type', 'updated_by', 'modified', 'locked');
						if ($filter) {
							$data = $this->_tx->db->query('cms_element', array('type', $search_keys[0]), array('link', $search_values[0]), $fields, true, array('name'));
						} else {
							$data = $this->_tx->db->query('cms_element', array('type'), array('link'), $fields, true, array('name'));
						}
						if ($data === false) $data = array();
						# load data into arrays
						for ($j=0;$j<count($data);$j++) {
							$c =& $this->_tx->xml_3->_cc('element')->_sas(array(
								'name'=>htmlentities(stripslashes($data[$j]['name'])),
								'id'=>htmlentities($data[$j]['id']),
								'type'=>'link'
								));
							$c->extended_permissions = 0;
							$c->_cc('last_modified')->_sa('by', $data[$j]['updated_by'])->_setValue($data[$j]['modified']);
							$c->locked = $data[$j]['locked'];
							$c->in_menu = 0;
							$c->permissions->delete = 1; // static until permissions are implemented
							$c->permissions->edit_content = 1;
							$c->permissions->edit_info = 1;
							$c->permissions->lock = 1;
							$c->permissions->set_permissions = 1;
						}
						break;
					case 'thread':
						$fields = array('id', 'name', 'shared', 'type', 'updated_by', 'modified', 'locked');
						if ($filter) {
							$data = $this->_tx->db->query('cms_element', array('type', $search_keys[0]), array('thread', $search_values[0]), $fields, true, array('name'));
						} else {
							$data = $this->_tx->db->query('cms_element', array('type'), array('thread'), $fields, true, array('name'));
						}
						if ($data === false) $data = array();
						# load data into arrays
						for ($j=0;$j<count($data);$j++) {
							$c =& $this->_tx->xml_3->_cc('element')->_sas(array(
								'name'=>htmlentities(stripslashes($data[$j]['name'])),
								'id'=>htmlentities($data[$j]['id']),
								'type'=>'thread'
								));
							$c->extended_permissions = 0;
							$c->_cc('last_modified')->_sa('by', $data[$j]['updated_by'])->_setValue($data[$j]['modified']);
							$c->locked = $data[$j]['locked'];
							$c->in_menu = 0;
							$c->permissions->delete = 1; // static until permissions are implemented
							$c->permissions->edit_content = 1;
							$c->permissions->edit_info = 1;
							$c->permissions->lock = 1;
							$c->permissions->set_permissions = 1;
						}
						break;
				}
			}
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_elements_by_template($template_id = null)
		/* list content elements affiliated with the specified template
		 *
		 */
		{
			# input validation
			if ( is_null($template_id) || (strlen($template_id) == 0)) return xml_error('Invalid ID Provided');
			
			$this->_tx->_preRegister('xml_3', array('_tag'=>'response'));
			
			# compile the list of elements
			# looking for threads, circuits, fuses, filaments, aliases, links, and files
			
		/*
			<cms_element>
				<element name="some name" id="5" type="filament">
					<extended_permissions>0</extended_permissions>
					<file_info mime_type="jpeg" name="something.jpg" size="40k" />
					<last_modified by="strucke.1">2010-02-22 19:04:00</last_modified>
					<locked>0</locked>
					<in_menu>1</in_menu>
					<permissions>
						<delete>1</delete>
						<edit_content>1</edit_content>
						<edit_info>1</edit_info>
						<lock>1</lock>
						<set_permissions>1</set_permissions>
					</permissions>
				</element>
			</cms_element>
		*/
		
			$aliases = array(); // from alias table -- alias table no longer exists!! nav table for this now?
			
			# get fuses, filaments, and files
			$filaments = $this->_tx->_getFilaments();
			$fuses = $this->_tx->_getFuses();
			$files = $this->_tx->file->list_files();
			
			$elementFields = '`id`,`name`,`shared`,`type`,`updated_by`,`modified`,`locked`,`description`,`enabled`';
			
			$tcl = $this->_tx->db->queryFull("SELECT $elementFields FROM `cms_element` WHERE `template_id`='$template_id'");
			
			if (is_array($tcl)) {
				# load data into arrays
				for ($i=0;$i<count($tcl);$i++) {
					$tmp = new xml_object($this->_tx, array('_tag'=>'element'));
					$tmp->_setAttribute('name', $tcl[$i]['name']);
					$tmp->_setAttribute('id', $tcl[$i]['id']);
					$tmp->_setAttribute('type', $tcl[$i]['type']);
					$tmp->extended_permissions = 0;
					$tmp->last_modified = $tcl[$i]['modified'];
					$tmp->last_modified->_setAttribute('by', $tcl[$i]['updated_by']);
					$tmp->locked = $tcl[$i]['locked'];
					$tmp->in_menu = 0; // static until the menu is implemented
					$tmp->description = $tcl[$i]['description'];
					$tmp->enabled = $tcl[$i]['enabled'];
					$tmp->permissions->delete = 1; // static until permissions are implemented
					$tmp->permissions->edit_content = 1;
					$tmp->permissions->edit_info = 1;
					$tmp->permissions->lock = 1;
					$tmp->permissions->set_permissions = 1;
					$this->_tx->xml_3->_addChild($tmp);
				}
			} else {
				echo "<xml/>"; return true;
			}
			
			echo $this->_tx->xml_3; return true;
		}
		
		public function xml_list_navigation($menu_only = false, $include_xml = false)
		/* outputs all registered navigation items as xml
		 *
		 * if menu_only is true; only return items that are assigned to the menu system
		 *
		 */
		{
			# get navigation table fields
			$fields = $this->field_list('cms_navigation');
			# since we'll be doing a join, prepend the table name to each
			for($i=0;$i<count($fields);$i++) { $fields[$i] = 'cms_navigation,' . $fields[$i]; }
			$fields[] = 'cms_element,name';
			$fields[] = 'cms_element,link_title';
			$searchKeys = array(); $searchValues = array();
			# build the search keys
			if ($menu_only) {
				$searchKeys = array('cms_navigation,menu_visible');
				$searchValues = array(true);
				$sort = array('cms_navigation,parent_id','cms_navigation,parent_order');
			} else {
				$sort = array('cms_navigation,path', 'cms_element,name');
			}
			if (!$include_xml) {
				$searchKeys[] = 'cms_element,content_type';
				$searchValues[] = '!= \'xml\'';
			}
			if (count($searchKeys) == 0) { $searchKeys = ''; $searchValues = ''; }
			# get data from both tables
			$data = $this->_tx->db->query(array('cms_navigation'=>'element_id', 'cms_element'=>'id'), $searchKeys, $searchValues, $fields, true, $sort);
			if ($data === false) $data = array();
			
		/* <cms_navigation>
		      <record element="id" menu="true" level="0">path</record>
		   </cms_navigation> */
		  
		  $this->_tx->_preRegister('new_xml', array('_tag'=>'response'));
		  $x = $this->_tx->new_xml;
		  
		  foreach($data as $record) {
		  	$x->_cc('record')->_setValue($record['path'])->_sas(array(
		  		'id'=>$record['id'],
		  		'element'=>$record['element_id'],
		  		'template'=>$record['template_id'],
		  		'menu'=>b2s($record['menu_visible']),
		  		'level'=>$record['nav_level'],
		  		'parent_id'=>$record['parent_id'],
		  		'parent_order'=>$record['parent_order'],
		  		'name'=>htmlentities($record['name']),
		  		'link_title'=>htmlentities($record['link_title'])
		  		));
		  }
		  
		  echo $x;
		  
		  return true;
		}
		
		public function xml_module_disable($id = null)
		/* disable the specified module via an xml request
		 *
		 */
		{
			# cast id as a string
			$id = (string)$id;
			# check that id *appears* valid
			if (strlen($id) == 0) return xml_error('No id provided');
			# make sure the module exists
			if (! db_qcheck_exec(array('table'=>'modules', 'search_keys'=>array('id'), 'search_values'=>array($id)))) return xml_error('Module does not exist');
			
			# enable the module
			if ($this->_tx->db->update('modules', array('id'), array($id), array('enabled'), array(false))) {
				return xml_response();
			} else {
				return xml_error('Unable to disable module');
			}
		}
		
		public function xml_module_enable($id = null)
		/* enable the specified module via an xml request
		 *
		 */
		{
			# check that id *appears* valid
			if (strlen($id) == 0) return xml_error('No id provided');
			# make sure the module exists
			if (! db_qcheck_exec(array('table'=>'modules', 'search_keys'=>array('id'), 'search_values'=>array($id)))) return xml_error('Module does not exist');
			
			# enable the module
			if ($this->_tx->db->update('modules', array('id'), array($id), array('enabled'), array(true))) {
				return xml_response();
			} else {
				return xml_error('Unable to enable module');
			}
		}
		
		public function xml_module_drop_schema($id = null)
		/* drop the attached schema for the specified module
		 *
		 * this function should only be enabled in development mode
		 *
		 */
		{
			# make sure the system is in development mode
			if (! $this->dev_mode) return xml_error('This function is only available in development mode');
			
			# check that the id *appears* valid
			if (is_null($id) || (strlen($id) == 0)) return xml_error('No id provided');
			
			# attempt to retrieve this extension's data
			$r = $this->_tx->db->query('modules', array('id'), array($id), array('name', 'path'));
			
			# make sure the module exists
			if (! db_qcheck($r)) return xml_error('Module does not exist');
			
			# attempt to load the module's xml file
			$xml_path = $this->_tx->get->engine_root . dirname($r[0]['path']) . '/extension.xml';
			if (! $this->_tx->xml_document->_load($xml_path)) return xml_error('Error loading schema');
			
			# retrieve this extension's schema
			$schema = $this->_tx->xml_document->schema->_getChildren();
			
			# drop the tables
			foreach($schema as $table) {
				$this->_tx->db->drop($table->_getTag());
			}
			
			# update the schema version in the database
			$this->_tx->db->update('modules', array('id'), array($id), array('schema_version'), array('0'));
			
			return xml_response();
		}
		
		public function xml_module_refresh($id = null)
		/* set the refresh flag to TRUE for the specified module
		 *
		 */
		{
			# check that id *appears* valid
			if (strlen($id) == 0) return xml_error('No id provided');
			# make sure the module exists
			if (! db_qcheck_exec(array('table'=>'modules', 'search_keys'=>array('id'), 'search_values'=>array($id)))) return xml_error('Module does not exist');
			
			# refresh the module
			if ($this->_tx->db->update('modules', array('id'), array($id), array('refresh'), array(true))) {
				return xml_response();
			} else {
				return xml_error('Unable to update refresh flag for the requested module');
			}
		}
		
		public function xml_test()
		{
			$this->_debug('xml_test');
			
			$this->_tx->_preRegister('xml_3', array('_tag'=>'xml_test'));
			$this->_tx->xml_3->field1->_setAttribute('test', 'true');
			$this->_tx->xml_3->field1 = 'Test A. Tester';
			$this->_tx->xml_3->password = 'gutless';
			
			echo $this->_tx->xml_3;
		}
		
		public function xml_toggle_path_menu($nav_id)
		/* toggles the menu_enabled flag for the specified path (using the nav table id)
		 *
		 * returns the nav item
		 *
		 */
		{
			# check that the path *appears* valid
			if (strlen($nav_id) == 0) return xml_error('No id provided');
			
			# try to load this path
			$r = $this->db->query('cms_navigation', array('id'), array($nav_id), $this->field_list('cms_navigation'), true);
			if (!db_qcheck($r)) return xml_error('No result');
			
			# toggle the menu setting
			if ($r[0]['menu_visible']) {
				$this->db->update('cms_navigation', array('id'), array($nav_id), array('menu_visible', 'nav_level', 'parent_id'), array(false, 0, null));
			} else {
				# if making the item menu visable, also set the parent_id and parent_order
				if (strlen($r[0]['parent_id']) > 0) {
					$parent_id = $r[0]['parent_id'];
					# get the parent's nav level
					$s = $this->db->query('cms_navigation', array('id'), array($parent_id), array('nav_level'));
					$nav_level = $s[0]['nav_level'] + 1;
				} else {
					# try to guess the parent id from the path
					/* ... */
					$parent_id = null;
					$nav_level = 0;
				}
				# place this item last in the list
				$s = $this->db->query('cms_navigation', array('parent_id'), array($parent_id), array('parent_order'), true);
				$max = 0;
				for ($i=0;$i<count($s);$i++) { if ($s[$i]['parent_order'] >= $max) { $max = $s[$i]['parent_order'] + 1; }}
				$this->db->update('cms_navigation', array('id'), array($nav_id), array('menu_visible', 'parent_id', 'parent_order', 'nav_level'), array(true, $parent_id, $max, $nav_level));
			}
			
			# get the new settings to return
			$r = $this->db->query('cms_navigation', array('id'), array($nav_id), $this->field_list('cms_navigation'), true);
			
			# build the xml output
			$this->_tx->_preRegister('new_xml', array('_tag'=>'cms_navigation'));
		  $x = $this->_tx->new_xml;
		  $x->_cc('record')->_setValue($r[0]['path'])->_sas(array(
		  	'id'=>$r[0]['id'],
	  		'element'=>$r[0]['element_id'],
	  		'menu'=>b2s($r[0]['menu_visible']),
	  		'level'=>$r[0]['nav_level'],
	  		'parent_id'=>$r[0]['parent_id']
	  		));
		  
		  echo $x;
		  
		  return true;
		}
		
		public function xml_update_element_info($id, $type)
		/* 
		 * saves any element information except content
		 * get data entered in forms, organize/separate it somehow into individual parts, update the elements table
		 */
		{
			# cast id as a string
			$id = (string)$id;
			# check that id *appears* valid
			if (strlen($id) == 0) return xml_error('No id provided');
			
			# set our field list
			$fieldlist = $this->field_list('cms_element');
			
			# sql escape id and type
			$id = $this->_tx->db->escape($id);
			$type = $this->_tx->db->escape($type);
			
			# prepare our update array
			$updateArr = array();
			
			# loop through posted values
			for($i=0;$i<count($fieldlist);$i++){
				if (array_key_exists($fieldlist[$i], $_REQUEST)) {
					$updateArr[$fieldlist[$i]] = urldecode($_REQUEST[$fieldlist[$i]]);
				}
			}
			
			# update the cache to expire
	  	$this->cache_last_modified = $id;
			
			$result = $this->_tx->db->update(
					'cms_element',
					array('id', 'type'),
					array($id, $type),
					array_keys($updateArr),
					array_values($updateArr));
			
			if ($result) {
				$q = $this->_tx->db->query('cms_element', array('id', 'type'), array($id, $type), array('content'));
				$content = $q[0]['content'];
				echo "<cms_element>\r\n\t<id>$id</id>\r\n\t<type>$type</type>\r\n\t<content>$content</content>\r\n</cms_element>";
			} else {
				echo "<error>unable to save changes</error>";
			}
			
			return true;
		}
		
		public function xml_update_element_content($id = '')
		/* saves content for threads and circuits
		 *
		 */
		{
			# cast id as a string
			$id = (string)$id;
			# check that id *appears* valid
			if (strlen($id) == 0) return xml_error('No id provided: ' . $_SERVER['QUERY_STRING']);
			
			# sql escape id and content
			$id = $this->_tx->db->escape($id);
			$content = urldecode($_POST['content']);
			
			# validate content
			if (get_magic_quotes_gpc()) { $content = stripslashes($content); }
			
			# update the cache to expire
	  	$this->cache_last_modified = $id;
	  	
			$result = $this->_tx->db->update(
					'cms_element',
					array('id'),
					array($id),
					array('content'),
					array($content));
			
			if ($result) {
				echo "<xml>success</xml>";
			} else {
				echo "<error>unable to save changes</error>";
			}
			
			return true;
		}
		
		public function xml_update_path_parent($id = false, $parent_id = false, $after = '')
		/* changes the cms_navigation parent_id for the provided path
		 *
		 * path and parent_id are required, after is optional (defaults to first)
		 *
		 * a parent id of '0' will be ignored to allow specification of the top level order (with no parent)
		 *
		 */
		{
			# check that the paths *appears* valid
			if (strlen($id) == 0) return xml_error('No id provided');
			if ($id == $parent_id) return xml_error('You can not make a path a descendent of itself');
			if ((strlen($parent_id) == 0)||($parent_id=='0')) { $parent_id = null; }
			if (strlen($after) == 0) { $after = false; }
			
			# try to load this path and validate the result
			$ele = $this->db->query('cms_navigation', array('id'), array($id), $this->field_list('cms_navigation'), true);
			if (! db_qcheck($ele)) return xml_error('Path not found');
			
			# try to load the parent and validate the result
			if (! is_null($parent_id)) {
				$par = $this->db->query('cms_navigation', array('id'), array($parent_id), $this->field_list('cms_navigation'), true);
				if (! db_qcheck($par)) return xml_error('Parent not found');
			} else {
				$parent_id = null;
			}
			
			# try to load all children of the parent
			$children = $this->db->query(
				'cms_navigation',
				array('parent_id'),
				array($parent_id),
				$this->field_list('cms_navigation'),
				true,
				array('parent_order'));
			
			# preset new order array; elements will be stored simply as array(path_1, path_2, etc...)
			$new_children = array();
			
			# process each child element
			if ($children) {
				for ($i=0;$i<count($children);$i++) {
					# ignore the child if it is the element we are changing
					if ($children[$i]['id'] != $ele[0]['id']) {
						# add this child to the new order array
						$new_children[] = $children[$i]['id'];
						# only add the changing element if this is the after element
						if (($after) && ($children[$i]['id'] == $after)) $new_children[] = $ele[0]['id'];
					}
				}
				# if there was no after, add this element to the beginning of the list
				if (! $after) array_unshift($new_children, $ele[0]['id']);
			} else {
				# special case where there this is the only child for this parent
				$new_children[] = $ele[0]['id'];
			}
			
			# set the new nav_level
			if (! is_null($parent_id)) { $level = $par[0]['nav_level']+1; } else { $level = 0; }
			
			# save the new order
			for($i=0;$i<count($new_children);$i++) {
				$this->db->update(
					'cms_navigation',
					array('id'),
					array($new_children[$i]),
					array('parent_id', 'parent_order', 'nav_level'),
					array($parent_id, $i+1, $level));
			}
			
			# success
			return xml_response();
		}
		
		public function xml_update_site_settings()
		/* saves site settings
		 *
		 */
		{
			# set options array
			$settings = array('content_request_code'=>'content_code', 'default_home'=>'home',
				'download_request_code'=>'download_code', 'ps'=>'ps', 'template_id'=>'template',
				'dev_mode'=>'dev_mode', 'site_title'=>'title', 'administrator_menu'=>'admin_menu');
			# validate post data
			$data = array();
			foreach ($settings as $k=>$v) {
				if (array_key_exists($v, $_POST)) $data[$k] = stripslashes(urldecode($_POST[$v]));
			}
			if (count($data) == 0) return xml_error('No fields were posted');
			# validate save data
			if (array_key_exists('content_request_code', $data) && $this->link_rewrite) {
				$tmp = $this->content_request_code;
				$this->content_request_code = $data['content_request_code'];
				if (! $this->write_htaccess()) {
					# htaccess update failed -- stop here
					$this->content_request_code = $tmp;
					unset($data['content_request_code']);
				}
			}
			if (array_key_exists('administrator_menu', $data)) {
				if ($data['administrator_menu'] == true) {
					$data['administrator_menu'] = 1;
				} else {
					$data['administrator_menu'] = 0;
				}
			}
			# save to database
			foreach ($data as $opt=>$val) {
				if (! $this->db->update('cms_settings', array('option'), array($opt), array('value'), array($val))) {
					if (! $this->db->insert('cms_settings', array('option', 'value'), array($opt, $val))) {
						return xml_error("Error updating field '$opt'");
					}
				}
			}
			return xml_response();
		}
		
	}
?>