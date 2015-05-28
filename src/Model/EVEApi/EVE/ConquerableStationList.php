<?php


namespace ProjectRena\Model\EVEApi\EVE;

use ProjectRena\Lib\PhealLoader;

/**
 * Class ConquerableStationList
 *
 * @package ProjectRena\Model\EVEApi\EVE
 */
class ConquerableStationList {
	/**
	 * @var int
	 */
	public $accessMask = null;

	/**
	 * @return mixed
	 */
	public function getData()
	{
		$pheal = PhealLoader::loadPheal();
		$pheal->scope = "EVE";
		$result = $pheal->ConquerableStationList()->toArray();

		return $result;
	}
}