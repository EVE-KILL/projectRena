<?php

namespace ProjectRena\Task\Resque;
use ProjectRena\RenaApp;

/**
 * Class updateCharacter
 *
 * @package ProjectRena\Task\Resque
 */
class updateCharacter
{
    protected $app;

    /**
     *
     */
    public function setUp()
    {
        $this->app = RenaApp::getInstance();
    }

    /**
     *
     */
    public function perform()
    {
        if ($this->app->Storage->get("Api904") >= date("Y-m-d H:i:s")) return;

        $this->app->StatsD->increment("ccpRequests");
        $this->app->StatsD->increment("charactersUpdated");
        $characterID = $this->args["characterID"];

        // Just exit if the char id is 0
        if ($characterID == 0)
            exit;

        // Skip NPC and DUST characters
        if ($characterID >= 2100000000 && $characterID <= 2200000000) return;
        if ($characterID >= 30000000 && $characterID <= 31004590) return;
        if ($characterID >= 40000000 && $characterID <= 41004590) return;

        // Get the character affiliate data
        $data = $this->app->EVEEVECharacterInfo->getData($characterID);
        // Update/Insert the character
        $this->app->characters->updateCharacterDetails($data["result"]["characterID"], $data["result"]["corporationID"], (isset($data["result"]["allianceID"]) ? $data["result"]["allianceID"] : 0), $data["result"]["characterName"], json_encode($data["result"]["employmentHistory"]));
        // Update the last updated
        $this->app->characters->setLastUpdated($characterID, date("Y-m-d H:i:s"));
        // Insert the corporation to the corporationtable
        $this->app->corporations->insertCorporationID($data["result"]["corporationID"]);
    }

    /**
     *
     */
    public function tearDown()
    {
        $this->app = null;
    }
}
