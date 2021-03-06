<?php

namespace ProjectRena\Model\EVEApi\Character;

use ProjectRena\RenaApp;

/**
 * Class PlanetaryRoutes.
 */
class PlanetaryRoutes
{
    /**
     * @var int
     */
    public $accessMask = 2;

    /**
     * @var
     */
    private $app;

    /**
     * @param \ProjectRena\RenaApp $app
     */
    function __construct(RenaApp $app)
    {
        $this->app = $app;
    }

    /**
     * @param $apiKey
     * @param $vCode
     * @param $characterID
     * @param $planetID
     *
     * @return mixed
     */
    public function getData($apiKey, $vCode, $characterID, $planetID)
    {
        try {
            $pheal = $this->app->Pheal->Pheal($apiKey, $vCode);
            $pheal->scope = 'Char';
            $result = $pheal->PlanetaryRoutes(array('characterID' => $characterID, 'planetID' => $planetID))->toArray();

            return $result;
        } catch (\Exception $exception) {
            $this->app->Pheal->handleApiException($apiKey, null, $exception);
        }
    }
}
