<?php
namespace ProjectRena\Task\Cronjobs;

use ProjectRena\RenaApp;


/**
 * Finds all killmails in killmails where upgraded is 0
 */
class upgradeKillmailsCronjob
{

    /**
     * Executes the cronjob task
     *
     * @param mixed $pid
     * @param mixed $md5
     * @internal param RenaApp $app
     */

    public static function execute($pid, $md5)
    {
        /** @var RenaApp $app */
        $app = RenaApp::getInstance();

        $toUpgrade = $app->Db->query("SELECT killID FROM killmails WHERE upgraded = 0 AND processed != 1 ORDER BY dateAdded ASC LIMIT 100", array(), 1);
        if($toUpgrade)
            foreach($toUpgrade as $kill)
                \Resque::enqueue("turbo", "\\ProjectRena\\Task\\Resque\\upgradeKillmail", array("killID" => $kill["killID"]));

        exit ();
        // Keep this at the bottom, to make sure the fork exits
    }

    /**
     * Defines how often the cronjob runs, every 1 second, every 60 seconds, every 86400 seconds, etc.
     */

    public static function getRunTimes()
    {
        return 60;
        // Never runs
    }
}
