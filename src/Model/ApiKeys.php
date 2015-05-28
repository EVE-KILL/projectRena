<?php


namespace ProjectRena\Model;


use ProjectRena\Lib\Database;

/**
 * Class ApiKeys
 *
 * @package ProjectRena\Model
 */
class ApiKeys {
	/**
	 * @param $apiKeyID
	 * @param $vCode
	 * @param null $userID
	 *
	 * @return bool|int|string
	 */
	public static function addAPIKey($apiKey, $vCode, $userID = NULL)
	{
		return Database::execute("INSERT INTO apiKeys (keyID, vCode, userID) VALUES (:keyID, :vCode, :userID)", array(":keyID" => $apiKey, ":vCode" => $vCode, ":userID" => $userID));
	}

	/**
	 * @param $apiKey
	 *
	 * @return array
	 */
	public static function getAPIKey($apiKey)
	{
		return Database::queryRow("SELECT * FROM apiKeys WHERE keyID = :keyID", array(":keyID" => $apiKey));
	}

	/**
	 * @param $apiKey
	 * @param $vCode
	 * @param $userID
	 *
	 * @return bool|int|string
	 */
	public static function updateAPIKey($apiKey, $vCode, $userID)
	{
		return Database::execute("INSERT INTO apiKeys (keyID, vCode, userID) VALUES (:keyID, :vCode, :userID) ON DUPLICATE UPDATE keyID = :keyID, vCode = :vCode, userID = :userID", array(":keyID" => $apiKey, ":vCode" => $vCode, ":userID" => $userID));
	}

	/**
	 * @param $apiKey
	 *
	 * @return bool|int|string
	 */
	public static function deleteAPIKey($apiKey)
	{
		return Database::execute("DELETE FROM apiKeys WHERE keyID = :keyID", array(":keyID" => $apiKey));
	}
}