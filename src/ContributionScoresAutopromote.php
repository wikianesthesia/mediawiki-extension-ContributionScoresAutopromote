<?php

namespace MediaWiki\Extension\ContributionScoresAutopromote;

use ContributionScores;
use MediaWiki\MediaWikiServices;
use User;

class ContributionScoresAutopromote {
    public static function initialize() {
        global $wgAutopromote, $wgContribScoreMetric,
               $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteAddUsersToUsergroup;

        define( 'APCOND_CONTRIBUTIONSCORE', 27271 );

        if( !count( $wgContributionScoresAutopromotePromotions ) ) {
            return;
        }

        // Don't define autopromote conditions if configured to explicitly add users to usergroups
        if( $wgContributionScoresAutopromoteAddUsersToUsergroup ) {
            return;
        }

        foreach( $wgContributionScoresAutopromotePromotions as $promotion ) {
            $metric = $promotion[ 'metric' ] ?? $wgContribScoreMetric;
            $threshold = $promotion[ 'threshold' ] ?? null;
            $userGroup = $promotion[ 'usergroup' ] ?? null;

            if( !$metric || !$threshold || !$userGroup ) {
                continue;
            }

            $autopromoteCondition = [
                APCOND_CONTRIBUTIONSCORE,
                $metric, $threshold
            ];

            if( !isset( $wgAutopromote[ $userGroup ] ) ) {
                $wgAutopromote[ $userGroup ] = $autopromoteCondition;
            } else {
                $wgAutopromote[ $userGroup ] = [
                    '|',
                    $autopromoteCondition,
                    $wgAutopromote[ $userGroup ]
                ];
            }
        }
    }

    public static function isMetricThresholdMet( User $user, string $metric, float $threshold ) {
        $contributionScore = ContributionScores::getMetricValue( $user->getName(), $metric );

        return $contributionScore && $contributionScore >= $threshold;
    }

    public static function tryPromote( User $user ) {
        global $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteAddUsersToUsergroup,
               $wgContribScoreMetric;

        if( !count( $wgContributionScoresAutopromotePromotions ) ) {
            return;
        }

        // Don't try to explicitly promote user unless configured accordingly
        if( !$wgContributionScoresAutopromoteAddUsersToUsergroup ) {
            return;
        }

        foreach( $wgContributionScoresAutopromotePromotions as $promotion ) {
            $metric = $promotion[ 'metric' ] ?? $wgContribScoreMetric;
            $threshold = $promotion[ 'threshold' ] ?? null;
            $userGroup = $promotion[ 'usergroup' ] ?? null;

            if( !$metric || !$threshold || !$userGroup ) {
                continue;
            }

            $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();

            if( !in_array( $userGroup, $userGroupManager->getUserGroups( $user ) ) &&
                static::isMetricThresholdMet( $user, $metric, $threshold ) ) {
                $userGroupManager->addUserToGroup( $user, $userGroup );
            }
        }
    }
}