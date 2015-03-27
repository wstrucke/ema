<?php
 /* Unique Pair Object for ema
  * Copyright 2010 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.2.0, Feb-26-2010
  * William Strucke, wstrucke@gmail.com
  *
  */
	
	class unique_pair extends standard_extension
	{
		protected $keys;
		protected $values;
		#protected $key_exceptions;
		#protected $value_exceptions;
		protected $unique_keys;
		#protected $unique_values;
		
		public $_name = 'Unique Pair Object';
		public $_version = '1.2.0';
		protected $_debug_prefix = 'pair';
		protected $_no_debug = true;
		
		public function __get($name) { }		
		public function __set($name, $value) { }
		public function __sleep() { }
		public function __wakeup() { }
		public function __toString() { }
		
		protected function _construct()
		/* initialize unique pair object
		 *
		 */
		{
			# initialize internal variables
			$this->clear();
		}
		
		public function add($key, $value)
		/* add a pair
		 *
		 * the add function is the authority for ensuring all pairs are unique
		 *
		 */
		{
			# if a key or value is empty, internally treat it as a special entity
			if (strlen($key) == 0) $key = '###EMPTY###';
			if (strlen($value) == 0) $value = '###EMPTY###';
			
			# make sure this pair is not already set
			if (! $this->check_pair($key, $value)) { return false; }
			
			# set this pair
			if (isset($this->unique_keys[$key]))
			{
				array_push($this->unique_keys[$key], $value);
			} else {
				$this->unique_keys[$key] = array($value);
			}
			
			array_push($this->keys, $key);
			array_push($this->values, $value);
			
			return true;
		}
		
		public function add_key_exception($key)
		/* add a key exception
		 *
		 */
		{
		
		}
		
		public function add_value_exception($value)
		/* add a value exception
		 *
		 */
		{
		
		}
		
		protected function check_pair($key, $value)
		/* check if the unique pair of $key,$value is already set
		 *
		 * if it is, return false, if not return true
		 *
		 */
		{
			if (isset($this->unique_keys[$key]))
			{
				# there are multiple values corresponding to this key
				foreach ($this->unique_keys[$key] as $chk)
				{
					if ($chk == $value) { return false; }
				}
			}
			return true;
		}
		
		public function clear()
		/* clear and reset the object
		 *
		 */
		{
			# (re)initialize internal variables
			$this->keys = array();
			$this->values = array();
			#$key_exceptions = array();
			#$value_exceptions = array();
			$this->unique_keys = array();
			#$unique_values = array();
		}
		
		public function flip()
		/* swap the keys and values arrays
		 *
		 */
		{
			$tmp = $this->keys;
			$this->keys = $this->values;
			$this->values = $tmp;
			return true;
		}
		
		public function get_keys()
		/* return all keys
		 *
		 */
		{
			# create return array
			$ret = array();
			
			# remove any internal references
			foreach ($this->keys as $k)
			{
				if ($k == '###EMPTY###')
				{
					array_push($ret, '');
				} else {
					array_push($ret, $k);
				}
			}
			
			return $ret;
		}
		
		public function get_pairs($pseudo_duplicates = false, $split_after_match_character = false)
		/* return all pairs
		 *
		 * optionally this function can perform additional checking on unique
		 *  pairs to ignore after a matching character and consider otherwise
		 *  unique matches duplicates and remove them.
		 *
		 *  to use this function, both $pseudo_duplicates and $split_after_match_character
		 *  must be provided
		 *
		 * for example, if pseude duplicates == 'values' and split character == '.', 
		 *  this function will remove everything at and after every value matching
		 *  '.' and return those unique pairs
		 *
		 *  if we have two keys: 'one', 'one'
		 *  and two values 'value', 'value.alpha'
		 *
		 *  the second pair will be removed since 'value' and 'value' now match
		 *
		 */
		{
			if ($pseudo_duplicates && $split_after_match_character)
			{
				$all = array('keys'=>$this->get_keys(),'values'=>$this->get_values());
				$new = new unique_pair($this->_tx);
				foreach($all['keys'] as $k=>$v)
				{
					if ($pseudo_duplicates == 'keys')
					{
						$pos = strpos($v, $split_after_match_character);
						if ($pos !== false) $new->add(substr($v, 0, $pos), $all['values'][$k]);
					} else {
						$pos = strpos($all['values'][$k], $split_after_match_character);
						if ($pos !== false) $new->add($v, substr($all['values'][$k], 0, $pos));
					}
				}
				return $new->get_pairs();
			}
			# return everything
			return array('keys'=>$this->get_keys(),'values'=>$this->get_values());
		}
		
		public function get_values()
		/* return all values
		 *
		 */
		{
			# create return array
			$ret = array();
			
			# remove any internal references
			foreach ($this->values as $v)
			{
				if ($v == '###EMPTY###')
				{
					array_push($ret, '');
				} else {
					array_push($ret, $v);
				}
			}
			
			return $ret;
		}
		
		public function is_pair($key, $value)
		/* return true if the provided $key/$value is a registered pair
		 *
		 */
		{
			return (! $this->check_pair($key, $value));
		}
		
	}
?>