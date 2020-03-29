<?php

# makes a demographics table listing all of the data by first CDA date.
# intended to be modified
# for aggregate data

use \Vanderbilt\CareerDevLibrary\Links;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\FlightTrackerExternalModule\CareerDev;

require_once(dirname(__FILE__).'/baseWeb.php');
require_once(dirname(__FILE__).'/../CareerDev.php');
require_once(dirname(__FILE__).'/../classes/Links.php');
require_once(dirname(__FILE__).'/../classes/Download.php');
require_once(dirname(__FILE__).'/../classes/REDCapManagement.php');
require_once(dirname(__FILE__).'/../../../redcap_connect.php');

$nameForAll = "Entire Society";
if ($_GET['cohort']) {
	$nameForAll = "Entire Cohort";
}

echo "<script>var data = {};</script>";

$metadata = Download::metadata($token, $server);

# fields to download: summary, scholars' survey, degree fields
$fields = array_merge(array("identifier_last_name", "identifier_first_name", "record_id"), CareerDev::$summaryFields, CareerDev::$checkFields, getDegreeFields($metadata));
$redcapData = Download::getIndexedRedcapData($token, $server, $fields, $_GET['cohort'], $metadata);

# get the cohorts
$cohorts = array();
foreach ($redcapData as $recordId => $rows) {
	foreach ($rows as $row) {
		if ($cohort = \Vanderbilt\FlightTrackerExternalModule\getCohort($row)) {
			if (!isset($cohorts[$cohort])) {
				$cohorts[$cohort] = array();
			}
			$cohorts[$cohort][$recordId] = $row['identifier_first_name']." ".$row['identifier_last_name'];
		}
	}
}

# translates a select_choices string into an array
function translateChoices($choiceStr) {
	$choices2 = preg_split("/\s*\|\s*/", $choiceStr);
	if (count($choices2) == 1) {
		return array();
	}
	$choices = array();
	foreach($choices2 as $choice) {
		$a = preg_split("/\s*,\s*/", $choice);
		if (count($a) > 2) {
			$b = array();
			$b[] = $a[0];
			$b[] = $a[1];
			for ($i = 2; $i < count($a); $i++) {
				$b[1] = $b[1].", ".$a[$i];
			}
			$a = $b;
		}
		$choices[(string) $a[0]] = $a[1];
	}
	return $choices;
}

# function to be called dynamically
# gets external k-to-r01 conversion rate
# currently replaced by a summary field
function get_converted_k_to_r01($row) {
	if ($row['summary_first_external_k'] && $row['summary_first_r01']) {
		return 1;
	} else if ($row['summary_first_external_k']) {
		if (REDCapManagement::datediff($row['summary_first_external_k'], date('Y-m-d'), "y") <= 5) {
			return 2;
		} else {
			return 3;
		} 
	} else {
		return 4;
	}
}

# function to be called dynamically
# gets any k-to-r01 conversion rate
# currently replaced by a summary field
function get_converted_any_k_to_r01($row) {
	if ($row['summary_first_any_k'] && $row['summary_first_r01']) {
		return 1;
	} else if ($row['summary_first_any_k']) {
		if (REDCapManagement::datediff($row['summary_first_any_k'], date('Y-m-d'), "y") <= 5) {
			return 2;
		} else {
			return 3;
		} 
	} else {
		return 4;
	}
}

# called dynamically
# categories for any k conversion rate
function get_converted_any_k_to_r01_cats() {
	$ary = array();
	$ary[1] = "Converted Any K to R01-or-Equivalent";
	$ary[2] = "Has Any K <= 5 Years Old; No R01-or-Equivalent";
	$ary[3] = "Has Any K; No R01-or-Equivalent";
	$ary[4] = "No Any K";
	return $ary;
}

# called dynamically
# categories for external k conversion rate
function get_converted_k_to_r01_cats() {
	$ary = array();
	$ary[1] = "Converted External K to R01-or-Equivalent";
	$ary[2] = "Has External K <= 5 Years Old; No R01-or-Equivalent";
	$ary[3] = "Has External K; No R01-or-Equivalent";
	$ary[4] = "No External K";
	return $ary;
}

# get timespan from any k to r01/equivalent
function get_any_timespan_less_than_five($row) {
	if ($row['summary_first_any_k'] && $row['summary_first_r01']) {
		$val = REDCapManagement::datediff($row['summary_first_any_k'], $row['summary_first_r01'], "y");
		if ($val > 5) {
			return 2;  // no
		} else {
			return 1;  // yes
		}
	} else if ($row['summary_first_any_k']) {
		$d = strtotime($row['summary_first_any_k']);
		$dnow = strtotime('now'); 
		$span = $dnow - $d;
		if ($span > 5 * 3600 * 24 * 365) {
			return 2; // no
		} else {
			return 3; // unknown
		}
	} else {
		return 3;
	}
}

# get timespan from external k to r01/equivalent
function get_timespan_less_than_five($row) {
	if ($row['summary_first_external_k'] && $row['summary_first_r01']) {
		$val = REDCapManagement::datediff($row['summary_first_external_k'], $row['summary_first_r01'], "y");
		if ($val > 5) {
			return 2;  // no
		} else {
			return 1;  // yes
		}
	} else if ($row['summary_first_external_k']) {
		$d = strtotime($row['summary_first_external_k']);
		$dnow = strtotime('now'); 
		$span = $dnow - $d;
		if ($span > 5 * 3600 * 24 * 365) {
			return 2; // no
		} else {
			return 3; // unknown
		}
	} else {
		return 3;
	}
}

# timespan categories
function get_any_timespan_less_than_five_cats() {
	$ary = array();
	$ary[1] = "Yes";
	$ary[2] = "No";
	$ary[3] = "In Progress";
	return $ary;
}

# timespan categories
function get_timespan_less_than_five_cats() {
	$ary = array();
	$ary[1] = "Yes";
	$ary[2] = "No";
	$ary[3] = "In Progress";
	return $ary;
}

function get_average_age_at_first_r($data) {
	$rs = array(5, 6);
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				for ($i = 1; $i <= 15; $i++) {
					if ($row['summary_dob'] && $row['summary_award_date_'.$i] && in_array($row['summary_award_type_'.$i], $rs)) {
						$ages[] = REDCapManagement::datediff($row['summary_dob'], $row['summary_award_date_'.$i], "y");
					}
				}
			}
		}
	}
	$sum = array_sum($ages);
	return (floor($sum * 10 / count($ages)) / 10);
}

function get_average_age_at_first_cda($data) {
	$ages = array();
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				if ($row['summary_award_date_1'] && $row['summary_dob']) {
					$ages[] = REDCapManagement::datediff($row['summary_dob'], $row['summary_award_date_1'], "y");
				}
			}
		}
	}
	$sum = array_sum($ages);
	return (floor($sum * 10 / count($ages)) / 10);
}

# calculates the average age of the group
# skips over those with no date-of-birth
function get_average_age($data) {
	$ages = array();
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				if ($row['summary_dob']) {
					$ages[] = REDCapManagement::datediff($row['summary_dob'], date("Y-m-d"), "y");
				}
			}
		}
	}
	$sum = array_sum($ages);
	return (floor($sum * 10 / count($ages)) / 10);
}

# get average conversion time for any K's
function get_any_k_to_r_conversion_average($data) {
	$ks = array(1, 2, 3, 4);
	$rs = array(5, 6);
	$diffs = array();
	
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				$first_k = "";
				$first_r = "";
				for ($i = 1; $i <= 15; $i++) {
					if ($row['summary_award_type_'.$i]) {
						if (in_array($row['summary_award_type_'.$i], $ks)) {
							if (!$first_k) {
								$first_k = $row['summary_award_date_'.$i];
							}
						}
						else if (in_array($row['summary_award_type_'.$i], $rs)) {
							if (!$first_r) {
								$first_r = $row['summary_award_date_'.$i];
							}
						}
					}
				}
		
				if ($first_k && $first_r) {
					$diffs[] = REDCapManagement::datediff($first_k, $first_r, "y");
				}
			}
		
			$sum = array_sum($diffs);
			return (floor($sum * 10 / count($diffs)) / 10)." years";
		}
	}
}
		
# get average conversion percentage for any K's
function get_any_k_to_r_conversion_rate($data) {
	$ks = array(1, 2, 3, 4);
	$rs = array(5, 6);
	$diffs = array();
	$started = array();
	$ageThreshold = 5;
	
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				$firstCDAAge = 0;
				if ($row['summary_award_date_1']) {
					$firstCDAAge = REDCapManagement::datediff($row['summary_award_date_1'], date("Y-m-d"), "y");
				}
				$first_k = "";
				$first_r = "";
				for ($i = 1; $i <= 15; $i++) {
					if ($row['summary_award_type_'.$i]) {
						if (in_array($row['summary_award_type_'.$i], $ks)) {
							if (!$first_k) {
								$first_k = $row['summary_award_date_'.$i];
							}
						}
						else if (in_array($row['summary_award_type_'.$i], $rs)) {
							if (!$first_r) {
								$first_r = $row['summary_award_date_'.$i];
							}
						}
					}
				}

				if ($first_k && $first_r) {
					$diffs[] = REDCapManagement::datediff($first_k, $first_r, "y");
					$started[] = $first_k;
				} else if ($first_k && ($firstCDAAge > $ageThreshold)) {
					$started[] = $first_k;
				}
			}
		}
	}
	return count($diffs)." (".(floor(1000 * count($diffs) / count($started)) / 10)."%)";
}

# get average conversion time for external K's
function get_external_k_to_r_conversion_average($data) {
	$ks = array(3, 4);
	$rs = array(5, 6);
	$diffs = array();
	
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				$first_k = "";
				$first_r = "";
				for ($i = 1; $i <= 15; $i++) {
					if ($row['summary_award_type_'.$i]) {
						if (in_array($row['summary_award_type_'.$i], $ks)) {
							if (!$first_k) {
								$first_k = $row['summary_award_date_'.$i];
							}
						}
						else if (in_array($row['summary_award_type_'.$i], $rs)) {
							if (!$first_r) {
								$first_r = $row['summary_award_date_'.$i];
							}
						}
					}
				}

				if ($first_k && $first_r) {
					$diffs[] = REDCapManagement::datediff($first_k, $first_r, "y");
				}
			}
		}
	}

	$sum = array_sum($diffs);
	return (floor($sum * 10 / count($diffs)) / 10)." years";
}

# get average conversion rate for external K's
function get_external_k_to_r_conversion_rate($data) {
	$ks = array(3, 4);
	$rs = array(5, 6);
	$diffs = array();
	$started = array();

	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				$first_k = "";
				$first_r = "";
				for ($i = 1; $i <= 15; $i++) {
					if ($row['summary_award_type_'.$i]) {
						if (in_array($row['summary_award_type_'.$i], $ks)) {
							if (!$first_k) {
								$first_k = $row['summary_award_date_'.$i];
							}
						}
						else if (in_array($row['summary_award_type_'.$i], $rs)) {
							if (!$first_r) {
								$first_r = $row['summary_award_date_'.$i];
							}
						}
					}
				}

				if ($first_k && $first_r) {
					$diffs[] = REDCapManagement::datediff($first_k, $first_r, "y");
				}
				if ($first_k) {
					$started[] = $first_k;
				}
			}
		}
	}
	return count($diffs)." (".(floor(1000 * count($diffs) / count($started)) / 10)."%)";
}

# get percentage who have an internal K
function get_internal_k($data) {
	$int_ks = array(1);
	$people = array();
	$ever = array();
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['identifier_first_name']) {
				$people[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				$qualified = false;
				for ($i = 1; $i <= 15; $i++) {
					if (in_array($row['summary_award_type_'.$i], $int_ks)) {
						$qualified = true;
					}
				}
				if ($qualified) {
					$ever[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				}
			}
		}
	}
	return count($ever)." (".(floor(count($ever) * 1000 / count($people)) / 10)."%)";
}

# get percentage who have an individual K/equivalent
function get_individual_k_or_equiv($data) {
	$ks = array(3, 4);
	$people = array();
	$ever = array();
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['identifier_first_name']) {
				$people[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				$qualified = false;
				for ($i = 1; $i <= 15; $i++) {
					if (in_array($row['summary_award_type_'.$i], $ks)) {
						$qualified = true;
					}
				}
				if ($qualified) {
					$ever[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				}
			}
		}
	}
	return count($ever)." (".(floor(count($ever) * 1000 / count($people)) / 10)."%)";
}

# get percentage who have a K12/KL2
function get_k12_kl2($data) {
	$k12s = array(2);
	$people = array();
	$ever = array();
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['identifier_first_name']) {
				$people[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				$qualified = false;
				for ($i = 1; $i <= 15; $i++) {
					if (in_array($row['summary_award_type_'.$i], $k12s)) {
						$qualified = true;
					}
				}
				if ($qualified) {
					$ever[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				}
			}
		}
	}
	return count($ever)." (".(floor(count($ever) * 1000 / count($people)) / 10)."%)";
}

# get percentage who have a R01/equivalent
function get_r01_or_equiv($data) {
	$rs = array(5, 6);
	$people = array();
	$ever = array();
	foreach ($data as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['identifier_first_name']) {
				$people[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				$qualified = false;
				for ($i = 1; $i <= 15; $i++) {
					if (in_array($row['summary_award_type_'.$i], $rs)) {
						$qualified = true;
					}
				}
				if ($qualified) {
					$ever[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				}
			}
		}
	}
	return count($ever)." (".(floor(count($ever) * 1000 / count($people)) / 10)."%)";
}

# returns the total number of publications in the database
function get_total_publications($data) {
	global $token, $server, $metadata;
	$fields = array("record_id", "citation_include");
	$redcapData = Download::getIndexedRedcapData($token, $server, $fields, $_GET['cohort'], $metadata);
	$total = 0;
	foreach ($redcapData as $recordId => $rows) {
		foreach ($rows as $row) {
			if ($row['citation_include'] == '1') {
				$total++;
			}
		}
	} 
	return \Vanderbilt\FlightTrackerExternalModule\pretty($total);
}

# if $withinFiveYears == true, counts if have first k within 5 years or first internal k within 3 years
function get_current_cda($data, $withinFiveYears = true) {
	$ks = array(3, 4);
	$int_ks = array(1, 2);
	$rs = array(5, 6);
	$today = date("Y-m-d");
	$people = array();
	$qualifiers = array();

	foreach ($data as $recordId => $rows) {
		$first_r = "";
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				if ($row['identifier_first_name']) {
					$people[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				}
				$qualified = false;
				for ($i = 1; $i <= 15; $i++) {
					if (in_array($row['summary_award_type_'.$i], $rs)) {
						if (!$first_r) {
							$first_r = $row['summary_award_date_'.$i];
						}
					}
				}
				if (!$first_r) {
					$first_k = "";
					for ($i = 1; $i <= 15; $i++) {
						if (in_array($row['summary_award_type_'.$i], $ks)) {
							if (!$first_k) {
								if (!$withinFiveYears) {
									$qualified = true;
								} else if (REDCapManagement::datediff($row['summary_award_date_'.$i], $today, "y") <= 5) {
									$qualified = true;
								}
								$first_k = $row['summary_award_date_'.$i];
							}
						}
					}
					if (!$first_k) {
						$first_int_k = "";
						for ($i = 1; $i <= 15; $i++) {
							if (in_array($row['summary_award_type_'.$i], $int_ks)) {
								if (!$first_int_k) {
									if (!$withinFiveYears) {
										$qualified = true;
									} else if (REDCapManagement::datediff($row['summary_award_date_'.$i], $today, "y") <= 3) {
										$qualified = true;
									}
									$first_int_k = $row['summary_award_date_'.$i];
								}
							}
						}
					}
				}
				if ($qualified) {
					$qualifiers[] = $row['identifier_first_name']." ".$row['identifier_last_name'];
				}
			}
		}
	}
	return count($qualifiers)." (".(floor(count($qualifiers) / count($people) * 1000) / 10)."%)";
}


$tableRows = array("summary_degrees", "summary_gender", "summary_race_ethnicity", "summary_primary_dept", "summary_award_type_1","summary_ever_external_k_to_r01_equiv", "summary_ever_first_any_k_to_r01_equiv", "summary_ever_last_external_k_to_r01_equiv", "summary_ever_last_any_k_to_r01_equiv", "summary_ever_internal_k","summary_ever_individual_k_or_equiv","summary_ever_k12_kl2","summary_ever_r01_or_equiv");
$summaries = array(
	"Average Age" => array("get_average_age", $nameForAll),
	"Average Age at First CDA" => array("get_average_age_at_first_cda", $nameForAll),
	"Average Age at First R" => array("get_average_age_at_first_r", $nameForAll),
	"Any-K-to-R Conversion Rate<br>(omit within 5 years)" => array("get_any_k_to_r_conversion_rate", "All with Any K"),
	"Any-K-to-R Conversion Average" => array("get_any_k_to_r_conversion_average", "All who Converted"),
	"External-K-to-R Conversion Rate<br>(omit within 5 years)" => array("get_external_k_to_r_conversion_rate", "All with External K"),
	"External-K-to-R Conversion Average" => array("get_external_k_to_r_conversion_average", "All who Converted"),
	"Ever Internal K" => array("get_internal_k", $nameForAll),
	"Ever Individual K or Equivalent" => array("get_individual_k_or_equiv", $nameForAll),
	"Ever K12/KL2" => array("get_k12_kl2", $nameForAll),
	"Ever R01 or Equivalent" => array("get_r01_or_equiv", $nameForAll),
	"Current CDA" => array("get_current_cda", $nameForAll),
	"Total Finalized Publications" => array("get_total_publications", $nameForAll),
);

$tableChoices = array();
foreach ($tableRows as $tableRow) {
	$found = false;
	foreach ($metadata as $row) {
		if ($row['field_name'] == $tableRow) {
			$found = true;
			if ($row['field_type'] == "yesno") {
				$tableChoices[$tableRow] = translateChoices("0, No | 1, Yes");
			} else {
				$tableChoices[$tableRow] = translateChoices($row['select_choices_or_calculations']);
				# these are group categories that are defined by a search text
				foreach ($tableChoices[$tableRow] as $choice => $value) {
					if (preg_match("/^Medicine \[.+\]$/", $value)) {
						$tableChoices[$tableRow]["/^Medicine/"] = "Medicine (All)";
					} else if (preg_match("/^Neurology \[.+\]$/", $value)) {
						$tableChoices[$tableRow]["/^Neurology/"] = "Neurology (All)";
					} else if (preg_match("/^Pediatrics\/General Pediatrics \[.+\]$/", $value)) {
						$tableChoices[$tableRow]["/Pediatrics/"] = "Pediatrics (All)";
					} else if (preg_match("/^Psychiatry\/Adult Psychiatry \[.+\]$/", $value)) {
						$tableChoices[$tableRow]["/^Psychiatry/"] = "Psychiatry (All)";
					} else if (preg_match("/^Surgery \[.+\]$/", $value)) {
						$tableChoices[$tableRow]["/Surgery/"] = "Surgery (All)";
					} else if (preg_match("/^Otolaryngology/", $value)) {
						$tableChoices[$tableRow][$choice] = "Surgery/Otolaryngology [104781]";
					} else if (preg_match("/^Orthopaedics/", $value)) {
						$tableChoices[$tableRow][$choice] = "Surgery/Orthopaedics and Rehabilitation [104475]";
					}
				}
			}
		}
	}
	if (!$found) {
		$functionName = "get_".$tableRow."_cats";
		$tableChoices[$tableRow] = $functionName();
	}
}
$tableData = array();
$cda = array("Total");
foreach ($tableChoices as $tableChoice => $tableAry) {
	$tableData[$tableChoice] = array();
	if (empty($tableAry)) {
		$tableData[$tableChoice]["TYPE"] = "SINGLE";
		foreach ($cda as $i => $date) {
			$tableData[$tableChoice][$i] = 0;
		}
	} else {
		foreach ($tableAry as $value => $label) {
			$tableData[$tableChoice]["TYPE"] = "ARRAY";
			$tableData[$tableChoice]["TOTAL"] = array();
			$tableData[$tableChoice][$value] = array();
			foreach ($cda as $i => $date) {
				$tableData[$tableChoice][$value][$i] = 0;
				$tableData[$tableChoice]["TOTAL"][$i] = 0;
			}
		}
	}
}

function getDegreeFields($metadata) {
	 $possible = array(
				"vfrs_graduate_degree",
				"vfrs_degree2",
				"vfrs_degree3",
				"vfrs_degree4",
				"vfrs_degree5",
				"vfrs_please_select_your_degree",
				"newman_demographics_degrees",
				"newman_data_degree1",
				"newman_data_degree2",
				"newman_data_degree3",
				"newman_sheet2_degree1",
				"newman_sheet2_degree2",
				"newman_sheet2_degree3",
				"newman_new_degree1",
				"newman_new_degree2",
				"newman_new_degree3",
				"check_degree1",
				"check_degree2",
				"check_degree3",
				"check_degree4",
				"check_degree5",
				);
	$included = array();
	foreach ($metadata as $row) {
		if (in_array($row['field_name'], $possible)) {
			array_push($included, $row['field_name']);
		}
	}
	return $included;
}

function makeLink($row) {
	global $pid, $metadata;
	$recordId = $row['record_id'];
	$name = $row['identifier_first_name']." ".$row['identifier_last_name'];

	$degrees = array();
	$fields = getDegreeFields($metadata);
	$legend = array(
			1 => "MD",
			2 => "PhD",
			6 => "Other",
			7 => "MD, MSCI",
			8 => "MD, MS",
			9 => "MD, PhD",
			10 => "MS, MD, PhD",
			11 => "MHS",
			12 => "MD, MHS",
			13 => "PharmD",
			14 => "MD, PharmD",
			15 => "PsyD",
			16 => "MPH, MS",
			17 => "RN",
			3 => "MPH",
			4 => "MSCI",
			5 => "MS",
			);
	foreach ($fields as $field) {
		$value = $row[$field];
		if ($value && !in_array($value, $degrees)) {
			$degrees[] = $value;
		}
	}
	$degreeLetters = array();
	foreach ($degrees as $degree) {
		$degreeLetters[] = $legend[$degree];
	}

	$age = floor($row['summary_award_age_1']);
	if (!$age) {
		$age = "<span style='color: red;'>N/A</span>";
	}
	$eventIds = array(66635 => 155946, 68465 => 159386);
	return Links::makeSummaryLink($pid, $recordId, $eventIds[$pid], "Record $recordId: $name")." <span style='font-size: 12px;'>Age at first CDA: $age</span>";
}

$noDate = array();
$processed = array();
foreach($redcapData as $recordId => $rows) {
	foreach ($rows as $row) {
		$categories = array(0);
		if ($row['summary_award_date_1']) {
			$cohorts = \Vanderbilt\FlightTrackerExternalModule\getCohorts($row);
			if (in_array("1998-2002", $cohorts)) {
				$categories[] = 1;
			}
			if (in_array("2003-2007", $cohorts)) {
				$categories[] = 2;
			}
			if (in_array("2008-2012", $cohorts)) {
				$categories[] = 3;
			}
			if (in_array("2013-2017", $cohorts)) {
				$categories[] = 4;
			}
			if (in_array("KL2s + Int_Ks", $cohorts)) {
				$categories[] = 5;
			}
		}
		if ($row['redcap_repeat_instrument'] == "") {
        		$link = makeLink($row);
			if (!$date) {
				$noDate[] = $row['record_id'];
            			$link .= "<span style='color: red;'> *no award*</span>";
			}
			if (!$row['summary_primary_dept']) {
            			$link .= "<span style='color: red;'> *no department*</span>";
			}
			if (!$row['summary_race_ethnicity']) {
            			$link .= "<span style='color: red;'> *no race/ethnicity*</span>";
			}
			if (!isset($_GET['CDAOnly']) || !in_array($row['record_id'], $noDate)) {
				$processed[] = $link;
			}
		}
		if (!isset($_GET['CDAOnly']) || !in_array($row['record_id'], $noDate)) {
			foreach ($tableRows as $tableRow) {
				if ($tableData[$tableRow]["TYPE"] == "SINGLE") {
					foreach ($categories as $cat) {
						if ($cat != "TYPE") {
							$tableData[$tableRow][$cat]++;
						}
					}
				} else {
					if (!preg_match("/^summary_/", $tableRow)) {
						if ($row['redcap_repeat_instrument'] == "") {
							foreach ($categories as $cat) {
								$functionName = "get_".$tableRow;
								$value = $functionName($row);
								if ($value !== "") {
									$tableData[$tableRow][$value][$cat]++;
									$tableData[$tableRow]["TOTAL"][$cat]++;
								}
							}
						}
	    				} else if ($row[$tableRow] !== "") {
						foreach ($categories as $cat) {
							$tableData[$tableRow][$row[$tableRow]][$cat]++;
							$tableData[$tableRow]["TOTAL"][$cat]++;
						}
					}
				}
			}
		}
	}
}

# get label for the table
function getLabel($field, $metadata) {
	foreach ($metadata as $row) {
		if ($row['field_name'] == $field) {
			return $row['field_label'];
		}
	}
	if ($field == "timespan_less_than_five") {
		return "External K to R01 < 5 Years";
	}
	if ($field == "any_timespan_less_than_five") {
		return "Any K to R01 < 5 Years";
	}
	if ($field == "converted_k_to_r01") {
		return "Ever Converted External K to Any R01";
	}
	if ($field == "converted_any_k_to_r01") {
		return "Ever Converted Any K to Any R01";
	}
	return "";
}

# this puts out the table; code is complex and confusing, so tread with care

echo "<h1>Grant Demographics</h1>\n";
if ($_GET['cohort']) {
	echo "<h2>For Cohort ".$_GET['cohort']."</h2>\n";
}
echo "<p class='centered'>Cohort: ".\Vanderbilt\FlightTrackerExternalModule\getCohortSelect($token, $server, $pid)."</p>\n";
echo \Vanderbilt\FlightTrackerExternalModule\makeHeadersOfTables("<h4>");
echo "<br><br>";
?>
<script>
function showNames(rowno, colno) {
	if (<?= count($processed) ?> <= 100) {
	}
}
</script>
<?php
# display the cohorts
echo "<script>\n";
echo "function hideCohorts() {\n";
echo "	console.log('hideCohorts');\n";
foreach ($cohorts as $cohort => $cohortData) {
	echo "	$('#cohort_$cohort').hide();\n";
}
echo "}\n";
echo "</script>";
echo "<table style='margin-left: auto; margin-right: auto;'>";
echo "<tr><th>Variable</th><th>Population</th><th>Value</th></tr>";
foreach ($summaries as $header => $ary) {
	$func = $ary[0];
	$pool = $ary[1];
	echo "<tr>";
	echo "<th>".$header."</th>";
	echo "<td class='pool'>$pool</td>";
	echo "<td>";
	echo $func($redcapData);
	echo "</td>";
	echo "</tr>";
}
echo "</table>";
echo "<br><br>";
echo "<p class='recordsSummary'>".count($processed)." records processed";
if (!isset($_GET['CDAOnly'])) {
	echo "; ".count($noDate)." records are without a Career Development Award (CDA).";
} else {
	echo ".";
}
echo "<br>The Total column includes all records with and without a CDA; all other columns are categorized by their CDA.</p>";
?>
<style>
h1 { text-align: center; }
h4 { margin: 2px; text-align: center; }
.centered { text-align: center; }
.border { border: 1px solid #cccccc; padding: 8px; text-align: center; }
.smallfont { font-size: 12px; }
.normalfont { font-size: 14px; }
.recordsSummary { text-align: center; font-size: 14px; }
table { border-radius: 8px; margin-left:auto; margin-right:auto; border: 1px solid #cccccc; }
th { padding-left: 8px; padding-right: 8px;  font-size: 18px; background-color: #eeeeee; }
th.top { font-size: 18px; width: 60px; background-color: #c1e7f9; }
th.left { font-size: 18px; width: 510px; }
.total { background-color: #dddddd; }
.subheader { font-size: 18px; background-color: #bbbbbb; }
th.total { font-size: 18px; background-color: #bbbbbbb; }
th.summary { background-color: #c1e7f9; }
td.pool { padding-left: 8px; padding-right: 8px; background-color:#eeeeee; text-align: center;  font-size: 14px; }
tr:hover td { background-color:#aaaaaa; }
tr:hover th { background-color:#aaaaaa; }
tr.fixed { position: fixed; top: 0px; }
.red { color: red; text-align: center; }
.black { color: black; text-align: center; }
</style>
<?php
# unit test
foreach ($tableData as $tableRow => $ary) {
	$label = getLabel($tableRow, $metadata);
	if ($ary['TYPE'] == "SINGLE") {
		$total = $ary[0];
		$categorized = 0;
		for ($i = 1; $i < count($ary); $i++) {
			$categorized += $ary[$i];
		}
	} else {
		$total = 0;
		$categorized = 0;
		foreach ($cda as $i => $cat) {
			if ($i === 0) {
				$total = $ary['TOTAL'][$i];
			} else {
				$categorized += $ary['TOTAL'][$i];
			}

		}
		foreach ($headerInfo[$tableRow] as $value => $choiceLabel) {
			$cats = $ary[$value];
			foreach ($cats as $i => $num) {
				$total = array();
				foreach ($cats as $i => $num) {
					$total[$i] = 0;
					foreach ($headerInfo[$tableRow] as $value2 => $choiceLabel2) {
						if (preg_match($value, $choiceLabel2)) {
							$total[$i] += $ary[$value2][$i];
						}
					}
				}
			}
			$categorized = 0;
			for ($i = 1; $i < count($total); $i++) {
				$categorized += $total[$i];
			}
		}
	}
}

# print table
echo "<table class='fixedHeaders'>";
echo "<tr class='fixed'><th class='border left'>Category</th>";
foreach ($cda as $i => $years) {
	$onclick = "";
	if (preg_match("/\-/", $years)) {
		$onclick = "onclick='hideCohorts(); \$(\"#cohort_$years\").show();'";
		$years = "<a class='black' href='#cohort_$years'>$years</a>";
	}
	echo "<th class='border top' $onclick>$years</th>";
}
echo "</tr>";
echo "<tr><th class='border left'>Category</th>";
foreach ($cda as $i => $years) {
	$onclick = "";
	if (preg_match("/\-/", $years)) {
		$onclick = "onclick='hideCohorts(); \$(\"#cohort_$years\").show();'";
		$years = "<a class='black' href='#cohort_$years'>$years</a>";
	}
	echo "<th class='border top' $onclick>$years</th>";
}
echo "</tr>";
$headerInfo = array();
$skip = array("TYPE", "TOTAL");
foreach ($tableData as $tableRow => $ary) {
	$headerInfo[$tableRow] = array();
	foreach ($ary as $value => $cats) {
		$value = (String) $value;
		if (!in_array($value, $skip)) {
			if (isset($tableChoices[$tableRow][$value])) {
				$headerInfo[$tableRow][$value] = $tableChoices[$tableRow][$value];
			} else {
				$headerInfo[$tableRow][$value] = "";
			}
		}
	}
}
foreach ($headerInfo as $tableRow => $ary) {
	if (($tableRow == "timespan_less_than_five") || ($tableRow == "any_timespan_less_than_five")) {
		ksort($headerInfo[$tableRow]);
	} else {
		asort($headerInfo[$tableRow]);
	}
}

foreach ($tableData as $tableRow => $ary) {
	$label = getLabel($tableRow, $metadata);
	if ($label != "Award Date #1") {
		echo "<tr>";
		if ($ary["TYPE"] == "SINGLE") {
			echo "<th class='border'>$label</th><td class='border total'>{$ary[0]}</td><td class='border'>{$ary[1]}</td><td class='border'>{$ary[2]}</td><td class='border'>{$ary[3]}</td><td class='border'>{$ary[4]}</td><td class='border'{$ary[5]}</td>";
		} else {
			echo "<th class='border subheader'><br><br><br>$label</th>";
			foreach ($cda as $i => $cat) {
				echo "<td class='border subheader'><br><br><br>n={$ary['TOTAL'][$i]}</td>";
			}
			echo "</tr>";
			foreach ($headerInfo[$tableRow] as $value => $choiceLabel) {
				$cats = $ary[$value];
				if (($value !== "TYPE") && ($value !== "TOTAL")) {
					$printRow = false;
					if (preg_match("/\(All\)$/", $choiceLabel)) {
						$total = array();
						# num is invalid because this is a general category
						foreach ($cats as $i => $num) {
							$total[$i] = 0;
							foreach ($headerInfo[$tableRow] as $value2 => $choiceLabel2) {
								if (preg_match($value, $choiceLabel2)) {
									$total[$i] += $ary[$value2][$i];
								}
							}
							if ($total[$i] > 0) {
								$cats[$i] = (floor(1000 * $total[$i] / $ary['TOTAL'][$i]) / 10)."%<br><span class='smallfont'>({$total[$i]})</span>";
							} else {
								$cats[$i] = "0%<br><span class='smallfont'>({$total[$i]})</span>";
							}
							if ($total[$i] == 0) {
								$cats[$i] = "<span class='smallfont'>({$total[$i]})</span>";
							}
						}
						$printRow = true;
					} else if ($cats[0] > 0) {
						foreach ($cats as $i => $num) {
							if ($ary['TOTAL'][$i] > 0) {
								$cats[$i] = (floor(1000 * $num / $ary['TOTAL'][$i]) / 10)."%<br><span class='smallfont'>($num)</span>";
							} else {
								$cats[$i] = "0%<br><span class='smallfont'>($num)</span>";
							}
							if ($num == 0) {
								$cats[$i] = "<span class='smallfont'>($num)</span>";
							}
						}
						$printRow = true;
					}
					if ($printRow) {
						$additionalClass = "";
						if (preg_match("/\(All\)$/", $choiceLabel)) {
							$additionalClass = "summary";
						}
						echo "<tr><th class='border $additionalClass'>$choiceLabel</th><td class='border total'>{$cats[0]}</td>";   // "<td class='border'>{$cats[1]}</td><td class='border'>{$cats[2]}</td><td class='border'>{$cats[3]}</td><td class='border'>{$cats[4]}</td><td class='border'>{$cats[5]}</td>";
					}
				}
			}
		}
		echo "</tr>";
	}
}
echo "</table>";

foreach ($cohorts as $cohort => $cohortData) {
	echo "<div class='centered' id='cohort_$cohort' style='display: none; padding-top: 72px; padding-bottom: 16px;'><h4>$cohort Cohort (".count($cohortData).")</h4><div class='normalfont'>";
	$cohortStrs = array();
	foreach ($cohortData as $cohortRecord => $cohortName) {
		array_push($cohortStrs, Links::makeRecordHomeLink($pid, $cohortRecord, "Record $cohortRecord - $cohortName"));
	}
	echo implode("<br>", $cohortStrs);
	echo "</div></div>";
}

