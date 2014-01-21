<?php

namespace Drupal\Core;

/**
 * Class Bootstrap
 * @package Drupal\Core
 */
class Bootstrap extends \Pimple
{
    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects.
     */
    public function __construct(array $values = array())
    {

        parent::__construct($values);

        // Bootstrap phases are represented by a consecutive series of
        // integer constants. These phases should not be changed at all.
        // Use event listeners to add new functionality between
        // existing bootstrap phases.
        $this['phases'] = array(
            DRUPAL_BOOTSTRAP_CONFIGURATION,
            DRUPAL_BOOTSTRAP_PAGE_CACHE,
            DRUPAL_BOOTSTRAP_DATABASE,
            DRUPAL_BOOTSTRAP_VARIABLES,
            DRUPAL_BOOTSTRAP_SESSION,
            DRUPAL_BOOTSTRAP_PAGE_HEADER,
            DRUPAL_BOOTSTRAP_LANGUAGE,
            DRUPAL_BOOTSTRAP_FULL,
        );

        $this[DRUPAL_BOOTSTRAP_CONFIGURATION] = $this->share(function () {
            _drupal_bootstrap_configuration();
        });

        $this[DRUPAL_BOOTSTRAP_PAGE_CACHE] = $this->share(function () {
            _drupal_bootstrap_page_cache();
        });

        $this[DRUPAL_BOOTSTRAP_DATABASE] = $this->share(function () {
            _drupal_bootstrap_database();
        });

        $this[DRUPAL_BOOTSTRAP_VARIABLES] = $this->share(function () {
            _drupal_bootstrap_variables();
        });

        $this[DRUPAL_BOOTSTRAP_SESSION] = $this->share(function () {
            require_once DRUPAL_ROOT . '/' . variable_get('session_inc', 'includes/session.inc');
            drupal_session_initialize();
        });

        $this[DRUPAL_BOOTSTRAP_PAGE_HEADER] = $this->share(function () {
            _drupal_bootstrap_page_header();
        });

        $this[DRUPAL_BOOTSTRAP_LANGUAGE] = $this->share(function () {
            drupal_language_initialize();
        });

        $this[DRUPAL_BOOTSTRAP_FULL] = $this->share(function () {
            require_once DRUPAL_ROOT . '/includes/common.inc';
            _drupal_bootstrap_full();
        });
    }

    /**
     * @param  null  $phase     Phase
     * @param  bool  $new_phase New phase
     * @return mixed
     *
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

                $this->call($current_phase);
            }
        }

        return $stored_phase;
    }

    /**
     * Calls the bootstrap phase.
     *
     * This method can be overridden to support events in subclasses.
     *
     * @param null $phase Phase
     */
    protected function call($phase = NULL)
    {
        $this[$phase];
    }
}
