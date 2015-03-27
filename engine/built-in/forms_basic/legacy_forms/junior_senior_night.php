<?php
$script = <<<END

	<!--
	  function capitalizeFirst(field) {
	    // capitalize first letter
	    try {
	      temp = field.value;
	      if (temp.length > 1) {
	        letter = Left(temp, 1);
					field.value = letter.toUpperCase() + Right(temp, (temp.length - 1));
	        }
	      }
	    catch (e) {
	      alert('Please notify the webmaster of the following error and anything you just did: ' + e);
	      }
	    return true;
	    }
		function checkEnable(value) {
			// enable other field if true, disable if false
			try {
				document.registration.otherText.disabled = value;
				if (value) { 
					document.registration.otherText.style.background = "#eeeeee"; 
					document.registration.otherText.value = "";
					} else {
					document.registration.otherText.style.background = "white";
					document.registration.otherText.focus();
					}
				}
			catch (e) {
				alert('Please notify the webmaster of the following error and anything you just did: ' + e);
				}
			return true;
			}
	  function validateTelephone(field) {
	    // validate a telephone number, add dashes
	    try {
	      temp = field.value;
	      if (temp.length == 7) { temp = '614' + temp; }
	      if (temp.length == 8) { field.value = '614-' + temp; }
	      if ((temp.length == 11) && (Left(temp, 1) == '1')) { temp = Right(temp, 10); }
	      if ((temp.indexOf("-") == -1) && (temp.length == 10)) {
	        middle = '-' + Left(Right(temp, 7), 3) + '-';
	        field.value = Left(temp, 3) + middle + Right(temp, 4);
	        }
	      }
	    catch (e) {
	      alert('Please notify the webmaster of the following error and anything you just did: ' + e);
	      }
	    return true;
	    }
	-->

END;

$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','language'=>'javascript'))->set_value($script);

?>

<h1>Junior/Senior Night 2012 Registration</h1>

<div style="margin: 10px 0px; line-height: 1.5em;">
  We need to hear from you soon to reserve your place at this year's Junior/Senior Night!<br />
	<em>Please submit this form by the 1st of April to ensure you are registered.</em>

</div>
		
<h3>2012 Junior/Senior Night Information</h3>

<p><strong>Who:</strong> Open to all prospective High School Juniors and Seniors interested 
	in joining the OSU Marching and Athletic Bands (Brass-Woodwinds-Percussion)</p>
<p><strong>What:</strong> The Ohio State University Marching & Athletic Bands 2012 Junior/Senior Night</p>
<p><strong>Where:</strong> The Joan Zeig Steinbrenner Band Center, in the Ohio Stadium (enter 
		through the glass doors located at gate 10)</p>

<p><strong>When:</strong> Sunday, April 22, 2012 from 3:00 pm to 7:00 pm</p>
<p><strong>Why:</strong> To encourage High School Junior and Senior musicians to continue their 
		talent with The Ohio State University Marching and Athletic Bands.</p>
<p><strong>What to Bring:</strong> Yourself and your instrument (you may check out an instrument 
		if you do not have one). Parents are invited.</p>

<p><?php echo l('Click here for directions to the Steinbrenner Band Center', 'download/pdfs/steinbrenner_band_center_directions.pdf'); ?>.</p>

	
<form name="registration" class="invisible" method="post" action="<?php echo url('forms/junior_senior_night'); ?>">

	<input name="form_verify" type="hidden" value="<?php echo $t->forms->code_temp('junior_senior_night'); ?>" />
	
	<hr />
  <h3>GENERAL INFORMATION</h3>
  <div class="row">
    First Name: <input name="first" onChange="capitalizeFirst(this)" value="<?php echo @$_POST['first']; ?>" />
    MI:  <input name="middle" maxlength="1" style="width: 10px;" onChange="this.value=this.value.toUpperCase();" value="<?php echo @$_POST['middle']; ?>" />
    Last Name: <input name="last" onChange="capitalizeFirst(this)" value="<?php echo @$_POST['last']; ?>" />

  </div>
  <div class="row">
    Street Address:  <input name="street" value="<?php echo @$_POST['street']; ?>" />
    City: <input name="city" onChange="capitalizeFirst(this)" value="<?php echo @$_POST['city']; ?>" />
    State: <input name="state" maxlength="2" style="width: 20px;" onChange="this.value=this.value.toUpperCase();" value="<?php echo @$_POST['state']; ?>" />
    Zip: <input name="zip" maxlength="10" style="width: 70px;" value="<?php echo @$_POST['zip']; ?>" /><br />
  </div>

  <div class="row">
    Phone: <input name="telephone" maxlength="12" style="width: 80px;" onChange="validateTelephone(this);" value="<?php echo @$_POST['telephone']; ?>" />
    E-mail:  <input name="email" maxlength="254" style="width: 200px;" value="<?php echo @$_POST['email']; ?>" />
	</div>
	<div class="row">
		High School: <input name="hs" maxlength="254" style="width: 250px;" value="<?php echo @$_POST['hs']; ?>" />
    HS Graduation Year: <input name="hs_year" maxlength="4" style="width: 35px;" value="<?php echo @$_POST['hs_year']; ?>" />

  </div>
  <hr />
	<h3>ATTENDANCE</h3>
	<div class="row">
		<input type="radio" class="radio" name="attending" value="1" <?php if (@$_POST['attending'] == '1') { echo 'checked="checked" '; } ?>/><em><strong>Yes</strong></em>, 
			I will be attending the 2012 OSU Marching and Athletic Bands Junior/Senior Night
	</div>
	<div class="row">
		<input type="radio" class="radio" name="attending" value="0" <?php if (@$_POST['attending'] == '0') { echo 'checked="checked" '; } ?>/><em><strong>No</strong></em>, 
			I am unable to attend, but would like to receive information about Marching Band tryouts and other 
			Athletic Bands for the 2012-2013 Season.
	</div>

	<br />
	<div class="row">
		I will be bringing 
		<select name="guests" style="width: 35px; background-color: white;">
			<option value="0"<?php if (@$_POST['guests'] == '0') { echo ' selected="selected"'; } ?>>0</option>
			<option value="1"<?php if (@$_POST['guests'] == '1') { echo ' selected="selected"'; } ?>>1</option>
			<option value="2"<?php if (@$_POST['guests'] == '2') { echo ' selected="selected"'; } ?>>2</option>
		</select>
		guests with me.
	</div>

	<br />
	<div class="row">
		T-Shirt Size: <input type="radio" class="radio" name="tshirt_size" value="Small" <?php if (@$_POST['tshirt_size'] == 'Small') { echo 'checked="checked" '; } ?>/>S
									<input type="radio" class="radio" name="tshirt_size" value="Medium" <?php if (@$_POST['tshirt_size'] == 'Medium') { echo 'checked="checked" '; } ?>/>M
									<input type="radio" class="radio" name="tshirt_size" value="Large" <?php if (@$_POST['tshirt_size'] == 'Large') { echo 'checked="checked" '; } ?>/>L
									<input type="radio" class="radio" name="tshirt_size" value="X-Large" <?php if (@$_POST['tshirt_size'] == 'X-Large') { echo 'checked="checked" '; } ?>/>XL
									<input type="radio" class="radio" name="tshirt_size" value="XX-Large" <?php if (@$_POST['tshirt_size'] == 'XX-Large') { echo 'checked="checked" '; } ?>/>XXL
									<input type="radio" class="radio" name="tshirt_size" value="N/A" <?php if (@$_POST['tshirt_size'] == 'N/A') { echo 'checked="checked" '; } ?>/>I will not be attending Junior/Senior Night
	</div>
	<hr />
  <h3>INSTRUMENT</h3>

  <h5>Please select your primary instrument and part; if it is not listed check "Other" and type it in.  
			For Baritone please indicate Bass Clef (BC) or Treble Clef (TC).</h5>
	<br />
	<div class="ivForm_box" style="line-height: 2em;">
		<span>Part</span><br />
		<input type="radio" name="part" value="0" <?php if (@$_POST['part'] == '0') { echo 'checked="checked" '; } ?>/>My instrument does not require a part.<br />
		<input type="radio" name="part" value="1" <?php if (@$_POST['part'] == '1') { echo 'checked="checked" '; } ?>/>I usually play the <strong>first</strong> part.<br />

		<input type="radio" name="part" value="2" <?php if (@$_POST['part'] == '2') { echo 'checked="checked" '; } ?>/>I usually play the <strong>second</strong> part.<br />
		<input type="radio" name="part" value="3" <?php if (@$_POST['part'] == '3') { echo 'checked="checked" '; } ?>/>I usually play the <strong>third</strong> part.
	</div>
  <div class="ivForm_box">
    <span>Brass</span>

    <div class="row"><input type="radio" name="instrument" value="Trumpet" <?php if (@$_POST['instrument'] == 'Trumpet') { echo 'checked="checked" '; } ?>/>Trumpet</div>
    <div class="row"><input type="radio" name="instrument" value="Mellophone" <?php if (@$_POST['instrument'] == 'Mellophone') { echo 'checked="checked" '; } ?>/>Mellophone</div>
    <div class="row"><input type="radio" name="instrument" value="Trombone" <?php if (@$_POST['instrument'] == 'Trombone') { echo 'checked="checked" '; } ?>/>Trombone</div>
    <div class="row"><input type="radio" name="instrument" value="Sousaphone" <?php if (@$_POST['instrument'] == 'Sousaphone') { echo 'checked="checked" '; } ?>/>Sousaphone</div>
    <div class="row"><input type="radio" name="instrument" value="Baritone, Tenor Clef" <?php if (@$_POST['instrument'] == 'Baritone, Tenor Clef') { echo 'checked="checked" '; } ?>/>Baritone TC</div>
    <div class="row"><input type="radio" name="instrument" value="Baritone, Bass Clef" <?php if (@$_POST['instrument'] == 'Baritone, Bass Clef') { echo 'checked="checked" '; } ?>/>Baritone BC</div>

  </div>
	<div class="ivForm_box">
    <span>Woodwinds</span>
    <div class="row"><input type="radio" name="instrument" value="Flute" <?php if (@$_POST['instrument'] == 'Flute') { echo 'checked="checked" '; } ?>/>Flute</div>
    <div class="row"><input type="radio" name="instrument" value="Clarinet" <?php if (@$_POST['instrument'] == 'Clarinet') { echo 'checked="checked" '; } ?>/>Clarinet</div>
    <div class="row"><input type="radio" name="instrument" value="Saxophone" <?php if (@$_POST['instrument'] == 'Saxophone') { echo 'checked="checked" '; } ?>/>Saxophone</div>
		<hr style="clear: left; border: none; color: rgb(238,238,238); margin: 5px;" />

		<span style="display: block;">Percussion</span>
    <div class="row"><input type="radio" name="instrument" value="Snare Drum" <?php if (@$_POST['instrument'] == 'Snare Drum') { echo 'checked="checked" '; } ?>/>Snare Drum</div>
    <div class="row"><input type="radio" name="instrument" value="Bass Drum" <?php if (@$_POST['instrument'] == 'Bass Drum') { echo 'checked="checked" '; } ?>/>Bass Drum</div>
    <div class="row"><input type="radio" name="instrument" value="Quads" <?php if (@$_POST['instrument'] == 'Quads') { echo 'checked="checked" '; } ?>/>Tenors (Quads)</div>
    <div class="row"><input type="radio" name="instrument" value="Cymbals" <?php if (@$_POST['instrument'] == 'Cymbals') { echo 'checked="checked" '; } ?>/>Cymbals</div>
  </div>

	<div class="ivForm_box" style="line-height: 2em;">
		<span>Not Listed</span>
		<br />
		<input type="checkbox" name="other" class="small" onClick="checkEnable(!this.checked);" value="1" <?php if (@$_POST['other'] == '1') { echo 'checked="checked" '; } ?>/>Other: 
		<input type="text" name="otherText" style="border: 1px solid #000;" value="<?php echo @$_POST['otherText']; ?>" <?php if (@$_POST['other'] != '1') { echo 'disabled'; } ?> />
	</div>
	<br class="clear" />
	<hr />
  <input type="submit" name="submit" class="submit" value="Send">
  <div class="spacer"> </div>
</form>