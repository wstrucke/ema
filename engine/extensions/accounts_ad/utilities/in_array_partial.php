<?php
 /*	check if the requested string is part of a string in the array
 	*
 	*	version 1.1.0, mar-24-2011 ws
 	*
 	* required:
 	*   $needle            the value to check for
 	*   $haystack          the array to check
 	*
 	* optional:
 	*   $start_position    if provided, an integer representing the position
 	*                      in each haystack item to check for the needle
 	*                      (this really only makes sense for strings)
 	*   $strict            if true, match the type in addition to the value
 	*   $case_sensitive    if true (default), match the case of string values
 	*
 	*/
	
	# conditionally define the function
	if (! function_exists('in_array_partial'))
	{
		function in_array_partial($needle, $haystack, $start_position = false, $strict = false, $case_sensitive = true)
		{
			if (! is_array($haystack)) return false;
			# preset match to false
			$match = false;
			# case sensitivity check
			if ((!$case_sensitive)&&is_string($needle)) $needle = strtolower($needle);
			# check needle against each array item
			foreach($haystack as $value) {
				# for strings, check the length first to improve performance
				if ($strict&&(is_string($needle))&&(strlen($needle) > strlen($value))) continue;
				if ((!$case_sensitive)&&is_string($value)) $value = strtolower($value);
				# boolean checks are fast
				if (is_bool($needle)) {
					if ($strict && ($needle === $value)) {
						$match = true; break;
					} elseif ($needle == $value) {
						$match = true; break;
					} else {
						continue;
					}
				}
				# integer checks are fast too
				if (is_integer($needle)) {
					if ($strict && is_integer($value)) {
						if ($needle === $value) {
							$match = true; break;
						} elseif (strlen($needle) > strlen($value)) {
							continue;
						} else {
							$pos = strpos((string)$value, (string)$needle);
							if ($pos === false) continue;
							if (($start_position !== false)&&($pos !== $start_position)) continue;
							$match = true; break;
						}
					}
				}
				if ($start_position !== false) {
					if (strlen($needle) <= (strlen($value)-$start_position)) {
						$value = substr($value, $start_position, strlen($needle));
						if ($strict) {
							if ($needle === $value) {
								$match = true; break;
							} else {
								continue;
							}
						} else {
							if ($needle == $value) {
								$match = true; break;
							} else {
								continue;
							}
						}
					} else {
						continue;
					}
				}
				if (strpos($value, $needle) !== false) {
					$match = true; break;
				}
			}
			# return match value
			return $match;
		}
	}
	
?>