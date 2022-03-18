<?php

namespace MediaWiki\Extension\ContributionScoresAutopromote\Hook;

use MediaWiki\Extension\ContributionScoresAutopromote\ContributionScoresAutopromote;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\AutopromoteConditionHook;

class HookHandler implements
    AutopromoteConditionHook,
    PageSaveCompleteHook {

    public function onAutopromoteCondition( $type, $args, $user, &$result ) {
        if( $type === APCOND_CONTRIBUTIONSCORE ) {
            $metric = $args[ 0 ] ?? null;
            $threshold = $args[ 1 ] ?? null;

            if( $metric && $threshold && ContributionScoresAutopromote::canAutopromote( $user ) ) {
                $result = ContributionScoresAutopromote::isMetricThresholdMet( $user, $metric, $threshold );
            } else {
                $result = false;
            }
        }
    }

    public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
        ContributionScoresAutopromote::tryPromoteAddUserToUserGroup( $user );
    }
}