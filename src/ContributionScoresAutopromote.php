<?php

namespace MediaWiki\Extension\ContributionScoresAutopromote;

use ContributionScores;
use MediaWiki\MediaWikiServices;
use RequestContext;
use User;

class ContributionScoresAutopromote {
    public static function canAutopromote( User $user = null ): bool {
        global $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteIgnoreUsernames;

        $user = $user ?? RequestContext::getMain()->getUser();

        // If no promotion conditions are defined, no user is logged in, or the user is configured to be ignored
        if( !count( $wgContributionScoresAutopromotePromotions ) ||
            !$user->isRegistered() ||
            in_array( $user->getName(), $wgContributionScoresAutopromoteIgnoreUsernames ) ) {
            return false;
        }

        return true;
    }

    public static function initialize() {
        global $wgAutopromote, $wgContribScoreMetric,
               $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteAddUsersToUsergroup;

        define( 'APCOND_CONTRIBUTIONSCORE', 27271 );

        // Don't define autopromote conditions if no promote conditions are configured
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

    public static function isMetricThresholdMet( User $user, string $metric, float $threshold ): bool {
        $contributionScore = ContributionScores::getMetricValue( $user, $metric );

        return $contributionScore && $contributionScore >= $threshold;
    }

    public static function tryPromoteAddUserToUsergroup( User $user ) {
        global $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteAddUsersToUsergroup,
               $wgContribScoreMetric;

        // Don't try to explicitly promote user unless configured accordingly
        if( !$wgContributionScoresAutopromoteAddUsersToUsergroup ) {
            return;
        }

        if( !static::canAutopromote( $user ) ) {
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