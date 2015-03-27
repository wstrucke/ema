<?php
 /* Cascading Style Sheet Document Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Apr-09-2009/May-30-2009
  * William Strucke, wstrucke@gmail.com
  *
  * This object intentionally knows nothing about CSS inheritance or styles,
  * it simply acts as a logical storage mechanism for styles, comments, and elements
  * as well as providing document access.
  *
  * A future version could include provisions for validation and verification, though
  * that is not the purpose of this object.  Implementing these features would most
  * definitely require a complete remodeling of the internal data access methods.
  *
  * All functions and procedures assume that the CSS is valid coming in and will
  * return the data as it is submitted.
  *
  * Despite the fact that we are using the xml_object as a storage device for CSS
  * elements and styles, utilizing the xml_object->__toString() procedure will 
  * NOT output valid XML.  This is due to the nature of CSS elements and how this
  * device stores them.  Additionally, since we are making some assumptions about
  * the internal functionality of the xml_object, future revisions of that object
  * could potentially disrupt the functionality of this one -- the user is strongly 
  * advised to extensivily test this class with any new version of the global
  * xml_object prior to placing anything in production.
  *
  * to do:
  * - support '!important' property
  * - support inline styles
  * - implement 'merge()' function to merge two css_document objects
  *
  */
	
	define('CSS_LINE_COMMENT_HYPHEN', '-------------------------------------------');
	
	define('CSS_LINE_COMMENT_ASTERISK', '********************************************');
	
	class css_document extends standard_extension
	{
		protected $_comments;					// an array of 0 or more document comments
		protected $_data;							// an array of 0 or more xml objects storing the css data in groups
		protected $_deliminators;			// an array of all valid css deliminators
		protected $_filename;					// the name of the css file saved to disk
		protected $_loaded = false;		// true if a document has been loaded
		protected $_saved = false;		// true if the loaded data matches the saved data
		
		public $_name = 'CSS Object Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'css_object';
		protected $_no_debug = true;
		
		public function __get($name)
		{
			
		}
		
		public function __set($name, $value)
		{
			
		}

		public function __sleep()
		{
			return array('_comments', '_data', '_deliminators', '_filename', '_loaded', '_saved');
		}
		
		public function __wakeup()
		{
		
		}
		
		public function __toString()
		/* returns a W3C valid CSS document from the loaded data
		 *
		 * if there is no loaded data, returns an empty string... or false??
		 *
		 */
		{
			# start output as nothing
			$output = '';
			
			# process each group seperately
			foreach($this->_data as $k=>$v)
			{
				# retrieve all of the comments for the group first
				foreach($this->_comments[$k] as $c)
				{
					$output .= $this->process_comment($c);
				}
				
				# get all of the group elements
				$elementList =& $v->_getChildren();
				
				# process each element individually
				foreach ($elementList as &$element)
				{
					# get all of the attributes
					$attributeList = $element->_getAttributes();
					# get all of the attribute comments
					if ($element->_countChildren() > 0)
					{
						$attributeComments = $element->_getChild('comments')->_getAttributes();
					} else {
						$attributeComments = array();
					}
					# start the element output
					$output .= "\t" . $element->_getName() . "\r\n";
					if (isset($attributeList['comment']))
					{
						# process and remove the element comment first
						$output .= $this->process_comment($attributeList['comment'], "\t", false);
						unset($attributeList['comment']);
					}
					$output .= "\t{\r\n";
					# process each attribute individually
					foreach ($attributeList as $attribute=>$value)
					{
						if (isset($attributeComments[$attribute]))
						{
							$output .= $this->process_comment($attributeComments[$attribute], "\t\t", false);
						}
						$output .= "\t\t$attribute: $value;\r\n";
					}
					# finally, close the element and add some space
					$output .= "\t}\r\n\r\n";
				}
				
				# add some additional space at the end of the group
				$output .= "\r\n";
			}
			
			return $output;
		}
		
		protected function _construct()
		/* initialize css object class 
		 *
		 */
		{
			# prep
			$this->reset();
			
			# set the valid deliminators for internal use
			$this->_deliminators = array('.','#','<','>');
		}
		
		public function comment($element, $comment, $property = '', $group = 0)
		/* add a comment to an element
		 *
		 * specifying an element of "document" or '' (empty string) 
		 *	will add a comment to the css document
		 *
		 */
		{
			# make sure the comment is valid
			if (strlen($comment) == 0) return false;
			
			# make sure group is an integer
			$group = intval($group);
			
			# make sure the group is greater than or equal to zero
			if ($group < 0) return false;
			
			# commit to the change
			$this->_saved = false;
			
			# if the group does not exist, create it
			if (! isset($this->_data[$group]))
			{
				$this->_data[$group] = new xml_object($this->_tx, array('_name'=>strval($group)));
				$this->_comments[$group] = array();
			}
			
			# determine whether the comment is for an element or the document
			if ( (strtolower($element) == 'document') || ($element == '') )
			{
				# comment for the document
				$this->_comments[$group][] = $comment;
			} else {
				# comment for an element

				if ($property == '')
				{
					# comment is for the element
					$this->_data[$group]->_setChild($element)->_setAttribute('comment', $comment);
				} else {
					# comment is for an attribute in the element
					$this->_data[$group]->_setChild($element)->_setChild('comments')->_setAttribute($property, $comment);
				}
			}
			
			return true;
		}
		
		protected function extract_class($item)
		/* given string $item, extract the right most object's class name, if there is one
		 *
		 * if there is none, return empty string ('')
		 *
		 */
		{
			# get the last pair
			$match = $this->extract_last_element_pair($item, '.');
			
			# return the id
			return $match[1];
		}
		
		protected function extract_element($item)
		/* given a string $item, extract the right most element name, if there is one
		 *
		 * if there is none, return empty string ('')
		 *
		 */
		{
			# get the last pair
			$match = $this->extract_last_element_pair($item, '#');
			
			if (strpos($match[0], '.') !== false)
			{
				# get the last pair
				$match = $this->extract_last_element_pair($item, '.');
			} elseif (strpos($match[0], ':') !== false) {
				# get the last pair
				$match = $this->extract_last_element_pair($item, ':');
			}
			
			# return the element
			return $match[0];
		}
		
		protected function extract_elements($item)
		/* given a string $item, extract all individual elements
		 *
		 * returns an array of each pair or an empty array if there are none:
		 *	$arr[0] = '.ele1 .ele2 #ele3 #ele4'
		 *	$arr[1] = '.ele1 .ele2 #ele3'
		 *	$arr[2] = '.ele1 .ele2'
		 *	$arr[3] = '.ele1'
		 *
		 */
		{
			# initialize the return array
			$returnArr = array();
			
			# make sure $item is a valid string
			$item = trim('' . $item);
			
			# make sure the string is not empty
			if (strlen($item) == 0) return $returnArr;
			
			# if there are spaces, this has a scope
			if (strpos($item, ' ') === false)
			{
				# no spaces, implied single item
				$tmp = $this->extract_last_element_pair($item);
				$returnArr[] = $tmp[0] . $tmp[1];
			} else {
				# there are spaces, implied scope
				$strArr = explode(' ', $item);
				$scope = '';
				foreach($strArr as $str)
				{
					# get the last pair from the element
					$tmp = $this->extract_last_element_pair($str);
					$returnArr[] = $scope . $tmp[0] . $tmp[1];
					$scope .= $tmp[0] . $tmp[1] . ' ';
				}
			}
			
			# return the result
			return $returnArr;
		}
		
		protected function extract_id($item)
		/* given a string $item, extract the right most id, if there is one
		 *
		 * if there is none, return empty string ('')
		 *
		 */
		{	
			# get the last pair
			$match = $this->extract_last_element_pair($item, '#');
			
			# return the id
			return $match[1];
		}
		
		protected function extract_last_element_pair($item, $deliminator = '')
		/* given a string $item, extract the right most element pair
		 * 	separated by the specified deliminator
		 *
		 * if there is a match, return an array = 0=>element, 1=>class/id/etc...
		 *
		 * if there is none, return an empty array pair
		 *
		 */
		{
			# make sure $item is a valid string
			$item = trim('' . $item);
			
			# make sure the string is not empty
			if (strlen($item) == 0) return array('', '');
			
			# if a deliminiator was provided, disable looping
			if ($deliminator == '')
			{
				# nothing provided, enable loop
				$loop = true;
				# set test string to first element
				$testStr = $this->_deliminators[0];
			} else {
				# disable loop
				$loop = false;
				# set test string to provided deliminator
				$testStr = $deliminator;
			}
			
			for ($i=1;$i<count($this->_deliminators);$i++)
			{
				# if there are spaces, this has a scope
				if (strpos($item, ' ') === false)
				{
					# no spaces, implied single item
					if (strpos($item, $testStr) === false) return array($item, '');
					# split the element name and the class
					$str = explode($testStr, $item);
				} else {
					# there are spaces, implied scope
					$strArr = explode(' ', $item);
					# check for a class deliminator in the last item in the list
					if (strpos($strArr[(count($strArr) - 1)], $testStr) === false) return array($strArr[(count($strArr) - 1)], '');
					# split the element name and the class
					$str = explode($testStr, $strArr[(count($strArr) - 1)]);
				}
				# if looping is disabled, exit here
				if (! $loop) break;
				# try the next deliminator
				$testStr = $this->_deliminators[$i];
			}
			
			# return the class name
			return array($str[0], $str[1]);
		}
		
		public function get_classes($name = '', $return_with_scope = false)
		/* retrieve a list of all classes that can be applied to the specified element
		 *
		 * if no element is specified, only global classes will be returned
		 *
		 * if $return_with_scope is true an array will be returned in the form:
		 *	$arr[classes] = class_array with duplicates
		 *	$arr[scope] = scope_array. keys in each sub array will match
		 *
		 * for example:
		 *	$arr[classes][0] = 'class1'
		 *	$arr[scope][0] = 'div#container div.test'
		 *
		 * duplicates will be returned since the *scope* is not duplicated
		 *
		 * if $return_with_scope is false an array will be returned with a list of 
		 *	classes and no duplicates
		 *
		 */
		{
			# prepare variables
			$classes = array();
			$scope = array();
			
			$d = new unique_pair($this->_tx);
			
			foreach ($this->_data as &$group)
			{
				# retrieve all of the elements for the group
				$elementList = $group->_getChildren();
				# process each element
				foreach($elementList as &$elementLine)
				{
					# prepare cache variable
					$last_scope = '';
					# get the individual elements from the complete line
					$elementArr = $this->extract_elements($elementLine->_getName());
					# process each individual element
					foreach($elementArr as $element)
					{
						# retrieve the class and element
						$class = $this->extract_class($element);
						$this_name = $this->extract_element($element);
						if ( ( ($this_name == '') || ($name == $this_name) ) && (strlen($class) > 0) )
						{
							# this is a global element; always return global elements OR
							# this is a not a global element and the name matches
							$d->add($last_scope, $class);
						}
						$last_scope = $element;
					}
				}
			}
						
			if ($return_with_scope)
			{
				$r = array('classes'=>$d->get_values(), 'scope'=>$d->get_keys());
				return $r;
			} else {
				# remove any duplicates and return the array
				return array_keys(array_count_values($d->get_values()));
			}
		}
		
		public function get_elements()
		/* retrieve a list of all elements that are specified, including their scope
		 *
		 * if an optional group is specified (0 or higher), return only that group
		 *
		 */
		{
			# prepare return array
			$r = array();
			foreach ($this->_data as &$group)
			{
				# retrieve all of the elements for the group
				$elementList = $group->_getChildren();
				# process each element
				foreach($elementList as &$elementLine)
				{
					$elementArr = $this->extract_elements($elementLine->_getName());
					foreach($elementArr as $element)
					{
						# retrieve the element name
						$name = $this->extract_element($element);
						if (strlen($name) > 0) $r[$element] = $name;
					}
				}
			/* original implementation
				# process each element
				foreach($elementList as $element)
				{
					# retrieve the element name
					$name = $this->extract_element($element->_getName());
					if (strlen($name) > 0) $r[$element->_getName()] = $name;
				}
			*/
			}
			return $r;
		}
		
		public function get_ids($name = '', $return_with_scope = false)
		/* retrieve a list of ids for the specified element
		 *
		 * if no element is specified all global ids will be returned
		 *
		 * if $return_with_scope is true an array will be returned in the form:
		 *	$arr[ids] = id_array with duplicates
		 *	$arr[scope] = scope_array. keys in each sub array will match
		 *
		 * for example:
		 *	$arr[ids][0] = 'id1'
		 *	$arr[scope][0] = 'div#container div.test'
		 *
		 * duplicates will be returned since the *scope* is not duplicated
		 *
		 * if $return_with_scope is false an array will be returned with a list of 
		 *	ids and no duplicates
		 *
		 */
		{
			# prepare variables
			$ids = array();
			$scope = array();
			
			$d = new unique_pair($this->_tx);
			
			foreach ($this->_data as &$group)
			{
				# retrieve all of the elements for the group
				$elementList = $group->_getChildren();
				# process each element
				foreach($elementList as &$elementLine)
				{
					# prepare cache variable
					$last_scope = '';
					# get the individual elements from the complete line
					$elementArr = $this->extract_elements($elementLine->_getName());
					# process each individual element
					foreach($elementArr as $element)
					{
						# retrieve the id and element
						$id = $this->extract_id($element);
						$this_name = $this->extract_element($element);
						if ( ( ($this_name == '') || ($name == $this_name) ) && (strlen($id) > 0) )
						{
							# this is a global element; always return global elements OR
							# this is a not a global element and the name matches
							$d->add($last_scope, $id);
						}
						$last_scope = $element;
					}
				}
			}
						
			if ($return_with_scope)
			{
				$r = array('ids'=>$d->get_values(), 'scope'=>$d->get_keys());
				return $r;
			} else {
				# remove any duplicates and return the array
				return array_keys(array_count_values($d->get_values()));
			}
		}
		
		public function get_style($name, $group = -1)
		/* retrieve an array of applied styles for the specified element
		 *
		 * if the element is not found, return an empty array
		 *
		 */
		{
			foreach ($this->_data as &$group)
			{
				# retrieve all of the elements for the group
				$elementList = $group->_getChildren();
				# process each element
				foreach($elementList as &$element)
				{
					if ($element->_getName() == $name)
					{
						$properties = $element->_getAttributes();
						if (isset($properties['comment'])) unset($properties['comment']);
						return $properties;
					}
				}
			}
			return array();
		}
		
		public function load($file)
		/* load a css file
		 *
		 */
		{
			# validate file name
			$test = stripslashes($file);
			if ($test != $file) return false;
			
			# validate file
			if ( (strlen($file) == 0) || (! file_exists($file)) ) return false;
			
			# reset before a fresh load
			$this->reset();
			
			# set global values
			$this->_filename = $file;
			$this->_loaded = true;
			$this->_saved = true;
			
			# load execute
			$this->load_exec($file);
			
			return true;
		}
		
		public function load_combine($file)
		/* load a css file into the object, appending styles onto the loaded styles
		 *
		 */
		{
			# validate file name
			$test = stripslashes($file);
			if ($test != $file) return false;
			
			# validate file
			if ( (strlen($file) == 0) || (! @file_exists($file)) ) return false;
			
			# set global values (combining files by definition means this object does not relate to any one file)
			$this->_filename = '';
			$this->_loaded = false;
			$this->_saved = false;
			
			# load execute
			$this->load_exec($file);
			
			return true;
		}
		
		protected function load_exec(&$file)
		/* execute load action
		 *
		 * calling function should ensure validity of the file
		 *
		 */
		{
			# read the file
			$data = file($file);
			
			# preset function values
			$css_group = 0;
			//$line_scope = '';
			$open_element = '';		// the full text of an active element or empty set for the entire document
			$open_comment = false;
			$open_style = '';
			$open_style_value = '';
			$last_property = '';
			$cache = array();
			$first_element = true;
			
			foreach($data as $line)
			{
				# remove leading and trailing white space
				$line = trim('' . $line);
				# process the line's contents, removing small sections until it is empty
				while (strlen($line) > 0)
				{
					if ($open_comment)
					{
						# continue processing comment on this line
						$line = $this->load_process_comment($line, $open_element, $open_comment, $last_property, $cache, $css_group);
					} elseif ( (strlen($line) > 1) && (substr($line, 0, 2) == '/*') ) {
						# this section opens a comment block
						$open_comment = true;
						# if styles have already been applied iterate the group number
						if ( ($open_element == '') && (! $first_element) ) $css_group++;
						if (strlen($line) > 2)
						{
							# continue processing comment on this line
							$line = $this->load_process_comment(substr($line, 2), $open_element, $open_comment, $last_property, $cache, $css_group);
						} else {
							# there is nothing on this line except the open comment string, 
							# clear the line to stop processing here
							$line = '';
						}
					} elseif ( (strlen($line) > 1) && (substr($line, 0, 2) == '//') ) {
						# the rest of the line is a comment
						# if styles have already been applied iterate the group number
						if ( ($open_element == '') && (! $first_element) ) $css_group++;
						if (strlen($line) > 2) $this->comment($open_element, substr($line, 2), $last_property, $css_group);
						# clear the rest of the line since, by definition, the rest is a comment
						$line = '';
					} elseif (substr($line, 0, 1) == '{') {
						# ignore an open bracket set by itself
						if (strlen($line) > 1) { $line = trim(substr($line, 1)); } else { $line = ''; }
					} elseif (substr($line, 0, 1) == '}') {
						# close the open element
						$open_element = '';
						$open_style = '';
						$open_style_value = '';
						$last_property = '';
						# check the rest of the line
						if (strlen($line) > 1) { $line = trim(substr($line, 1)); } else { $line = ''; }
					} elseif ( (strlen($line) > 1) && (substr($line, 0, 2) == '*/') ) {
						# close the open comment
						$open_comment = false;
						# apply the cached comment
						$this->comment($open_element, $cache, $last_property, $css_group);
						# clear the cache
						$cache = array();
						# check the rest of the line
						if (strlen($line) > 2) { $line = trim(substr($line, 2)); } else { $line = ''; }
					} elseif (substr($line, 0, 1) == ':') {
						# ignore an colon by itself
						if (strlen($line) > 1) { $line = trim(substr($line, 1)); } else { $line = ''; }
					} elseif (substr($line, 0, 1) == ';') {
						# if there is a style open, set it to whatever is cached and close it
						if (strlen($open_style) > 0)
						{
							$this->style($open_element, trim($open_style), trim($open_style_value), $css_group);
							# update the last property
							$last_property = $open_style;
							# clear the style cache data
							$open_style = '';
							$open_style_value = '';
							# confirm an element style has been added to the document
							$first_element = false;
						}
						# ignore a semicolon by itself
						if (strlen($line) > 1) { $line = trim(substr($line, 1)); } else { $line = ''; }
					} elseif (strlen($open_style) > 0) {
						# a style is open and the line does not begin with a comment
						
						/* we already know that the first character is not a semicolon, space, or comment
						 * therefore we can set each of them to zero if they are false and do a numerical sort
						 * to see which, if any, appear on the line first
						 */
						$semicolon_pos = intval(strpos($line, ';'));
						$block_com_pos = intval(strpos($line, '/*'));
						$line_com_pos = intval(strpos($line, '//'));
						
						# if a line comment exists and is closer to the start of the line than a block comment, use the line comment
						if ( ($line_com_pos > 0) && ($line_com_pos < $block_com_pos) ) $block_com_pos = $line_com_pos;
						
						# if there is no block comment, use the line comment
						if ($block_com_pos == 0) $block_com_pos = $line_com_pos;
						
						# now we only need to check the value of block_com_pos since it represents the nearest comment
						if ( ($block_com_pos > 0) && ($block_com_pos < $semicolon_pos) )
						{
							# there is a comment on this line BEFORE the style definition ends
							# set the semicolon_position to the comment position
							$semicolon_pos = $block_com_pos;
						}
						
						if ($semicolon_pos > 0)
						{
							# grab everything before the semicolon or comment
							$open_style_value .= trim(substr($line, 0, $semicolon_pos)) . ' ';
							# remove the applied style from the line
							$line = substr($line, $semicolon_pos);
						} else {
							# grab the entire line
							$open_style_value .= $line . ' ';
							# clear the line
							$line = '';
						}
					} elseif ($open_element == '') {
						# the line must be a new element
						
						/* we already know that the first character is not a semicolon, space, or comment
						 * therefore we can set each of them to zero if they are false and do a numerical sort
						 * to see which, if any, appear on the line first
						 */
						$bracket_pos = intval(strpos($line, '{'));
						$block_com_pos = intval(strpos($line, '/*'));
						$line_com_pos = intval(strpos($line, '//'));
						
						# if a line comment exists and is closer to the start of the line than a block comment, use the line comment
						if ( ($line_com_pos > 0) && ($line_com_pos < $block_com_pos) ) $block_com_pos = $line_com_pos;
						
						# if there is no block comment, use the line comment
						if ($block_com_pos == 0) $block_com_pos = $line_com_pos;
						
						# now we only need to check the value of block_com_pos since it represents the nearest comment
						if ( ($block_com_pos > 0) && ($block_com_pos < $bracket_pos) )
						{
							# there is a comment on this line BEFORE the element definition begins
							# set the bracket position to the comment position
							$bracket_pos = $block_com_pos;
						}
						
						if ($bracket_pos > 0)
						{
							# grab everything before the bracket or comment
							$open_element = trim(substr($line, 0, $bracket_pos));
							# remove the element from the line
							$line = substr($line, $bracket_pos);
						} else {
							# grab the entire line
							$open_element = $line;
							# clear the line
							$line = '';
						}
						# reset the open style value to prep for the next section
						$open_style_value = '';
					} elseif (strlen($open_element) > 0) {
						# the line must be an element style
						
						/* we already know that the first character is not a semicolon, space, or comment
						 * therefore we can set each of them to zero if they are false and do a numerical sort
						 * to see which, if any, appear on the line first
						 */
						$colon_pos = intval(strpos($line, ':'));
						$block_com_pos = intval(strpos($line, '/*'));
						$line_com_pos = intval(strpos($line, '//'));
						
						# if a line comment exists and is closer to the start of the line than a block comment, use the line comment
						if ( ($line_com_pos > 0) && ($line_com_pos < $block_com_pos) ) $block_com_pos = $line_com_pos;
						
						# if there is no block comment, use the line comment
						if ($block_com_pos == 0) $block_com_pos = $line_com_pos;
						
						# now we only need to check the value of block_com_pos since it represents the nearest comment
						if ( ($block_com_pos > 0) && ($block_com_pos < $colon_pos) )
						{
							# there is a comment on this line BEFORE the element definition begins
							# set the bracket position to the comment position
							$colon_pos = $block_com_pos;
						}
						
						if ($colon_pos > 0)
						{
							# grab everything before the colon or comment
							$open_style = trim(substr($line, 0, $colon_pos));
							# remove the style from the line
							$line = substr($line, $colon_pos);
						} else {
							# grab the entire line
							$open_style = $line;
							# clear the line
							$line = '';
						}
					} else {
						# ERROR
						echo "ERROR 690\r\n";
						break;
					}
				} // while (strlen($line) > 0)
			} // foreach($data as $line)
			
			return true;
		}
		
		protected function load_process_comment($line, $element, &$comment, $prop, &$cache, &$group)
		/* called from the load function only, process a comment found on a line
		 *
		 * adds the comment into the loaded object and returns anything left on the line
		 * 	after the comment
		 *
		 */
		{
			if (strpos($line, '*/') === false)
			{
				# the comment does *not* end on this line
				// if (...) // check for boilerplate ******* or -------- horizontal lines here
				$cache[] = $line;
				$line = '';
			} else {
				# the comment also ends on this line
				$comment = false;
				$comment_end_pos = strpos($line, '*/');
				$cache[] = substr($line, 0, $comment_end_pos);
				# make sure single line comments are not treated as multi-line comments
				if (count($cache) == 1) $cache = trim($cache[0]);
				$this->comment($element, $cache, $prop, $group);
				$cache = array();
				# remove the rest of the comment from the line for further processing
				if (strlen($line) > ($comment_end_pos + 2))
				{
					$line = substr($line, ($comment_end_pos + 2));
				} else {
					$line = '';
				}
			}
			return trim($line);
		}
		
		protected function process_comment($comment, $line_prefix = '', $post_line_break = true)
		/* given a comment, return the processed output
		 *
		 */
		{
			if (strlen($comment) == 0) return false;
			
			$output = '';
			
			if (is_array($comment))
			{
				# multiple lines
				#$output .= "$line_prefix/*" . CSS_LINE_COMMENT_ASTERISK . "\r\n";
				foreach($comment as $cArr)
				{
					if (strlen($output) == 0)
					{
						$output = "$line_prefix/*";
					} else { 
						$output .= "$line_prefix *";
					}
					if ($cArr == '*')
					{
						$output .= CSS_LINE_COMMENT_ASTERISK . "\r\n";
					} elseif ($cArr == '-') {
						$output .= ' ' . CSS_LINE_COMMENT_HYPHEN . "\r\n";
					} else {
						$output .= " $cArr\r\n";
					}
				}
				$output .= "$line_prefix *\r\n$line_prefix */\r\n";
			} else {
				# one line only
				$output .= "$line_prefix/* $comment */\r\n";
			}
			
			if ($post_line_break) $output .= "\r\n";
			
			return $output;
		}
		
		public function reset()
		/* reset the object to a fresh state
		 *
		 */
		{
			# create initial, empty arrays for internal variables
			$this->_comments = array();
			$this->_data = array();
			
			# create the default group and group comment
			$this->_data[0] = new xml_object($this->_tx, array('_name'=>'0'));
			$this->_comments[0] = array(0=>'CSS Document');
			
			# set initial values
			$this->_filename = '';
			$this->_loaded = false;
			$this->_saved = false;
		}
		
		public function save()
		/* save a loaded css file 
		 *
		 */
		{
			# if the loaded document is unchanged, there is nothing to save
			if ($this->_saved == true) return true;
			
			/* NOT IMPLEMENTED */
			
			return false;
		}
		
		public function style($element, $style, $value, $group = 0)
		/* apply the specified style to the specified element
		 *
		 * if the style already has a value the value will be overwritten
		 *
		 */
		{
			# make sure group is an integer
			$group = intval($group);
			
			# make sure the group is greater than or equal to zero
			if ($group < 0) return false;
			
			# commit to the change
			$this->_saved = false;
			
			# if the group does not exist, create it
			if (! isset($this->_data[$group]))
			{
				$this->_data[$group] = new xml_object($this->_tx, array('_name'=>strval($group)));
				$this->_comments[$group] = array();
			}
			
			# set the attribute
			$this->_data[$group]->_setChild($element)->_setChild('comments');
			$this->_data[$group]->_getChild($element)->_setAttribute($style, $value);
			
			return true;
		}
				
	}
?>