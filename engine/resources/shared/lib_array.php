<?php
 /*	ema shared library :: array support functions
	*
	* Version 1.0 : 2009.03.02
	*
	*	William Strucke [wstrucke@gmail.com]
	*
	*/
	
	function &array_merge_byref(&$a, &$b)
	/* merge the elements in two arrays by reference
	 *
	 * returns a new, combined array without altering either input array
	 *
	 * if either value is not an array, returns $a
	 *
	 * if the keys are numeric, $b is appended to $a, otherwise
	 *  if the keys are identical $b overwrites $a
	 *
	 */
	{
		if ((!is_array($a))||(!is_array($b))) return $a;
		$c = Array();
		foreach($a as $k=>&$v) { $c[$k]=&$v; }
		foreach($b as $k=>&$v) {
			if (is_numeric($k)) { $c[] =& $v; } else { $c[$k]=&$v; }
		}
		return $c;
	}
	
	function array_merge_keys(array &$array1, array $array2 = NULL)
	/* merge two arrays, maintaining keys
	 *
	 */
	{
		foreach($array2 as $k=>$v) {
			if (array_key_exists($k, $array1)) {
				if (is_array($v)&&is_array($array1[$k])) {
					array_merge_keys($array1[$k], $v);
				} else {
					$array1[$k] = $v;
				}
			} else {
				$array1[$k] = $v;
			}
		}
	}
	
	function array_multi_reduce(&$a, $key = 0)
	/* given a multi-dimensional array $a, return a new array with only the specified keys
	 *
	 * for example:
	 *   given array(0=>array('a', 'b'),1=>array('c','d')) and key 0
	 *   returns array('a','c')
	 *
	 *   given array(0=>array('name'=>'frank','age'=>25),1=>array('name'=>ted','age'=>30)) and key 'name'
	 *   returns array('frank','ted')
	 *
	 * this function does not alter the input array
	 *
	 * always returns an array
	 *
	 */
	{
		if (!is_array($a)) return array();
		$result = array();
		foreach($a as &$b) {
			if (!is_array($b)) continue;
			if (array_key_exists($key, $b)) $result[] = $b[$key];
		}
		return $result;
	}
	
	function array_multi_search($needle, &$haystack, $strict = false)
	/* given a multi-dimensional array, look for a child with a matching needle
	 *
	 * needle can optionally be an array in which case the key can be matched as well
	 *
	 * returns the key of the array if a match is found, false if not
	 *
	 * for example given the array:
	 *    arr( 0 => array(0=>'ted', 1=>'sally'),
	 *         1 => array(0=>'fred', 1=>'barry')
	 *       )
	 *
	 * and looking for needle 'fred' this function would return 1 since the second array
	 *   has a matching needle.  the position of the matching needle in the matched
	 *   array is *not* returned
	 *
	 */
	{
		$match = false;
		if (is_array($needle)) {
			reset($needle);
			$match_key = key($needle);
			$match_value = $needle[$match_key];
		} else {
			$match_key = false;
			$match_value = $needle;
		}
		for ($i=0;$i<count($haystack);$i++) {
			foreach($haystack[$i] as $k=>&$v) {
				if ((($match_key === false)&&($haystack[$i][$k] === $v))||(($match_key === $k)&&($haystack[$i][$k] === $v))) {
					$match = $k; break;
				}
			}
		}
		return $match;
	}
	
	function array_remove(&$arr, $value, $discard_keys = false, $level = 0)
	/* remove the specified element from the provided array
	 *
	 * this function is distinct from the php function "unset" in that it operates
	 *	on array values, not keys
	 *
	 * value can optionally be an array in which case the key can be matched as well
	 *
	 * if level is greater than zero, operate on multi-dimensional array.
	 *   that means the function will locate the value or key=>value pair at the specified
	 *   level and remove the *top level* array
	 *
	 * to remove only an element in a multi-dimensional array, call this function
	 *   on the specific child array instead
	 *
	 * this function alteris the original array, replacing it with a copy without the
	 *  matched element
	 *
	 * ONLY ONE ELEMENT WILL BE REMOVED, even if there are multiple matches
	 *
	 * if an element is matched and removed, returns true, otherwise returns false
	 *
	 */
	{
		if (!is_integer($level)) $level = 0;
		$return = array();
		$match_found = false;
		if (is_array($value)) {
			reset($value);
			$match_key = key($value);
			$match_value = $value[$match_key];
		} else {
			$match_key = false;
			$match_value = $value;
		}
		foreach($arr as $k=>&$v) {
			if ($match_found) { if ($discard_keys) { $return[] = $v; } else { $return[$k] = $v; } continue; }
			if ($level > 0) {
				if (array_remove($v, $value, $discard_keys, ($level - 1)) === true) { $match_found = true; continue; }
				if ($discard_keys) { $return[] = $v; } else { $return[$k] = $v; }
			} else {
				if ($match_key !== false) {
					if (($match_key === $k)&&($match_value === $v)) { $match_found = true; continue; }
					if ($discard_keys) { $return[] = $v; } else { $return[$k] = $v; }
				} else {
					if ($match_value === $v) { $match_found = true; continue; }
					if ($discard_keys) { $return[] = $v; } else { $return[$k] = $v; }
				}
			}
		}
		$arr = $return;
		return $match_found;
	}
	
?>