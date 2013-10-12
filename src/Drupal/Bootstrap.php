<?php

namespace Drupal;

/**
 * Class Bootstrap
 * @package Bangpound\Drupal
 */
abstract class Bootstrap extends \Pimple
{
    /**
     * @param  null  $phase
     * @param  bool  $new_phase
     * @return mixed
     * @see drupal_bootstrap()
     */
    public function __invoke($phase = NULL, $new_phase = TRUE)
    {
        // Not drupal_static(), because does not depend on any run-time information.
        static $phases;
        if (!isset($phases)) {
            $phases = $this['phases'];
        }
        // Not drupal_static(), because the only legitimate API to control this is to
        // call drupal_bootstrap() with a new phase parameter.
        static $final_phase;
        // Not drupal_static(), because it's impossible to roll back to an earlier
        // bootstrap state.
        static $stored_phase = -1;

        // When not recursing, store the phase name so it's not forgotten while
        // recursing.
        if ($new_phase) {
            $final_phase = $phase;
        }
        if (isset($phase)) {
            // Call a phase if it has not been called before and is below the requested
            // phase.
            while ($phases && $phase > $stored_phase && $final_phase > $stored_phase) {
                $current_phase = array_shift($phases);

                // This function is re-entrant. Only update the completed phase when the
                // current call actually resulted in a progress in the bootstrap process.
                if ($current_phase > $stored_phase) {
                    $stored_phase = $current_phase;
                }

                $this[$current_phase];
            }
        }

        return $stored_phase;
    }
}
