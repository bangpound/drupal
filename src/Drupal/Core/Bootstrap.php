<?php

namespace Drupal\Core;

use Drupal\Bootstrap as BaseBootstrap;

/**
 * Class Bootstrap
 * @package Drupal\Core
 */
class Bootstrap extends BaseBootstrap
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

        // The series of bootstrap phases is represented by a
        // consecutive series of integer constants. Probably this cannot
        // be changed, but maybe new steps can be inserted between other
        // steps if the intermediate steps are floats.
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
}
