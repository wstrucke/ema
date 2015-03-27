<?php
 /* Forms Extension for ema
  * Copyright 2011 William Strucke [wstrucke@gmail.com]
  * All Rights Reserved
  *
  * Revision 1.0.0, Apr-08-2011
  * William Strucke, wstrucke@gmail.com
  *
  * to do:
  * - spec/implement
  *
  */

class forms_basic extends standard_extension
{
	# standard extension variables
	protected $_debug_prefix = 'forms';
	public $_name = 'Forms Extension';
	public $_version = '1.0.0';
	
	# published variables
	
	# published settings
	
	# database version
	public $schema_version='0.1.0'; // the schema version to match the registered schema
	
	# internal variables
	
	# code
	
	public function code_temp($form_name)
	/* temp function to generate and store the verify code
	 *
	 */
	{
		# generate the verification code
		$code = uniqid('', true);
		# get the form id from the name
		$form_meta = $this->_tx->db->query('form_index', array('name','enabled'), array($form_name,true), array('id'));
		if (!db_qcheck($form_meta)) return false;
		# insert the record
		$this->_tx->db->insert(
			'form_verify',
			array('form_id', 'code', 'ipv4', 'expires'),
			array($form_meta[0]['id'], $code, $_SERVER['REMOTE_ADDR'], 'NOW() + INTERVAL 1 DAY'));
		# return the code
		return $code;
	}
	
	public function form($args = false)
	/* generate and return a form
	 *
	 * currently supports:
	 *  n/a
	 *
	 * minimum required fields:
	 *  n/a
	 *
	 * arguments should be an array or false
	 *
	 */
	{
		# basic input validation
		if (($args !== false)&&(!is_array($args))) return false;
		
		
	}
	
	protected function form_validate($id)
	/* validate the input for the specified form
	 *
	 */
	{
		# load the form data
		$form_meta = $this->_tx->db->query('form_index', array('id'), array($id), array('name'));
		if (!db_qcheck($form_meta)) return false;
		# temporarily build the field list based on the form name
		switch($form_meta[0]['name']){
			case 'buckeye_invitational':
				$fields = array('school','director','email','phone','number','auxiliary','class','omea');
				$required = array(
					'school'=>'Name of School',
					'director'=>'Name of Director',
					'email'=>'E-mail Address',
					'phone'=>'Telephone Contact Number',
					'number'=>'Size of Band',
					'class'=>'Festival/Competition Class',
					'omea'=>'OMEA Registration Verification'
					);
				$registration = 'buckeye-registration';
				$reg_subject = 'Buckeye Invitational Registration';
				$response = 'buckeye-response';
				$resp_subject = 'Buckeye Invitational Registration Confirmation';
				$client_address = $_POST['director'] . " <" . $_POST['email'] . ">";
				$confirmation = 'buckeye-confirm';
				break;
			case 'junior_senior_night':
				$fields = array('first','middle','last','city','state','zip','telephone','email','hs','hs_year',
					'attending','guests','tshirt_size','part','instrument','other','otherText');
				$required = array(
					'first'=>'First Name',
					'last'=>'Last Name',
					'street'=>'Street Address',
					'city'=>'City',
					'state'=>'State',
					'zip'=>'ZIP Code',
					'telephone'=>'Telephone Contact Number',
					'email'=>'E-mail Address',
					'hs'=>'Graduation High School',
					'hs_year'=>'Year of HS Graduation',
					'attending'=>'Are you attending Junior/Senior night this year?',
					'guests'=>'How many guests are you brining? (Enter 0 if you are attending by yourself)',
					'tshirt_size'=>'What is your T-Shirt size?'
					);
				$registration = 'jsnight-registration';
				$reg_subject = 'Junior/Senior Night Registration';
				$response = 'jsnight-response';
				$resp_subject = 'Junior/Senior Night Registration Confirmation';
				$client_address = $_POST['first'] . " " . $_POST['last'] . " <" . $_POST['email'] . ">";
				$confirmation = 'jsnight-confirm';
				break;
			case 'register_for_tryouts':
				$fields = array('first','middle','last','city','state','zip','telephone','email','hs','musical_experience',
					'instrument','part','tryouts','housing','info','schedule','loan','video');
				$required = array(
					'first'=>'First Name',
					'last'=>'Last Name',
					'city'=>'City',
					'state'=>'State',
					'zip'=>'ZIP Code',
					'telephone'=>'Telephone Contact Number',
					'email'=>'E-mail Address',
					'hs'=>'Year of HS Graduation',
					'musical_experience'=>'Please describe your musical experience',
					'instrument'=>'What instrument do you play?',
					'part'=>'What part do you play on your instrument?',
					'tryouts'=>'Will you attend tryout week and audition for the band?',
					'housing'=>'Do you want us to send you tryout housing information?',
					'info'=>'Do you need a free copy of the school songs?',
					'schedule'=>'Do you need the summer session practice schedule?',
					'loan'=>'Do you need to sign out an OSU instrument for the summer?',
					'video'=>'Would you like a free marching fundamentals DVD?'
					);
				$registration = 'tryout-registration';
				$reg_subject = 'Web Tryout Registration';
				$response = 'tryout-response';
				$resp_subject = 'OSUMB Tryout Registration Confirmation';
				$client_address = $_POST['first'] . " " . $_POST['last'] . " <" . $_POST['email'] . ">";
				$confirmation = 'tryout-confirm';
				break;
			default:
				message('An error has occurred processing your submissions', 'error');
				return false;
		}
		
		$errorFields = array();
		foreach($required as $f=>$value) {
			if (@strlen($_POST[$f])==0) {
				$errorFields[] = $f;
			}
		}
		
		# email validation
		if (!is_valid_email_address(@$_POST['email'])) {
			$errorFields[] = 'emailv';
			$required['emailv'] = 'The e-mail address you provided is not valid.';
		}
		
		# custom verification
		if ($form_meta[0]['name'] == 'junior_senior_night') {
			if (@$_POST['other'] == '1') {
				if (strlen(@$_POST['otherText'])==0) {
					$errorFields[] = 'instrument';
					$required['instrument'] = 'What instrument do you play?';
				}
			} else {
				if (@strlen($_POST['instrument'])==0) {
					$errorFields[] = 'instrument';
					$required['instrument'] = 'What instrument do you play?';
				}
				if (@strlen($_POST['part'])==0) {
					$errorFields[] = 'part';
					$required['part'] = 'What part do you play on your instrument?';
				}
			}
			if (@in_array($_POST['instrument'], array('Snare Drum','Bass Drum','Quads','Cymbals'))) {
				array_remove($errorFields, 'part', true);
			}
		}
		
		if ($form_meta[0]['name'] == 'register_for_tryouts') {
			if (@in_array($_POST['instrument'], array('Snare Drum','Bass Drum','Quads','Cymbals','Drum Major'))) {
				array_remove($errorFields, 'part', true);
			}
		}
		
		if (count($errorFields)>0) {
			$str = 'Please correct or enter a value for the following fields and try again:<br /><ul>';
			for($i=0;$i<count($errorFields);$i++){$str.='<li>'.$required[$errorFields[$i]].'</li>';}
			$str .= '</ul>';
			message($str, 'error');
			return buffer($this->_myloc . "/legacy_forms/" . $form_meta[0]['name'] . ".php");
		}
		
		$bcc = 'strucke.1@osu.edu,waters.33@osu.edu';
		$this->legacy_mail($client_address,"OSU Marching and Athletic Bands <osumb@osu.edu>",$reg_subject,$this->legacy_template($registration),$bcc);
		$this->legacy_mail("OSU Marching and Athletic Bands <osumb@osu.edu>",$client_address,$resp_subject,$this->legacy_template($response),$bcc);
		
		return $this->_content($confirmation);
	}
	
	public function legacy_mail($from, $recipient, $subject, $message_body, $bcc = '')
	/* send an e-mail
	 *
	 */
	{
		/* To send HTML mail, you can set the Content-type header. */
		$header  = "MIME-Version: 1.0\r\n";
		$header .= "Content-type: text/html; charset=iso-8859-1\r\n";
		$header .= "From: $from\r\n";
		if (strlen($bcc) > 0) $header .= "Bcc: $bcc\r\n";
		$header .= "X-Mailer: ema forms module, elegant modular applications version 2.0.0-alpha-1\r\n";
		$header .= "X-Source: OSU Marching & Athletic Bands, tbdbitl.osu.edu\r\n";
		$header .= "X-AntiAbuse: This message was composed from IP " . $_SERVER['REMOTE_ADDR'] . "\r\n";
		
		return mail($recipient, $subject, $message_body, $header);
	}
	
	public function legacy_template($name)
	/* populate the specified legacy template with post vars and return the completed string
	 *
	 */
	{
		if (!file_exists(dirname(__FILE__) . "/legacy_templates/$name.htm")) return false;
		$content = buffer(dirname(__FILE__) . "/legacy_templates/$name.htm", false);
		
		# replace conditional variables
		if (@$_POST['tryouts'] == '1') { $tryouts = '<strong>will</strong>'; } else { $tryouts = 'will not'; }
		$content = str_replace("#tryout_week#", $tryouts, $content);
    if (@$_POST['housing'] == '1') { $housing = '<strong>Needs</strong>'; } else { $housing = 'Does not need'; }
    $content = str_replace("#housing#", $housing, $content);
    if (@$_POST['info'] == '1') { $info = '<strong>Needs</strong>'; } else { $info = 'Does not need'; }
    $content = str_replace("#music#", $info, $content);
    if (@$_POST['schedule'] == '1') { $schedule = '<strong>Needs</strong>'; } else { $schedule = 'Does not need'; }
    $content = str_replace("#schedule#", $schedule, $content);
    if (@$_POST['sinst'] == '1') { $loan = '<strong>Needs</strong>'; } else { $loan = 'Does not need'; }
    $content = str_replace("#loan#", $loan, $content);
    if (@$_POST['video'] == '1') { $video = '<strong>Wants to buy</strong>'; } else { $video = 'Does not want'; }
    $content = str_replace("#video#", $video, $content);
    if (@$_POST['tryouts'] == '1') { $tryouts = '<strong>will</strong>'; } else { $tryouts = 'will not'; }
    $content = str_replace("#tryouts#", $tryouts, $content);
    if (@$_POST['attending'] == '1') { $attending = '<strong>Will</strong>'; } else { $attending = 'Will Not'; }
    $content = str_replace("#attending#", $attending, $content);
    
		# replace special variables
		$content = str_replace("#time#", date('F jS, Y ') . 'at ' . date('h:i A ') . '(EDT)', $content);
		$content = str_replace("#ip#", $_SERVER['REMOTE_ADDR'], $content);
		$content = str_replace("#host#", @$_SERVER['REMOTE_HOST'], $content);
		$content = str_replace("#agent#", $_SERVER['HTTP_USER_AGENT'], $content);
		$content = str_replace("#year#", '2012', $content);
		$content = str_replace("#nextyear#", '2013', $content);
		$content = str_replace("#date#", date('F jS, Y '), $content);
		$content = str_replace("#deadline#", 'June 1<sup>st</sup> 2012', $content);
		$content = str_replace("#buckeye_event_date#", 'October 13, 2012', $content);
		$content = str_replace("#jsnight_event_date#", 'Sunday, April 22, 2012 from 3:00 pm to 7:00 pm.', $content);
		$content = str_replace("#ssn#", 'DATA NOT COLLECTED (FERPA)', $content);
		
		# replace post variables
		foreach ($_POST as $k=>$v){ $content = @str_replace("#$k#", $_POST[$k], $content); }
					
		return $content;
	}
	
	public function verify_cleanup()
	/* clean up old verify codes
	 *
	 */
	{
		$this->_tx->db->delete('form_verify', array('expires'), array('<= NOW()'));
	}
	
	public function web_form($name)
	/* output the specified web form if it exists
	 *
	 * handle form posts
	 *   verify input
	 *   optionally send emails
	 *   display response
	 *
	 */
	{
		# for now use the temporary implementation
		return $this->web_form_temp($name);
		return false;
	}
	
	public function web_form_post()
	/* system form post handler
	 *
	 */
	{
		
	}
	
	protected function web_form_temp($name)
	/* temporary forms implementation
	 *
	 * use basic form protection
	 *
	 */
	{
		if (@strlen($name) == 0) return false;
		# load the form from the database
		$form_meta = $this->_tx->db->query('form_index', array('name','enabled'), array($name,true), array('id'));
		if (!db_qcheck($form_meta)) return false;
		# clean up stale records
		$this->verify_cleanup();
		# check if a form was posted
		if (array_key_exists('form_verify', $_POST)) {
			# validate the id string
			$check = $this->_tx->db->query(
				'form_verify',
				array('form_id', 'code', 'ipv4'),
				array($form_meta[0]['id'], $_POST['form_verify'], $_SERVER['REMOTE_ADDR']));
			if (!db_qcheck($check)) {
				message('We\'re sorry, the form you posted could not be verified. Please try submitting again or contact us for assistance.', 'error');
			} else {
				# remove the one time code
				$this->_tx->db->delete(
					'form_verify',
					array('form_id', 'code', 'ipv4'),
					array($form_meta[0]['id'], $_POST['form_verify'], $_SERVER['REMOTE_ADDR']));
				return $this->form_validate($form_meta[0]['id']);
			}
		}
		# verify the path exists
		if (!file_exists(dirname(__FILE__) . "/legacy_forms/$name.php")) return false;
		return buffer($this->_myloc . "/legacy_forms/$name.php");
	}
}
?>