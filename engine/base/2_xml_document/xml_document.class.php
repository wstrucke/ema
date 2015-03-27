<?php
 /* XML Document Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.2, Sep-20-2010
  * William Strucke, wstrucke@gmail.com
  *
  * to do:
  *  - allow client to set encoding
  *  - fix restore function so the type="html" attribute is not necessary
  *
  */
	
	class xml_document extends xml_object
	{
		protected $_connector;
		protected $_filename;
		protected $_newfile;
		protected $_uri;
		
		public $_name = 'XML Document Extension';
		public $_version = '1.0.2';
		protected $_debug_prefix = 'xml_document';
		protected $_no_debug = true;		// enables debugging for this object
		
		public $_file_loaded = false;
		
		protected function _construct()
		/* initialize xml document class
		 *
		 */
		{
			$this->_newfile = false;
			$this->_uri = false;
			
			# a name is required for the xml_object parent class
			if ($this->_tag == '') $this->_tag = 'NEW_xml_document_OBJECT';
			
			return parent::_construct();
		}
					
		public function _create($file)
		/* create a new xml file 
		 *
		 *	nothing is written until save() is called
		 *
		 */
		{
			$this->_debug_start("file=$file");
			
			# validate file name
			$test = stripslashes($file);
			if ($test != $file) return $this->_return(false, 'error: invalid file name');
			
			# make sure file does not exist already
			if (file_exists($file)) return $this->_return(false, 'error: can not overwrite existing file with this function');
			
			$this->_clear();
			
			$this->_tag = 'xml';
			$this->_loaded = true;
			$this->_newfile = true;
			$this->_filename = $file;
			
			return $this->_return(true);
		}
		
		protected function _clear()
		/* reset this object
		 *
		 */
		{
			$this->_attributes = array();
			$this->_children = array();
			$this->_loaded = false;
			$this->_order = array();
			$this->_parent = null;
			$this->_rorder = array();
			$this->_tag = 'NEW_xml_document_OBJECT';
			$this->_uri = false;
			$this->_value = '';
			$this->_whoami = null;
			$this->_filename = '';
			$this->_newfile = false;
			$this->_file_loaded = false;
			
			if (is_resource($this->_connector)) fclose($this->_connector);
			
			return true;
		}
		
		protected function _curl($url, $post = false, $postArgs = null)
		/* retrieves a file from a url
		 *
		 */
		{
			# create a new cURL resource
			$c = curl_init($url);
			curl_setopt($c, CURLOPT_HEADER, 0);
			#curl_setopt($c, CURLOPT_VERBOSE, 0);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
			if ( $post && (! is_null($postArgs)) )
			{
				#curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($c, CURLOPT_POST, true);
				curl_setopt($c, CURLOPT_POSTFIELDS, $postArgs);
			}
			ob_start();
			curl_exec($c);
			curl_close($c);
			$r = ob_get_contents();
			ob_end_clean();
			return $r;
		}
		
		protected function _extract_next_tag(&$str)
		/* locates and removes the next complete html tag found in $str
		 *
		 * returns an array:
		 *	$arr['tag'] = name of tag
		 *	$arr['attributes'] = array of attributes=>values
		 *	$arr['inner_html'] = inner html for the tag
		 *
		 * alters data so it is returned without the tag
		 *
		 * Variable Reference:
		 *	$str => the source string
		 *	$a => the return array
		 *	$tag => the tag we are working with
		 *	$found_close_tag => true when a closing tag has been located
		 *	$o_s_pos => open tag start position
		 *	$o_e_pos => open tag end position
		 *	$e_s_pos => end tag start position
		 *	$e_e_pos => end tag end position
		 *	$inner_twins => running count of twins within the inner html of this element
		 *
		 * Loop Variables:
		 *	$s_pos => space position in loop
		 *	$tmp => temporary var in loop
		 *	$attr => attribute name in loop
		 *	$val => attribute value in loop
		 *	$quote_open => quote tracking in loop
		 *	$attr_open => attribute tracking in loop
		 *	$char => character tracking in loop
		 *	$offset => character offset while searching inner_html in loop
		 *	$l_tmp => temp string, left section
		 *	$r_tmp => temp string, right section
		 *
		 */
		{
			# start debug output
			$this->_debug_start();
			
			# prepare return array
			$a = array('tag'=>'','attributes'=>array(),'inner_html'=>'','comment'=>'','position'=>0);
			
			# sanity check
			if (strlen($str) == 0) return $this->_return($a, 'string length was 0');
			
			$this->_debug('string length: ' . strlen($str));
			
			# preset variables
			$found_close_tag = false;
			$inner_twins = 0;
			$l_tmp = '';
			$r_tmp = '';
			
			# attempt to locate the next tag
			if ( (($o_s_pos = strpos($str, '<')) !== false) && (($o_e_pos = strpos($str, '>')) !== false) && ($o_s_pos < $o_e_pos) )
			{
				# special case for comments
				if (substr($str, $o_s_pos, 4) == '<!--') $o_e_pos = strpos($str, '-->') + 2;
						
				# found something that looks like a tag
				$tag = substr($str, ($o_s_pos + 1), ($o_e_pos - $o_s_pos - 1));
				
				# set the position
				$a['position'] = $o_s_pos;
				
				$this->_debug('found tag: ' . $tag);
				
				#$strLenBefore = strlen($str);
				
				# remove the tag from the source string
				if ( ($o_s_pos > 0) && (strlen($str) > ($o_e_pos + 1)) )
				{
					# take the data before and after the tag
					$l_tmp = substr($str, 0, $o_s_pos);
					$r_tmp = substr($str, ($o_e_pos + 1));
					//$r_tmp = substr($str, ($o_e_pos + 1));
					$str = $l_tmp . $r_tmp;
				} elseif ($o_s_pos > 0) {
					# take the data before the tag
					$str = substr($str, 0, $o_s_pos);
				} elseif (strlen($str) > ($o_e_pos + 1)) {
					# just take the data after the end of the tag
					$str = substr($str, ($o_e_pos + 1));
				} else {
					# the tag is empty without this
					$str = '';
				}
				
				# if this is a comment, do not do anything further
				if ( (strpos($tag, '!') !== 0) && (strpos($tag, '?') !== 0) )
				{						
					# check for a self-closing tag
					if ($this->_remove_trailing_slash($tag)) $found_close_tag = true;
					
					# check for attributes in the tag name
					while ($s_pos = strpos($tag, ' '))
					{
						$this->_debug('found a space in the tag name');
						
						# extract the space and everything after it
						$tmp = str_split(trim(substr($tag, $s_pos)));
						
						# shorten the tag
						$tag = trim(substr($tag, 0, $s_pos));
						
						$this->_debug('after space removal: ' . $tag);
						
						# preset the attribute to nothing
						$attr = '';
						$val = '';
						$quote_open = false;
						$attr_open = true;
						
						# process the attribute, one piece at a time
						while (count($tmp) > 0)
						{
							$char = array_shift($tmp);
							
							if ( ($attr_open) && ($char == '=') )
							{
								$attr_open = false;
							} elseif ( (! $attr_open) && ($quote_open) && ($char == '"') ) {
								$quote_open = false;
							} elseif ( (! $attr_open) && (! $quote_open) && ($char == '"') ) {
								$quote_open = true;
							} elseif ($quote_open) {
								$val .= $char;
							} elseif ($char == ' ') {
								# a space occuring outside of quotes means it's the end of this attribute
								$tag .= ' ' . implode('', $tmp);
								break;
							} elseif ($attr_open) {
								$attr .= $char;
							} else {
								$val .= $char;
							}
							
							# free memory
							unset($char);
						}
						
						# add the attribute
						$a['attributes'][$attr] = $val;
						
						# free memory
						unset($tmp, $attr, $val, $quote_open, $attr_open);
					} // while $s_1_pos
				} else {
					# this is a comment
					$this->_debug('this tag is a comment: ' . $tag);
					$found_close_tag = true;
					$a['comment'] = $tag;
					$a['inner_html'] = $str;
				}
				
				$this->_debug('after attribute extraction: ' . strlen($str));
				
				# prepare variables for end tag search
				$offset = 0;
				$e_s_pos = 0;
				$k = null;
				
				$this->_debug('set offset: ' . $offset);
				
				# locate the end tag for this element and trap any inner html
				while ( (! $found_close_tag) && ($offset < strlen($str)) )
				{
					# extract the tail of the data string to search
					$tmp = substr($str, $offset);
					
					$this->_debug('e_s_pos: ' . strpos($tmp, '<') . '; e_e_pos: ' . 
											($e_s_pos + strpos(substr($tmp, $e_s_pos), '>')) . "; offset: $offset");
					$this->_debug("o_s_pos: $o_s_pos; o_e_pos: $o_e_pos; loop number $k");
					#$this->_debug("tmp (str offset to end): $tmp");
					
					# get the next tag in the excerpt
					if ( 	(($e_s_pos = strpos($tmp, '<')) !== false) && 
								(($e_e_pos = ($e_s_pos + strpos(substr($tmp, $e_s_pos), '>'))) !== false) && 
								($e_s_pos < $e_e_pos) 
							)
					{
						# special case for comments
						if (substr($tmp, $e_s_pos, 4) == '<!--') $e_e_pos = strpos($tmp, '-->') + 2;
						
						# get the complete tag
						$tmp = substr($tmp, ($e_s_pos + 1), ($e_e_pos - $e_s_pos - 1));
						
						$this->_debug("checking next tag: $tmp");
						
						# locate any spaces
						if (($s_pos = strpos($tmp, ' ')) !== false)
						{
							# truncate so tmp is just the tag
							$tmp = substr($tmp, 0, $s_pos);
							$this->_debug("found a space at $s_pos");
						}
						
						$this->_debug("after space removal: $tmp == $tag ?");
						
						# check for twins
						if ($tmp == $tag)
						{
							$this->_debug("found a twin, incrementing");
							$inner_twins++;
						} else {
							# check if this is a closing tag
							if ( ($this->_remove_leading_slash($tmp)) && ($tmp == $tag) )
							{
								# check twins before declaring victory
								if ($inner_twins > 0)
								{
									$this->_debug("found a twin, decrementing");
									$inner_twins--;
								} else {
									$this->_debug("found my close tag: $tag, o_s_pos: $o_s_pos, offset: $offset, e_s_pos: $e_s_pos");
									# found our closing tag!
									$found_close_tag = true;
									# extract the inner html
									$a['inner_html'] = trim(substr($str, $o_s_pos, ($offset + $e_s_pos - $o_s_pos)));
									#$this->_debug("set inner html: " . strlen($a['inner_html']) . ' ' . $a['inner_html']);
									
									# truncate the source data
									if ( ($o_s_pos > 0) && (strlen($str) > ($offset + $e_e_pos + 1)) )
									{
										# take the data before and after the tag
										$str = substr($str, 0, $o_s_pos) . substr($str, ($offset + $e_e_pos + 1));
									} elseif ($o_s_pos > 0) {
										# take the data before the tag
										$str = substr($str, 0, $o_s_pos);
									} elseif (strlen($str) > ($offset + $e_e_pos - $o_s_pos + 1)) {
										# just take the data after the end of the tag
										$str = substr($str, ($offset + $e_e_pos - $o_s_pos + 1));
									} else {
										# the tag is empty without this
										$str = '';
									}
									
									$this->_debug("set str: " . strlen($str) . ' ' . $str);
									# stop looking
									break;
								}
							}
						}
					} else {
						# there are no more tags
						return $this->_return(false, 'there are no more tags, exiting function');
					}
					
					# increment the offset
					$offset += $e_e_pos + 1;
					
					# free memory
					unset($tmp, $e_e_pos);
					
					# reset for next loop
					$e_s_pos = 0;
				}
				
				# check
				if (! $found_close_tag)
				{
					# this tag does not have an end, html validation error
					$tag = '';
				}
				
				# set the tag
				if ($a['comment'] == '') $a['tag'] = $tag; // . $o_s_pos;
				
			} else {
				# did not find a tag
				if (strlen($str) > 0)
				{
					$a['inner_html'] = $str;
					$str = '';
					return $this->_retByRef($a, 'no more tags');
				} else {
					return $this->_return(false, 'did not find a tag');
				}
			}
			
			# free memory
			unset($found_close_tag, $o_s_pos, $o_e_pos, $inner_twins, $s_pos, $offset);
			
			return $this->_retByRef($a);
		}
		
		public function _getXML($line_prefix = '')
		/* return raw xml output for this object and children
		 *
		 */
		{
			$this->_debug_start("prefix=$line_prefix");
			
			if (! $this->_loaded) return $this->_return(false, 'error: no data is loaded to return');
			
			if ($this->_tag == 'NEW_xml_document_OBJECT') return $this->_return(false, 'error: you must assign this object a name (tag).');
			
			$preOutput = parent::_getXML($line_prefix);
			
			return $this->_retByRef($preOutput);
		}
				
		public function _load($file)
		/* read a file
		 *
		 */
		{
			$this->_debug_start("file=$file");
						
			# validate file name
			$test = stripslashes($file);
			if ($test != $file) return $this->_return(false, 'error: invalid file name');
			
			$this->_clear();
			
			if ((strlen($file) > 6)&&(strcasecmp('http://', substr($file, 0, 7)) == 0)) {
				# file is a URI resource
				$this->_uri = true;
			} elseif ((strlen($file) > 7)&&(strcasecmp('https://', substr($file, 0, 8)) == 0)) {
				# file is a URI resource
				$this->_uri = true;
			} elseif ((strlen($file) > 5)&&(strcasecmp('ftp://', substr($file, 0, 6)) == 0)) {
				# file is a URI resource
				$this->_uri = true;
			} else {
				# validate file
				if ( (strlen($file) == 0) || (! file_exists($file)) ) return $this->_return(false, 'error: bad file name or file does not exist');	
			}
			
			$this->_debug('after clear there are ' . count($this->_children) . ' children');
			
			$this->_loaded = true;
			$this->_filename = $file;
			$this->_tag = '';
			
			# read the file
			if ($this->_uri) {
				$data = $this->_curl($file);
			} else {
				$data = file_get_contents($file);
			}
			
			# restore it into this object
			$this->_restore($this, $data);
			
			$this->_file_loaded = true;
			
			return $this->_return(true);
		}
		
		protected function _remove_leading_slash(&$str)
		/* locate and remove any leading '/' in the provided string
		 *
		 * if a slash is found return true, otherwise return false
		 *
		 */
		{
			if (substr($str, 0, 1) == '/')
			{
				if (strlen($str) > 1)
				{
					# remove the leading slash
					$str = trim(substr($str, 1));
				} else {
					$str = '';
				}
				return true;
			}
			return false;
		}
		
		protected function _remove_trailing_slash(&$str)
		/* locate and remove any trailing '/' in the provided string
		 *
		 * if a slash is found return true, otherwise return false
		 *
		 */
		{
			if (substr($str, (strlen($str) - 1)) == '/')
			{
				if (strlen($str) > 1)
				{
					# remove the trailing slash
					$str = trim(substr($str, 0, (strlen($str) - 1)));
				} else {
					$str = '';
				}
				return true;
			}
			return false;
		}
		
		protected function _restore(xml_object &$obj, &$data)
		/* restore a string form of xml into the provided object
		 *
		 * this function will be executed recursively for child objects
		 *
		 */
		{
			# start debug output
			$this->_debug_start();
			
			# make sure to remove whitespace
			$data = str_replace(array("\n","\r","\t","\o","\xOB"), '', $data);
			
			# prepare search variable
			$search = array();
			
			/* DEBUGGING * $i = 0; $dlen = strlen($data); */
						
			# prepare loop variables
			$restore_str = '';
			$child = '';
			$tmp = '';
			
			while ($search = $this->_extract_next_tag($data))
			{
				if ( ($obj->_getTag() == '') && ($search['tag'] != '') )
				{
					# set the tag
					$obj->_setTag($search['tag']);
					# set the attributes
					$this->_debug('adding ' . count($search['attributes']) . ' attributes');
					foreach ($search['attributes'] as $attr=>$val)
					{
						$obj->_setAttribute($attr, $val);
					}
					# set the inner xml
					$this->_restore($obj, $search['inner_html']);
				} elseif ($search['tag'] != '') {
					$this->_debug("sweet child of mine: " . $search['tag']);
					# create the child
					$args = array('_tag'=>$search['tag']);
					$child = new xml_object($this->_tx, $args);
					# set any attributes
					foreach ($search['attributes'] as $attr=>$val)
					{
						$child->_setAttribute($attr, $val);
					}
					# check for html special tag
					if ($child->_getAttribute('type') == 'html')
					{
						# leave well enough alone
						$child->_setValue($search['inner_html'], false);
					} else {
						if ($search['inner_html'] != '')
						{
							$this->_debug('restoring inner_xml');
							# restore the data
							$this->_restore($child, $search['inner_html']);
						}
					}
					# add the child
					$obj->_addChild($child);
				} elseif ($search['comment'] != '') {
					# found a comment
					$this->_debug('skipping comment');
				} elseif ($search['inner_html'] != '') {
					$this->_debug('setting inner xml');
					$obj->_setValue($search['inner_html']);
				} else {
					$this->_debug("not adding tag due to error or comment: " . $search['tag']);
					break;
				}
			}
			# if there is any data left, set it to our value
			#if (strlen($data) > 0) $obj->set_value($data);
			
			$this->_debug('post loop data length: ' . strlen($data));
			$this->_debug('i now have ' . $obj->_countChildren() . ' children;  i am ' . $obj->_getTag());
			#echo 'here i am:' . NL . NL . $this . NL . NL;
			
			return $this->_return(true);
		}
				
		public function _save()
		/* save the loaded file
		 *
		 */
		{
			$this->_debug_start();
			
			if ($this->_newfile && file_exists($this->_filename))
			{
				return $this->_return(false, 'error: file already exists while trying to create a new file.');
			}
			
			if ($this->_tag == 'NEW_xml_document_OBJECT')
			{
				return $this->_return(false, 'error: you must assign this object a name (tag).');
			}
			
			$data = $this->_getXML();
			
			if ($data === false) return $this->_return(false, 'error generating XML from stored data');
			
			$this->_connector = fopen($this->_filename, "w");
			$result = fwrite($this->_connector, $data);
			fclose($this->_connector);
			
			$this->_file_loaded = true;
			
			return $this->_retByRef($result);
		}
		
		public function _unload()
		/* unload the loaded file
		 *
		 */
		{
			$this->_debug_start();
			
			$this->_clear();
			
			return $this->_return(true);
		}

	}
?>