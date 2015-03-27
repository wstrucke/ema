<?php
 /* HTML Element Extension for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.1.0, 2012-03-04
  * William Strucke, wstrucke@gmail.com
  *
  * The html_element object exists to logically and consistently provide scope and context
  * to a loaded css document as it is applied to a page.  This will be used extensively
  * by the template_manager class to simplify the management of the many objects and
  * in the various templates. The elements this class will create will combine to output
  * a completed web page (though the intent is to use them soley for the body element and
  * its' children).
  *
  * This class is loosely modeled on the xml_object class.
  *
  */
	
	class html_element extends standard_extension
	{
		# data variables
		protected $attributes;  // an array of 0 or more html properties (attributes) applied
		protected $child_debug; // default child debug mode
		protected $childpreout; // a character to output before each child element (for spacing)
		protected $children;    // an array of 0 or more html_elements
		protected $class;       // this element's class(es) -- an array of 0 or more class names
		protected $doctype;     // special field for a doctype (if this element is a complete document)
		protected $expanded;    // (bool) true if the inner_html has been expanded into objects, default false
		protected $false;       // special placeholder to return a boolean value from a byref function
		protected $filename;    // the loaded file name
		protected $id;          // this element's id
		protected $initialized; // should always be true once the element is successfully initialized
		protected $linebrthreshold;
		protected $loaded;      // true if a file has been loaded
		protected $order;       // an array of child ids identifying the output order (keys are 0 - n),
		                        //  values are the unique ids in $this->children[]
		protected $rorder;      // reverse index of unique child ids to their corresponding order entry
		protected $parent;      // this element's parent
		protected $preoutput;   // optional value set by a parent to prepend to every line of output
		protected $saved;       // true if the loaded data is saved in the loaded file
		protected $style;       // (array) in-line css styles
		protected $tag;         // this element's tag name
		protected $true;        // special placeholder to return a boolean value from a byref function
		protected $value;       // optional string value of this element
		protected $whoami;      // this element's unique identifier from it's parent element
		
		# object variables
		protected $css;         // the provided css document object
		protected $html;        // xml_document containing an html reference for all possible
		                        //  elements and attributes
		
		# inherited variables
		public $_name = 'HTML Element Extension';
		public $_version = '1.1.0';
		protected $_debug_prefix = 'html_element';
		
		public function __clone()
		/* handle clone processes
		 *
		 */
		{
			$this->_debug('Help! Help! I\'ve been cloned!');
			$this->parent = null;
			$this->whoami = null;
			return parent::__clone();
		}
		
		public function &__get($tag)
		/* retrieve a child element matching the specified tag
		 *	if multiple elements match, return all of them
		 *
		 * returns an html_element, an array, or false on error
		 *
		 * if one or more elements are returned, they are
		 *	returned by reference
		 *
		 */
		{
			# sanity checks
			if (! $this->initialized) return $this->false;
			if (strlen(trim('' . $tag)) == 0) return $this->false;
			
			$matchArr = array();
			
			foreach($this->children as &$child) {
				if ($child->get_tag() == $tag) $matchArr[] =& $child;
			}
			
			# check results
			if (count($matchArr) == 0) {
				# if no match, create a new element and return it
				$newID = $this->new_child_id();
				if (count($this->order) > 0) { $n = max(array_keys($this->order)); } else { $n = 0; }
				if (!array_key_exists($n, $this->order)||(!is_array($this->order[$n]))) $this->order[$n] = array();
				array_push($this->order[$n], $newID);
				$this->rorder[$newID] = $n;
				
				# add the child element
				$args = array('tag'=>$tag,'css'=>&$this->css,'html'=>&$this->html,'whoami'=>$newID,'_debug_mode'=>$this->child_debug,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
				$this->children[$newID] = new html_element($this->_tx, $args);
				if ( (strpos($this->tag, '!') !== 0) && ($this->tag != 'html') ) {
					$this->children[$newID]->set_preout($this->childpreout);
				}
				$this->children[$newID]->set_parent($this);
				$this->children[$newID]->set_identity($newID, $this->css);
				
				return $this->children[$newID];
			}
			
			# if there is only one match, return that element
			if (count($matchArr) == 1) { return $matchArr[0]; }
			
			# there were multiple matches; return all of them
			return $matchArr;
		}
		
		public function __set($name, $value)
		/* create a child element
		 *
		 */
		{
			/* NOT IMPLEMENTED */
		}

		public function __sleep()
		{
			# do not attempt to serialize a child element
			if (gettype($this->parent) == 'html_element') return array();
			
			# prep child array
			$arr = array();
			
			# convert each top level child to an html string representation for storage
			while (count($this->children) > 0)
			{
				$child =& array_shift($this->children);
				$arr[] = $child->__toString();
			}
			
			# overwrite children array with new html representations
			$this->children = $arr;
			
			# overwrite css array with serialized copy of the css object reference
			$this->css = serialize(clone($this->css));
			
			#return array('\0*\0attributes', '\0*\0children', '\0*\0class', '\0*\0css', '\0*\0id', '\0*\0initialized', 
			#							'\0*\0order', '\0*\0preoutput', '\0*\0tag', '\0*\0value', '\0*\0whoami');
			
			return array('attributes', 'children', 'class', 'css', 'id', 'initialized', 'order', 'preoutput', 'tag', 
									'value', 'whoami', 'rorder');
		}
		
		public function __wakeup()
		/* WARNING: This function does *not* restore the original order of elements!!!!
		 *	
		 *	This needs to be fixed ASAP!
		 *
		 */
		{
			# copy the html representation array of child elements
			$arr = $this->children;
			
			# clear children array
			$this->children = array();
			
			# unserialize css object
			$this->css = unserialize($this->css);
			
			# get the transmission
			if (!is_object($this->_tx)) { global $t; $this->_tx = $t; }
			
			# initialize
			#$this->html = new xml_document($this->_tx);
			#$this->html->_load(dirname(__FILE__) . '/html4.xml');
			
			# temporarily clear the order since this function is not coded to honor it!
			$this->order = array();
			$this->rorder = array();
			
			# restore the child elements
			while (count($arr) > 0)
			{
				# use the special implementation of the html_element constructor to restore the saved data
				$args = array('tag'=>array(array_shift($arr)),'css'=>&$this->css,'html'=>&$this->html,'_debug_mode'=>$this->child_debug,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
				$child = new html_element($this->_tx, $args);
				$this->add_child($child);
			}
		}
		
		public function __toString()
		/* return a properly formatted element with all attributes and children
		 *
		 */
		{
			$this->_debug_start();
			
			# sanity check
			if (! $this->initialized) return $this->_return('error');
			
			#$endtag = 'optional';
			
			# get the end tag setting for this element
			$endtag = $this->close_tag($this->tag);
			/*if ($this->html->tags->_getChild(strtolower($this->tag)))
			{
				if ($this->html->tags->_getChild(strtolower($this->tag))->_getAttribute('endtag'))
				{
					$endtag = $this->html->tags->_getChild(strtolower($this->tag))->_getAttribute('endtag');
				}
			}*/
			
			if (strlen($this->doctype) > 0)
			{
				$this->_debug('adding doctype');
				$output = $this->preoutput . '<' . $this->doctype . ">\r\n";
			} else {
				$output = '';
			}
			
			$output .= $this->preoutput . '<' . $this->tag;
			
			# insert the id
			if (strlen($this->id) > 0) $output .= ' id="' . $this->id . '"';
			
			# insert any classes
			if (count($this->class) > 0) $output .= ' class="' . implode(' ', $this->class) . '"';
			
			# insert any inline css
			if (count($this->style) > 0)
			{
				$output .= ' style="';
				$first = true;
				foreach($this->style as $s=>$v) {
					if (! $first) $output .= ' ';
					$output .= $s . ': ' . $v . ';';
					$first = false;
				}
				$output .= '"';
			}
			
			# insert any attributes/attributes
			foreach($this->attributes as $attribute=>$value)
			{
				$output .= " $attribute=\"$value\"";
			}
			
			# add a trailing space before the close of the open tag if there are any attributes
			if ( (count($this->attributes) > 0) || (strlen($this->id) > 0) || (count($this->class) > 0) )
			{
				$output .= ' ';
			}
			
			# if this is a special tag (doctype, comment, etc...) close it now
			if (substr($this->tag, 0, 1) == '!')
			{
				if (count($this->children) == 0)
				{
					return $this->_return($output . ">");
				} else {
					$output .= '>';
					foreach ($this->children as &$child) $output .= "\r\n$child";
					return $this->_return($output);
				}
			}
			
			$this->_debug('i am ' . $this->tag . ' and i have ' . count($this->children) . ' children');
			
			# if there is neither data nor children, return an empty tag (??)
			if ( ($this->value == '') && (count($this->children) == 0) && ($endtag != 'required') )
			{
				$this->_debug('terminating');
				return $this->_return($output . '/>');
			}
			
			# remove the last space
			if ( (count($this->attributes) > 0) || (strlen($this->id) > 0) || (count($this->class) > 0) )
			{
				$output = substr($output, 0, (strlen($output) - 1));
			}
			
			# complete the open tag
			$output .= '>';
			
			# add exception -- do not break the lines if there is only one short child element
			if (count($this->children) != 1) { $line_break = "\r\n"; } else { $line_break = ''; }
			
			# copy and reverse sort the order of children for output
			$orderForOutput = $this->order;
			krsort($orderForOutput, SORT_NUMERIC);
			
			# prepare the final output
			$fulldataOutput = $this->value;
			$cursor = strlen($fulldataOutput); // + strlen($output);
			$delta = 0;
			
			$this->_debug('adding children to output');
			
			# add in the children
			foreach ($orderForOutput as $pos=>&$posArr)
			{
				$this->_debug('processing');
				while ( is_array($posArr) && (count($posArr) > 0) )
				{
					$this->_debug('retrieving child');
					# get the child value
					$child_val = $this->children[array_pop($posArr)]->__toString();	// i do not understand why this is backwards?! now it doesn't matter?!
					
					if ( ($line_break == '') && (strlen($child_val) > $this->linebrthreshold) )
					{
						$line_break = NL;
					} elseif ($line_break == '') {
						$child_val = trim($child_val);
					}
					
					# original position was from the start of the tag, adjust as necessary
					$pos = $pos - $delta;
					#if ($pos < 0) $pos = 0;
					
					if ($pos > $cursor)
					{
						$fulldataOutput .= $line_break . $child_val;
						#$delta = $pos - $cursor;
						#$delta = strlen($output);
						#echo "adjusting positions by $delta\r\n";
						#$cursor = strlen($fulldataOutput);
					} else {
						#$pos -= $delta;
						$fulldataOutput = substr($fulldataOutput, 0, $pos) . $line_break . $child_val . substr($fulldataOutput, $pos);
						$cursor = $pos;
					}
					
					#$cursor = $pos - 1;
					
					#if ($cursor < 0) $cursor = 0;
				}
			}
			
			# update the output now
			$output .= $fulldataOutput;
		
			# add the close tag
			if ( (count($this->children) > 0) && ($line_break != '') ) $output .= $line_break . $this->preoutput;
			
			# if the tag is a comment, do not output a closing tag
			if (strpos($this->tag, '!') !== 0)
			{
				$output .= '</' . $this->tag . '>';
			}
			
			unset($fulldataOutput);
			
			return $this->_retByRef($output);
		}
		
		protected function _construct()
		/* initialize html_element object class
		 *
		 * requires both a tag (string) and a valid css_document object
		 *
		 * an optional $whoami identifier supplied by a parent html_element gives
		 *	this element a reference point in the parent
		 *
		 */
		{
			# set boolean placeholders
			$this->false = false;
			$this->true = true;
			
			# set default child debug mode (use for extended debugging only)
			$this->child_debug = -1;
			
			# check for restored data
			if (is_array($this->tag))
			{
				$data = $this->tag;
				
				$this->tag = '';
				
				# tag provided is restored data from an element's __toString() method
				$this->init();
				
				# restore data from the string
				$this->restore($data[0]);
				
				return true;
			}
			
			# sanity check
			if (strlen(trim('' . $this->tag)) == 0) return false;
			
			$this->init();
			
			return true;
		}
		
		public function _new($tag, $args = false)
		/* create a new child element WITHOUT linking it to this one, but using the context of this element
		 *
		 */
		{
			$cargs = array('tag'=>$tag,'css'=>&$this->css,'html'=>&$this->html,'_debug_mode'=>$this->_debug_mode,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
			if (is_array($args)) $cargs = array_merge($args, $cargs);
			return new html_element($this->_tx, $cargs);
		}
		
		public function &ac(html_element &$child, $position = 'bottom', $element = 'me') {
			return $this->add_child($child, $position, $element);
		}
		
		public function &add_child(html_element &$child, $position = 'bottom', $element = 'me')
		/* copies the provided child element as a new child of this element
		 *	at the specified position
		 *
		 * the optional argument $element is for the sole use by a child html_element
		 *	requesting an element be added before or after itself. as such any value
		 *	for $element will be ignored unless $position == 'before' or 'after'
		 *
		 * valid element values include the default, "me", referring to this element
		 *	or an internal array key value to reference the calling child element
		 *
		 * valid positions include "top", "bottom", "before", "after", or a
		 *	specific character position
		 *
		 * the character position can be any integer (even negative) since it will be
		 *	sorted and then output with the text. values of zero or less will be output
		 *	first, in order. multiple elements with the same value will be output in the
		 *	order they are added as children. values beyond the total length of the text
		 *	contained in this object will simply output last -- there will not be any
		 *	empty space prepended.
		 *
		 *	use 'order' array to store the order of the elements
		 *		keys are the unique element ids
		 *		values are the order
		 *	
		 *	to output...
		 *		use arsort($arr) to sort by value in reverse order and maintain indices
		 *
		 * returns true on success and false on error
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return $this->false;
			
			# get the new id
			$newID = $this->new_child_id();
			
			# set the order based on the supplied position
			switch(strtolower($position)) {
				case 'top':
					# place the element before all children
					if (count($this->order) > 0) { $n = min(array_keys($this->order)); } else { $n = 0; }
					if (!@is_array($this->order[$n])) $this->order[$n] = array();
					array_unshift($this->order[$n], $newID);
					$this->rorder[$newID] = $n;
					break;
				case 'bottom':
					# place the element after all children
					if (count($this->order) > 0) { $n = max(array_keys($this->order)); } else { $n = 0; }
					if ((!array_key_exists($n, $this->order))||(!is_array($this->order[$n]))) $this->order[$n] = array();
					array_push($this->order[$n], $newID);
					$this->rorder[$newID] = $n;
					break;
				case 'before':
					# place the element before a specific element
					if ($element == 'me') {
						return $this->parent->add_child($child, $position, $this->whoami);
					} else {
						# to put the element before the other one, just prepend it to the order array
						array_unshift($this->order[$this->rorder[$element]], $newID);
						$this->rorder[$newID] = $this->rorder[$element];
					}
					break;
				case 'after':
					# place the element after a specific element
					if ($element == 'me') {
						return $this->parent->add_child($child, $position, $this->whoami);
					} else {
						# to put the element after the other one, append it to the order array
						$n = count($this->order[$this->rorder[$element]]);
						for ($i=0;$i<$n;$i++) {
							$chk_ele = array_pop($this->order[$this->rorder[$element]]);
							if ($chk_ele == $element) array_unshift($this->order[$this->rorder[$element]], $newID);
							array_unshift($this->order[$this->rorder[$element]], $chk_ele);
						}
						$this->rorder[$newID] = $this->rorder[$element];
					}
					break;
				default:
					# place the element at a specific numeric position (usu. within a body of text)
					if ((!array_key_exists(intval($position), $this->order))||(!is_array($this->order[intval($position)]))) {
						$this->order[intval($position)] = array();
					}
					array_push($this->order[intval($position)], $newID);
					$this->rorder[$newID] = intval($position);
			}
			
			# add the child element
			$this->children[$newID] =& $child;
			if ( (strpos($this->tag, '!') !== 0) && ($this->tag != 'html') ) {
				$this->children[$newID]->set_preout($this->childpreout);
			}
			$this->children[$newID]->set_parent($this);
			$this->children[$newID]->set_identity($newID, $this->css);
			
			return $this;
		}
		
		public function &add_class($name)
		/* add named class to this element
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return $this->false;
			if (strlen(trim('' . $name)) == 0) return $this->false;
			# if the class name is already set, do not duplicate it
			if (array_search($name, $this->class) !== false) return $this;
			# add the class
			array_push($this->class, $name);
			return $this;
		}
		
		public function add_event($event, $fn)
		/* add a javascript event to an element
		 *
		 * this requires mootools
		 *
		 * it (will work) by adding the event to a local array, then when
		 * __toString is called it will output javascript to run with
		 * either domready to add the events or provide javascript to run
		 * at load time if the html is returned via AJAX
		 *
		 * implementation pending
		 *
		 */
		{
			return false;
		}
		
		public function &append_value($str)
		/* append a string value (internal contents) to this element
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return $this->false;
			
			# append the value, replacing special characters with html equivilents
			#$this->value = htmlspecialchars($str);
			$this->value .= $str;
			
			return $this;
		}
		
		public function &cc($tag, $position = 'bottom', $element = 'me') {
			return $this->create_child($tag, $position, $element);
		}
		
		public function clear_attribute($name)
		/* unset the provided attribute name
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			if (isset($this->attributes[$name]))
			{
				unset($this->attributes[$name]);
			}
			
			return false;
		}
		
		public function clear_class()
		/* clear all configured classes
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			$this->class = array();
			
			return true;
		}
		
		public function clear_doctype()
		/* clear this element's doctype
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			$this->doctype = '';
			
			return true;
		}
		
		public function clear_id()
		/* clear this element's id
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			$this->id = '';
			
			return true;
		}
		
		protected function close_tag($tag)
		/* temporariy replaces the need for the html4.xml file
		 *
		 */
		{
		/*
			$required = array('a', 'abbr', 'acronym', 'address', 'b', 'bdo', 'big', 'blockquote', 'body', 'button', 'caption', 'cite', 'code',
				'dd', 'del', 'dfn', 'div', 'dl', 'em', 'fieldset', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'iframe',
				'ins', 'kbd', 'label', 'legend', 'li', 'map', 'noframes', 'noscript', 'object', 'ol', 'optgroup', 'option', 'p', 'pre', 'q',
				'samp', 'script', 'select', 'small', 'span', 'strong', 'style', 'sub', 'sup', 'table', 'textarea', 'title', 'tt', 'ul', 'var');
		*/
			$forbidden = array('area', 'base', 'br', 'col', 'hr', 'img', 'input', 'link', 'meta', 'param');
			$optional = array('colgroup', 'dt', 'head', 'html', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr');
			$tag = strtolower($tag);
			if (in_array($tag, $forbidden)) {
				return 'forbidden';
			} elseif (in_array($tag, $optional)) {
				return 'optional';
			}
			return 'required';
		}
		
		public function count_children()
		/* return the number of children this element has
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			return count($this->children);
		}
		
		public function &create_child($tag, $position = 'bottom', $element = 'me')
		/* create and return a child element at the specified position
		 *
		 * the optional argument $element is for the sole use by a child html_element
		 *	requesting an element be added before or after itself. as such any value
		 *	for $element will be ignored unless $position == 'before' or 'after'
		 *
		 * valid element values include the default, "me", referring to this element
		 *	or an internal array key value to reference the calling child element
		 *
		 * valid positions include "top", "bottom", "before", "after", or a
		 *	specific character position from 0+
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return $this->false;
			if (strlen(trim('' . $tag)) == 0) return $this->false;
			
			# create the new element
			$args = array('tag'=>$tag,'css'=>&$this->css,'html'=>&$this->html,'_debug_mode'=>$this->child_debug,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
			$child = new html_element($this->_tx, $args);
			
			# add it to this parent and return the child or false
			if ($this->add_child($child, $position, $element)) {
				return $child;
			} else {
				return $this->false;
			}
		}
		
		protected function extract_next_tag(&$str)
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
		 *
		 */
		{
			# start debug output
			$this->_debug_start();
			
			# prepare return array
			$a = array('tag'=>'','attributes'=>array(),'inner_html'=>'','comment'=>'','position'=>0);
			
			# sanity check
			if (strlen($str) == 0) return $this->_return($a, 'string length was 0');
			
			# first check if the next tag is a comment
			if (preg_match('/^\s*\<![\s\r\n\t]*(--([^\-]|[\r\n]|-[^\-])*--[\s\r\n\t]*)\>/', $str, $match, PREG_OFFSET_CAPTURE)) {
				# remove this match from the provided string
				if (strlen($str) == (strlen($match[1][0]) + 3)) {
					$str = '';
				} elseif ($match[1][1] == 0) {
					$str = substr($str, strlen($match[0][0]));
				} else {
					$str = substr($str, 0, $match[1][1] - 2) . substr($str, $match[1][1] + 1 + strlen($match[1][0]));
				}
				$a['comment'] = '!' . $match[1][0]; // return comment without opening or closing brackets since this object will add them
				$a['inner_html'] = $str;
			} elseif (preg_match('/<([A-Za-z][A-Za-z0-9]*)\b\s*(\b(?:\b[A-Za-z][A-Za-z0-9]{0,}\b(?:\s|=(?:"[^"]{0,}"|[^\s]*))\s*)*\s*)\s*\/{0,}\1{0,}[^<]*>/i', $str, $match)) {
				# this should return an array with two or three items
				#  0 = complete match
				#  1 = tag
				#  2 = attributes
				$a['position'] = strpos($str, $match[0]);
				# remove this tag from the provided string
				if (strlen($str) == strlen($match[0])) {
					$str = '';
				} elseif ($a['position'] == 0) {
					$str = substr($str, strlen($match[0]));
				} else {
					$str = substr($str, 0, $a['position']) . substr($str, $a['position'] + strlen($match[0]));
				}
				$a['tag'] = $match[1];
				if (count($match) > 2) {
					$num_attributes = preg_match_all('/\b([A-Za-z][A-Za-z0-9]{0,})\b(?:\s|=(?:"([^"]{0,})"|([^\s]*)))/', $match[2], $property);
					for ($i=0;$i<$num_attributes;$i++) {
						if (strlen($property[2][$i]) > 0) {
							# index 2 contains property values with quotes
							$a['attributes'][$property[1][$i]] = $property[2][$i];
						} else {
							# index 3 contains property values without quotes
							$a['attributes'][$property[1][$i]] = $property[3][$i];
						}
					}
				}
				
				# check if this is a self-closing tag
				if (substr($match[0], (strlen($match[0])-2), 1) != '/') {
					$offset = $a['position'];
					$twin_count = 0;
					
					# locate the position of the next closing tag
					# i.e.:
					# ....<span>...</span><span>....</span>....<span><span>asdflj</span>lakjsd</span>....</span>
					#                                                                                    ^ this one
					# this is accomplished by starting at the current string offset and moving forward,
					#  checking each identified tag one by one (accounting for nested identical tags)
					#  until we locate an unmatched closing tag for the current element
					#
					# once a closing tag is found, everything in between becomes our inner html and is extracted from the original string
					#
					while (preg_match('/<\/{0,1}([A-Za-z][A-Za-z0-9]{0,})\b\s*\b(?:\b[A-Za-z][A-Za-z0-9]{0,}\b(?:\s|=(?:"[^"]{0,}"|[^\s]*))\s*)*\s*\/{0,}[^<]*>/i', $str, $match, PREG_OFFSET_CAPTURE, $offset) == 1) {
						if (strcasecmp($match[1][0], $a['tag']) !== 0) {
							$offset = $match[0][1] + strlen($match[0][0]);
							continue;
						}
						if ((substr($match[0][0], 1, 1) == '/')&&($twin_count > 0)) {
							$offset = $match[0][1] + strlen($match[0][0]);
							$twin_count--;
							continue;
						}
						if (substr($match[0][0], 1, 1) != '/') {
							$offset = $match[0][1] + strlen($match[0][0]);
							$twin_count++;
							continue;
						}
						# if the loop has not continued then we have located the inner html
						$a['inner_html'] = substr($str, $a['position'], $match[0][1] - $a['position']);
						# truncate the string
						$str = substr($str, 0, $a['position']) . substr($str, $match[0][1] + strlen($match[0][0]));
						break;
					}
				}
			}
			
			return $this->_retByRef($a);
		}
		
		public function get_attribute($attr)
		/* return the value of the attribute $attr or false if it is not set
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			if (isset($this->attributes[$attr])) return $this->attributes[$attr];
			
			return false;
		}
		
		public function get_class()
		/* returns this element's class(es) or any empty array if none
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			return $this->class;
		}
		
		public function get_id()
		/* returns this element's id or empty string if none
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			return $this->id;
		}
		
		public function get_child_scope()
		/* return the scope for children of this element
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			# retrieve this element's complete scope
			$p = $this->get_scope();
			
			# retrieve element's id (if any)
			$pId = $this->get_id();
			
			# retrieve element's class(es) (if any)
			$pCl = $this->get_class();
			
			# retrieve element's tag
			$pTg = $this->get_tag();
			
			# preset result to nothing
			$resultArr =& $p;
			
			$resultArr[] = $pTg;
			if (strlen($pId) > 0)
			{
				$resultArr[] = '#' . $pId;
				$resultArr[] = $pTg . '#' . $pId;
			}
			
			foreach($pCl as &$pClass)
			{
				$resultArr[] = '.' . $pClass;
				$resultArr[] = $pTg . '.' . $pClass;
			}
			
			# iterate through each unique parent scope
			foreach($p as &$pTop)
			{
				if (strlen($pTop) > 0)
				{
					$resultArr[] = $pTop . ' ' . $pTg;
					if (strlen($pId) > 0) { $resultArr[] = $pTop . ' ' . $pTg . '#' . $pId; }
					foreach($pCl as &$pClass) { $resultArr[] = $pTop . ' ' . $pTg . '.' . $pClass; }
				}
			}
			
			return $resultArr;
		}
		
		public function get_scope()
		/* returns this element's complete scope
		 *
		 * if the scope is global, returns empty array
		 *	otherwise each valid scope is returned in an array
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			# if there is no parent, scope is global
			if (gettype($this->parent) != 'html_element') return array('');
			
			# combine parent's scope with parent element
			$p = $this->parent->get_scope();
			
			# retrieve parent's id (if any)
			$pId = $this->parent->get_id();
			
			# retrieve parent's class(es) (if any)
			$pCl = $this->parent->get_class();
			
			# retrieve parent's tag
			$pTg = $this->parent->get_tag();
			
			# preset result to nothing
			$resultArr =& $p;
			
			$resultArr[] = $pTg;
			if (strlen($pId) > 0) 
			{
				$resultArr[] = '#' . $pId;
				$resultArr[] = $pTg . '#' . $pId;
			}
			
			foreach($pCl as &$pClass)
			{
				$resultArr[] = '.' . $pClass;
				$resultArr[] = $pTg . '.' . $pClass;
			}
			
			# iterate through each unique parent scope
			foreach($p as &$pTop)
			{
				$resultArr[] = $pTop . ' ' . $pTg;
				if (strlen($pId) > 0) { $resultArr[] = $pTop . ' ' . $pTg . '#' . $pId; }
				foreach($pCl as &$pClass) { $resultArr[] = $pTop . ' ' . $pTg . '.' . $pClass; }
			}
			
			return $resultArr;
		}
		
		public function get_style()
		/* return the inline css style for this element
		 *
		 * returns an array of zero or more items
		 *
		 */
		{
			if (! $this->initialized) return false;
			return $this->style;
		}
		
		public function get_css_style()
		/* return the combined style applied to this element
		 *
		 * style is based upon this element's scope
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			# prep return array
			$arr = array();
			
			# prep scope
			$scope = $this->get_child_scope();
			
			# make sure anything applied to all elements gets retrieved
			array_unshift($scope, '*');
			
			foreach($scope as &$s) {
				$arr = array_merge($arr, $this->css->get_style($s));
			}
			
			return $arr;
		}
		
		public function get_tag()
		/* returns this element's tag
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			return $this->tag;
		}
		
		public function &get_top_parent()
		/* returns the top most parent in any hierarchy
		 *
		 */
		{
			if (gettype($this->parent) == 'html_element') {
				return $this->parent->get_top_parent();
			} else {
				return $this;
			}
		}
		
		protected function init()
		/* initialize the class
		 *
		 */
		{
			# initialize internal variables
			$this->childpreout = "    ";	// another option is "\t" for a tab
			$this->children = array();
			$this->class = array();
			$this->doctype = '';
			$this->expanded = false;
			$this->id = '';
			$this->order = array();
			$this->rorder = array();
			$this->parent = null;
			$this->preoutput = '';
			$this->attributes = array();
			$this->saved = false;
			$this->style = array();
			$this->loaded = false;
			$this->filename = '';
			$this->tag = trim($this->tag);
			$this->value = '';
			$this->initialized = true;
			$this->linebrthreshold = 100;		// the maximum length of a child element to be contained to one line
			#if (! $this->html->_file_loaded) { $this->html->_load(dirname(__FILE__) . '/html4.xml'); }
			return true;
		}
		
		public function inject(&$ele, $pos = false)
		/* inject this element into another as a child
		 *
		 */
		{
			if (! $ele instanceof html_element) return false;
			if (is_string($pos)) {
				$ele->add_child($this, $pos);
			} else {
				$ele->add_child($this);
			}
		}
		
		public function load($file)
		/* load an html file
		 *
		 */
		{
			# validate file name
			$test = stripslashes($file);
			if ($test != $file) return false;
			
			# validate file
			if ( (strlen($file) == 0) || (! file_exists($file)) ) return false;
			
			# set global values
			$this->filename = $file;
			$this->loaded = true;
			$this->saved = true;
			$this->tag = '';
			
			# read the file
			$this->restore(file_get_contents($file));
			
			return true;
		}
		
		public function &match_children($attr, $value, $multiple_results = true)
		/* match all children (grandchildren, et. al.) matching the specified attribute
		 *
		 * if $multiple_results, return an array of 0 or more children, otherwise
		 *	return one child or false
		 *
		 * all results will be returned by reference
		 *
		 * this function matches all nested elements, but not itself
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return $this->false;
			if ( (strlen($attr) == 0) || (strlen($value) == 0) ) return $this->false;
			
			# initialize results array
			$results = array();
			$r = array();
			
			$this->_debug('matching ' . $this->count_children() . " children for `$attr`=`$value`");
			
			# process each child element
			foreach($this->children as &$c) {
				switch (strtolower($attr)) {
					case 'id':
						$this->_debug('matching id against value `' . $c->get_id() . '`');
						if ($c->get_id() === $value) $results[] =& $c;
						break;
					case 'class':
						$this->_debug('matching class against values `' . implode(',', $c->get_class()) . '`... ', true);
						if (array_search($value, $c->get_class()) !== false) { $results[] =& $c; $this->_debug('FOUND ONE'); } else { $this->_debug('no match'); }
						break;
					case 'tag':
						$this->_debug('matching tag');
						if ($c->get_tag() === $value) $results[] =& $c;
						break;
					default:
						$this->_debug('matching attribute ' . $attr);
						if ($c->get_attribute($attr) === $value) $results[] =& $c;
						break;
				}
				$cr = $c->match_children($attr, $value, true);
				if (is_array($cr)) { $results = array_merge_byref($results, $cr); }
				$r = array_merge_byref($r, $results);
			}
			
			if ($multiple_results) { $this->_debug('returning ' . count($r) . ' results in an array'); return $r; }
			if (count($r) == 0) { $this->_debug('no results'); return $this->false; }
			$this->_debug('returning one result');
			return $r[0];
		}
		
		protected function new_child_id()
		/* generate a new unique (for this class) child id
		 *
		 */
		{
			$id = uniqid();
			while(in_array($id, $this->order)) $id = uniqid();
			return $id;
		}
		
		public function restore(&$data, $complete_restore = false)
		/* restore a string form of html into this element
		 *
		 * if complete_restore then process all children and set $this->expanded to true
		 *
		 */
		{
			# check parent mode
			(is_null($this->parent)) ? $parent = true : $parent = false;
			/* TEMP if ($parent) $this->_debug_mode = 99; */
			
			# start debug output
			$this->_debug_start('parent=' . b2s($parent));
			
			# sanity check
			if (! $this->initialized) return $this->_return(false, 'object not initialized');
			if (!is_bool($complete_restore)) $complete_restore = false;
			
			# make sure to remove whitespace
			$data = str_replace(array("\n","\r","\t","\o","\xOB"), '', $data);
			
			# preset expanded
			$this->expanded = $complete_restore;
			
			# prepare search variable
			$search = array();
			
			/* DEBUGGING * $i = 0; $dlen = strlen($data); */
			
			# prepare loop variables
			$restore_str = '';
			$child = '';
			$tmp = '';
			
			while ($search = $this->extract_next_tag($data)) {
				# check for doctype
				if ( ($this->tag == '') && (strtoupper(substr($search['comment'], 0, 8)) == '!DOCTYPE') ) {
					$this->set_doctype($search['comment']);
					$search = $this->extract_next_tag($search['inner_html']);
				}
				
				if ( ($this->tag == '') && ($search['tag'] != '') ) {
					# set the tag
					$this->tag = $search['tag'];
					# set the attributes
					foreach ($search['attributes'] as $attr=>$val) {
						switch(strtolower($attr)) {
							case 'class':
								$tmp = explode(' ', $val);
								while ($this->add_class(array_shift($tmp))) { }
								break;
							case 'id':
								$this->set_id($val);
								break;
							case 'style':
								$this->set_style($val);
								break;
							default:
								$this->set_attribute($attr, $val);
						}
					}
					# set the inner html
					$data = $search['inner_html'];
					# do not restore child elements
					if (!$complete_restore) break;
				} elseif ($search['tag'] != '')  {
					if ($search['inner_html'] == '') {
						$args = array('tag'=>$search['tag'],'css'=>&$this->css,'html'=>&$this->html,'_debug_mode'=>$this->_debug_mode,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
						$child = new html_element($this->_tx, $args);
					} else {
						# found a child element
						$restore_str = '<' . $search['tag'] . '>' . $search['inner_html'] . '</' . $search['tag'] . '>';
						# create the child
						$args = array('tag'=>array($restore_str),'css'=>&$this->css,'html'=>&$this->html,'_debug_mode'=>$this->_debug_mode,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
						$child = new html_element($this->_tx, $args);
					}
					# set any attributes
					foreach ($search['attributes'] as $attr=>$val) {
						switch(strtolower($attr)) {
							case 'class':
								$tmp = explode(' ', $val);
								while ($child->add_class(array_shift($tmp))) { }
								break;
							case 'id':
								$child->set_id($val);
								break;
							case 'style':
								$child->set_style($val);
								break;
							default:
								$child->set_attribute($attr, $val);
						}
					}
					$this->add_child(clone($child), $search['position']);
				} elseif ( ($search['comment'] != '') && ($this->tag == '') ) {
					# found a comment
					$this->tag = $search['comment'];
					$data = $search['inner_html'];
				} elseif ($search['comment'] != '') {
					# found a comment
					$args = array('tag'=>$search['comment'], 'css'=>&$this->css, 'html'=>&$this->html,'_debug_mode'=>$this->child_debug,'_debug_output'=>$this->_debug_output,'_debug_match'=>$this->_debug_match);
					$child = new html_element($this->_tx, $args);
					$this->add_child(clone($child), $search['position']);
				} else {
					$this->_debug("endless loop on tag: " . $search['tag']);
					break;
				}
			}
			# if there is any data left, set it to our value
			if (strlen($data) > 0) $this->set_value($data);
			
			$this->verify_children();
			
			return $this->_return(true);
		}
				
		public function remove_child(html_element &$child)
		/* remove the specified child from this element
		 *
		 * destroys the child element and sets it to false
		 *
		 * returns true on success, false on any failure
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			# get the child's id
			$child_id = $child->who();
			
			# can not continue without an id
			if ($child_id == false) return false;
			
			# can not continue if the provided child is not registered
			if (! isset($this->children[$child_id])) return false;
			
			# remove the element
			unset($this->children[$child_id]);
			unset($this->order[array_search($child_id, $this->order)]);
			
			return true;
		}
		
		public function remove_class($name)
		/* remove named class from this element
		 *
		 * returns true if the provided $name is not in the class list,
		 *	false on failure
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			$key = array_search($name, $this->class);
			
			if ($key !== false) unset($this->class[$key]);
			
			return true;
		}
		
		protected function remove_leading_slash(&$str)
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
		
		protected function remove_trailing_slash(&$str)
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
		
		public function reset($tag)
		/* clear all loaded data and reset to a new object with $tag
		 *
		 */
		{
			/* NOT IMPLEMENTED */
		}
		
		public function sa($name, $value) { return $this->set_attribute($name, $value); }
		public function sas($arr) { return $this->set_attributes($arr); }
		
		public function set_attribute($name, $value)
		/* set an attribute for this element
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			switch(strtolower($name)) {
				case 'id': $this->set_id($value); break;
				case 'class': $this->add_class($value); break;
				case 'html': $this->set_value($value); break;
				case 'style': $this->set_style($value); break;
				default: $this->attributes[$name] = $value; break;
			}
			
			return $this;
		}
		
		public function set_attributes($arr)
		/* set multiple attributes from an array
		 *
		 * array keys should be attribute names
		 * array values should be corresponding values
		 *
		 */
		{
			# sanity checks
			if (! $this->initialized) return false;
			if (! is_array($arr)) return false;
			
			foreach($arr as $k=>$v) {
				switch(strtolower($k)) {
					case 'id': $this->set_id($v); break;
					case 'class': $this->add_class($v); break;
					case 'html': $this->set_value($v); break;
					case 'style': $this->set_style($v); break;
					default: $this->attributes[$k] = $v; break;
				}
			}
			
			return $this;
		}
		
		public function set_debug_mode($level)
		/* set debug mode to specified value 
		 *
		 */
		{
			$this->_debug_mode = $level;
			foreach ($this->children as &$child) {
				$child->set_debug_mode($level - 1);
			}
			return true;
		}
		
		public function set_doctype($str)
		/* set this element's doctype
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			if (strlen($str) == 0) return false;
			
			# remove any leading and trailing brackets
			if ( (substr($str, 0, 1) == '<') && (substr($str, (strlen($str) - 1), 1) == '>') )
			{
				$this->doctype = substr($str, 1, (strlen($str) - 2));
			} else {
				$this->doctype = $str;
			}
			
			return true;
		}
		
		public function set_id($name)
		/* set this element's id to $name
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			if (strlen($name) == 0) return false;
			
			$this->id = $name;
			
			return true;
		}
		
		public function set_identity($whoami, css_document &$css_document)
		/* this function is to be used soley by a new parent element 
		 *	(e.g. from a copying procedure) to provide this element's 
		 *	unique identity in it's context
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			if (strlen($whoami) == 0) return false;
			
			$this->whoami = $whoami;
			$this->css =& $css_document;
			
			return true;
		}
		
		public function set_parent(html_element &$element)
		/* set a reference to the parent of this element to enable
		 *	certain capabilities
		 *
		 */
		{
			if (! $this->initialized) return false;
			$this->parent =& $element;
		}
		
		public function set_preout($str, $multiplier = 1)
		/* set this element's preoutput to the provided string
		 *
		 */
		{
			$this->preoutput = '';
			for ($i = 0; $i < $multiplier; $i++) { $this->preoutput .= $str; }
			# increase child tab spacing if necessary
			if ( (strpos($this->tag, '!') !== 0) && ($this->tag != 'html') ) {
				foreach ($this->children as &$child) {
					$child->set_preout($str, ($multiplier + 1));
				}
			}
			return true;
		}
		
		public function set_style($style, $value = '')
		/* add or override a style or an array of styles
		 *
		 * $style can be a string or an array of styles=>values
		 *
		 * if style is a string it can be either the attribute name or
		 *  a complete css style definition with values
		 *
		 * $value must be a string (0 or more characters)
		 *
		 * returns true on success or false on error
		 *
		 */
		{
			if (! $this->initialized) return false;
			if (! is_string($value)) return false;
			if (! is_array($style)) {
				if ($value == '') {
					 # if value is not set then style is going to be at least one instance of "style: value"
					 $tmp = explode(';', $style);
					 foreach($tmp as $s=>$v) {
					 	 if (strpos($style, ':') === false) continue;
					 	 $s = explode(':', $v);
					 	 if (count($s) == 2) $this->set_style($s[0], $s[1]);
					 }
					 $style = array();
				} else {
					$style = array($style=>$value);
				}
			} else {
				$style = array($style=>$value);
			}
			foreach($style as $s=>$v) {
				if ((!is_string($s))||(!is_string($v))) continue;
				$this->style[trim($s)] = trim($v);
			}
			# maintain alphabetical order for styles
			ksort($this->style);
		}
		
		public function set_tag($str)
		/* set this element's tag to something else
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			if (strlen($str) == 0) return false;
			
			$this->tag = $str;
			
			return true;
		}
		
		public function &set_value($str)
		/* set the optional string value (internal contents) of this element
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return $this->false;
			
			# set the value, replacing special characters with html equivilents
			#$this->value = htmlspecialchars($str);
			$this->value = $str;
			
			return $this;
		}
		
		public function valid_child_classes($tag = '')
		/* returns all valid child classes for the specified tag
		 *	within the scope of this element
		 *
		 * if tag is not provided then only global classes will
		 *	be returned
		 *
		 * tags will be returned in an array
		 *
		 */
		{
			if (! $this->initialized) return false;
			
			$list = $this->css->get_classes($tag, true);
			$completeScope = $this->get_child_scope();
						
			$returnArr = array();

			foreach($completeScope as &$scope)
			{			
				foreach($list['scope'] as $key=>&$item)
				{
					if ( ($scope == $item) || ($item == '') || (substr($item, 0, strlen($scope)) == $scope) )
					{
						$returnArr[$list['classes'][$key]] = '';
					}
				}
			}
			
			return array_keys($returnArr);
		}
		
		public function valid_child_ids($tag = '')
		/* returns all valid child ids for the specified tag
		 *	within the scope of this element
		 *
		 * if the tag is not provided then only global ids will
		 *	be returned
		 *
		 * ids will be returned in an array
		 *
		 */
		{
			if (! $this->initialized) return false;
			
			$list = $this->css->get_ids($tag, true);
			$completeScope = $this->get_child_scope();
			
			$returnArr = array();

			foreach($completeScope as &$scope)
			{			
				foreach($list['scope'] as $key=>&$item)
				{
					if ( ($scope == $item) || ($item == '') || (substr($item, 0, strlen($scope)) == $scope) )
					{
						$returnArr[$list['ids'][$key]] = '';
					}
				}
			}
			
			return array_keys($returnArr);
		}
		
		public function valid_children()
		/* returns all valid child element tags within the scope of
		 *	this element
		 *
		 * tags will be returned in an array
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
			
			$myElement =& $this->html->tags->_getChild(strtolower($this->tag));
					
			//echo "Test Output: function valid_children()\r\n\r\n"; var_dump($myElement); echo "\r\n\r\n";
			//echo "Test Output: function valid_children()\r\n\r\n" . $myElement->children;
			
			# prep output array
			$arr = array();
			
			foreach($myElement->children->_getChildren() as $element) {
				$e = $element->_getName();
				if ($e == 'any') {
					# check conditional "exceptions"
					if ($element->_getAttribute('except')) {
						# get all tags
						$arr = $this->valid_tags();
						# get the exception tags
						$exceptionArr = explode(',', $element->_except);
						# swap the array values for the keys in all tags
						$arr = array_flip($arr);
						# unset all excepttions
						foreach($exceptionArr as &$tag) { unset($arr[$tag]); }
						# return the keys as values
						return array_keys($arr);						
					}
				} elseif ($e == 'none') {
					# check exceptions for none, these are explicit elements
					if ($element->_getAttribute('except')) {
						$arr = explode(',', $element->_except);
					}
				} else {
					# implies there is a list of elements, cache and output each
					$arr[] = $e;
				}
			}
			return $arr;
		}
		
		public function valid_attributes()
		/* returns an array of all valid attributes for this element
		 *
		 */
		{
			if (! $this->initialized) return false;
			$e = $this->html->tags->_getChild(strtolower($this->tag))->attributes->_getChildren();
			$arr = array();		
			foreach($e as &$ele) { $arr[] = $ele->_getName(); }
			return $arr;
		}
		
		protected function valid_tags()
		/* returns all possible tags
		 *
		 */
		{
			# sanity check
			if (! $this->initialized) return false;
						
			# initialize return array
			$arr = array();
						
			foreach($this->html->tags->_getChildren() as $tag)
			{
				$arr[] = $tag->_getName();
			}
			
			return $arr;
		}
		
		protected function verify_children()
		/* function to validate and correct child heirarchy
		 *
		 */
		{
			foreach($this->children as &$c) {
				$c->set_parent($this);
				$c->verify_children();
			}
			return true;
		}
		
		public function who()
		/* returns this element's unique id (if one is set)
		 *
		 * if no id is set, returns false
		 *
		 */
		{
			if ($this->whoami == '') { return false; } else { return $this->whoami; }
		}
						
	} // class html_element

?>