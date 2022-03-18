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
               $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteAddUsersToUserGroup;

        define( 'APCOND_CONTRIBUTIONSCORE', 27271 );

        // Don't define autopromote conditions if no promote conditions are configured
        if( !count( $wgContributionScoresAutopromotePromotions ) ) {
            return;
        }

        // Don't define autopromote conditions if configured to explicitly add users to usergroups
        if( $wgContributionScoresAutopromoteAddUsersToUserGroup ) {
            return;
        }

        // Autopromote conditions may need to merge with existing conditions, so gather this extensions conditions
        // by usergroup and assign results to $wgAutopromote all at once after the main loop
        $contributionScoresAutopromoteConditions = [];

        foreach( $wgContributionScoresAutopromotePromotions as $promotion ) {
            $metric = $promotion[ 'metric' ] ?? $wgContribScoreMetric;
            $threshold = $promotion[ 'threshold' ] ?? null;
            $userGroups = $promotion[ 'usergroup' ] ?? null;

            if( !$metric || !$threshold || !$userGroups ) {
                continue;
            }

            // If usergroups is a string, convert to array
            $userGroups = is_array( $userGroups ) ? $userGroups : [ $userGroups ];

            foreach( $userGroups as $userGroup ) {
                if( !isset( $contributionScoresAutopromoteConditions[ $userGroup ] ) ) {
                    $contributionScoresAutopromoteConditions[ $userGroup ] = [ '|' ];
                }

                $contributionScoresAutopromoteConditions[ $userGroup ][] = [
                    APCOND_CONTRIBUTIONSCORE,
                    $metric, $threshold
                ];
            }
        }

        foreach( $contributionScoresAutopromoteConditions as $userGroup => $autopromoteConditions ) {
            if( !isset( $wgAutopromote[ $userGroup ] ) ) {
                $wgAutopromote[ $userGroup ] = $autopromoteConditions;
            } else {
                $wgAutopromote[ $userGroup ] = [
                    '|',
                    $autopromoteConditions,
                    $wgAutopromote[ $userGroup ]
                ];
            }
        }
    }

    public static function isMetricThresholdMet( User $user, string $metric, float $threshold ): bool {
        $contributionScore = ContributionScores::getMetricValue( $user, $metric );

        return $contributionScore && $contributionScore >= $threshold;
    }

    public static function tryPromoteAddUserToUserGroup( User $user ): array {
        global $wgContributionScoresAutopromotePromotions, $wgContributionScoresAutopromoteAddUsersToUserGroup,
               $wgContribScoreMetric;

        $promotedUserGroups = [];

        // Don't try to explicitly promote user unless configured accordingly
        if( !$wgContributionScoresAutopromoteAddUsersToUserGroup ) {
            return $promotedUserGroups;
        }

        if( !static::canAutopromote( $user ) ) {
            return $promotedUserGroups;
        }

        foreach( $wgContributionScoresAutopromotePromotions as $promotion ) {
            $metric = $promotion[ 'metric' ] ?? $wgContribScoreMetric;
            $threshold = $promotion[ 'threshold' ] ?? null;
            $userGroups = $promotion[ 'usergroup' ] ?? null;

            if( !$metric || !$threshold || !$userGroups ) {
                continue;
            }

            // If usergroups is a string, convert to array
            $userGroups = is_array( $userGroups ) ? $userGroups : [ $userGroups ];

            $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();

            foreach( $userGroups as $userGroup ) {
                if( !in_array( $userGroup, $userGroupManager->getUserGroups( $user ) ) &&
                    static::isMetricThresholdMet( $user, $metric, $threshold ) ) {
                    $userGroupManager->addUserToGroup( $user, $userGroup );
                    $promotedUserGroups[] = $userGroup;
                }
            }
        }

        return $promotedUserGroups;
    }
}