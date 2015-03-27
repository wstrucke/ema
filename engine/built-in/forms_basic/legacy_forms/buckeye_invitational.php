<script language="javascript" type="text/javascript">
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
	function mapit()
	// Open window with directions from google maps
	{
		src_address = document.getElementById('street');
		src_zip = document.getElementById('zip');
		if ( (src_address.value == '') || (src_zip.value == '') )
		{
			alert ('Error: You must provide your address and zip code to use this link.');
			return true;
		}
		url_1 = 'http://maps.google.com/maps?f=d&hl=en&saddr=';
		url_3 = '&daddr=1961+Tuttle+Park+Place,+43210&layer=&ie=UTF8&om=1';
		url_2 = src_address.value.replace(/\s/,'+') + ',+' + src_zip.value;
		openWindow(url_1 + url_2 + url_3, '', '');
	}
	
	function donothing()
	// Don't do anything
	{
	}
-->
</script>
<style>
	form.invisible input { width: 300px; }
</style>

<h1>2012 Buckeye Invitational Registration</h1>

<div style="margin: 10px 0px; line-height: 1.5em;">
	<em>Please submit this form by June 1<sup>st</sup> 2012 to ensure you are registered.</em>

	<p>To apply for the Buckeye Invitational please fill out all of the fields in the form below.  Once you successfully
		submit this form you will be provided with a link to download the application you will have to mail to the OSU Marching
		Band.  The information you provide here will be sent to our staff to provide us with preliminary information for planning
		purposes.  We will not store your e-mail address or contact information for any other reason and you will not
		be added to any mailing lists.  Thank you for your interest.</p>
<!--<p><strong>The Buckeye Invitational registration has been a great success.  Due to the popularity of the event, we are not 
		accepting any more registrations.  You may still fill out a registration form to be put on a wait-list for this year in 
		case any band is unable to attend.  Also, by filling out wait-list registration, we will give you first consideration 
		for next year's (2008) Buckeye Invitational.</strong></p>-->
	<p><strong>You must</strong> also register on the OMEA web site: <a href="https://www.omea-ohio2.org/AE/AE_MembersOnly.html" target="_blank">https://www.omea-ohio2.org/AE/AE_MembersOnly.html</a></p>
	<p><strong>All form fields are required.</strong></p>
	
</div>

<form name="registration" class="invisible" method="post" action="<?php echo url('forms/buckeye_invitational'); ?>">

	<input name="form_verify" type="hidden" value="<?php echo $t->forms->code_temp('buckeye_invitational'); ?>" />
	
  <div class="row">
  	Name of School: <input name="school" onChange="capitalizeFirst(this)" value="<?php echo @$_POST['school']; ?>" />
  </div>
	<div class="row">
		Name of Director: <input name="director" onChange="capitalizeFirst(this)" value="<?php echo @$_POST['director']; ?>" />
	</div>
	<div class="row">
		E-mail: <input name="email" value="<?php echo @$_POST['email']; ?>" />

	</div>
  <div class="row">
  	Phone Number you can be reached at: 
		<input name="phone" maxlength="12" style="width: 120px;" onChange="validateTelephone(this);" 
					 value="<?php echo @$_POST['phone']; ?>" />
	</div>
	<div class="row">
		Total number in band (including auxiliary): <input name="number" style="width: 40px;" value="<?php echo @$_POST['number']; ?>" />
		Total Auxiliary (if any): <input name="auxiliary" style="width: 40px;" value="<?php echo @$_POST['auxiliary']; ?>" />

	</div>
	<div class="row">
		<input type="radio" name="class" value="festival" <?php if (@$_POST['class'] == 'festival') { echo 'checked="checked" '; } ?>/>
			Festival Class (Comments Only)
		<input type="radio" name="class" value="competition" <?php if (@$_POST['class'] == 'competition') { echo 'checked="checked" '; } ?>/>
			Competition Class (OMEA Adjudication Only)
	</div>
	<div class="row">
		<input type="checkbox" name="omea" value="1" <?php if (@$_POST['omea'] == '1') { echo 'checked="checked" '; } ?>/> I have registered on the <a href="https://www.omea-ohio2.org/AE/AE_MembersOnly.html">OMEA Web Site</a>

	</div>
  <div class="spacer"> </div>

	<hr />
  <input type="submit" name="submit" class="submit" value="Send">
  <div class="spacer"> </div>
</form>