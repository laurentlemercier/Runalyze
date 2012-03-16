<?php
/**
 * File for displaying the kilometers of each week.
 * Call:   call/window.wochenkilometer.php
 */
require '../inc/class.Frontend.php';

new Frontend();

if (!isset($_GET['y']))
	$_GET['y'] = date("Y");
?>

<h1>Wochenkilometer</h1>

<div style="position:relative;width:802px;height:502px;margin:2px auto;">
	<div class="flot waitImg" id="weekKM<?php echo $_GET['y']; ?>" style="width:800px;height:500px;position:absolute;"></div>
</div>

<?php
include FRONTEND_PATH.'draw/Plot.WeekKM.php';
?>

	<br />
	<br />

<center>
<?php
for ($j = START_YEAR; $j <= date("Y"); $j++) {
	if ($j == $_GET['y'])
		echo '<strong style="margin-right:20px;">'.$j.'</strong>';
	else
		echo Ajax::window('<a href="call/window.wochenkilometer.php?y='.$j.'" style="margin-right:20px;">'.$j.'</a>');	
}
?>
</center>