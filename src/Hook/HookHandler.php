<?php

namespace MediaWiki\Extension\ContributionScoresAutopromote\Hook;

use MediaWiki\Extension\ContributionScoresAutopromote\ContributionScoresAutopromote;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\AutopromoteConditionHook;

class HookHandler implements
    AutopromoteConditionHook,
    PageSaveCompleteHook {

    public function onAutopromoteCondition( $type, $args, $user, &$result ): bool {
        $result = false;

        if( $type === APCOND_CONTRIBUTIONSCORE ) {
            $metric = $args[ 0 ] ?? null;
            $threshold = $args[ 1 ] ?? null;

            if( $metric && $threshold ) {
                $result = ContributionScoresAutopromote::isMetricThresholdMet( $user, $metric, $threshold );
            }
        }

        return true;
    }

    public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
        ContributionScoresAutopromote::tryPromote( $user );
    }
}