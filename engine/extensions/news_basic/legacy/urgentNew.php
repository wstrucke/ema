<?php
 /****************************************
	* Urgent News Controller
	* ======================================
	* Version 1.0
	* Build 0001, 2006.11.16
	* --------------------------------------
	* William Strucke, [wstrucke@gmail.com]
	*
	*/
	
?>

	<style>
	<!--
		form.newsForm
		{
			width: 600px;
			margin: 10px auto;
			padding-bottom: 5px;
			color: #333;
			background-color: #ddd;
			border: 1px solid #ccc;
		}
		
		form.newsForm > span
		{
			display: block;
			margin-bottom: 5px;
			padding: 4px 5px;
			font-weight: bold;
			color: #000;
			background-color: #C8D3FF;
			border-bottom: 2px solid #bbb;
		}
		
		form.newsForm ul
		{
			line-height: 1.8em;
			list-style-type: none;
		}
		
		form.newsForm ul li
		{
			height: 1.8em;
			margin-left: 25px;
			border-bottom: 1px dotted #bbb;
		}
		
		form.newsForm ul li a
		{
			color: #000;
			background-color: inherit;
			text-decoration: none;
		}
		
		form.newsForm ul li a:hover
		{
			padding-left: 5px;
			font-weight: bold;
			cursor: pointer;
		}
		
		form.newsForm ul li > span
		{
			float: left;
			margin-right: 10px;
		}
		
		form.newsForm ul li > input
		{
			float: right;
			width: 500px;
			margin: 5px 10px 0 0;
		}
		
		form.newsForm > input.button
		{
			float: right;
			width: auto;
			margin: 5px 10px 0 0;
			padding: 2px 4px;
			cursor: pointer;
		}
		
		form.newsForm ol
		{
			clear: both;
			padding-top: 1em;
		}
		
		form.newsForm ol > li
		{
			clear: both;
			height: 5.4em;
			margin: 0 0 1em 25px;
		}
		
		form.newsForm ol > li > ul { margin-top: 0px; }
		
		form.newsForm ol > li > ul > li { margin: 0px; padding: 0px; }
		
		form.newsForm select { border: 1px solid #ccc; width: 150px; }
		
	-->
	</style>
	
<?php
	# scope application data array
	global $_APP;
	
	# If there is no news ticker configured, load the add ticker page
	if ($_APP['urgent_news'] == '') { $do = 'create'; }
	
	# Set Navigation
	if (isset($_GET['do'])) { $do = $_GET['do']; }
	
	switch($do)
	{
		case 'create':	include('dsp_tickerCreate.php');				break;
		case 'edit':		include('dsp_tickerEdit.php');					break;
		case 'post':		include('act_tickerPostProcessor.php');	break;
		case 'preview':	include('dsp_tickerPreview.php');				break;
		case 'remove':	include('dsp_tickerRemove.php');				break;
		case 'replace':	include('urgent.php');									break;
		default:				include('dsp_tickerMenu.php');					break;
	}

?>