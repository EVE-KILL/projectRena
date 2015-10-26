<?php

namespace ProjectRena\Model\EVEApi\Map;

use ProjectRena\RenaApp;

/**
 * Class Jumps.
 */
class Jumps
{
    /**
     * @var int
     */
    public $accessMask = null;

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
     * @return mixed
     */
    public function getData()
    {
        try {
            $pheal = $this->app->Pheal->Pheal();
            $pheal->scope = 'Map';
            $result = $pheal->Jumps()->toArray();
            return $result;
        } catch (\Exception $exception) {
            $this->app->Pheal->handleApiException(null, null, $exception);
        }
    }
}
