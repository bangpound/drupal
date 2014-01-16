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

        /**
         * Include autoload scripts for each possible source.
         *
         * @see drupal_get_profile()
         */
        $this[DRUPAL_BOOTSTRAP_DATABASE] = $this->share($this->extend(DRUPAL_BOOTSTRAP_DATABASE, function () {
            $this['_drupal_bootstrap_composer_autoload'];
        }));

        $this['_drupal_bootstrap_composer_autoload'] = $this->share(function () {
            global $install_state;

            if (isset($install_state['parameters']['profile'])) {
                $profile = $install_state['parameters']['profile'];
            } else {
                $profile = variable_get('install_profile', 'standard');
            }

            $filename = DRUPAL_ROOT .'/autoload/autoload.php';
            if (file_exists($filename)) {
                require $filename;
            }

            $searchdirs = array();
            $searchdirs[] = 'profiles/'. $profile;
            $searchdirs[] = 'sites/all';
            $searchdirs[] = conf_path();

            foreach ($searchdirs as $dir) {
                $filename = DRUPAL_ROOT .'/'. $dir .'/autoload/autoload.php';
                if (file_exists($filename)) {
                    require $filename;
                }
            }
        });
    }
}
