<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\ContributionScoresAutopromote\ContributionScoresAutopromote;

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
    require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
} else {
    require_once __DIR__ . '/../../../maintenance/Maintenance.php';
}

class AutopromoteAllUsers extends Maintenance {

    public function __construct() {
        parent::__construct();
        $this->requireExtension( 'ContributionScoresAutopromote' );
        $this->addDescription( 'Autopromotes users based upon contribution score metric thresholds defined by $wgContributionScoresAutopromotePromotions.' );
    }

    /**
     * @inheritDoc
     */
    public function execute() {
        global $wgContributionScoresAutopromoteAddUsersToUserGroup;

        if( !$wgContributionScoresAutopromoteAddUsersToUserGroup ) {
            $this->output( 'Autopromotion maintenance is not required if $wgContributionScoresAutopromoteAddUsersToUsergroup is false since no usergroups will be added to the database.' . "\n" );

            return;
        }

        $this->output( 'Loading users...' . "\n" );

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );

        $res = $dbr->select(
            'user',
            'user_id',
            [],
            __METHOD__,
            [ 'ORDER BY' => 'user_name']
        );

        foreach( $res as $row ) {
            $user = User::newFromId( $row->user_id );
            $promotedUserGroups = ContributionScoresAutopromote::tryPromoteAddUserToUserGroup( $user );

            $promotedUserGroupsString = count( $promotedUserGroups ) ?
                implode( ', ', $promotedUserGroups ) :
                'no groups';
            $this->output( 'User "' . $user->getName() . '": Added ' . $promotedUserGroupsString . "\n" );
        }

        $this->output( 'Autopromotion processed for ' . $res->numRows() . ' users.' . "\n" );
    }
}

$maintClass = "AutopromoteAllUsers";
require_once RUN_MAINTENANCE_IF_MAIN;