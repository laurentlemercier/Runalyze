<?php
/**
 * This file contains the class::Helper
 */

/**
 * Maximal heart-frequence of the user
 * @const HF_MAX
 */
define('HF_MAX', Helper::getHFmax());

/**
 * Heart-frequence in rest of the user
 * @const HF_REST
 */
define('HF_REST', Helper::getHFrest());

/**
 * Timestamp of the first training
 * @const START_TIME
 */
define('START_TIME', Helper::getStartTime());

/**
 * Year of the first training
 * @const START_YEAR
 */
define('START_YEAR', date("Y", START_TIME));

require_once(FRONTEND_PATH.'class.JD.php');

/**
 * Class for all helper-functions previously done by functions.php
 * @defines   HF_MAX       int   Maximal heart-frequence [bpm]
 * @defines   HF_REST      int   Heart-frequence in rest [bpm]
 * @defines   START_TIME   int   Timestamp of first training
 * @defines   START_YEAR   int   Year of first training
 * 
 * @author Hannes Christiansen <mail@laufhannes.de>
 * @version 1.1
 * @uses class::Error
 * @uses class::Mysql
 * @uses class::JD
 *
 * Last modified 2011/04/14 17:30 by Hannes Christiansen
 */
class Helper {
	/**
	 * This class contains only static methods
	 */
	private function __construct() {}
	private function __destruct() {}

	/**
	 * Get the name or all information of a sport
	 * @param int $sport_id ID of the sport
	 * @param bool $as_array Return as array, default: false
	 * @return string|array Name of sport or all information as array
	 */
	public static function Sport($sport_id, $as_array = false) {
		$sport = Mysql::getInstance()->fetch(PREFIX.'sports', $sport_id);

		if ($sport === false)
			$sport = array('name' => '?', 'bild' => '', 'online' => 0, 'short' => 0, 'kalorien' => 0,
				'HFavg' => 0, 'RPE' => 0, 'distanztyp' => 0, 'kmh' => 0, 'typen' => 0, 'pulstyp' => 0,
				'outside' => 0, 'distanz' => 0, 'dauer' => 0);

		return ($as_array) ? $sport : $sport['name'];
	}

	/**
	 * Get all information of a type as an array
	 * @param int $type_id
	 * @return array
	 */
	public static function TypeAsArray($type_id) {
		$type = Mysql::getInstance()->fetch(PREFIX.'typ', $type_id);

		if ($type === false)
			return array('name' => '?', 'abk' => '?', 'splits' => 0, 'RPE' => 0);

		return $type;
	}

	/**
	 * Get the name of a type
	 * @param int  $type_id
	 * @return string
	 */
	public static function TypeName($type_id) {
		$array = self::TypeAsArray($type_id);

		return $array['name'];
	}

	/**
	 * Get all information of a type as an array
	 * @param int $type_id
	 * @return array
	 */
	public static function TypeShort($type_id) {
		$array = self::TypeAsArray($type_id);

		return $array['abk'];
	}

	/**
	 * Get boolean flag wheather type alouds splits
	 * @param int $type_id
	 * @return bool
	 */
	public static function TypeHasSplits($type_id) {
		$array = self::TypeAsArray($type_id);

		return ($array['splits'] == 1);
	}

	/**
	 * Get boolean flag: Is the RPE of this type higher than 4?
	 * @param int $type_id
	 * @return bool
	 */
	public static function TypeHasHighRPE($type_id) {
		$array = self::TypeAsArray($type_id);

		return ($array['RPE'] > 4);
	}

	/**
	 * Get the name or all information of a shoe
	 * @param int  $shoe_id   ID of the shoe
	 * @param bool $as_array  Return as array, default: false
	 * @return string|array   Name of shoe or all information as array
	 */
	public static function Shoe($shoe_id, $as_array = false) {
		$shoe = Mysql::getInstance()->fetch(PREFIX.'schuhe', $shoe_id);

		if ($shoe === false)
			$shoe = array('name' => '?');

		return ($as_array) ? $shoe : $shoe['name'];
	}

	/**
	 * Returns the img-Tag for a weather-symbol
	 * @param int $wetter_id
	 * @return string img-tag
	 */
	public static function WeatherImage($wetterid) {
		return Icon::getWeatherIcon($wetterid);
	}

	/**
	 * Returns the name for a given weather-id
	 * @param int $wetterid
	 * @return string name for this weather
	 */
	public static function WeatherName($wetterid) {
		$wetter = Mysql::getInstance()->fetch(PREFIX.'wetter', $wetterid);
		return ($wetter !== false)
			? $wetter['name']
			: '';
	}

	/**
	 * Get a string for displaying any pulse
	 * @param int $pulse
	 * @param int $time
	 */
	public static function PulseString($pulse, $time = 0) {
		if ($pulse == 0)
			return '';
		if ($time == 0)
			$time = time();

		$bpm = round($pulse).'bpm';
		$hf  = '?&nbsp;&#37;';

		$HFmax = Mysql::getInstance()->fetchSingle('SELECT * FROM `'.PREFIX.'user` ORDER BY ABS(`time`-'.$time.') ASC');
		if ($HFmax !== false && $HFmax['puls_max'] != 0)
			$hf = round(100*$pulse / $HFmax['puls_max']).'&nbsp;&#37';

		if (CONF_PULS_MODE != 'bpm' && $HFmax['puls_max'] != 0)
			return '<span title="'.$bpm.'">'.$hf.'</span>';

		return '<span title="'.$hf.'">'.$bpm.'</span>';
	}

	/**
	 * Get the speed depending on the sport as pace or km/h
	 * @uses self::Pace
	 * @uses self::Kmh
	 * @uses self::Sport
	 * @param float $km       Distance [km]
	 * @param int $time       Time [s]
	 * @param int $sport_id   ID of sport for choosing pace/kmh
	 * @return string
	 */
	public static function Speed($km, $time, $sport_id = 0) {
		if ($km == 0 || $time == 0)
			return '';

		$kmh_mode = 0;
		$title = '';
		$as_pace = self::Pace($km, $time).'/km';
		$as_kmh = self::Kmh($km, $time).'&nbsp;km/h';

		if ($sport_id != 0) {
			$sport = self::Sport($sport_id, true);
			$kmh_mode = $sport['kmh'];
		}
		if ($kmh_mode == 1)
			return '<span title="'.$as_pace.'">'.$as_kmh.'</span>';
		return '<span title="'.$as_kmh.'">'.$as_pace.'</span>';
	}

	/**
	 * Get the speed in min/km without unit
	 * @uses self::Time
	 * @param float $km   Distance [km]
	 * @param int $time   Time [s]
	 * @return string
	 */
	public static function Pace($km, $time) {
		if ($km == 0) {
			Error::getInstance()->addWarning("Helper::Pace($km, $time): Division by zero.");
			return '-:--';
		}

		return self::Time(round($time/$km));
	}

	/**
	 * Get the demanded pace if set in description (e.g. "... in 3:05 ...")
	 * @param string $description
	 * @return int
	 */
	public static function DescriptionToDemandedPace($description) {
		$array = explode("in ", $description);
		if (count($array) != 2)
			return 0;

		$array = explode(",", $array[1]);
		$array = explode(":", $array[0]);

		return sizeof($array) == 2 ? 60*$array[0]+$array[1] : 0;
	}

	/**
	 * Get the speed in km/h without unit
	 * @param float $km   Distance [km]
	 * @param int $time   Time [s]
	 * @return string
	 */
	public static function Kmh($km, $time) {
		return number_format($km*3600/$time, 1, ',', '.');
	}

	/**
	 * Display a distance as km or m
	 * @param float $km       Distance [km]
	 * @param int $decimals   Decimals after the point, default: 1
	 * @param bool $track     Run on a tartan track?, default: false
	 */
	public static function Km($km, $decimals = 1, $track = false) {
		if ($km == 0)
			return '';
		if ($track)
			return number_format($km*1000, 0, ',', '.').'m';
		return number_format($km, $decimals, ',', '.').'&nbsp;km';
	}

	/**
	 * Display the time as a formatted string
	 * @uses self::TwoNumbers
	 * @param int $time_in_s
	 * @param bool $show_days	Show days (default) or count hours > 24, default: true
	 * @param bool $show_zeros	Show e.g. '0:00:00' for 0, default: false, can be '2' for 0:00
	 * @return string
	 */
	public static function Time($time_in_s, $show_days = true, $show_zeros = false) {
		$string = '';
		if ($show_zeros === true) {
			$string = floor($time_in_s/3600).':'.self::TwoNumbers(floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
			if ($time_in_s - floor($time_in_s) != 0)
			$string .= ','.self::TwoNumbers(round(100*($time_in_s - floor($time_in_s))));
			return $string;
		}
		if ($show_zeros == 2)
			return (floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
		if ($time_in_s < 60)
			return number_format($time_in_s, 2, ',', '.').'s';
		if ($time_in_s >= 86400 && $show_days)
			$string = floor($time_in_s/86400).'d ';
		if ($time_in_s < 3600)
			$string .= (floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
		elseif ($show_days)
			$string .= (floor($time_in_s/3600)%24).':'.self::TwoNumbers(floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);
		else
			$string .= floor($time_in_s/3600).':'.self::TwoNumbers(floor($time_in_s/60)%60).':'.self::TwoNumbers($time_in_s%60);

		if ($time_in_s - floor($time_in_s) != 0 && $time_in_s < 3600)
			$string .= ','.self::TwoNumbers(round(100*($time_in_s - floor($time_in_s))));

		return $string;
	}

	/**
	 * Calculate time in seconds from a given string (min:s)
	 * @param string $string
	 * @return int
	 */
	public static function TimeToSeconds($string) {
		$TimeArray = explode(':', $string);
		if (count($TimeArray) == 2)
			return $TimeArray[0]*60 + $TimeArray[1];

		return $string;
	}

	/**
	 * Boolean flag: Is this training a competition?
	 * @param int $id
	 */
	public static function TrainingIsCompetition($id) {
		if (!is_numeric($id))
			return false;
		return (Mysql::getInstance()->num('SELECT 1 FROM `'.PREFIX.'training` WHERE `typid`='.CONF_WK_TYPID.' AND `id`='.$id) > 0);
	}

	/**
	 * Find the personal best for a given distance
	 * @uses self::Time
	 * @param float $dist       Distance [km]
	 * @param bool $return_time Return as integer, default: false
	 * @return mixed
	 */
	public static function PersonalBest($dist, $return_time = false) {
		$pb = Mysql::getInstance()->fetchSingle('SELECT `dauer`, `distanz` FROM `'.PREFIX.'training` WHERE `typid`='.CONF_WK_TYPID.' AND `distanz`="'.$dist.'" ORDER BY `dauer` ASC');
		if ($return_time)
			return ($pb != '') ? $pb['dauer'] : 0;
		if ($pb != '')
			return self::Time($pb['dauer']);
		return '<em>keine</em>';
	}

	/**
	 * Get the TRIMP for a training or get the minutes needed for a given TRIMP
	 * @param int $training_id   Training-ID
	 * @param bool $trimp        [optional] If set, calculate backwards, default: false     
	 * @return int
	 */
	public static function TRIMP($training_id, $trimp = false) {
		$dat = Mysql::getInstance()->fetch(PREFIX.'training', $training_id);
		if ($dat === false)
			$dat = array();

		$factor_a  = (CONF_GENDER == 'm') ? 0.64 : 0.86;
		$factor_b  = (CONF_GENDER == 'm') ? 1.92 : 1.67;
		$sportid   = ($dat['sportid'] != 0) ? $dat['sportid'] : 1;
		$sport     = Helper::Sport($sportid, true);
		$typ       = ($dat['typid'] != 0) ? self::TypeAsArray($dat['typid']) : 0;
		$HFavg     = ($dat['puls'] != 0) ? $dat['puls'] : $sport['HFavg'];
		$RPE       = ($typ != 0) ? $typ['RPE'] : $sport['RPE'];
		$HFperRest = ($HFavg - HF_REST) / (HF_MAX - HF_REST);
		$TRIMP     = $dat['dauer']/60 * $HFperRest * $factor_a * exp($factor_b * $HFperRest) * $RPE / 10;
	
		if ($trimp === false)
			return round($TRIMP);

		// Anzahl der noetigen Minuten fuer $back als TRIMP-Wert
		return $trimp / ( $HFperRest * $factor_a * exp($factor_b * $HFperRest) * 5.35 / 10 );
	}

	/**
	 * Calculating ActualTrainingLoad (at a given timestamp)
	 * @uses ATL_DAYS
	 * @uses DAY_IN_S
	 * @param int $time [optional] timestamp
	 */
	public static function ATL($time = 0) {
		if ($time == 0)
			$time = time();

		$dat = Mysql::getInstance()->fetch('SELECT SUM(`trimp`) as `sum` FROM `'.PREFIX.'training` WHERE `time` BETWEEN '.($time-ATL_DAYS*DAY_IN_S).' AND "'.$time.'"');
		return round($dat['sum']/ATL_DAYS);
	}

	/**
	 * Calculating ChronicTrainingLoad (at a given timestamp)
	 * @uses CTL_DAYS
	 * @uses DAY_IN_S
	 * @param int $time [optional] timestamp
	 */
	public static function CTL($time = 0) {
		if ($time == 0)
			$time = time();

		$dat = Mysql::getInstance()->fetch('SELECT SUM(`trimp`) as `sum` FROM `'.PREFIX.'training` WHERE `time` BETWEEN '.($time-CTL_DAYS*DAY_IN_S).' AND "'.$time.'"');
		return round($dat['sum']/CTL_DAYS);
	}

	/**
	 * Calculating TrainingStressBalance (at a given timestamp)
	 * @uses self::CTL
	 * @uses self::ATL
	 * @param int $time [optional] timestamp
	 */
	public static function TSB($time = 0) {
		return self::CTL($time) - self::ATL($time);
	}

	/**
	 * Creating a RGB-color for a given stress-value [0-100]
	 * @param int $stress   Stress-value [0-100]
	 */
	public static function Stresscolor($stress) {
		if ($stress > 100)
			$stress = 100;
		$gb = dechex(200 - 2*$stress);
		if ((200 - 2*$stress) < 16)
			$gb = '0'.$gb;
		return 'C8'.$gb.$gb;
	}

	/**
	 * Calculating basic endurance
	 * @uses DAY_IN_S
	 * @param bool $as_int as normal integer, default: false
	 * @param int $timestamp [optional] timestamp
	 */
	public static function BasicEndurance($as_int = false, $timestamp = 0) {
		// TODO: New algorithm
		$points = 0;
		if ($timestamp == 0)
			$timestamp = time();

		// Weekkilometers
		$wk_sum = 0;
		$data = Mysql::getInstance()->fetchAsArray('SELECT `time`, `distanz` FROM `'.PREFIX.'training` WHERE `time` BETWEEN '.($timestamp-140*DAY_IN_S).' AND '.$timestamp.' ORDER BY `time` DESC');
		foreach($data as $dat) {
			$tage = round ( ($timestamp - $dat['time']) / DAY_IN_S , 1 );
			$wk_sum += (2 - (1/70) * $tage) * $dat['distanz'];
		}
		$points += $wk_sum / 20;

		// LongJogs ...
		$data = Mysql::getInstance()->fetchAsArray('SELECT `distanz` FROM `'.PREFIX.'training` WHERE `typid`='.CONF_LL_TYPID.' AND `time` BETWEEN '.($timestamp-70*DAY_IN_S).' AND '.$timestamp.' ORDER BY `time` DESC');
		foreach($data as $dat) {
			$p = ($dat['distanz']-15) / 2;
			if ($p > 0)
				$points += ($dat['distanz']-15) / 2;
		}

		$points = round($points - 50);
		if ($points < 0)
			$points = 0;
		if ($points > 100)
			$points = 100;
	
		if ($as_int)
			return $points;

		return ($as_int) ? $points : $points.' &#37;';
	}

	/**
	 * Calculate a prognosis for a given distance
	 * @uses VDOT_FORM
	 * @uses self::BasicEndurance
	 * @uses self::PersonalBest
	 * @uses self::Time
	 * @uses self::Km
	 * @uses JD::CompetitionPrognosis
	 * @param float $dist Distance [km]
	 * @param bool $bahn  A track run?, default: false
	 * @param int $VDOT   Make prognosis for a given VDOT value? (used in plugin/panel.prognose)
	 */
	public static function Prognosis($dist, $bahn = false, $VDOT = 0) {
		$VDOT_new = ($VDOT == 0) ? VDOT_FORM : $VDOT;
		$pb = self::PersonalBest($dist, true);
		// Grundlagenausdauer
		if ($dist > 5)
			$VDOT_new *= 1 - (1 - self::BasicEndurance(true)/100) * (exp(0.005*($dist-5)) - 1);
		$prognose_dauer = JD::CompetitionPrognosis($VDOT_new, $dist);
		if ($VDOT != 0)
			return self::Time($prognose_dauer);
		$bisher_tag = ($prognose_dauer < $pb) ? 'del' : 'strong';
		$neu_tag = ($prognose_dauer > $pb) ? 'del' : 'strong';
		return '
	<p>
		<span>
			<small>von</small>
				<'.$bisher_tag.' title="VDOT '.JD::Competition2VDOT($dist, $pb).'">
					'.self::Time($pb).'
				</'.$bisher_tag.'>
			<small>auf</small>
				<'.$neu_tag.' title="VDOT '.$VDOT_new.'">
					'.self::Time($prognose_dauer).'
				</'.$neu_tag.'>
			<small>('.self::Pace($dist, $prognose_dauer).'/km)</small>
		</span>
		<strong>'.self::Km($dist, 0, $bahn).'</strong>
	</p>'.NL;
	}

	/**
	 * Get weather-data from external api if possible
	 * @return array
	 */
	public static function getWeatherData() {
		if (CONF_PLZ == 0)
			return array(NULL, 0);

		require_once('tcx/class.ParserTcx.php');
		$Parser = new ParserTcx(@file_get_contents('http://www.google.de/ig/api?weather='.CONF_PLZ.'&hl=de'));
		$Result = $Parser->getContentAsArray();

		$temp    = @(int)$Result['xml_api_reply']['weather']['current_conditions']['temp_c']['attr']['data'];
		$weather = @$Result['xml_api_reply']['weather']['current_conditions']['condition']['attr']['data'];

		// Translate weather to default wetter-names in own database
		switch ($weather) {
			case 'Meist sonnig':
			case 'Klar':				$weather = 'sonnig'; break;
			case 'Teils sonnig':		$weather = 'heiter'; break;
			case 'Bedeckt':
			case 'Meistens bew�lkt':
			case 'Bew�lkt':				$weather = 'bew&ouml;lkt'; break;
			case 'Vereinzelt st�rmisch':
			case 'Vereinzelte Schauer':
			case 'Vereinzelt Regen':	$weather = 'wechselhaft'; break;
			case 'Regen':
			case 'Gewitterschauer':		$weather = 'regnerisch'; break;
			case 'Schnee':				$weather = 'Schnee'; break;
			default: $weather = 'unbekannt'; break;
		}

		$data = Mysql::getInstance()->fetchSingle('SELECT `id` FROM `'.PREFIX.'wetter` WHERE `name`="'.$weather.'"');

		return array($temp, $data['id']);
	}

	/**
	 * Get a leading 0 if $int is lower than 10
	 * @param int $int
	 */
	public static function TwoNumbers($int) {
		return ($int < 10) ? '0'.$int : $int;
	}

	/**
	 * Get a special $string if $var is not set
	 * @param mixed $var
	 * @param string $string string to be displayed instead, default: ?
	 */
	public static function Unknown($var, $string = '?') {
		if ($var == NULL || !isset($var))
			return $string;

		if ((is_numeric($var) && $var != 0) || (!is_numeric($var) && $var != '') )
			return $var;

		return $string;
	}

	/**
	 * Cut a string if it is longer than $cut (default CUT_LENGTH)
	 * @uses CUT_LENGTH
	 * @param string $text
	 * @param int $cut [optional]
	 */
	public static function Cut($text, $cut = 0) {
		if ($cut == 0)
			$cut = CUT_LENGTH;

		if (strlen($text) >= $cut)
			return '<span title="'.$text.'">'.substr ($text, 0, $cut-3).'...</span>';

		return $text;
	}

	/**
	 * Get the timestamp of the start of the week
	 * @param int $time
	 */
	public static function Weekstart($time) {
		$w = date("w", $time);
		if ($w == 0)
			$w = 7;
		$w -= 1;
		return mktime(0, 0, 0, date("m",$time), date("d",$time)-$w, date("Y",$time));
	}

	/**
	 * Get the timestamp of the end of the week
	 * @param int $time
	 */
	public static function Weekend($time) {
		$start = self::Weekstart($time);
		return mktime(23, 59, 50, date("m",$start), date("d",$start)+6, date("Y",$start));
	}

	/**
	 * Get the name of a day
	 * @param string $w     date('w');
	 * @param bool $short   short version, default: false
	 */
	public static function Weekday($w, $short = false) {
		switch($w%7) {
			case 0: return ($short) ? 'So' : 'Sonntag';
			case 1: return ($short) ? 'Mo' : 'Montag';
			case 2: return ($short) ? 'Di' : 'Dienstag';
			case 3: return ($short) ? 'Mi' : 'Mittwoch';
			case 4: return ($short) ? 'Do' : 'Donnerstag';
			case 5: return ($short) ? 'Fr' : 'Freitag';
			case 6: return ($short) ? 'Sa' : 'Samstag';
		}
	}

	/**
	 * Get the name of the month
	 * @param string $m     date('m');
	 * @param bool $short   short version, default: false
	 */
	public static function Month($m, $short = false) {
		switch($m) {
			case 1: return ($short) ? 'Jan' : 'Januar';
			case 2: return ($short) ? 'Feb' : 'Februar';
			case 3: return ($short) ? 'Mrz' : 'M&auml;rz';
			case 4: return ($short) ? 'Apr' : 'April';
			case 5: return ($short) ? 'Mai' : 'Mai';
			case 6: return ($short) ? 'Jun' : 'Juni';
			case 7: return ($short) ? 'Jul' : 'Juli';
			case 8: return ($short) ? 'Aug' : 'August';
			case 9: return ($short) ? 'Sep' : 'September';
			case 10: return ($short) ? 'Okt' : 'Oktober';
			case 11: return ($short) ? 'Nov' : 'November';
			case 12: return ($short) ? 'Dez' : 'Dezember';
		}
	}

	/**
	 * Replace every comma with a point
	 * @param string $string
	 */
	public static function CommaToPoint($string) {
		return str_replace(",", ".", $string);
	}

	/**
	 * Replace ampersands for a textarea
	 * @param string $text
	 * @return string
	 */
	public static function Textarea($text) {
		return stripslashes(str_replace("&", "&amp;", $text));
	}

	/**
	 * Get ' checked="checked"' if boolean value is true
	 * @param bool $value
	 * @param mixed $value_to_be_checked [optional]
	 * @return string
	 */
	public static function Checked($value, $value_to_be_checked = NULL) {
		if ($value_to_be_checked != NULL)
			$value = ($value == $value_to_be_checked);
		if ($value == NULL || !isset($value))
			$value = false;

		return $value
			? ' checked="checked"'
			: '';
	}

	/**
	 * Get ' selected="selected"' if boolean value is true
	 * @param bool $value
	 * @param mixed $value_to_be_checked [optional]
	 * @return string
	 */
	public static function Selected($value, $value_to_be_checked = NULL) {
		if ($value_to_be_checked != NULL)
			$value = ($value == $value_to_be_checked);

		return $value
			? ' selected="selected"'
			: '';
	}

	/**
	 * Replace umlauts from AJAX
	 * @param string $text
	 * @return string
	 */
	public static function Umlaute($text) {
		$encrypted = array("ÃŸ", "Ã„", "Ã–", "Ãœ", "Ã¤", "Ã¶", "Ã¼");
		$correct   = array("ß",  "Ä",  "Ö",  "Ü",  "ä",  "ö",  "ü");
		$text = utf8_decode($text);

		return str_replace($encrypted, $correct, $text);
	}

	/**
	 * Calculate the variance of a given (numeric) array
	 * @param array $array
	 * @return double
	 */
	public static function getVariance($array) {
		$avg = array_sum($array) / count($array);
		$d   = 0;

		foreach ($array as $dat)
			if (is_numeric($dat))
				$d += pow($dat - $avg, 2);

		return ($d / count($array));
	}

	/**
	 * Check the modus of a row from dataset
	 * @param string $row   Name of dataset-row
	 * @return int   Modus
	 */
	public static function getModus($row) {
		$dat = Mysql::getInstance()->fetchSingle('SELECT `name`, `modus` FROM `'.PREFIX.'dataset` WHERE `name`="'.$row.'"');
		return $dat['modus'];
	}

	/**
	 * Return an empty td-Tag
	 * @param int $colspan
	 * @return string
	 */
	public static function emptyTD($colspan = 0) {
		$colspan = ($colspan > 0) ? ' colspan="'.$colspan.'"' : '';

		return '<td'.$colspan.'>&nbsp;</td>'.NL;
	}

	/**
	 * Get a tr-tag for a bold header-line containing all month-names
	 * @param int $fixedWidth Fixed width for every month-td in percent [set '0' for no fixed width]
	 * @param int $emptyTDs Number of empty td before the month-td
	 * @return string
	 */
	public static function monthTR($fixedWidth = 0, $emptyTDs = 1) {
		$width = ($fixedWidth > 0) ? ' width="'.$fixedWidth.'%"' : '';
		$html = '<tr class="b">'.NL;

		for ($i = 1; $i <= $emptyTDs; $i++)
			$html .= '<td />'.NL;

		for ($m = 1; $m <= 12; $m++)
			$html .= '<td'.$width.'>'.Helper::Month($m, true).'</td>'.NL;

		$html .= '</tr>'.NL;

		return $html;
	}

	/**
	 * Get a tr-tag for a bold header-line containing all years
	 * @param int $fixedWidth Fixed width for every year-td in percent [set '0' for no fixed width]
	 * @param int $emptyTDs Number of empty td before the year-td
	 * @return string
	 */
	public static function yearTR($fixedWidth = 0, $emptyTDs = 1) {
		$width = ($fixedWidth > 0) ? ' width="'.$fixedWidth.'%"' : '';
		$html = '<tr class="b">'.NL;

		for ($i = 1; $i <= $emptyTDs; $i++)
			$html .= '<td />'.NL;

		for ($y = START_YEAR; $y <= date("Y"); $y++)
			$html .= '<td'.$width.'>'.$y.'</td>'.NL;

		$html .= '</tr>'.NL;

		return $html;
	}

	/**
	 * Get a tr-tag for a space-line
	 * @param int $colspan
	 */
	public static function spaceTR($colspan) {
		return '
			<tr class="space">
				<td colspan="'.$colspan.'">
				</td>
			</tr>'.NL;
	}

	/**
	 * Return a break with class="clear"
	 * @return string
	 */
	public static function clearBreak() {
		return '<br class="clear" />';
	}

	/**
	 * Get the HFmax from user-table
	 * @return int   HFmax
	 */
	public static function getHFmax() {
		if (defined('HF_MAX'))
			return HF_MAX;

		$userdata = Mysql::getInstance()->fetchSingle('SELECT `puls_max` FROM `'.PREFIX.'user` ORDER BY `time` DESC');

		if ($userdata === false) {
			Error::getInstance()->addWarning('HFmax is not set in database, 200 as default.');
			return 200;
		} elseif ($userdata['puls_max'] == 0) {
			Error::getInstance()->addWarning('HFmax is 0, taking 200 as default.');
			return 200;
		}

		return $userdata['puls_max'];
	}

	/**
	 * Get the HFrest from user-table
	 * @return int   HFrest
	 */
	public static function getHFrest() {
		if (defined('HF_REST'))
			return HF_MAX;

		$userdata = Mysql::getInstance()->fetchSingle('SELECT `puls_ruhe` FROM `'.PREFIX.'user` ORDER BY `time` DESC');

		if ($userdata === false) {
			Error::getInstance()->addWarning('HFrest is not set in database, 60 as default.');
			return 60;
		}

		return $userdata['puls_ruhe'];
	}

	/**
	 * Get timestamp of first training
	 * @return int   Timestamp
	 */
	public static function getStartTime() {
		$data = Mysql::getInstance()->fetch('SELECT MIN(`time`) as `time` FROM `'.PREFIX.'training`');

		if ($data === false || $data['time'] == 0)
			return time();

		return $data['time'];
	}
}
?>