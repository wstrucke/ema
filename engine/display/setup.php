<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
<title><?php echo $this->_version; ?> setup</title>

<style type="text/css">
<!--

-->
</style>

</head>

<body>

<h1><?php echo $this->_version; ?></h1>
<h3>Initial Configuration</h3>
<hr />

<p><em>No configuration file was found for this ema deployment.</em></p>
<br />

<form name="cms" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" method="post">

<?php 
/*
	if ($_SERVER['HTTPS'] != "on")
	{
	
?>
	<strong><em>WARNING: Your connection is not secure.  It is strongly recommended that you run
	all CMS configuration from an encrypted connection (e.g. HTTPS).</em></strong>

<?php
	}
*/
	if (! isset($_POST['step'])) 
	{ 
		/* get the type of database interface */	
?>

	<input name="step" type="hidden" value="1" />
	
	<strong>Please select the type of database server you will be using:</strong><br />
	<!-- <input name="data_interface" type="radio" value="file_db" />PHP Emulated Database<br /> -->
	<input name="data_interface" type="radio" value="mysql_db" />MySQL Database<br />
	
<?php 
	} else {

		switch($_POST['step'])
		{
			case '1':
				/* get the database connection details */
	?>
	
	<input name="step" type="hidden" value="2" />
	<input name="data_interface" type="hidden" value="<?php echo $_POST['data_interface']; ?>" />
	
	<?php
	
				if ($_POST['data_interface'] == 'file_db')
				{
	?>
	
	<strong>The PHP Emulated Database requires a writeable store location on the server. The default location
	is entered below -- please adjust the path if necessary.</strong><br />
	Path: <input name="location" value="<?php echo $this->resourcePath; ?>database" style="width: 500px;" /><br />
	Password: <input name="password" /><br />
	
	<?php
				} elseif ($_POST['data_interface'] == 'mysql_db') {
	?>
	
	<p><strong>The MySQL Database requires a server (the localhost is valid), a database name, user name, and
	password for access.  Please adjust the defaults as necessary.</strong></p>
	<p>If you have not already created the database, run this command:<br /><br /><em>
		CREATE DATABASE `ema` CHARSET 'utf8' COLLATE 'utf8_general_ci';
	</em></p>
	Server: <input name="server" value="127.0.0.1" /><br />
	Login ID: <input name="username" value="ema" /><br />
	Password: <input name="password" type="password" value="" /><br />
	Database: <input name="database" value="ema" /><br />
	Engine: <select name="engine"><option value="MYISAM">MyISAM</option><option value="INNODB" selected="selected">InnoDB</option><option value="NDBCLUSTER">NDBCLUSTER</option></select>
	Character Set: <select name="charset"><option value="latin1">latin1</option><option value="utf8" selected="selected">utf-8</option></select>
	
	<?php
				} else {
	?>
	
	<strong>An invalid or unsupported option has been posted. Please select another option or try again later.</strong><br />
	
	<?php
				}
				break;
			case '2':
				/* test the selected database connection */
	?>
	
	<input name="step" type="hidden" value="3" />
	<input name="data_interface" type="hidden" value="<?php echo $_POST['data_interface']; ?>" />
	<input name="location" type="hidden" value="<?php echo @$_POST['location']; ?>" />
	<input name="server" type="hidden" value="<?php echo $_POST['server']; ?>" />
	<input name="username" type="hidden" value="<?php echo $_POST['username']; ?>" />
	<input name="password" type="hidden" value="<?php echo $_POST['password']; ?>" />
	<input name="database" type="hidden" value="<?php echo $_POST['database']; ?>" />
	<input name="engine" type="hidden" value="<?php echo $_POST['engine']; ?>" />
	<input name="charset" type="hidden" value="<?php echo $_POST['charset']; ?>" />
	
	<?php
				if ($_POST['data_interface'] == 'file_db')
				{
					#require_once ($this->objectPath . 'file_db/file_db.class.php');
					#$dbTester = new file_db();
	?>
	
	<?php
				} elseif ($_POST['data_interface'] == 'mysql_db') {
					$this->_tx->_preRegister('db', array('server'=>$_POST['server'], 'database'=>$_POST['database'],
																								'user'=>$_POST['username'], 'password'=>$_POST['password'],
																								'charset'=>$_POST['charset'], 'engine'=>$_POST['engine']));
					$this->_tx->_setDefault('db', 'mysql');
					
					if (! $this->_tx->db->connected) {
	?>
	<input name="dbtest" type="hidden" value="0" />
	
	<strong>Error, unable to connect to the database server. Please change your settings and try again.</strong><br />
	
	<?php
					} else {
	?>
	
	<input name="dbtest" type="hidden" value="1" />
	
	<p>Successfully connected to your database server. Click submit to continue.</p><br />
	
	<?php
					}
				} else {
	?>
	
	<strong>An invalid or unsupported option has been posted. Please select another option or try again later.</strong><br />
	
	<?php
				}
				break;
			case '3':
				/* set up or upgrade the database if necessary */
	?>
	
	<input name="step" type="hidden" value="3" />
	<input name="data_interface" type="hidden" value="<?php echo $_POST['data_interface']; ?>" />
	<input name="location" type="hidden" value="<?php echo $_POST['location']; ?>" />
	<input name="server" type="hidden" value="<?php echo $_POST['server']; ?>" />
	<input name="username" type="hidden" value="<?php echo $_POST['username']; ?>" />
	<input name="password" type="hidden" value="<?php echo $_POST['password']; ?>" />
	<input name="database" type="hidden" value="<?php echo $_POST['database']; ?>" />
	<input name="engine" type="hidden" value="<?php echo $_POST['engine']; ?>" />
	<input name="charset" type="hidden" value="<?php echo $_POST['charset']; ?>" />
	<input name="dbtest" type="hidden" value="<?php echo $_POST['dbtest']; ?>" />
	
	<?php
				# first make sure the database test succeeded
				if ($_POST['dbtest'] != 1) {
	?>
	
	<strong>Error:</strong> the database connection failed.  Please 
	<a href="<?php echo $_SERVER["REQUEST_URI"];  ?>">click here</a> or go back to correct the settings.<br />
	
	<?php
				} else {
	
					# Setup the database connection
					if ($_POST['data_interface'] == 'file_db') {
						#require_once ($this->objectPath . 'file_db/file_db.class.php');
						#$db = new file_db();
					} elseif ($_POST['data_interface'] == 'mysql_db') {
						$this->_tx->_preRegister('db', array('server'=>$_POST['server'], 'database'=>$_POST['database'],
																								'user'=>$_POST['username'], 'password'=>$_POST['password'],
																								'charset'=>$_POST['charset'], 'engine'=>$_POST['engine']));
						$this->_tx->_setDefault('db', 'mysql');
					}
					
					# Check the database version
					$check = $this->_tx->db->query('master_config', array('cms-option'), array('db_version'), array('option-value'));
					
					if (! db_qcheck($check)) {
						# no result -- database does not exist
						# execute db create script
						require ($this->queryPath . 'setup/db_create.php');
					} else {
						# check version against current CMS version
						if (! $check[0]['option-value'] == $this->db_version)
						{
							# versions do not match -- run appropriate update scripts
							/* --- This is the 1st version of the database so there are no update scripts --- */
							# don't forget to specify $r here!
						}
						
						# insert built-in modules data just in case
						
						# get the built-in path
						$fields = array('id','name','module','enabled','load_order','provides','type','path','refresh');
						
						# we can generalize the list since we're just adding these to be refreshed
						$list = array('output_standard', 'file_manager', 'cms_manager', 'css_document', 'html_element',
								'template_manager','security_basic');
						
						# additional built-in modules will have to be added as they are developed
						
						for ($i=0;$i<count($list);$i++) {
							# get the provides and type
							$tmp = explode('_', $list[$i]);
							$this->_tx->db->insert('modules',$fields,array($i+1,$list[$i],'engine',1,$i+1,$tmp[0],$tmp[1],
									"./built-in/$list[$i]/$list[$i].class.php",1));
						}
						
						$r = true;
					}
					
					if ($r) {
						# Write the configuration file
						$this->_tx->_preRegister('xml', array('_tag'=>'cms'));
						$this->_tx->_setDefault('xml', 'document');
						
						$xmli =& $this->_tx->xml;
						
						$xmli->_create($this->configPath . 'config.xml');
						
						$xmli->_version = $this->obj_version;
						$xmli->data_interface = $_POST['data_interface'];
						
						switch($_POST['data_interface']) {
							case 'file_db':
								$xmli->file_db->location = $_POST['location'];
								$xmli->file_db->password = $_POST['password'];
								break;
							case 'mysql_db':
								$xmli->mysql_db->address = $_POST['server'];
								$xmli->mysql_db->database = $_POST['database'];
								$xmli->mysql_db->username = $_POST['username'];
								$xmli->mysql_db->password = $_POST['password'];
								$xmli->mysql_db->engine = $_POST['engine'];
								$xmli->mysql_db->charset = $_POST['charset'];
								break;
						}
									
						if ($xmli->_save()) {
							# register accounts_db and security_basic
							$cms_args = unserialize('a:5:{s:4:"outs";s:27:"true,object,output_standard";s:8:"template";s:28:"true,object,template_manager";s:3:"css";s:24:"true,object,css_document";s:2:"db";s:14:"true,object,db";s:17:"client_extensions";s:23:"false,object,xml_object";}');
							
							# register the module with the transmission
							$this->_tx->_registerExtension('output','standard','0','./built-in/output_standard/output_standard.class.php',
								array(),array(),array(),array(),false,'0');
							$this->_tx->_registerExtension('css','document','0','./built-in/css_document/css_document.class.php',
								array(),array(),array(),array(),false,'0');
							$this->_tx->_registerExtension('html','element','0','./built-in/html_element/html_element.class.php',
								array(),array(),array(),array(),false,'0');
							$this->_tx->_registerExtension('file','manager','0','./built-in/file_manager/file_manager.class.php',
								array('db'),array(),array(),array(),false,'0');
							$this->_tx->_registerExtension('template','manager','0','./built-in/template_manager/template_manager.class.php',
								array('db','file'),array(),array(),array(),false,'0');
							$this->_tx->_registerExtension('cms','manager','0','./built-in/cms_manager/cms_manager.class.php',
								array('db','output_standard','template_manager','html_element','css_document','file'),$cms_args,array(),array(),false,'0');
							
							# make sure pre-requisite modules are completely set up before enabling the security system
							$this->_tx->file;
							$this->_tx->cms;
							
							$this->_tx->_registerExtension('accounts','db','0','./built-in/accounts_db/accounts_db.class.php',
								array('db','file'),array(),array(),array(),false,'0');
							$this->_tx->_registerExtension('security','basic','0','./built-in/security_basic/security_basic.class.php',
								array('db','cms','file','accounts'),array(),array(),array(),false,'0');
							
							# install the security system
							$this->_tx->security;
							
							# fix the security permissions
							$sec =& $this->_tx->new_security;
							$list = $sec->get_modules();
							$this->_tx->db->update('security_guid', array('guid'), array('61123ca4-f075-11df-bff3-aa46601030c1'), array('accounts_guid', 'module_id'), array('c8cec89e-747d-11e0-8767-8576c061a515', '1'));
							
	?>
	
	<p>You have successfully configured the Content Management System.  Please <a href="">click here</a> to 
	continue to the CMS Management Console.</p>
	
	<?php
						} else {
	?>
	
	<p><strong>Error:</strong> Unable to save the configuration file.  Please try again or contact your
	system administrator.</p>
	
	<?php
						}
					} else {
	?>
	<p><strong>Error:</strong> Unable to create the database. Please try again or contact your system
	administrator.</p>
	<?php
					}
				}
				break;
		}
	
	} 

	?>
	
	<br />
	<input name="submit" type="submit" value="Submit" />
</form>

</body>
</html>