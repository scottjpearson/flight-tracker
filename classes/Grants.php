<?php

namespace Vanderbilt\CareerDevLibrary;

# This file compiles all of the grants from various data sources and compiles them into an ordered list of grants.
# It should remove duplicate grants as well.
# Unit-testable.

require_once(dirname(__FILE__)."/../Application.php");
require_once(dirname(__FILE__)."/Download.php");
require_once(dirname(__FILE__)."/Links.php");
require_once(dirname(__FILE__)."/Grant.php");
require_once(dirname(__FILE__)."/Scholar.php");
require_once(dirname(__FILE__)."/GrantFactory.php");
require_once(dirname(__FILE__)."/GrantLexicalTranslator.php");

define('MAX_GRANTS', 15);
define('NUM_GRANT_TESTS', 20);
define('SHOW_DEBUG', FALSE);

class Grants {
	public function __construct($token, $server, $metadata = array()) {
		$this->token = $token;
		$this->server = $server;
		if (empty($metadata)) {
			$myMetadata = self::getMetadata($token, $server);
		} else {
			$myMetadata = $metadata;
		}
		$tempHolder = array();
		foreach ($myMetadata as $row) {
			$tempHolder[$row['field_name']] = $row;
		}
		$this->metadata = $tempHolder;
		if ($module = Application::getModule()) {
			$this->lexicalTranslator = new GrantLexicalTranslator($token, $server, Application::getModule());
		} else {
			$this->lexicalTranslator = new GrantLexicalTranslator($token, $server, $metadata);
		}
	}

	public function excludeUnnamedGrants_test($tester) {
		$patterns = array("/K12\/KL2 - Rec\./", "/Internal K - Rec\./");
		for ($i = 1; $i <= NUM_GRANT_TESTS; $i++) {
			$recordId = $this->setupTests();
			$this->compileGrants();
			foreach ($this->getGrants() as $grant) {
				foreach ($patterns as $re) {
					$tester->tag("Checking if $re is in record $recordId's grants");
					$tester->assertMatch($re, $grant->getNumber());
				}
			}
		}
	}

	public function getNumberOfGrants_test($tester) {
		$types = array("compiled", "native", "abcd");
		$this->setupTests();
		$this->compileGrants();
		foreach($types as $type) {
			$tester->tag($type);
			$tester->assertEqual(count($this->getGrants($type)), $this->getNumberOfGrants($type));
		}
	}

	public function getTotalDollars($type = "compiled") {
		return $this->getBudgets($type, "budget");
	}

	public function getDirectDollars($type = "compiled") {
		return $this->getBudgets($type, "direct_budget");
	}

	private function getBudgets($type, $variable) {
		$grants = $this->getGrants($type);
		$dollars = 0;
		foreach ($grants as $grant) {
			$grantDollars = $grant->getVariable($variable);
			if ($grantDollars) {
				$dollars += $grantDollars;
			}
		}
		return round($dollars);
	}

	public function getCount($type = "compiled") {
		return $this->getNumberOfGrants($type);
	}

	public function getNumberOfGrants($type = "compiled") {
		if ($type == "precompiled") {
			return count($this->priorGrants);
		}
		if ($type == "prior") {
			return count($this->priorGrants);
		}
		if ($type == "compiled") {
			return count($this->compiledGrants);
		}
		if ($type == "native") {
			return count($this->nativeGrants);
		}
		return 0;
	}

	public function getGrants($type = "compiled") {
		if ($type == "precompiled") {
			return $this->priorGrants;
		}
		if ($type == "prior") {
			return $this->priorGrants;
		}
		if ($type == "compiled") {
			return $this->compiledGrants;
		}
		if ($type == "native") {
			return $this->nativeGrants;
		}
		return array();
	}

	private static function makeDollarsAndCents($cost) {
		return round($cost * 100) / 100;
	}

	public static function directCostsFromTotal($total, $awardNo, $date = "current") {
		# not possible with current setup because F&A rate is highly variable and often negotiated
		// $fAndA = self::getFAndA($awardNo, $date);
		// return self::makeDollarsAndCents($total / (1 + $fAndA));
		return 0;
	}

	public static function totalCostsFromDirect($direct, $awardNo, $date = "current") {
		# not possible with current setup because F&A rate is highly variable and often negotiated
		// $fAndA = self::getFAndA($awardNo, $date);
		// return self::makeDollarsAndCents($direct * (1 + $fAndA));
		return 0;
	}

	public static function getFinanceType($awardNo) {
		$activityCode = Grant::getActivityCode($awardNo);
		if (preg_match("/W81XWH/", $awardNo)) {
			return "defense";
		} else if (preg_match("/R/", $activityCode) || preg_match("/P/", $activityCode) || preg_match("/U/", $activityCode)) {
			return "research";
		} else if (preg_match("/T/", $activityCode) || preg_match("/K/", $activityCode)) {
			return "training";
		} else if (preg_match("/F/", $activityCode)) {
			return "fellowship";
		}
		return "";
	}

	public static function getFAndA($awardNo, $date) {
		$type = self::getFinanceType($awardNo);
		if ($date == "current") {
			$ts = time();
		} else {
			$ts = strtotime($date);
		}
		if ($type == "research") {
			if (($ts >= strtotime("2004-07-01")) && ($ts < strtotime("2005-07-01"))) {
				return 0.51;
			} 
			if (($ts >= strtotime("2005-07-01")) && ($ts < strtotime("2006-07-01"))) {
				return 0.52;
			}
			if (($ts >= strtotime("2006-07-01")) && ($ts < strtotime("2007-07-01"))) {
				return 0.53;
			}
			if (($ts >= strtotime("2007-07-01")) && ($ts < strtotime("2008-07-01"))) {
				return 0.535;
			}
			if (($ts >= strtotime("2008-07-01")) && ($ts < strtotime("2008-07-01"))) {
				return 0.535;
			}
			if (($ts >= strtotime("2009-07-01")) && ($ts < strtotime("2010-07-01"))) {
				return 0.55;
			}
			if (($ts >= strtotime("2010-07-01")) && ($ts < strtotime("2011-07-01"))) {
				return 0.55;
			}
			if (($ts >= strtotime("2011-07-01")) && ($ts < strtotime("2012-07-01"))) {
				return 0.56;
			}
			if (($ts >= strtotime("2012-07-01")) && ($ts < strtotime("2013-07-01"))) {
				return 0.56;
			}
			if (($ts >= strtotime("2013-07-01")) && ($ts < strtotime("2014-07-01"))) {
				return 0.56;
			}
			if (($ts >= strtotime("2014-07-01")) && ($ts < strtotime("2015-07-01"))) {
				return 0.57;
			}
			if (($ts >= strtotime("2015-07-01")) && ($ts < strtotime("2016-03-01"))) {
				return 0.57;
			}
			if (($ts >= strtotime("2016-03-01")) && ($ts < strtotime("2018-07-01"))) {
				return 0.58;
			}
			if (($ts >= strtotime("2018-07-01")) && ($ts < strtotime("2019-07-01"))) {
				return 0.70;
			}
		} else if ($type == "defense") {
			if (($ts >= strtotime("2004-07-01")) && ($ts < strtotime("2005-07-01"))) {
				return 0.54;
			} 
			if (($ts >= strtotime("2005-07-01")) && ($ts < strtotime("2006-07-01"))) {
				return 0.55;
			}
			if (($ts >= strtotime("2006-07-01")) && ($ts < strtotime("2007-07-01"))) {
				return 0.56;
			}
			if (($ts >= strtotime("2007-07-01")) && ($ts < strtotime("2008-07-01"))) {
				return 0.565;
			}
			if (($ts >= strtotime("2008-07-01")) && ($ts < strtotime("2008-07-01"))) {
				return 0.548;
			}
			if (($ts >= strtotime("2009-07-01")) && ($ts < strtotime("2010-07-01"))) {
				return 0.563;
			}
			if (($ts >= strtotime("2010-07-01")) && ($ts < strtotime("2011-07-01"))) {
				return 0.563;
			}
			if (($ts >= strtotime("2011-07-01")) && ($ts < strtotime("2012-07-01"))) {
				return 0.573;
			}
			if (($ts >= strtotime("2012-07-01")) && ($ts < strtotime("2013-07-01"))) {
				return 0.573;
			}
			if (($ts >= strtotime("2013-07-01")) && ($ts < strtotime("2014-07-01"))) {
				return 0.573;
			}
			if (($ts >= strtotime("2014-07-01")) && ($ts < strtotime("2015-07-01"))) {
				return 0.588;
			}
			if (($ts >= strtotime("2015-07-01")) && ($ts < strtotime("2016-03-01"))) {
				return 0.588;
			}
			if (($ts >= strtotime("2016-03-01")) && ($ts < strtotime("2018-07-01"))) {
				return 0.;
			}
			if (($ts >= strtotime("2018-07-01")) && ($ts < strtotime("2019-07-01"))) {
				return 0.;
			}
		} else if ($type == "training") {
			return 0.08;
		}
		return 0;
	}

	public function setRows($rows) {
		if ($rows) {
			$this->specs = array();
			$this->rows = $rows;
			$this->recordId = 0;
			$this->nativeGrants = array();
			$this->compiledGrants = array();
			$this->priorGrants = array();
			foreach ($rows as $row) {
				if ($row['redcap_repeat_instrument'] == "") {
					$this->name = $row['identifier_first_name']." ".$row['identifier_last_name'];
					$this->recordId = $row['record_id'];
				}
			}
			foreach ($rows as $row) {
				$gfs = array();
				if ($row['redcap_repeat_instrument'] == "") {
					$hasCoeus = FALSE;
					foreach ($row as $field => $value) {
						if ($value && preg_match("/^coeus_/", $field)) {
							$hasCoeus = TRUE;
							break;
						}
					}

					if ($hasCoeus) {
						# for non-infinitely-repeating COEUS forms
						array_push($gfs, new CoeusGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
					} else {
						foreach ($row as $field => $value) {
							if (preg_match("/^newman_/", $field)) {
								array_push($gfs, new NewmanGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
								break;
							}
						}
						foreach ($row as $field => $value) {
							if (preg_match("/^check_/", $field)) {
								array_push($gfs, new ScholarsGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
								break;
							}
						}

						$this->calculate = array();
						$this->calculate['to_import'] = json_decode($row['summary_calculate_to_import'], true);
						$this->calculate['order'] = json_decode($row['summary_calculate_order'], true);
						$this->calculate['list_of_awards'] = json_decode($row['summary_calculate_list_of_awards'], true);
						foreach ($this->calculate as $type => $ary) {
							if (!$ary) {
								$this->calculate[$type] = array();
							}
						}

						$priorGF = new PriorGrantFactory($this->name, $this-elexicalTranslator, $this->metadata);
						$priorGF->processRow($row);
						$priorGFGrants = $priorGF->getGrants();
						foreach ($priorGFGrants as $grant) {
							$this->priorGrants[] = $grant;
						}
					}
				} else if ($row['redcap_repeat_instrument'] == "coeus") {
					array_push($gfs, new CoeusGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
				} else if ($row['redcap_repeat_instrument'] == "reporter") {
					array_push($gfs, new RePORTERGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
				} else if ($row['redcap_repeat_instrument'] == "exporter") {
					array_push($gfs, new ExPORTERGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
				} else if ($row['redcap_repeat_instrument'] == "custom_grant") {
					array_push($gfs, new CustomGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
				} else if ($row['redcap_repeat_instrument'] == "followup") {
					array_push($gfs, new FollowupGrantFactory($this->name, $this->lexicalTranslator, $this->metadata));
				}
				foreach ($gfs as $gf) {
					$gf->processRow($row);
					$gs = $gf->getGrants();
					foreach ($gs as $g) {
						# combine all grants into one unordered list
						if (SHOW_DEBUG) { Application::log("Prospective grant ".json_encode($g->toArray())); }
						$this->nativeGrants[] = $g;
					}
				}
			}
		}
	}

	public function getRecordID() {
		return $this->recordId;
	}

	public static function getSourceOrder() {
		return array_keys(self::getSourceOrderWithLabels());
	}

	public static function getSourceOrderWithLabels() {
		return array(
				"modify" => "Manual Modifications",
				"exporter" => "NIH ExPORTER",
				"reporter" => "Federal RePORTER",
				"coeus" => "COEUS",
				"followup" => "Follow-Up Survey",
				"scholars" => "Initial Scholar's Survey",
				"custom" => "REDCap Custom Grants",
				"data" => "Newman Spreadsheet 'data'",
				"sheet2" => "Newman Spreadsheet 'sheet2'",
				"new2017" => "Spreadsheet with 2017 Scholars",
				);
	}

	public static function getSourceOrderForOlderData() {
		return array(
				"modify",
				"coeus",
				"custom",
				"reporter",
				"exporter",
				"followup",
				"scholars",
				"data",
				"sheet2",
				"new2017",
				);
	}

	# strategy = array("Conversion", "Financial");
	public function compileGrants($strategy = "Conversion") {
		if ($strategy == "Conversion") {
			$this->compileGrantsForConversion();
		} else {
			# Financial
			$this->compileGrantsForFinancial(FALSE);
		}
	}

	private function compileGrantsForFinancial($combine = FALSE) {
		# 1. look for all eligible grants
		$coeusGrants = array();
		foreach ($this->nativeGrants as $grant) {
			if (SHOW_DEBUG) { Application::log("1. nativeGrants: ".json_encode($grant->toArray())); }
			if ($grant->getVariable("source") == "coeus") {
				if ($grant->getVariable("title") != "000") {
					array_push($coeusGrants, $grant);
				}
			}
		}

		foreach ($coeusGrants as $grant) {
			if (SHOW_DEBUG) { Application::log("2. coeusGrants: ".json_encode($grant->toArray())); }
		}

		# 2. combine same grants
		$awardsBySource = self::combineBySource(array("coeus"), $coeusGrants, $combine);

		foreach ($awardsBySource as $awardNo => $grants) {
			if (SHOW_DEBUG) { Application::log("compileGrantsForFinancial: 3. awardsBySource[$awardNo]: ".count($grants)." grants"); }
		}

		# 3. flatten grants instead of throwing out dups
		$flattenedBySource = self::flatten($awardsBySource);

		foreach ($flattenedBySource as $awardNo => $grant) {
			if (SHOW_DEBUG) { Application::log("compileGrantsForFinancial: 4. flattenedBySource[$awardNo]: ".json_encode($grant->toArray())); }
		}

		# 4. order grants by starting date
		$awardsByStart = self::orderGrantsByStart($flattenedBySource);

		foreach ($awardsByStart as $awardNo => $grant) {
			if (SHOW_DEBUG) { Application::log("5. awardsByStart[$awardNo]: ".json_encode($grant->toArray())); }
		}

		# 5. save in data structure
		if ($combine) {
			$awardsByBaseAwardNumber = array();
			$translateBaseNumbers = array();
			foreach ($awardsByStart as $awardNo => $grant) {
				$sponsorAwardNo = $grant->getNumber();
				$baseAwardNo = $grant->getBaseNumber();
				if (preg_match("/\(Old \# \S+\)/", $sponsorAwardNo, $matches)) {
					$oldNumber = preg_replace("/\(Old # /", "", $matches[0]);
					$oldNumber = preg_replace("/\)$/", "", $oldNumber);
					$oldBaseNumber = Grant::translateToBaseAwardNumber($oldNumber);
					$translateBaseNumbers[$baseAwardNo] = $oldBaseNumber;
				}
				if (!isset($awardsByBaseAwardNumber[$baseAwardNo])) {
					$awardsByBaseAwardNumber[$baseAwardNo] = array();
				}
				array_push($awardsByBaseAwardNumber[$baseAwardNo], $grant);
			}
			$changed = TRUE;
			while ($changed) {
				$changed = FALSE;
				foreach ($awardsByBaseAwardNumber as $baseAwardNo => $grants) {
					if (isset($translateBaseNumbers[$baseAwardNo])) {
						$oldBaseNumber = $translateBaseNumbers[$baseAwardNo];
						if (isset($awardsByBaseAwardNumber[$oldBaseNumber])) {
							# old list comes first; new list is after old list
							$awardsByBaseAwardNumber[$oldBaseNumber] = array_merge($awardsByBaseAwardNumber[$oldBaseNumber], $grants);
							unset($awardsByBaseAwardNumber[$baseAwardNo]);
							$changed = TRUE;
							break;   // foreach loop
						}
					}
				}
			}

			$this->compiledGrants = array();
			foreach ($awardsByBaseAwardNumber as $awardNo => $grants) {
				array_push($this->compiledGrants, self::combineGrants($grants));
			}
		} else {
			$this->compiledGrants = array_values($awardsByStart);
		}
	}

	private static function flatten($awardsBySource) {
		$newAwards = array();
		foreach ($awardsBySource as $awardNo => $grants) {
			if (is_array($grants)) {
				foreach ($grants as $grant) {
					array_push($newAwards, $grant);
				}
			} else {
				$grant = $grants;	 // just one grant; misnamed so correcting misnomer
				array_push($newAwards, $grant);
			}
		}
		return $newAwards;
	}

	private static function combineBySource($sourceOrder, $grants, $combine = TRUE) {
		foreach ($sourceOrder as $source) {
			foreach ($grants as $grant) {
				if ($grant->getVariable("source") == $source) {
					$awardNo = $grant->getNumber();
					if (!isset($awardsBySource[$awardNo])) {
						$awardsBySource[$awardNo] = array();
					}
					$awardsBySource[$awardNo][] = $grant;
					if (SHOW_DEBUG) { Application::log("combineBySource setup: ".$awardNo." adding ".$grant->getVariable("type")); }
				}
			}
		}
		if ($combine) {
			foreach ($awardsBySource as $awardNo => $grants) {
				$awardsBySource[$awardNo] = self::combineGrants($grants);
			}
		}
		if (SHOW_DEBUG) { Application::log("combineBySource. ".count($awardsBySource)." awardsBySource"); }
		foreach ($awardsBySource as $awardNo => $grants) {
			if (SHOW_DEBUG) { Application::log("combineBySource: ".$awardNo." with ".count($grants)); }
		}
		return $awardsBySource;
	}

	private static function isAssoc($ary) {
		if (empty($ary)) {
			return FALSE;
		}
		return array_keys($ary) !== range(0, count($ary) - 1);
	}

	private static function orderGrantsByStart($awards) {
		if (self::isAssoc($awards)) {
			$startingTimes = array();
			foreach ($awards as $awardNo => $grant) {
				$start = $grant->getVariable('start');
				if ($start) {
					$startingTimes[$awardNo] = $start; 
				}
			}
			asort($startingTimes);
			$awardsByStart = array();	// a list of the awards used, ordered by starting time
			foreach ($startingTimes as $awardNo => $ts) {
				if (isset($awards[$awardNo])) {
					$awardsByStart[$awardNo] = $awards[$awardNo];
				}
			}
			return $awardsByStart;
		} else {
			$startingTimes = array();
			$i = 0;
			foreach ($awards as $grant) {
				$start = $grant->getVariable('start');
				if ($start) {
					$startingTimes[$i] = $start;
				}
				$i++;
			}
			asort($startingTimes);
			$awardsByStart = array();
			foreach ($startingTimes as $i => $ts) {
				if (isset($awards[$i])) {
					array_push($awardsByStart, $awards[$i]);
				}
			}
			return $awardsByStart;
		}
	}

	private function compileGrantsForConversion() {
		# Strategy: Do not include N/A's. Sort by start timestamp and then look for duplicates

		# listOfAwards contain all the awards
		# awardTimestamps contain the timestamps of the awards
		# changes contain the changes that are made my the modification lists in toImport

		# all awards have important "specifications" or "specs" stored with the grant
		# this facilitates their use by multiple outfits

		# award numbers are also important as our sources and types

		# primary order by starting time
		# secondary order by source ($sourceOrder)

		# exclude certain names
		$exclude = array(
				new Name("Harold", "L", "Moses"),
				new Name("Richard", "", "Hoover"),
				new Name("E", "Michelle", "Southard-Smith"),
				new Name("C", "M", "Stein"),
				new Name("John", "", "Wilson"),
				);

		# 0. Initialize
		$this->changes = array();	// the changes requested by the Grant Wrangler
		$sourceOrder = self::getSourceOrder();
		$awardsBySource = array();	// a list of the awards used, ordered by source
		$awardTimestamps = array();	// the starting date/times
		$filteredGrants = array();

		# 1. Filter for exclusions
		foreach ($this->nativeGrants as $grant) {
			$person = $grant->getVariable("person_name");
			$filterOut = FALSE;
			if ($person) {
				foreach ($exclude as $name) {
					if ($name->isMatch($person)) {
						$filterOut = TRUE;
					}
				}
			}
			if (!$filterOut) {
				array_push($filteredGrants, $grant);
			}
		}

		# 2. Organize grants
		$awardsBySource = self::combineBySource($sourceOrder, $filteredGrants);

		# 3. import modified lists first from the wrangler/index.php interface (Grant Wrangler)
		# these trump everything
		foreach ($this->calculate['to_import'] as $index => $ary) {
			$action = $ary[0];
			$grant = $this->dataWranglerToGrant($ary[1]);
			$awardno = $grant->getNumber();
			$grant->setVariable('source', "modify");
			if ($action == "ADD") {
				$listOfAwards[$awardno] = $grant;
				if ($grant->getVariable('type') != "N/A") {
					$change = new ImportedChange($awardno);
					$change->setChange("type", $grant->getVariable('type'));
					array_push($this->changes, $change);
				}
			} else if (preg_match("/CHANGE/", $action)) {
				$change1 = new ImportedChange($awardno);
				$change1->setChange("type", $grant->getVariable('type'));
				array_push($this->changes, $change1);

				$change2 = new ImportedChange($awardno);
				$change2->setChange("start", $grant->getVariable("start"));
				array_push($this->changes, $change2);

				if (isset($award['end_date'])) {
					$change3 = new ImportedChange($awardno);
					$change3->setChange("end", $grant->getVariable("end"));
					array_push($this->changes, $change3);
				}
			} else if ($action == "TAKEOVER") {
				$change = new ImportedChange($awardno);
				$change->setTakeOverDate($grant->getVariable("start"));
				array_push($this->changes, $change);
			} else if ($action == "REMOVE") {
				$change = new ImportedChange($awardno);
				$change->setRemove(TRUE);
				array_push($this->changes, $change);
			}
		}
		$this->calculate['list_of_awards'] = self::makeListOfAwards($awardsBySource);

		# 4. make changes
		foreach ($this->changes as $change) {
			$changeAwardNo = $change->getNumber();
			if ($change->isRemove()) {
				$baseNumber = $change->getBaseNumber();
				foreach ($awardsBySource as $awardNo => $grant) {
					if ($baseNumber == $awardsBySource[$awardNo]->getBaseNumber()) {
						$awardsBySource[$awardNo]->setVariable("type", "N/A");
					}
				}
			} else if ($change->getTakeOverDate()) {
				$takeOverDate = strtotime($change->getTakeOverDate());
				$baseNumber = $change->getBaseNumber();
				foreach ($awardsBySource as $awardNo => $grant) {
					if ($baseNumber == $awardsBySource[$awardNo]->getBaseNumber()) {
						$start = strtotime($awardsBySource[$awardNo]->getVariable('start'));
						if ($start < $takeOverDate) {
							$awardsBySource[$awardNo]->setVariable("takeover", "TRUE");
						}
					}
				}
			} else {
				if ($changeAwardNo && isset($awardsBySource[$changeAwardNo])) {
					$awardsBySource[$changeAwardNo]->setVariable($change->getChangeType(), $change->getChangeValue());
				}
			}
		}
		$this->calculate['list_of_awards'] = self::makeListOfAwards($awardsBySource);

		# grants are ordered by source; need to order by start date
		# 5. order grants
		$awardsByStart = self::orderGrantsByStart($awardsBySource);
		foreach ($awardsByStart as $awardNo => $grant) {
			if (SHOW_DEBUG) { Application::log("5. awardsByStart: ".$awardNo." ".$grant->getVariable("type")); }
		}

		# 6. remove duplicates by sources; most-preferred by sourceOrder
		$awardsByBaseAwardNumberAndSource = array();
		foreach ($awardsByStart as $awardNo => $grant) {
			$baseNumber = $grant->getBaseNumber();
			$source = $grant->getVariable("source");
			if (!isset($awardsByBaseAwardNumberAndSource[$baseNumber])) {
				$awardsByBaseAwardNumberAndSource[$baseNumber] = array();
			}
			if (!isset($awardsByBaseAwardNumberAndSource[$baseNumber][$source])) {
				$awardsByBaseAwardNumberAndSource[$baseNumber][$source] = array();
			}
			array_push($awardsByBaseAwardNumberAndSource[$baseNumber][$source], $grant);
		}
		$awardsByBaseAwardNumber = array();
		foreach ($awardsByBaseAwardNumberAndSource as $baseNumber => $sources) {
			if (self::spansRePORTERBeginning($sources)) {
				$mySourceOrder = self::getSourceOrderForOlderData();
			} else {
				$mySourceOrder = self::getSourceOrder();
			} 
			foreach ($mySourceOrder as $source) {
				if (isset($sources[$source])) {
					$grants = $sources[$source];
					$combinedGrant = self::combineGrants($grants);
					if ($combinedGrant) {
						$awardsByBaseAwardNumber[$baseNumber] = $combinedGrant;
						break;	// sourceOrder loop
					}
				}
			}
		}
		foreach ($awardsByBaseAwardNumber as $baseNumber => $grant) {
			if (SHOW_DEBUG) { Application::log("6. ".$baseNumber." ".$grant->getVariable("type")); }
		}

		# 7. remove N/A's
		foreach ($awardsByBaseAwardNumber as $baseNumber => $grant) {
			if ($grant->getVariable("type") == "N/A") {
				if (SHOW_DEBUG) { Application::log("Removing ".json_encode($grant->toArray())); }
				if (SHOW_DEBUG) { Application::log("7. Removing because N/A ".$baseNumber); }
				unset($awardsByBaseAwardNumber[$baseNumber]);
			}
		}

		# 8. remove duplicates by starting timestamp
		# if two grants start on the same date and have the same type
		# => remove the grant that is of a less-preferred source
		$clean = FALSE;
		while (!$clean) {
			$prevGrant = NULL;
			$prevBaseNumber = "";
			$clean = TRUE;
			foreach ($awardsByBaseAwardNumber as $baseNumber => $grant) {
				if (($prevGrant) && ($prevGrant->getVariable('start') == $grant->getVariable('start')) && ($prevGrant->getVariable('type') == $grant->getVariable('type'))) {
					foreach (array_reverse($sourceOrder) as $source) {
						if ($prevGrant->getVariable("source") == $source) {
							if (SHOW_DEBUG) { Application::log("8a. Removing ".$baseNumber); }
							$clean = FALSE;
							unset($awardsByBaseAwardNumber[$prevBaseNumber]);
							$prevGrant = $grant;
							$prevBaseNumber = $baseNumber;
							break; // sourceOrder loop
						} else if ($grant->getVariable("source") == $source) {
							if (SHOW_DEBUG) { Application::log("8b. Removing ".$baseNumber); }
							$clean = FALSE;
							unset($awardsByBaseAwardNumber[$baseNumber]);
							break; // sourceOrder loop
						}
					}
				} else {
					$prevGrant = $grant;
					$prevBaseNumber = $baseNumber;
				}
			}
		}

		# 9. adjust end times for Internal Ks and K12s; K awards cannot overlap
		foreach ($awardsByBaseAwardNumber as $baseNumber1 => $grant1) {
			if (($grant1->getVariable("type") == "Internal K") || ($grant1->getVariable("type") == "K12/KL2")) {
				$after = FALSE;
				$setEnd = FALSE;
				foreach ($awardsByBaseAwardNumber as $baseNumber2 => $grant2) {
					if ($baseNumber2 == $baseNumber1) {
						$after = TRUE;
					} else if ($after) {
						if (($grant2->getVariable("type") == "Individual K") || ($grant2->getVariable("type") == "K Equivalent")) {
							$start1 = $grant1->getVariable("start");
							$start2 = $grant2->getVariable("start");
							$ts2 = strtotime($start2);
							$yearspan1 = self::findYearSpan($grant1->getVariable("type"));
							$naturalEnd1 = self::addYears($start1, $yearspan1);
							$naturalTs1 = strtotime($naturalEnd1);
							if ($ts2 < $naturalTs1) {
								$end1 = self::subtractOneDay($start2);
							} else {
								$end1 = self::subtractOneDay($naturalEnd1);
							}
							$awardsByBaseAwardNumber[$baseNumber1]->setVariable("end", $end1);
							$setEnd = TRUE;
						}
						break;
					}
				}
				if (!$setEnd && !$grant1->getVariable("end")) {
					$start1 = $grant1->getVariable("start");
					$yearspan = self::findYearSpan($grant1->getVariable("type"));
					$grant1->setVariable("end", self::addYears($start1, $yearspan));
				}
			}
		}

		# 10. done - move into final data structure
		$this->compiledGrants = array();
		foreach ($awardsByBaseAwardNumber as $baseNumber => $grant) {
			array_push($this->compiledGrants, $grant);
		}

		$this->calculate['order'] = self::makeOrder($this->compiledGrants);
	}

	private static function addYears($date, $yearspan) {
		if ($date) {
			$ts = strtotime($date);
			$month = date("m", $ts);
			$date = date("d", $ts);
			$year = date("Y", $ts) + $yearspan;
			return $year."-".$month."-".$date;
		} else {
			return $date;
		}
	}

	private static function spansRePORTERBeginning($grantsBySource) {
		$startOfRePORTER = 2008;
		if (isset($grantsBySource["coeus"])) {
			$startTsOfRePORTER = strtotime($startOfRePORTER."-01-01");
			foreach ($grantsBySource["coeus"] as $grant) {
				$startTs = strtotime($grant->getVariable("start"));
				if ($startTs < $startTsOfRePORTER) {
					return TRUE;
				}
			}
		}
		return FALSE;
	}

	private static function subtractOneDay($date) {
		$ts = strtotime($date);
		return date("Y-m-d", $ts - 24 * 3600);
	}

	private static function makeDate($ts) {
		if ($ts) {
			return date("Y-m-d", $ts);
		}
		return "";
	}

	public function compileAndCombineGrantsForFinancial() {
		$this->compileGrantsForFinancial(TRUE);
	}

	# grants is an array of class Grant, not an instance of class Grants
	# grants should have the same base award number
	private static function combineGrants($grants) {
		if (count($grants) == 0) {
			return NULL;
		} else if (count($grants) == 1) {
			return $grants[0];
		} else {
			$basisGrant = $grants[0];
			if (SHOW_DEBUG) { Application::log("Using basisGrant: ".$basisGrant->getNumber()." ".$basisGrant->getVariable("type")." from ".$basisGrant->getVariable("source")." with $".$basisGrant->getVariable("budget")); }
			for ($i = 1; $i < count($grants); $i++) {
				if (SHOW_DEBUG) { Application::log("combineGrants $i ".$grants[$i]->getNumber().": ".$grants[$i]->getVariable("type")); }
				$currGrant = $grants[$i];
				if (($currGrant->getVariable("type") != "N/A") && !$currGrant->getVariable("takeover")) {
					# use first grant that is not N/A as basis or is not a takeover
					# deMorgan's law remixed
					if (($basisGrant->getVariable("type") == "N/A") || $basisGrant->getVariable("takeover")) {
						if (SHOW_DEBUG) { Application::log("Setting grant to $i"); }
						$basisGrant = $currGrant;
						$basisGrant->setNumber($basisGrant->getBaseNumber());
					}

					# combine start, end, direct budget, and total budget
					$currStartTs = strtotime($currGrant->getVariable("start"));
					$grantStartTs = strtotime($basisGrant->getVariable("start"));
					if ($currStartTs < $grantStartTs) {
						$basisGrant->setVariable("start", self::makeDate($currStartTs));
					}

					$currEndTs = strtotime($currGrant->getVariable("end"));
					$grantEndTs = strtotime($basisGrant->getVariable("end"));
					if ($currEndTs > $grantEndTs) {
						$basisGrant->setVariable("end", self::makeDate($currEndTs));
					}

					# only combine moneys if sources are the same; e.g., cannot combine money from coeus and reporter
					if ($currGrant->getVariable("source") == $basisGrant->getVariable("source")) {
						$currDirectBudget = $currGrant->getVariable("direct_budget");
						$grantDirectBudget = $basisGrant->getVariable("direct_budget");
						$basisGrant->setVariable("direct_budget", Grant::convertToMoney($grantDirectBudget + $currDirectBudget));

						$currBudget = $currGrant->getVariable("budget");
						$grantBudget = $basisGrant->getVariable("budget");
						if (SHOW_DEBUG) { Application::log("combineGrants: ".$basisGrant->getBaseNumber()." (".$currGrant->getNumber()." from ".$currGrant->getVariable("source").") Adding ".$currBudget." to ".$grantBudget." = ".Grant::convertToMoney($grantBudget + $currBudget)); }
						$basisGrant->setVariable("budget", Grant::convertToMoney($grantBudget + $currBudget));
					}
				}
			}
			if (SHOW_DEBUG) { Application::log("Returning basisGrant ".$basisGrant->getNumber()." ".$basisGrant->getVariable("type")); }
			return $basisGrant;
		}
	}

	public static function makeOrder($order) {
		$transformed = array();
		foreach ($order as $grant) {
			array_push($transformed, self::makeJSON($grant));
		}
		return $transformed;
	}

	public static function makeListOfAwards($listOfAwards) {
		$transformed = array();
		foreach ($listOfAwards as $awardNo => $grant) {
			$transformed[$awardNo] = self::makeJSON($grant);
		}
		return $transformed;
	}

	public static function makeJSON($grant) {
		$specs = $grant->toArray();
		$translate = array(
					"start" => "start_date",
					"end" => "end_date",
					"project_start" => "project_start_date",
					"project_end" => "project_end_date",
					"type" => "redcap_type",
					);
		$omit = array(
				"activity_code",
				"activity_type",
				"funding_institute",
				"institute_code",
				"title",
				"serial_number",
				"support_year",
				"other_suffixes",
				"link",
				);
		$transformed = array();
		foreach ($specs as $var => $value) {
			if (isset($translate[$var])) {
				$transformed[$translate[$var]] = $value;
			} else if (!in_array($var, $omit)) {
				$transformed[$var] = $value;
			}
		}
		return $transformed;
	}

	public function order_test($tester) {
		$this->setupTests();
		$this->compileGrants();
		$orderCompiled = $this->calculate['order'];
		$badNames = array("start", "end", "type");

		$i = 0;
		foreach ($orderCompiled as $specs) {
			foreach ($specs as $var => $value) {
				$tester->tag($i." ".$var);
				$tester->assertTrue(!in_array($var, $badNames));
			}
			$i++;
		}
	}

	public function listOfAwards_test($tester) {
		$this->setupTests();
		$this->compileGrants();
		$listOfAwardsCompiled = $this->calculate['list_of_awards'];
		$badNames = array("start", "end", "type");

		foreach ($listOfAwardsCompiled as $awardNo => $specs) {
			foreach ($specs as $var => $value) {
				$tester->tag($awardNo." ".$var);
				$tester->assertTrue(!in_array($var, $badNames));
			}
		}
	}

	public function dataWranglerToGrant($award) {
		$translate = array(
					"start_date" => "start",
					"end_date" => "end",
					"project_start_date" => "project_start",
					"project_end_date" => "project_end",
					"redcap_type" => "type",
					);
		$grant = new Grant($this->lexicalTranslator);
		foreach ($award as $key => $value) {
			if (isset($translate[$key])) {
				$grant->setVariable($translate[$key], $value);
			} else {
				$grant->setVariable($key, $value);
			}
		}
		if (!$grant->getVariable("type")) {
			$grant->putInBins();
		}
		return $grant;
	}

	public function getSummaryVariables_test($tester) {
		$this->setupTests();
		$this->compileGrants();
		$ary = $this->getSummaryVariables($this->rows);

		$fields = array(
				'summary_ever_internal_k',
				'summary_ever_individual_k_or_equiv',
				'summary_ever_k12_kl2',
				'summary_ever_r01_or_equiv',
				'summary_first_external_k',
				'summary_first_any_k',
				'summary_last_any_k',
				'summary_first_r01',
				'summary_first_r01_or_equiv',
				'summary_ever_external_k_to_r01_equiv',
				'summary_ever_last_external_k_to_r01_equiv',
				'summary_ever_first_any_k_to_r01_equiv',
				'summary_ever_last_any_k_to_r01_equiv',
				);
		for ($i = 1; $i <= MAX_GRANTS; $i++) {
			array_push($fields, 'summary_award_type_'.$i);
		}
		foreach ($fields as $field) {
			$tester->tag($field);
			$tester->assertTrue(isset($ary[$field]));
		}
	}

	public function getSummaryVariables($rows) {
		$ary = array();
		$ary['summary_ever_internal_k'] = 0;
		$ary['summary_ever_individual_k_or_equiv'] = 0;
		$ary['summary_ever_k12_kl2'] = 0;
		$ary['summary_ever_r01_or_equiv'] = 0;
		$ary['summary_first_external_k'] = "";
		$ary['summary_first_any_k'] = "";
		$ary['summary_last_any_k'] = "";
		$ary['summary_first_r01'] = "";
		$ary['summary_first_r01_or_equiv'] = "";

		$overrideFirstR01 = "";
		$overrideFirstR01OrEquiv = "";
		foreach ($rows as $row) {
			if ($row['redcap_repeat_instrument'] == "") {
				$overrideFirstR01 = $row['override_first_r01'];
				$overrideFirstR01OrEquiv = $row['override_first_r01_or_equiv'];
				break;
			}
		}

		$grants = $this->getGrants("compiled");
		$awardTypeConversion = Grant::getAwardTypes();
		if (count($grants) == 0) {
			$grants = $this->getGrants("prior");
		}
		foreach ($grants as $grant) {
			$t = $grant->getVariable("type");
			if ($overrideFirstR01 !== "") {
				$ary['summary_first_r01_or_equiv'] = $overrideFirstR01;
				$ary['summary_first_r01_or_equiv_source'] = "manual";
				$ary['summary_first_r01_or_equiv_sourcetype'] = "2";
				$ary['summary_first_r01'] = $overrideFirstR01;
				$ary['summary_first_r01_source'] = "manual";
				$ary['summary_first_r01_sourcetype'] = "2";
				$ary['summary_ever_r01_or_equiv'] = 1;
			} else if ($overrideFirstR01OrEquiv !== "") {
				$ary['summary_first_r01_or_equiv'] = $overrideFirstR01OrEquiv;
				$ary['summary_first_r01_or_equiv_source'] = "manual";
				$ary['summary_first_r01_or_equiv_sourcetype'] = "2";
				$ary['summary_ever_r01_or_equiv'] = 1;
			}
			if ($t == "Internal K") {
				$ary['summary_ever_internal_k'] = 1;
			} else if ($t == "K12/KL2") {
				$ary['summary_ever_k12_kl2'] = 1;
			} else if (($t == "Individual K") || ($t == "K Equivalent")) {
				$ary['summary_ever_individual_k_or_equiv'] = 1;
			} else if ($t == "R01") {
				if ($ary['summary_first_r01'] == "") {
					$ary['summary_first_r01'] = $grant->getVariable("start");
					$ary['summary_first_r01_source'] = $grant->getVariable("source");
					$ary['summary_first_r01_sourcetype'] = $grant->getSourceType();
				}

				if ($ary['summary_first_r01_or_equiv'] == "") {
					$ary['summary_first_r01_or_equiv'] = $grant->getVariable("start");
					$ary['summary_first_r01_or_equiv_type'] = $awardTypeConversion[$t];
					$ary['summary_first_r01_or_equiv_source'] = $grant->getVariable("source");
					$ary['summary_first_r01_or_equiv_sourcetype'] = $grant->getSourceType();
				}
				$ary['summary_ever_r01_or_equiv'] = 1;
			} else if ($t == "R01 Equivalent") {
				if ($ary['summary_first_r01_or_equiv'] == "") {
					$ary['summary_first_r01_or_equiv'] = $grant->getVariable("start");
					$ary['summary_first_r01_or_equiv_type'] = $awardTypeConversion[$t];
					$ary['summary_first_r01_or_equiv_source'] = $grant->getVariable("source");
					$ary['summary_first_r01_or_equiv_sourcetype'] = $grant->getSourceType();
				}
				$ary['summary_ever_r01_or_equiv'] = 1;
			}

			$externalKs = array("Individual K", "K Equivalent");
			$Ks = array("Internal K", "K12/KL2", "Individual K", "K Equivalent");
			if (in_array($t, $externalKs) && !$ary['summary_first_external_k']) {
				$ary['summary_first_external_k'] = $grant->getVariable("start");
				$ary['summary_first_external_k_source'] = $grant->getVariable("source");
				$ary['summary_first_external_k_sourcetype'] = $grant->getSourceType();
			}
			if (in_array($t, $externalKs)) {
				$ary['summary_last_external_k'] = $grant->getVariable("start");
				$ary['summary_last_external_k_source'] = $grant->getVariable("source");
				$ary['summary_last_external_k_sourcetype'] = $grant->getSourceType();
			}
			if (in_array($t, $Ks) && !$ary['summary_first_any_k']) {
				$ary['summary_first_any_k'] = $grant->getVariable("start");
				$ary['summary_first_any_k_source'] = $grant->getVariable("source");
				$ary['summary_first_any_k_sourcetype'] = $grant->getSourceType();
			}
			if (in_array($t, $Ks)) {
				$ary['summary_last_any_k'] = $grant->getVariable("start");
				$ary['summary_last_any_k_source'] = $grant->getVariable("source");
				$ary['summary_last_any_k_sourcetype'] = $grant->getSourceType();
			}
		}
		$i = 1;
		$awardTypeConversion = Grant::getAwardTypes();
		foreach ($grants as $grant) {
			$ary['summary_award_type_'.$i] = $awardTypeConversion[$grant->getVariable("type")];
			$i++;
		}
		$ary['summary_ever_external_k_to_r01_equiv'] = self::converted($ary, "first_external");
		$ary['summary_ever_last_external_k_to_r01_equiv'] = self::converted($ary, "last_external");
		$ary['summary_ever_first_any_k_to_r01_equiv'] = self::converted($ary, "first_any");
		$ary['summary_ever_last_any_k_to_r01_equiv'] = self::converted($ary, "last_any");
		return $ary;
	}

	private static function findYearSpan($type) {
		if (($type == "3") || ($type == "4") || ($type == "Individual K") || ($type == "External K") || ($type == "K Equivalent")) {
			return Application::getIndividualKLength();
		} else if (($type == "2") || ($type == "K12/KL2") || ($type == "K12KL2")) {
			return Application::getK12KL2Length();
		} else if (($type == "1") || ($type == "Internal K")) {
			return Application::getInternalKLength();
		}
		return 0;
	}

	public static function datediff($d1, $d2, $unit=null, $returnSigned=false, $returnSigned2=false)
	{
		global $missingDataCodes;
		// Make sure Units are provided and that dates are trimmed
		if ($unit == null) return NAN;
		$d1 = trim($d1);
		$d2 = trim($d2);
		// Missing data codes
		if (isset($missingDataCodes) && !empty($missingDataCodes)) {
			if ($d1 != '' && isset($missingDataCodes[$d1])) $d1 = '';
			if ($d2 != '' && isset($missingDataCodes[$d2])) $d2 = '';
		}
		// If ymd, mdy, or dmy is used as the 4th parameter, then assume user is using Calculated field syntax
		// and assume that returnSignedValue is the 5th parameter.
		if (in_array(strtolower(trim($returnSigned)), array('ymd', 'dmy', 'mdy'))) {
			$returnSigned = $returnSigned2;
		}
		// Initialize parameters first
		if (strtolower($d1) === "today") $d1 = TODAY; elseif (strtolower($d1) === "now") $d1 = NOW;
		if (strtolower($d2) === "today") $d2 = TODAY; elseif (strtolower($d2) === "now") $d2 = NOW;
		$d1isToday = ($d1 == TODAY);
		$d2isToday = ($d2 == TODAY);
		$d1isNow = ($d1 == NOW);
		$d2isNow = ($d2 == NOW);
		$returnSigned = ($returnSigned === true || $returnSigned === 'true');
		// Determine data type of field ("date", "time", "datetime", or "datetime_seconds")
		$format_checkfield = ($d1isToday ? $d2 : $d1);
		$numcolons = substr_count($format_checkfield, ":");
		if ($numcolons == 1) {
			if (strpos($format_checkfield, "-") !== false) {
				$datatype = "datetime";
			} else {
				$datatype = "time";
			}
		} else if ($numcolons > 1) {
			$datatype = "datetime_seconds";
		} else {
			$datatype = "date";
		}
		// TIME only
		if ($datatype == "time" && !$d1isToday && !$d2isToday) {
			if ($d1isNow) {
				$d2 = "$d2:00";
				$d1 = substr($d1, -8);
			} elseif ($d2isNow) {
				$d1 = "$d1:00";
				$d2 = substr($d2, -8);
			}
			// Return in specified units
			return secondDiff(timeToSeconds($d1),timeToSeconds($d2),$unit,$returnSigned);
		}
		// DATE, DATETIME, or DATETIME_SECONDS
		// If using 'today' for either date, then set format accordingly
		if ($d1isToday) {
			if ($datatype == "time") {
				return NAN;
			} else {
				$d2 = substr($d2, 0, 10);
			}
		} elseif ($d2isToday) {
			if ($datatype == "time") {
				return NAN;
			} else {
				$d1 = substr($d1, 0, 10);
			}
		}
		// If a date[time][_seconds] field, then ensure it has dashes
		if (substr($datatype, 0, 4) == "date" && (strpos($d1, "-") === false || strpos($d2, "-") === false)) {
			return NAN;
		}
		// Make sure the date/time values aren't empty
		if ($d1 == "" || $d2 == "" || $d1 == null || $d2 == null) {
			return NAN;
		}
		// Make sure both values are same length/datatype
		if (strlen($d1) != strlen($d2)) {
			if (strlen($d1) > strlen($d2) && $d2 != '') {
				if (strlen($d1) == 16) {
					if (strlen($d2) == 10) $d2 .= " 00:00";
					$datatype = "datetime";
				} else if (strlen($d1) == 19) {
					if (strlen($d2) == 10) $d2 .= " 00:00";
					else if (strlen($d2) == 16) $d2 .= ":00";
					$datatype = "datetime_seconds";
				}
			} else if (strlen($d2) > strlen($d1) && $d1 != '') {
				if (strlen($d2) == 16) {
					if (strlen($d1) == 10) $d1 .= " 00:00";
					$datatype = "datetime";
				} else if (strlen($d2) == 19) {
					if (strlen($d1) == 10) $d1 .= " 00:00";
					else if (strlen($d1) == 16) $d1 .= ":00";
					$datatype = "datetime_seconds";
				}
			}
		}
		// Separate time if datetime or datetime_seconds
		$d1b = explode(" ", $d1);
		$d2b = explode(" ", $d2);
		// Split into date and time (in units of seconds)
		$d1 = $d1b[0];
		$d2 = $d2b[0];
		$d1sec = (!empty($d1b[1])) ? timeToSeconds($d1b[1]) : 0;
		$d2sec = (!empty($d2b[1])) ? timeToSeconds($d2b[1]) : 0;
		// Separate pieces of date component
		$dt1 = explode("-", $d1);
		$dt2 = explode("-", $d2);
		// Convert the dates to seconds (conversion varies due to dateformat)
		$dat1 = mktime(0,0,0,$dt1[1],$dt1[2],$dt1[0]) + $d1sec;
		$dat2 = mktime(0,0,0,$dt2[1],$dt2[2],$dt2[0]) + $d2sec;
		// Get the difference in seconds
		$sec = $dat2 - $dat1;
		if (!$returnSigned) $sec = abs($sec);
		// Return in specified units
		if ($unit == "s") {
			return $sec;
		} else if ($unit == "m") {
			return $sec/60;
		} else if ($unit == "h") {
			return $sec/3600;
		} else if ($unit == "d") {
			return ($datatype == "date" ? round($sec/86400) : $sec/86400);
		} else if ($unit == "M") {
			return $sec/2630016; // Use 1 month = 30.44 days
		} else if ($unit == "y") {
			return $sec/31556952; // Use 1 year = 365.2425 days
		}
		return NAN;
	}

	private static function getLastKType($row) {
		$kTypes = array(1, 2, 3, 4);
		$lastK = FALSE;
		for ($i = 1; $i <= MAX_GRANTS; $i++) {
			if (in_array($row['summary_award_type_'.$i], $kTypes)) {
				$lastK = $row['summary_award_type_'.$i];
			}
		}
		return $lastK;
	}

	# converted to R01/R01-equivalent in $row for $typeOfK ("any" vs. "external")
	# return value:
	#		   1, Converted K to R01-or-Equivalent While on K
	#		   2, Converted K to R01-or-Equivalent Not While on K
	#		   3, Still On K; No R01-or-Equivalent
	#		   4, Not On K; No R01-or-Equivalent
	#		   5, No K, but R01-or-Equivalent
	#		   6, No K; No R01-or-Equivalent
	#			  7, Used K99/R00
	private static function converted($row, $typeOfK) {
		for ($i = 1; $i <= MAX_GRANTS; $i++) {
			if ($row['summary_award_type_'.$i] == 9) {
				# K99/R00
				return 7;
			}
		}

		$value = "";
		if ($row['summary_'.$typeOfK.'_k'] && $row['summary_first_r01_or_equiv']) {
			if (preg_match('/last_/', $typeOfK)) {
				$prefix = "last";
			} else {
				$prefix = "first";
			}
			if ($row['summary_'.$prefix.'_external_k'] == $row['summary_'.$prefix.'_any_k']) {
				$intendedYearSpan = self::findYearSpan("External K");
				if (self::datediff($row['summary_'.$typeOfK.'_k'], $row['summary_first_r01_or_equiv'], "y") <= $intendedYearSpan) {
					$value = 1;
				} else {
					$value = 2;
				}
			} else {
				$kType = self::getLastKType($row);
				$intendedYearSpan = self::findYearSpan($kType);
				if (self::datediff($row['summary_'.$typeOfK.'_k'], $row['summary_first_r01_or_equiv'], "y") <= $intendedYearSpan) {
					$value = 1;
				} else {
					$value = 2;
				}
			}
		} else if ($row['summary_'.$typeOfK.'_k']) {
			if (preg_match('/last_/', $typeOfK)) {
				$prefix = "last";
			} else {
				$prefix = "first";
			}
			$diffToToday = self::datediff($row['summary_'.$typeOfK.'_k'], date('Y-m-d'), "y");
			if (SHOW_DEBUG) { Application::log($typeOfK.": ".$diffToToday); }
			if ($row["summary_".$prefix."_external_k"] == $row["summary_".$prefix."_any_k"]) {
				$intendedYearSpan = self::findYearSpan("External K");
				if ($diffToToday <= $intendedYearSpan) {
					$value = 3;
				} else {
					$value = 4;
				}
			} else {
				$kType = self::getLastKType($row);
				$intendedYearSpan = self::findYearSpan($kType);
				if ($diffToToday <= $intendedYearSpan) {
					$value = 3;
				} else {
					$value = 4;
				}
			}
		} else {
			if ($row['summary_first_r01_or_equiv']) {
				$value = 5;
			} else {
				$value = 6;
			}
		}
		return $value;
	}


	# adjusts the award end dates to concur with the first r01
	private function reworkAwardEndDates($row) {
		if (!$row['summary_first_r01_or_equiv']) {
			return $row;
		}
		$kTypes = array(1, 2, 3, 4);
		$oneDay = 24 * 3600;

		$r01 = strtotime($row['summary_first_r01_or_equiv']);
		$endKTsFromR01 = $r01 - $oneDay;
		$endKDateFromR01 = date("Y-m-d", $endKTsFromR01);

		for ($i = 1; $i <= MAX_GRANTS; $i++) {
			$type = $row['summary_award_type_'.$i];
			$endDate = strtotime($row['summary_award_end_date_'.$i]);
			if (in_array($type, $kTypes) && ($r01 < $endDate)) {
				# compare ending from R01 to year length of Ks
				$yearLength = Scholar::getKLength($type);
				$startKTs = strtotime($row['summary_award_date_'.$i]);
				if (is_integer($yearLength)) {
					$adjustedStartKTs = $startKTs - $oneDay;
					$endKDateFromYearLength = (date("Y", $adjustedStartKTs) + $yearLength).date("-m-d", $adjustedStartKTs); 
				} else {
					# not adjusting for leap years
					$endKDateFromYearLength = date("Y-m-d", $startKTs + $yearLength * 365 * $oneDay);
				}
				$endKTsFromYearLength = strtotime($endKDateFromYearLength);
				if ($endKTsFromYearLength < $endKTsFromR01) { 
					$row['summary_award_end_date_'.$i] = $endKDateFromYearLength;
				} else {
					$row['summary_award_end_date_'.$i] = $endKDateFromR01;
				}
			}
		}
		return $row;
	}

	public function makeUploadRow() {
		if ($this->token && $this->server) {
			$uploadRow = array(
						"record_id" => $this->recordId,
						"redcap_repeat_instrument" => "",
						"redcap_repeat_instance" => "",
						"summary_grant_count" => $this->getCount("compiled"),
						"summary_total_budgets" => $this->getTotalDollars("compiled"),
						);
			$i = 1;
			foreach ($this->compiledGrants as $grant) {
				if ($i <= MAX_GRANTS) {
					$v = $grant->getREDCapVariables($i);
					if (SHOW_DEBUG) { Application::log("Grant $i: ".json_encode($v)); }
					foreach ($v as $key => $value) {
						if (isset($this->metadata[$key])) {
							$uploadRow[$key] = $value;
						} else {
							Application::log($key." not found in metadata, but in compiledGrants");
                            # Need to warn silently because of upgrade issues
							// throw new \Exception($key." not found in metadata, but in compiledGrants");
						}
					}
					$i++;
				} else {
					# warn
					Application::log("WARNING ".$i.": Discarding grant for ".$this->recordId); 
				}
			}
			$blankGrant = new Grant($this->lexicalTranslator);
			for ($grant_i = $i; $grant_i <= MAX_GRANTS; $grant_i++) {
				$v = $blankGrant->getREDCapVariables($grant_i);
				foreach ($v as $key => $value) {
					if (isset($this->metadata[$key])) {
						$uploadRow[$key] = $value;
					}
				}
			}
			$v = $this->getSummaryVariables($this->rows);
			foreach ($v as $key => $value) {
				if (isset($this->metadata[$key])) {
					$uploadRow[$key] = $value;
				} else {
					Application::log($key." not found in metadata, but in summary variables");

					# Need to warn silently because of upgrade issues
					// throw new \Exception($key." not found in metadata, but in summary variables");
				}
			}
			foreach ($this->calculate as $type => $ary) {
				$key = "summary_calculate_".$type;
				$value = json_encode($ary);
				$uploadRow[$key] = $value;
			}
			$uploadRow = $this->reworkAwardEndDates($uploadRow);
			$uploadRow['summary_complete'] = '2';
			return $uploadRow;

		}
		return array();
	}

	public function uploadGrants() {
		$uploadRow = $this->makeUploadRow();
		if (!empty($uploadRow)) {
			Upload::oneRow($uploadRow, $this->token, $this->server);
		}
	}

	private function setupTests() {
		$records = Download::recordIds($this->token, $this->server);
		$n = rand(0, count($records) - 1);
		$record = $records[$n];

		$redcapData = Download::records($this->token, $this->server, array($record));
		$this->setRows($redcapData);
		return $record;
	}

	# unit test - rows are of same record
	public function sameRecord_test($tester) {
		$this->setupTests();
		$recordId = 0;
		foreach ($this->rows as $row) {
			if ($recordId) {
				$tester->assertEqual($recordId, $row['record_id']);
			} else {
				$recordId = $row['record_id'];
			}
		}
	}

	public function calculate_test($tester) {
		$this->setupTests();
		foreach ($this->calculate as $type => $value) {
			$tester->tag($type." in memory");
			$tester->assertNotNull($value);
		}
	}

	# unit test - normative row exists
	public function normativeRowExists_test($tester) {
		$this->setupTests();
		$normativeRowFound = FALSE;
		foreach ($this->rows as $row) {
			if (($row['redcap_repeat_instrument'] == "") && ($row['redcap_repeat_instance'] == "")) {
				$normativeRowFound = TRUE;
			}
		}
		$tester->assertTrue($normativeRowFound);
	}

	# unit test - normative row contains first and last name
	public function normativeRowHasNames_test($tester) {
		$this->setupTests();
		$normativeRow = array();
		foreach ($this->rows as $row) {
			if (($row['redcap_repeat_instrument'] == "") && ($row['redcap_repeat_instance'] == "")) {
				$normativeRow = $row;
			}
		}
		$keys = array_keys($normativeRow);
		$tester->assertIn("identifier_first_name", $keys);
		$tester->assertIn("identifier_last_name", $keys);
		$tester->assertNotBlank($normativeRow['identifiers_first_name']);
		$tester->assertNotBlank($normativeRow['identifier_last_name']);
	}

	# unit test - before compiling grants, compiledGrants is empty
	public function compileGrantsEmpty_test($tester) {
		$this->setupTests();
		$tester->assertTrue(empty($this->compiledGrants));
	}

	# unit test - after compiling grants, compiledGrants is populated with objects of class Grant
	public function grantsCompile_test($tester) {
		$this->setupTests();
		$this->compileGrants();
		$tester->assertTrue(!empty($this->compiledGrants));
	}

	public static function getMetadata($token, $server) {
		return Download::metadata($token, $server);
	}

	private $metadata;	// keyed by field_name in constructor
	private $lexicalTranslator;
	private $rows;
	private $nativeGrants;
	private $compiledGrants;
	private $priorGrants;
	private $token;
	private $server;
	private $calculate;
	private $changes;
}

class ImportedChange {
	public function __construct($awardno) {
		$this->awardNo = $awardno;
	}

	public function setChange($type, $value) {
		$this->changeType = $type;
		$this->changeValue = $value;
	}

	public function getNumber() {
		return $this->awardNo;
	}

	public function getBaseAwardNumber() {
		return $this->getBaseNumber();
	}

	public function getBaseNumber() {
		return Grant::translateToBaseAwardNumber($this->awardNo);
	}

	public function setRemove($bool) {
		$this->remove = $bool;
	}

	public function isRemove() {
		return $this->remove;
	}

	public function setTakeOverDate($date) {
		$this->takeOverDate = $date;
	}

	public function getTakeOverDate() {
		return $this->takeOverDate;
	}

	public function getChangeType() {
		return $this->changeType;
	}

	public function getChangeValue() {
		return $this->changeValue;
	}

	public function toString() {
		$remove = "";
		if ($this->isRemove) {
			$remove = " REMOVE";
		}
		return $this->changeType." ".$this->changeValue.": ".$this->awardNo.$remove;
	}

	# unit test - award number is populated
	# unit test - change outputs values

	private $changeType;
	private $changeValue;
	private $awardNo;
	private $remove = FALSE;
	private $takeOverDate = "";
}

class Name {
	public function __construct($first, $middle, $last) {
		$this->first = strtolower($first);
		$this->middle = strtolower($middle);
		$this->last = strtolower($last);
	}

	public function isMatch($fullName) {
		$names = preg_split("/[\s,\.]+/", $fullName);
		$matchedFirst = FALSE;
		$matchedLast = FALSE;
		foreach ($names as $name) {
			$name = strtolower($name);
			if (($name != $this->first) && ($name != $this->middle) && ($name != $this->last)) {
				return FALSE;
			}
			if ($name == $this->first) {
				$matchedFirst = TRUE;
			}
			if ($name == $this->last) {
				$matchedLast = TRUE;
			}
		}
		if (!$matchedFirst || !$matchedLast) {
			return FALSE;
		}
		return TRUE;
	}

	private $first;
	private $middle;
	private $last;
}
