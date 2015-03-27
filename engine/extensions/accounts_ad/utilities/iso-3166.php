<?php
 /*	ISO-3166 Country Codes Utility Function
	*	
	*	Revision 1.0.0, Apr-20-2010
	*	  - created function
	*
	*	William Strucke, wstrucke@gmail.com
	*
	* Provides the following functions:
	*
	* -------------------------------------------------------
	*  iso3166_getCountry( $query = 'United States' )
	*
	*	   Given a country name, 2 character alphanumeric code,
	*      or 3 digit numeric code, return an array with the
	*      country information.
	*
	*    If no country is specified, return United States
	*
	* -------------------------------------------------------
	*  iso3166_getCountries( $all_data = false )
	*
	*    Also provides a function to simply return all
	*      possible countries OR all countries with 2
	*      character, 3 character, or 3 digit codes.
	*
	*    By default, return just country names
	*
	* -------------------------------------------------------
	*
	* Data Source (retrieved 04/20/2010 ws):
	*  http://www.unc.edu/~rowlett/units/codes/country.htm
	*
	*/

if (!function_exists('iso3166_getCountry')) {
	function iso3166_getCountry($query = 'United States') {
		# get the country list
		$c = iso3166_getCountries(true);
		
		for ($i=0;$i<count($c);$i++) {
			if (strcasecmp($c[$i][0], $query) == 0) break;
			if (strcasecmp($c[$i][1], $query) == 0) break;
			if ($c[$i][6] == $query) break;
		}
		
		# if $i is equal to the count then we did not find anything
		if ($i == count($c)) return false;
		
		return array(
			'name'=>$c[$i][0],
			'iso2-alpha'=>$c[$i][1],
			'iso3-alpha'=>$c[$i][2],
			'iana'=>$c[$i][3],
			'un'=>$c[$i][4],
			'ioc'=>$c[$i][5],
			'iso-numeric'=>$c[$i][6],
			'itu'=>$c[$i][7]
		);
	}
}

if (!function_exists('iso3166_getCountries')) {
	function iso3166_getCountries($all_data = false) {
		/* data format:
		     0 => Country Name
		     1 => ISO 2-alpha
		     2 => ISO 3-alpha
		     3 => IANA Internet
		     4 => UN Vehicle
		     5 => IOC Olympic
		     6 => UN/ISO numeric
		     7 => ITU calling
		*/
		
		# define the data array
		$c = array(
			array('AFGHANISTAN', 'AF', 'AFG', '.af', 'AFG', 'AFG', '004', '93'),
			array('LAND ISLANDS', 'AX', 'ALA', '.ax', null, null, '248', null),
			array('ALBANIA', 'AL', 'ALB', '.al', 'AL', 'ALB', '008', '355'),
			array('ALDERNEY', null, null, null, 'GBA', null, null, null),
			array('ALGERIA', 'DZ', 'DZA', '.dz', 'DZ', 'ALG', '012', '213'),
			array('AMERICAN SAMOA', 'AS', 'ASM', '.as', null, 'ASA', '016', '1-684'),
			array('ANDORRA', 'AD', 'AND', '.ad', 'AND', 'AND', '020', '376'),
			array('ANGOLA', 'AO', 'AGO', '.ao', null, 'ANG', '024', '244'),
			array('ANGUILLA', 'AI', 'AIA', '.ai', null, null, '660', '1-264'),
			array('ANTARCTICA', 'AQ', 'ATA', '.aq', null, null, '010', null),
			array('ANTIGUA AND BARBUDA', 'AG', 'ATG', '.ag', null, 'ANT', '028', '1-268'),
			array('ARGENTINA', 'AR', 'ARG', '.ar', 'RA', 'ARG', '032', '54'),
			array('ARMENIA', 'AM', 'ARM', '.am', 'AM', 'ARM', '051', '7'),
			array('ARUBA', 'AW', 'ABW', '.aw', null, 'ARU', '533', '297'),
			array('ASCENSION ISLAND', null, null, '.ac', null, null, null, '247'),
			array('AUSTRALIA', 'AU', 'AUS', '.au', 'AUS', 'AUS', '036', '61'),
			array('AUSTRIA', 'AT', 'AUT', '.at', 'A', 'AUT', '040', '43'),
			array('AZERBAIJAN', 'AZ', 'AZE', '.az', 'AZ', 'AZE', '031', '994'),
			array('BAHAMAS', 'BS', 'BHS', '.bs', 'BS', 'BAH', '044', '1-242'),
			array('BAHRAIN', 'BH', 'BHR', '.bh', 'BRN', 'BRN', '048', '973'),
			array('BANGLADESH', 'BD', 'BGD', '.bd', 'BD', 'BAN', '050', '880'),
			array('BARBADOS', 'BB', 'BRB', '.bb', 'BDS', 'BAR', '052', '1-246'),
			array('BELARUS', 'BY', 'BLR', '.by', 'BY', 'BLR', '112', '375'),
			array('BELGIUM', 'BE', 'BEL', '.be', 'B', 'BEL', '056', '32'),
			array('BELIZE', 'BZ', 'BLZ', '.bz', 'BH', 'BIZ', '084', '501'),
			array('BENIN', 'BJ', 'BEN', '.bj', 'DY', 'BEN', '204', '229'),
			array('BERMUDA', 'BM', 'BMU', '.bm', null, 'BER', '060', '1-441'),
			array('BHUTAN', 'BT', 'BTN', '.bt', null, 'BHU', '064', '975'),
			array('BOLIVIA', 'BO', 'BOL', '.bo', 'BOL', 'BOL', '068', '591'),
			array('BOSNIA AND HERZEGOVINA', 'BA', 'BIH', '.ba', 'BIH', 'BIH', '070', '387'),
			array('BOTSWANA', 'BW', 'BWA', '.bw', 'BW', 'BOT', '072', '267'),
			array('BOUVET ISLAND', 'BV', 'BVT', '.bv', null, null, '074', null),
			array('BRAZIL', 'BR', 'BRA', '.br', 'BR', 'BRA', '076', '55'),
			/* we'll add the middle section if we ever need it */
			array('UNITED ARAB EMIRATES', 'AE', 'ARE', '.ae', null, 'UAE', '784', '971'),
			array('UNITED KINGDOM', 'GB', 'GBR', '.uk', null, 'GBR', '826', '44'),
			array('UNITED STATES', 'US', 'USA', '.us', 'USA', 'USA', '840', '1'),
			array('UNITED STATES MINOR OUTLYING ISLANDS', 'UM', 'UMI', '.um', null, null, '581', null),
			array('URUGUAY', 'UY', 'URY', '.uy', 'ROU', 'URU', '858', '598'),
			array('UZBEKISTAN', 'UZ', 'UZB', '.uz', 'UZ', 'UZB', '860', '998'),
			array('VANUATU', 'VU', 'VUT', '.vu', null, 'VAN', '548', '678'),
			array('VATICAN CITY', 'VA', 'VAT', '.va', 'V', 'VAT', '336', '379'),
			array('VENEZUELA', 'VE', 'VEN', '.ve', 'YV', 'VEN', '862', '58'),
			array('VIET NAM', 'VN', 'VNM', '.vn', 'VN', 'VIE', '704', '84'),
			array('VIRGIN ISLANDS, BRITISH', 'VG', 'VGB', '.vg', 'BVI', 'IVB', '92', '1-284'),
			array('VIRGIN ISLANDS', 'VI', 'VIR', '.vi', null, 'ISV', '850', '1-340'),
			array('YUGOSLAVIA', null, null, '.yu', null, null, null, null),
			array('WALLIS AND FUTUNA', 'WF', 'WLF', '.wf', null, null, '876', '681'),
			array('WESTERN SAHARA', 'EH', 'ESH', '.eh', null, null, '732', null),
			array('YEMEN', 'YE', 'YEM', '.ye', 'YAR', 'YEM', '887', '967'),
			array('ZAMBIA', 'ZM', 'ZMB', '.zm', 'RNR', 'ZAM', '894', '260'),
			array('ZANZIBAR', null, null, null, 'EAZ', null, null, null),
			array('ZIMBABWE', 'ZW', 'ZWE', '.zw', 'ZW', 'ZIM', '716', '263')
		);
		
		# if all data was requested, return the array
		if ($all_data) return $c;
		
		# otherwise, strip out the country names and return them
		$ret = array();
		foreach ($c as $co) { $ret[] = $co[0]; }
		
		return $ret;
	}
}

?>