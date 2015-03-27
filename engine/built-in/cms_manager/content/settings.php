	
	<div>
		<h1>site settings<div><?php echo l('close', 'admin'); ?></div></h1>
	</div>
	
	<p><em>Site settings have been moved into the content manager</em></p>
	
	<?php
	
		echo $t->cms->generate_menu();
	
	?>
	
<!--
	<div class="container">
		<div class="head">SYSTEM STATUS</div>
		<span class="label">Public Site Online:</span><span class="yes">Yes</span>
		<span class="label">SSL Site Online:</span><span class="yes">Yes</span>
		<span class="label">SSL Site Enabled:</span><span class="yes">Yes</span>
	</div>
	
	<div class="container">
		<div class="head">ACTIVE LOGINS ( N/A )</div>
		<span class="lg_label" title="[$data[4]]">Administrator</span><span class="lg_time">(00:00)</span>
	</div>
	
	<div class="container">
		<div class="head">SITE STATISTICS</div>
		<span class="label">Site Hits:</span><span class="value"><strong>NA</strong></span>
		<span class="label">Page Hits:</span><span class="value">0</span>
		<span class="label">Unique Hits:</span><span class="value"><strong>NA</strong></span>
		<span class="label">Downloads:</span><span class="value">0</span>
		<span class="label">Failed Logins:</span><span class="value">0</span>
		<span class="label">
			<a href="?action=a004&s=last" title="Show last 20 logins">Successful Logins</a>:
		</span>
		<span class="value">0</span>
		<hr />
		<span class="label">Marching Band:</span><span class="value">0</span>
		<span class="label">Athletic Band:</span><span class="value">0</span>
		<span class="label">Staff &amp; Directors:</span><span class="value">0</span>
		<span class="label">Administrators:</span><span class="value">0</span>
	</div>
	<div style="font-size: 90%; text-align: center;">
		Last Administrator Login on N/A
	</div>
	
	<div class="head"><span>Configuration</span></div>
		
		<div class="form">
			<form name="configuration" action="?action=a016&s=3&v=config" method="post">

				<div class="row">
					<span class="label">Public Home Page:</span>
					<span class="value"><input name="home" type="text" value="<?php echo $_APP['pub']; ?>" /></span>
				</div>
				
				<div class="row">
					<span class="label">SSL Home Page:</span>
					<span class="value"><input name="ssl" type="text" value="<?php echo $_APP['ssl']; ?>" /></span>
				</div>
				
				<div class="row">
					<span class="label">Contact E-mail:</span>
					<span class="value"><input type="text" name="contact_address" value="<?php echo $_APP['contact_email']; ?>" /></span>
				</div>
				
				<div class="row">
					<span class="label">Website Locks:</span>
					<span class="value">
						<a href="?action=a016&s=4&v=pub_enabled">Public</a>
						<a href="?action=a016&s=4&v=ssl_enabled">SSL</a>
						<a href="?action=a016&s=4&v=sitelock">Lockout</a>
					</span>
				</div>
				
				<div class="row">
					<span class="label">Statistics:</span>
					<span class="value">
						<a href="?action=a016&s=4&v=stats">Reset</a>
					</span>
				</div>
				
				<div class="row">
					<span class="label">Login Tracking:</span>
					<span class="value">
						<a href="?action=a016&s=4&v=reset_logs">Reset</a>
						<a href="?action=a016&s=4&v=roll_logs">Rollover</a>
					</span>
				</div>
				
				<div class="row">
					<span class="label">Automated Tasks:</span>
					<span class="value">
						<a href="?action=a016&s=10&v=gen_mb_roster">Generate OSUMB Roster</a>
					</span>
				</div>
				
				<div class="command">
					<input type="submit" name="submit" value="Update" />
				</div>
			
			</form>
			<div class="spacer_right">&nbsp;</div>
		</div>
-->