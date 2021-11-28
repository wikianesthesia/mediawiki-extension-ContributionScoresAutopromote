<?php

namespace MediaWiki\Extension\ContributionScoresAutopromote\Hook;

use MediaWiki\Extension\ContributionScoresAutopromote\ContributionScoresAutopromote;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\User\Hook\AutopromoteConditionHook;

class HookHandler implements
    AutopromoteConditionHook,
    BeforeInitializeHook {

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

    public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
        ContributionScoresAutopromote::tryPromote( $user );
    }
}