<?php

namespace Bangpound\Drupal\Bootstrap;

use Drupal\Core\Bootstrap;

/**
 * Class AutoloadBootstrap
 * @package Bangpound\Drupal\Bootstrap
 */
class AutoloadBootstrap extends Bootstrap
{
    /**
     * @param array $values
     */
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $this[DRUPAL_BOOTSTRAP_DATABASE] = function () {
            // Redirect the user to the installation script if Drupal has not been
            // installed yet (i.e., if no $databases array has been defined in the
            // settings.php file) and we are not already installing.
            if (empty($GLOBALS['databases']) && !drupal_installation_attempted()) {
                include_once DRUPAL_ROOT . '/includes/install.inc';
                install_goto('install.php');
            }

            // The user agent header is used to pass a database prefix in the request when
            // running tests. However, for security reasons, it is imperative that we
            // validate we ourselves made the request.
            if ($test_prefix = drupal_valid_test_ua()) {
                // Set the test run id for use in other parts of Drupal.
                $test_info = &$GLOBALS['drupal_test_info'];
                $test_info['test_run_id'] = $test_prefix;
                $test_info['in_child_site'] = TRUE;

                foreach ($GLOBALS['databases']['default'] as &$value) {
                    // Extract the current default database prefix.
                    if (!isset($value['prefix'])) {
                        $current_prefix = '';
                    }
                    elseif (is_array($value['prefix'])) {
                        $current_prefix = $value['prefix']['default'];
                    }
                    else {
                        $current_prefix = $value['prefix'];
                    }

                    // Remove the current database prefix and replace it by our own.
                    $value['prefix'] = array(
                        'default' => $current_prefix . $test_prefix,
                    );
                }
            }

            // Initialize the database system. Note that the connection
            // won't be initialized until it is actually requested.
            require_once DRUPAL_ROOT . '/includes/database/database.inc';

            $this['_drupal_bootstrap_database__autoload'];
        };

        /**
         * Include autoload scripts for each possible source.
         *
         * @see drupal_get_profile()
         */
        $this['_drupal_bootstrap_database__autoload'] = function () {
            global $install_state;

            if (isset($install_state['parameters']['profile'])) {
                $profile = $install_state['parameters']['profile'];
            } else {
                $profile = variable_get('install_profile', 'standard');
            }

            $searchdirs = array();
            $searchdirs[] = 'profiles/'. $profile;
            $searchdirs[] = 'sites/all';
            $searchdirs[] = conf_path();

            foreach ($searchdirs as $dir) {
                $filename = $dir .'/vendor/autoload.php';
                if (file_exists($filename)) {
                    require $filename;
                }
            }
        };
    }
}
