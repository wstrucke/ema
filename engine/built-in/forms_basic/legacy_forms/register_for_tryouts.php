<?php
$script = <<<END

<!--
  function validateSSN() {
    // validate a social security number, add dashes
    try {
      temp = document.registration.SS.value;
      if ((temp.indexOf("-") == -1) && (temp.length == 9)) {
        middle = '-' + Left(Right(temp, 6), 2) + '-';
        document.registration.SS.value = Left(temp, 3) + middle + Right(temp, 4);
        }
      }
    catch (e) {
      alert('Please notify the webmaster of the following error and anything you just did: ' + e);
      }
    return true;
    }
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
	function limitSize(limit, obj) {
		// limit the max length of a textarea
		try {
			if (obj.value.length > limit) { alert('Please limit your response to ' + limit + ' characters or less.'); }
			}
		catch (e) {
			//alert('Please notify the webmaster of the following error and anything you just did: ' + e);
			}
		return false;
		}
-->

END;

$t->get->html->head->cc('script')->sas(array('type'=>'text/javascript','language'=>'javascript'))->set_value($script);

?>

<h1>Online Registration for Tryouts</h1>

<p>For those who are interested in becoming a member of The Ohio State University Marching Band, and would not like to register online, please write or call us and tell us who you are, where you live, and what instrument you play. Percussion players please specify an instrument (Snare, Quad-Toms, Bass Drum, or Cymbals).</p>

<p>Tryouts for the Ohio State University Marching Band for the 2011-2012 season will be held Tuesday, August 30<supth</sup> and
  Wednesday, August 31st. All candidates that have not been members of the marching band in past years are required to attend 
  "Candidate Training Days" on Sunday, August 28th and Monday, August 29th. These two days are meant to introduce new candidates
  to what will be expected during tryouts. For additional practice, feel free to attend the optional rehearsals lead by veteran
  members of the band known as summer sessions. The full schedule can be found <?php echo l('here','calendar'); ?>, where percussion
  players start at 6:00pm and brass players begin at 7:00pm on the rehearsal field in front of Lincoln Tower. For additional
  information about tryouts or summer sessions, please email us at <a href="mailto:osumb@osu.edu">osumb@osu.edu</a> or call
  the band center at (614) 292-2598.
</p>

<form name="registration" class="invisible" method="post" action="<?php echo url('forms/register_for_tryouts'); ?>">
	
	<input name="form_verify" type="hidden" value="<?php echo $t->forms->code_temp('register_for_tryouts'); ?>" />
	
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
    HS Graduation Year: <input name="hs" maxlength="4" style="width: 35px;" value="<?php echo @$_POST['hs']; ?>" />
  </div>
  
  <hr />
	<h3>MUSICAL EXPERIENCE</h3>
	
	<div class="row">
		<h5>Please list any solos or ensembles (and ratings) in which you have participated.  Also please indicate all of the musical ensembles in which you have performed.</h5>
		<textarea name="musical_experience" onkeypress="limitSize(400, this);" style="width: 600px; height: 100px; padding: 5px; margin-top: 5px;"><?php echo @htmlentities($_POST['musical_experience']); ?></textarea>
	</div>
	
	<hr />  
  <h3>INSTRUMENT</h3>
  <h5>Please select the primary instrument and part you played in high school and would like to tryout on.  For Baritone please indicate Bass Clef (BC) or Treble Clef (TC).</h5><br />
  <div class="ivForm_box">
    <span>Brass</span>
    <div class="row"><input type="radio" name="instrument" value="Trumpet" <?php if (@$_POST['instrument'] == 'Trumpet') { echo 'checked="checked" '; } ?>/>Trumpet</div>

    <div class="row"><input type="radio" name="instrument" value="Mellophone" <?php if (@$_POST['instrument'] == 'Mellophone') { echo 'checked="checked" '; } ?>/>Mellophone</div>
    <div class="row"><input type="radio" name="instrument" value="Trombone" <?php if (@$_POST['instrument'] == 'Trombone') { echo 'checked="checked" '; } ?>/>Trombone</div>
    <div class="row"><input type="radio" name="instrument" value="Sousaphone" <?php if (@$_POST['instrument'] == 'Sousaphone') { echo 'checked="checked" '; } ?>/>Sousaphone</div>
    <div class="row"><input type="radio" name="instrument" value="Baritone, Treble-Clef" <?php if (@$_POST['instrument'] == 'Baritone, Treble-Clef') { echo 'checked="checked" '; } ?>/>Baritone TC</div>
    <div class="row"><input type="radio" name="instrument" value="Baritone, Bass-Clef" <?php if (@$_POST['instrument'] == 'Baritone, Bass-Clef') { echo 'checked="checked" '; } ?>/>Baritone BC</div>
    <div class="row"><input type="radio" name="part" value="First" <?php if (@$_POST['instrument'] == 'First') { echo 'checked="checked" '; } ?>/>First</div>

    <div class="row"><input type="radio" name="part" value="Second" <?php if (@$_POST['instrument'] == 'Second') { echo 'checked="checked" '; } ?>/>Second</div>
    <div class="row"><input type="radio" name="part" value="Third" <?php if (@$_POST['instrument'] == 'Third') { echo 'checked="checked" '; } ?>/>Third</div>
  </div>
  <div class="ivForm_box">
    <span>Percussion</span>
    <div class="row"><input type="radio" name="instrument" value="Snare Drum" <?php if (@$_POST['instrument'] == 'Snare Drum') { echo 'checked="checked" '; } ?>/>Snare Drum</div>
    <div class="row"><input type="radio" name="instrument" value="Bass Drum" <?php if (@$_POST['instrument'] == 'Bass Drum') { echo 'checked="checked" '; } ?>/>Bass Drum</div>

    <div class="row"><input type="radio" name="instrument" value="Quads" <?php if (@$_POST['instrument'] == 'Quads') { echo 'checked="checked" '; } ?>/>Tenors (Quads)</div>
    <div class="row"><input type="radio" name="instrument" value="Cymbals" <?php if (@$_POST['instrument'] == 'Cymbals') { echo 'checked="checked" '; } ?>/>Cymbals</div>
		<br class="clear" />
		<div style="font-weight: bold;">Drum Majors</div>
		<div class="row" style="width: 150px;"><input type="radio" name="instrument" value="Drum Major" <?php if (@$_POST['instrument'] == 'Drum Major') { echo 'checked="checked" '; } ?>/>Drum Major Candidate</div>
  </div>
  <br class="clear" />

  <hr />
  <h3>PLEASE SELECT</h3>
  <div class="left">
    I plan to attend tryout week.
  </div>
  <div class="right">
    <input type="radio" name="tryouts" value="1" <?php if (@$_POST['tryouts'] == '1') { echo 'checked="checked" '; } ?>/>Yes
    <input type="radio" name="tryouts" value="0" <?php if (@$_POST['tryouts'] == '0') { echo 'checked="checked" '; } ?>/>No
  </div>
  <div class="left">

    I need tryout week housing information.
  </div>
  <div class="right">
    <input type="radio" name="housing" value="1" <?php if (@$_POST['housing'] == '1') { echo 'checked="checked" '; } ?>/>Yes
    <input type="radio" name="housing" value="0" <?php if (@$_POST['housing'] == '0') { echo 'checked="checked" '; } ?>/>No
  </div>
  <div class="left">
    I need school songs.
  </div>
  <div class="right">
    <input type="radio" name="info" value="1" <?php if (@$_POST['info'] == '1') { echo 'checked="checked" '; } ?>/>Yes
    <input type="radio" name="info" value="0" <?php if (@$_POST['info'] == '0') { echo 'checked="checked" '; } ?>/>No
  </div>

  <div class="left">
    I would like a summer session practice schedule.
  </div>
  <div class="right">
    <input type="radio" name="schedule" value="1" <?php if (@$_POST['schedule'] == '1') { echo 'checked="checked" '; } ?>/>Yes
    <input type="radio" name="schedule" value="0" <?php if (@$_POST['schedule'] == '0') { echo 'checked="checked" '; } ?>/>No
  </div>
  <div class="left">
    I need to sign out an OSU instrument for the summer.
  </div>
  <div class="right">

    <input type="radio" name="loan" value="1" <?php if (@$_POST['loan'] == '1') { echo 'checked="checked" '; } ?>/>Yes
    <input type="radio" name="loan" value="0" <?php if (@$_POST['loan'] == '0') { echo 'checked="checked" '; } ?>/>No
  </div>
  <div class="left">
    I would like to receive a free marching fundamentals DVD.
  </div>
  <div class="right">
    <input type="radio" name="video" value="1" <?php if (@$_POST['video'] == '1') { echo 'checked="checked" '; } ?>/>Yes
    <input type="radio" name="video" value="0" <?php if (@$_POST['video'] == '0') { echo 'checked="checked" '; } ?>/>No
  </div>
  <br class="clear" />

  <input type="submit" name="submit" class="submit" value="Send" />
  <div class="spacer"> </div>
  
</form>