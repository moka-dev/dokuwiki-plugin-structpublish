<?php

use dokuwiki\plugin\structpublish\meta\Constants;
use dokuwiki\plugin\structpublish\meta\Revision;

class action_plugin_structpublish_show extends DokuWiki_Action_Plugin
{
    /**
     * @var int 
     */
    protected static $latestPublishedRev;

    /**
     * @inheritDoc 
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleShow');
        $controller->register_hook('HTML_SHOWREV_OUTPUT', 'BEFORE', $this, 'handleShowrev');
    }

    /**
     * Decide which revision to show based on role assignments
     *
     * @param  Doku_Event $event
     * @return void
     */
    public function handleShow(Doku_Event $event)
    {
        if ($event->data != 'show') {
            return;
        }

        global $ID;
        global $REV;
        global $INFO;

        /**
 * @var helper_plugin_structpublish_db $dbHelper 
*/
        $dbHelper = plugin_load('helper', 'structpublish_db');

        if (!$dbHelper->isPublishable() 
            || (auth_isadmin() && !$this->getConf('restrict_admin'))
        ) {
            return;
        }

        $currentRevision = new Revision($ID, $REV ?: $INFO['currentrev']);
        $isPublished = $currentRevision->getStatus() === Constants::STATUS_PUBLISHED;

        //Show published version or nothing to user with no Role and no ACL write
        if (!$dbHelper->isPublisher($ID) && auth_quickaclcheck($ID) < 2) {
            $latestPublished = $currentRevision->getLatestPublishedRevision();
            // there is no published revision, show nothing
            if (!$isPublished && is_null($latestPublished)) {
                $event->data = 'denied';
                // FIXME we could add our own action to display a custom message instead of standard denied action
                return;
            }

            self::$latestPublishedRev = $latestPublished->getRev();

            // show either the explicitly requested or the latest published revision
            if (!$isPublished) {
                $REV = self::$latestPublishedRev;
                $INFO['rev'] = self::$latestPublishedRev;
            }
        }
    }

    /**
     * Suppress message about viewing an old revision if it is the latest one
     * that the current user is allowed to see.
     *
     * @param  Doku_Event $event
     * @return void
     */
    public function handleShowrev(Doku_Event $event)
    {
        /**
 * @var helper_plugin_structpublish_db $dbHelper 
*/
        $dbHelper = plugin_load('helper', 'structpublish_db');

        if (!$dbHelper->isPublishable() 
            || (auth_isadmin() && !$this->getConf('restrict_admin'))
        ) {
            return;
        }

        global $INFO;

        if (self::$latestPublishedRev && self::$latestPublishedRev == $INFO['rev']) {
            $event->preventDefault();
        }
    }
}
