<?php namespace WayneAbarquez\MyComposerTest\Hpw;

use App\BaseModel;
use Illuminate\Support\Facades\DB;


class Hpw extends BaseModel
{
	protected $table = 'mv_hpw_util';
//	protected $primaryKey = 'geomid';
	public $incrementing = false;
	public $timestamps = false;
	protected $fields = ['enodebid'];


	public static $serviceability = array();
	public static $result = array();
	public static $label = "Home Prepaid Wifi";

	public static $signal_matrix = array(
		"GREEN" => 5,
		"AMBER" => 5,
		"BLUE" => 5,
		"BLACK" => 5,
		"RED" => 0,
		"" => 0
	);

	 public static $color_matrix = array(
	 	"GREEN" => '#00ff00',
	 	"AMBER" => '#ffbf00',
	 	"RED" => '#ff0000',
	 	"" => '#ff0000' //blue and black has been taken care of in materialized views
	 );


	public static function getPolygonCoverage($latLlng, $facility)
	{
		$point = "ST_GeomFromText('POINT(" . $latLlng['lng'] . " " . $latLlng['lat'] . " )', 4326)";

		$query = "SELECT fg.id as geomid, fl.id as facilitylayerid, '$facility' as layer, fc.cv4 as sector, mhu.util_color, mhu.enodebid ";
		$query .= "FROM facility_geom fg ";
		$query .= "LEFT JOIN facility_column fc on fc.facilitygeomid = fg.id ";
		$query .= "LEFT JOIN facility_layer fl ON fl.id = fc.facilitylayerid ";
		$query .= "LEFT JOIN mv_hpw_util mhu ON mhu.sector_name = fc.cv4 ";
		$query .= "WHERE ST_Intersects($point, fg.geom) ";
		$query .= "AND fl.id = (SELECT id from facility_layer where name = '" . pg_escape_string($facility) . "') ";
		$query .= "AND fl.isCoverage = 't' ";
		$query .= "AND GeometryType(fg.geom) != 'GEOMETRYCOLLECTION' ";
		$query .= "ORDER BY fc.cv4;";

		//CahillDB::connect(); //TODO: make this abstract
		//$result = CahillDB::q($query); //TODO: make this abstract

		$result = DB::select($query);
		$ctr = 0;

//		while ( $row = pg_fetch_object($result) ) {
//			if ( strpos($row->sector, 'DL RSRP(dBm)') !== false ) {
//				//self::$serviceability[$row->layer]['signal'] = intval($row->sector);
//				self::$serviceability[$row->layer]['signal'] = $row->sector;
//				self::$serviceability[$row->layer]['facilitylayerid'] = $row->facilitylayerid;
//				self::$serviceability[$row->layer]['util'] = $row->util_color;
//				self::$serviceability[$row->layer]['geomid'] = $row->geomid;
//			} else {
//				self::$serviceability[$row->layer]['sector'] = $row->sector;
//				self::$serviceability[$row->layer]['facilitylayerid'] = $row->facilitylayerid;
//				self::$serviceability[$row->layer]['util'] = $row->util_color;
//				self::$serviceability[$row->layer]['geomid'] = $row->geomid;
//			}
//			$ctr += 1;
//		}

		foreach ($result as $row) {
			if ( strpos($row->sector, 'DL RSRP(dBm)') !== false ) {
				//self::$serviceability[$row->layer]['signal'] = intval($row->sector);
				self::$serviceability[$row->layer]['signal'] = $row->sector;
				self::$serviceability[$row->layer]['facilitylayerid'] = $row->facilitylayerid;
				self::$serviceability[$row->layer]['util'] = $row->util_color;
				self::$serviceability[$row->layer]['geomid'] = $row->geomid;
				self::$serviceability[$row->layer]['enodebid'] = $row->enodebid;
			} else {
				self::$serviceability[$row->layer]['sector'] = $row->sector;
				self::$serviceability[$row->layer]['facilitylayerid'] = $row->facilitylayerid;
				self::$serviceability[$row->layer]['util'] = $row->util_color;
				self::$serviceability[$row->layer]['geomid'] = $row->geomid;
				self::$serviceability[$row->layer]['enodebid'] = $row->enodebid;
			}
			$ctr += 1;
		}

		//CahillDB::disconnect(); //TODO: make this abstract
		return self::$serviceability;
	}

	public static function validateInitialOutput () {
		foreach (self::$serviceability as $item) {
			if (isset($item['signal'])) return true;
		}
		return false;
	}

	public static function getHPWCoverage()
	{
		if (!self::validateInitialOutput()) return false;

		self::getHighestHPW();

		if (!isset(self::$result['signal'])) return false;

		$config = new \stdClass();
		$config->signal = self::$result['signal'];
		$config->Facility = self::$label;
		$config->isHeatmap = false;
		$config->numeric_signal = self::$result['numeric_signal'];
		$config->sites = '';
		$config->hpw_sector = isset(self::$result['sector']) ? self::$result['sector'] : '';
		$config->facilitylayerid = isset(self::$result['facilitylayerid']) ? self::$result['facilitylayerid'] : '';
		$config->geomid = isset(self::$result['geomid']) ? self::$result['geomid'] : '';
		$config->hpw_color = isset(self::$result['color']) ? self::$result['color'] : '';
		$config->enodebid = self::$result['enodebid'];

		return $config;
	}

	public static function getHighestHPW()
	{
		$signal = array();
		//$result = array('numeric_signal' => '', 'signal' => '');
		$result = [];
		//get all current signal and compare
		foreach ( self::$serviceability as $key => $val ) {
			if ( isset(self::$serviceability[$key]['signal']) ) $signal[$key] = self::$serviceability[$key]['signal'];
		}

		arsort($signal);

		$morethantwo = (count($signal) > 1) ? true : false;
		foreach ( $signal as $key => $val ) {
			if ( $morethantwo == false ) {
				$result['numeric_signal'] = self::$signal_matrix[self::$serviceability[$key]['util']];
				$result['signal'] = (self::$serviceability[$key]['util'] == 'RED') ? 'NOT AVAILABLE' : 'AVAILABLE';
				$result['sector'] = isset(self::$serviceability[$key]['sector']) ? self::$serviceability[$key]['sector'] : '';
				$result['facilitylayerid'] = self::$serviceability[$key]['facilitylayerid'];
				$result['geomid'] = self::$serviceability[$key]['geomid'];
				$result['color'] = self::$color_matrix[self::$serviceability[$key]['util']];
				$result['enodebid'] = isset(self::$serviceability[$key]['enodebid']) ? self::$serviceability[$key]['enodebid'] : '';
				if ( self::$serviceability[$key]['util'] != 'RED' ) break;
			} else {
				//check if the same values
				if ( $signal['L2600'] == $signal['L700'] &&
					$signal['L700'] == $signal['L1800'] &&
					$signal['L1800'] == $signal['L2600'] &&
					$signal['L1800'] == $signal['L700']
				) {
					$result['numeric_signal'] = self::$signal_matrix[self::$serviceability['L2600']['util']];
					$result['signal'] = (self::$serviceability['L2600']['util'] == 'RED') ? 'NOT AVAILABLE' : 'AVAILABLE';
					$result['sector'] = self::$serviceability['L2600']['sector'];
					$result['facilitylayerid'] = self::$serviceability['L2600']['facilitylayerid'];
					$result['geomid'] = self::$serviceability['L2600']['geomid'];
					$result['color'] = self::$color_matrix[self::$serviceability['L2600']['util']];
					$result['enodebid'] = isset(self::$serviceability['L2600']['enodebid']) ? self::$serviceability['L2600']['enodebid'] : '';
					if ( self::$serviceability['L2600']['util'] != 'RED' ) break;
				} else if ( $key == 'L700' ) {
					$result['numeric_signal'] = self::$signal_matrix[self::$serviceability['L700']['util']];
					$result['signal'] = (self::$serviceability['L700']['util'] == 'RED') ? 'NOT AVAILABLE' : 'AVAILABLE';
					$result['sector'] = self::$serviceability['L700']['sector'];
					$result['facilitylayerid'] = self::$serviceability['L700']['facilitylayerid'];
					$result['geomid'] = self::$serviceability['L700']['geomid'];
					$result['color'] = self::$color_matrix[self::$serviceability['L700']['util']];
					$result['enodebid'] = isset(self::$serviceability['L700']['enodebid']) ? self::$serviceability['L700']['enodebid'] : '';
					if ( self::$serviceability['L700']['util'] != 'RED' ) break;
				} else if ( $key == 'L1800' ) {
					if ( $signal['L1800'] == $signal['L700'] ) {
						$result['numeric_signal'] = self::$signal_matrix[self::$serviceability['L700']['util']];
						$result['signal'] = (self::$serviceability['L700']['util'] == 'RED') ? 'NOT AVAILABLE' : 'AVAILABLE';
						$result['sector'] = self::$serviceability['L700']['sector'];
						$result['facilitylayerid'] = self::$serviceability['L700']['facilitylayerid'];
						$result['geomid'] = self::$serviceability['L700']['geomid'];
						$result['color'] = self::$color_matrix[self::$serviceability['L700']['util']];
						$result['enodebid'] = isset(self::$serviceability['L700']['enodebid']) ? self::$serviceability['L700']['enodebid'] : '';
						if ( self::$serviceability['L700']['util'] != 'RED' ) break;
					} else {
						$result['numeric_signal'] = self::$signal_matrix[self::$serviceability['L1800']['util']];
						$result['signal'] = (self::$serviceability['L1800']['util'] == 'RED') ? 'NOT AVAILABLE' : 'AVAILABLE';
						$result['sector'] = self::$serviceability['L1800']['sector'];
						$result['facilitylayerid'] = self::$serviceability['L1800']['facilitylayerid'];
						$result['geomid'] = self::$serviceability['L1800']['geomid'];
						$result['color'] = self::$color_matrix[self::$serviceability['L1800']['util']];
						$result['enodebid'] = isset(self::$serviceability['L1800']['enodebid']) ? self::$serviceability['L1800']['enodebid'] : '';
						if ( self::$serviceability['L1800']['util'] != 'RED' ) break;
					}
				} else {
					$result['numeric_signal'] = self::$signal_matrix[self::$serviceability['L2600']['util']];
					$result['signal'] = (self::$serviceability['L2600']['util'] == 'RED') ? 'NOT AVAILABLE' : 'AVAILABLE';
					$result['sector'] = self::$serviceability['L2600']['sector'];
					$result['facilitylayerid'] = self::$serviceability['L2600']['facilitylayerid'];
					$result['geomid'] = self::$serviceability['L2600']['geomid'];
					$result['color'] = self::$color_matrix[self::$serviceability['L2600']['util']];
					$result['enodebid'] = isset(self::$serviceability['L2600']['enodebid']) ? self::$serviceability['L2600']['enodebid'] : '';
					if ( self::$serviceability['L2600']['util'] != 'RED' ) break;
				}
			}
		}

		self::$result = $result;
	}
}