<?php
 /*************************************************************************
 * news control console home page / switch																|
 * =======================================================================|
 * William Strucke, 2005.06.30																						|
 * -----------------------------------------------------------------------|
 *																																				|
 *************************************************************************/
	global $_APP, $a, $message;
	
	include_once ('news_class.php');
	
	$news = new news_bulletin();
	?>

	<script language="javascript" type="text/javascript">
	
	function checkFields () {
		// check required fields for values before proceeding.  return true or false to continue.
		if ((document.forms[0].date.value == "") ||
				(document.forms[0].description.value == "") ||
				(document.forms[0].view.value == ""))
			{
				alert ('Can not add an event without a date, description, and view permissions.');
				return false;
			} else {
				return true;
				}
		}
		
	</script>

		<div style="width: 100%; text-align: center; margin-top: 30px; background-color: #ffffff; padding: 10px 0px;">
			[ <a href="?action=a015&s=1">Add News Item</a> ]
			[ <a href="?action=a015&s=2">Edit Post</a> ]
			[ <a href="?action=a015&s=3">Remove Post</a> ]
			[ <a href="?action=a015&s=4">List all News</a> ]
			[ <a href="?action=a015&s=5">Show Last 3</a> ]
			[ <a href="?action=a015&s=6">Show Last 10</a> ]
			[ <a href="?action=a015&s=7">Urgent News</a> ]<br /><br />
			[ <a href="?action=a015">Reset</a> ]
			<?php
				if ($a[7] == "1") { 
					echo '[ <a href="?action=a003">Administration Menu</a> ]';
					}
			?>
			[ <a href="?action=<?php echo $_APP['ssl']; ?>">Home</a> ]
		</div>
	
		<h1>News Manager</h1>
		
		<?php
			/* decide what to display */
			
			if (isset($_GET['s'])) { $temp = $_GET['s']; }
			
			include (ROOT_PATH . '/security/act_getSessionPermissions.php');
			
			$news = new news_bulletin();
			
			switch ($temp) {
				case '1':
					/* Add News Item */
					include ('add.php');
					break;
				case '2':
					/* Edit Post */
					include ('edit.php');
					break;
				case '3':
					/* Remove Post */
					include ('remove.php');
					break;
				case '4':
					/* List all News */
					$items = $news->GetNews(1000, implode('', $a));
					echo $news->PrepareOutput($items, $a);
					break;
				case '5':
					/* Show Last 3 */
					$items = $news->GetNews(3, implode('', $a));
					echo $news->PrepareOutput($items, $a);
					break;
				case '6':
					/* Show Last 10 */
					$items = $news->GetNews(10, implode('', $a));
					echo $news->PrepareOutput($items, $a);
					break;
				case '7':
					/* Urgent News */
					include ('urgentNew.php');
					break;
				default:
					/* Show Last 5 */
					$items = $news->GetNews(5, implode('', $a));
					echo $news->PrepareOutput($items, $a);
				} // switch
				
		?>