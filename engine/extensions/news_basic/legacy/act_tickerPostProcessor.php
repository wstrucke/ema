<?php
 /***************************************
	* Route posted forms to their
	* corresponding processors
	*
	*/

	if (! isset($_POST['route']))
	{
		echo '<strong>Error:</strong> Can not process form post without a route specified.';
		exit;
	}
	
	switch ($_POST['route'])
	{
		case 'create':	include ('act_tickerCreate.php');		break;
		case 'edit':		include ('act_tickerCreate.php');		break;
		case 'remove':	include ('act_tickerRemove.php');		break;
	}
	
?>