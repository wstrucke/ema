<?php
 /* XML Object Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Oct-06-2008/Sep-10-2009/May-02-2010/Nov-13-2010
  * William Strucke, wstrucke@gmail.com
  *
  * to do:
  *  - recode child storage to allow multiple children with the same name
  *
  */
	
	class xml_object extends standard_extension
	{
		protected $_attributes;         // an array of 0 or more xml properties (attributes) applied
		protected $_children;           // an array of 0 or more xml_objects
		protected $_loaded = false;     // will always be true once the object is successfully initialized
		protected $_order;              // an array of child ids identifying the output order (keys are 0 - n),
		                                //  values are the unique ids in $this->children[]
		protected $_parent;             // this element's parent
		protected $_rorder;             // reverse index of unique child ids to their corresponding order entry
		protected $_tag;                // this element's tag name
		protected $_value;              // optional string value of this element
		protected $_whoami;             // this element's unique identifier from it's parent element
		
		public $_name = 'XML Object Extension';
		public $_version = '1.0.0';
		protected $_debug_prefix = 'xml_object';
		protected $_no_debug = true;    // disables debugging for this object
		
		public function __get($tag)
		/* retrieve a child element matching the specified tag
		 *	if multiple elements match, return all of them
		 *
		 * returns an xml_object, an array, or false on error
		 *
		 * if one or more elements are returned, they are
		 *	returned by reference
		 *
		 */
		{
			# sanity checks
			if (! $this->_loaded) return false;
			if (strlen(trim('' . $tag)) == 0) return false;
			
			$matchArr = array();
			
			foreach($this->_children as &$child)
			{
				if ($child->_getTag() == $tag) $matchArr[] =& $child;
			}
			
			# check results
			if (count($matchArr) == 0)
			{
				# if no match, create a new element and return it
				return $this->_createChild($tag);
			}
			
			# if there is only one match, return that element
			if (count($matchArr) == 1) { return $matchArr[0]; }
			
			# there were multiple matches; return all of them
			return $matchArr;
		}
		
		public function __set($tag, $value)
		/* set the value of the specified child tag
		 *
		 * if there are multiple children with the provided
		 *  tag, this function will only process the first one
		 *
		 * if the tag does not exist, create it
		 *
		 */
		{
			if (! $this->_loaded) return false;
			if (strlen($tag) == 0) return false;
			
			# validate value
			if ($value === true) $value = 'true';
			if ($value === false) $value = 'false';
			
			# preset selected tag
			$selected = null;
			
			# attempt to locate the tag
			foreach($this->_children as &$child) {
				if ($child->_getTag() == $tag) {
					$selected =& $child;
					break;
				}
			}
			
			# by convention, an xml object with children should not also have a value
			$this->_value = '';
			
			if (! is_null($selected)) {
				# conditionally set the value and return the element
				if ($value != null) $selected->_setValue($value);
				return $selected;
			}
			
			# there was no match, create a new element and return it
			$c = $this->_createChild($tag);
			$c->_setValue($value);
			
			return $c;
		}

		public function __sleep()
		{
			return array('_attributes', '_children', '_loaded', '_order', '_parent', '_rorder', '_tag', '_value', '_whoami');
		}
		
		public function __wakeup()
		{
			
		}
		
		public function __toString()
		{
			# use the public _getXML function to recursively process child elements
			if ($this->_countChildren() > 0) return $this->_getXML();
			if (strlen($this->_value) == 0) return '<' . $this->_getTag() . '/>';
			return $this->_value;
		}
		
		public function _addChild(xml_object $child)
		/* add the provided xml_object as a child of this object
		 *
		 */
		{
			if (! $this->_loaded) return false;
			
			$newID = $this->_newChildID();
			if (count($this->_order) > 0) { $n = max(array_keys($this->_order)); } else { $n = 0; }
			if ( (! isset($this->_order[$n])) || (! is_array($this->_order[$n])) ) $this->_order[$n] = array();
			array_push($this->_order[$n], $newID);
			$this->_rorder[$newID] = $n;
			
			# add the child element
			$this->_children[$newID] =& $child;
			$this->_children[$newID]->_setParent($this);
			$this->_children[$newID]->_setIdentity($newID);
			
			return true;
		}
		
		public function _addChildren(&$c_arr)
		/* add the provided array of xml_objects as children
		 *	of this object
		 *
		 */
		{
			if (! $this->_loaded) return false;
			foreach ($c_arr as &$c) { $this->_addChild($c); }
			return true;
		}
		
		public function _cc($tag, $value = null) {
			return $this->_createChild($tag, $value);
		}
		
		public function _ccc($cArr) {
			return $this->_createChildren($cArr);
		}
		
		protected function _construct()
		/* initialize xml object class
		 *
		 */
		{
			# sanity check
			if ($this->_tag == '') return false;
			
			# validate data for the object
			if (is_null($this->_value)) $this->_value = '';
			
			$this->_children = array();
			$this->_order = array();
			$this->_rorder = array();
			
			# initialize other attributes
			$this->_attributes = array();
			$this->_loaded = true;
			
			return true;
		}
		
		public function _countChildren()
		/* returns the total number of children
		 *
		 */
		{
			if (! $this->_loaded) return false;
			
			return count($this->_children);
		}
		
		public function _createChild($tag, $value = null)
		/* create and return a child element at the specified position
		 *
		 * optionally provide a value to set for the child element
		 *
		 */
		{
			# sanity check
			if (! $this->_loaded) return false;
			if (strlen(trim('' . $tag)) == 0) return false;
			
			# create a new element and return it
			$newID = $this->_newChildID();
			if (count($this->_order) > 0) { $n = max(array_keys($this->_order)); } else { $n = 0; }
			if ( (! isset($this->_order[$n])) || (! is_array($this->_order[$n])) ) $this->_order[$n] = array();
			array_push($this->_order[$n], $newID);
			$this->_rorder[$newID] = $n;
			
			# add the child element
			$args = array('_tag'=>$tag,'_whoami'=>$newID);
			$this->_children[$newID] = new xml_object($this->_tx, $args);
			$this->_children[$newID]->_setParent($this);
			$this->_children[$newID]->_setIdentity($newID);
			if (! is_null($value)) $this->_children[$newID]->_setValue($value);
			
			return $this->_children[$newID];
		}
		
		public function _createChildren($cArr)
		/* given an array of key value pairs, create an xml child of this element for each
		 *
		 */
		{
			# sanity check
			if (! $this->_loaded) return false;
			if (! is_array($cArr)) return false;
			foreach($cArr as $key=>$value) {
				$this->_createChild($key, $value);
			}
			return $this;
		}
		
		public function _sa($name, $value) { return $this->_setAttribute($name, $value); }
		public function _sas($arr) { return $this->_setAttributes($arr); }
		
		public function _sanitize($strin)
		/* source: http://php.net/manual/en/function.htmlentities.php
		 * author: phil at lavin dot me dot uk
		 * retrieved: dec-03-2010 ws
		 *
		 * given string input (i.e. from a database),
		 *  returns a sanitized xml string
		 *
		 */
		{
			$strout = null;
			
			# make all new line characters into a vertical tab character for consistency
			$strin = str_replace(array("\r\n", "\r", "\n", chr(12)), chr(11), $strin);
			// we should also account for the following three sequences, though I don't
			//  know what the ascii codes are the moment
			//     NEL:   Next Line, U+0085
			//     LS:    Line Separator, U+2028
			//     PS:    Paragraph Separator, U+2029
			
			# define valid character ascii ids to ignore
			#  9    horizontal tab
			#  11   vertical tab
			$ignore = array(9, 11);
			
			for ($i = 0; $i < strlen($strin); $i++) {
				$ord = ord($strin[$i]);
				if ((! in_array($ord, $ignore)) && ($ord > 0 && $ord < 32) || ($ord >= 127)) {
					$strout .= "&amp;#{$ord};";
				} elseif ($ord == 11) {
					$strout .= NL;
				} else {
					switch ($strin[$i]) {
						case '<': $strout .= '&lt;'; break;
						case '>': $strout .= '&gt;'; break;
						case '&': $strout .= '&amp;'; break;
						case '"': $strout .= '&quot;'; break;
						default: $strout .= $strin[$i];
					}
				}
			}
			
			return $strout;
		}
		
		public function _setAttribute($a, $v)
		/* sets an attribute value based on the actual attribute name
		 *
		 *	e.g. no leading underscore
		 *
		 */
		{
			if (! $this->_loaded) return false;
			if (strlen($a) == 0) return false;
			
			$this->_attributes[$a] = $v;
			
			return $this;
		}
		
		public function _setAttributes($arr)
		/* set multiple attributes from an array
		 *
		 * array keys should be attribute names
		 * array values should be corresponding values
		 *
		 */
		{
			# sanity checks
			if (! $this->_loaded) return false;
			if (! is_array($arr)) return false;
			
			foreach($arr as $k=>$v) {
				$this->_attributes[$k] = $v;
			}
			
			return $this;
		}
		
		public function _setChild($tag, $value = null)
		/* creates or updates a child with the specified value
		 *
		 * this is identical to the __set function
		 *
		 */
		{
			return $this->__set($tag, $value);
		}
		
		public function _setIdentity($whoami)
		/* this function is to be used soley by a new parent element 
		 *	(e.g. from a copying procedure) to provide this element's 
		 *	unique identity in it's context
		 *
		 */
		{
			# sanity check
			if (! $this->_loaded) return false;
			if (strlen($whoami) == 0) return false;
			
			$this->_whoami = $whoami;
			
			return true;
		}
		
		public function _setName($newName)
		/* this function is an alias for _setTag()
		 *
		 */
		{
			return $this->_setTag($newName);
		}
		
		public function _setParent(xml_object &$obj)
		/* set a reference to the parent of this element to enable
		 *	certain capabilities
		 *
		 */
		{
			if (! $this->_loaded) return false;
			$this->_parent =& $obj;
		}
		
		public function _setTag($newTag)
		/* change this object's tag
		 *
		 */
		{
			if (strlen($newTag) == 0) return false;
			
			$this->_tag = $newTag;
			
			return true;
		}
		
		public function _setValue($v, $auto_sanitize = true)
		{
			if (! $this->_loaded) return false;
			
			if ($auto_sanitize) {
				$this->_value = $this->_sanitize((string)$v);
			} else {
				$this->_value = (string)$v;
			}
			
			return $this;
		}
		
		public function _getAttribute($a)
		{
			if (! $this->_loaded) return false;
			if (strlen($a) == 0) return false;
			if (! isset($this->_attributes[$a])) return false;
		
			return $this->_attributes[$a];
		}
		
		public function _getAttributes()
		/* return all attributes in an array
		 *
		 */
		{
			return $this->_attributes;
		}
		
		public function _getChild($tag)
		/* identical to the __get() function, except one major 
		 *	difference -- if the child does not exist, this
		 *	function returns false
		 *
		 * returns the first child element matching $name
		 *	- note that may not be the first *ordered* child
		 *
		 */
		{
			if (! $this->_loaded) return false;
			if (strlen($tag) == 0) return false;
			
			$selected = null;
			
			foreach($this->_children as &$child)
			{
				if ($child->_getTag() == $tag)
				{
					$selected =& $child;
					break;
				}
			}
			
			if (is_null($selected)) return false;
			
			return $selected;
		}
		
		public function _getChildren()
		/* returns an array of all child xml_objects
		 *
		 */
		{
			if (! $this->_loaded) return false;
			return $this->_children;
		}
		
		public function _getName()
		/* alias for _getTag()
		 *
		 */
		{	
			return $this->_getTag();
		}
		
		public function _getTag()
		/* returns this element's tag
		 *
		 */
		{
			# sanity check
			if (! $this->_loaded) return false;
			
			return $this->_tag;
		}
		
		public function _getTopParent(&$obj)
		/* returns the top most parent in any hierarchy
		 *
		 */
		{
			if (gettype($this->_parent) == 'xml_object')
			{
				$obj =& $this->_parent->_getTopParent($obj);
			} else {
				$obj =& $this;
			}
			
			return true;
		}
		
		public function _getValue()
		{
			if (! $this->_loaded) return false;
			return $this->_value;
		}
		
		public function _getXML($line_prefix = '')
		/* return raw xml output for this object and children
		 *
		 */
		{	
			if (! $this->_loaded) return '';
			
			# start generating the output
			$preOutput = $line_prefix . '<' . $this->_tag;
			
			# add the attributes
			foreach ($this->_attributes as $k=>$v) {
				$preOutput .= ' ' . $k . '="' . $v . '"';
			}
			
			# if there are neither children nor data, stop here
			if ( ($this->_value === '') && (count($this->_children) == 0) ) {
				$preOutput .= "/>";
				return $preOutput;
			}
			
			# close the start tag
			$preOutput .= '>';
			
			# if there are no children, finish up
			if (count($this->_children) == 0) {
				$preOutput .= $this->_value . '</' . $this->_tag . '>';
				return $preOutput;
			}
			
			$preOutput .= "\r\n";
			
			# copy and reverse sort the order of children for output
			$orderForOutput = $this->_order;
			krsort($orderForOutput, SORT_NUMERIC);
			
			# add the child elements
			foreach ($orderForOutput as $pos=>$posArr) {
				if (is_array($posArr)) {
					while (count($posArr) > 0) {
						$idx = array_shift($posArr);
						if (! is_object($this->_children[$idx])) {
							echo "bad index $idx\r\n";
							var_dump($this->_children[$idx]);
						} else {
							$tmp = $this->_children[$idx]->_getXML($line_prefix . "\t") . "\r\n";
							if ($tmp) $preOutput .= $tmp;
						}
					}
				} else {
					$preOutput .= $this->_children[$posArr]->_getXML($line_prefix . "\t") . "\r\n";
				}
			}
			
			# if the tag is a comment, do not output a closing tag
			if (strpos($this->_tag, '!') === false) {
				$preOutput .= $line_prefix . '</' . $this->_tag . '>';
			}
			
			return $preOutput;
		}
		
		protected function _newChildID()
		/* generate a new unique (for this class) child id
		 *
		 */
		{
			return uniqid();
		}
		
	}
?>