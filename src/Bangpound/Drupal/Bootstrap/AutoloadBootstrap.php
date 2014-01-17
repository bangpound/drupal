<?php

namespace Bangpound\Drupal\Bootstrap;

use Drupal\Core\Bootstrap;
use Symfony\Component\ClassLoader\MapClassLoader;

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
            spl_autoload_unregister('drupal_autoload_class');
            spl_autoload_unregister('drupal_autoload_interface');
            $this['_drupal_bootstrap_composer_autoload'];
        }));

        $this['_drupal_bootstrap_composer_autoload'] = $this->share(function () {
            global $install_state;

            if (isset($install_state['parameters']['profile'])) {
                $profile = $install_state['parameters']['profile'];
            } else {
                $profile = variable_get('install_profile', 'standard');
            }

            $searchdirs = array();
            $searchdirs[] = DRUPAL_ROOT;
            $searchdirs[] = DRUPAL_ROOT . '/profiles/'. $profile;
            $searchdirs[] = DRUPAL_ROOT . '/sites/all';
            $searchdirs[] = DRUPAL_ROOT . '/'. conf_path();

            foreach ($searchdirs as $dir) {
                $filename = $dir .'/classmap.php';
                if (file_exists($filename)) {
                    $loader = new MapClassLoader(require $filename);
                    $loader->register(true);
                }
            }
        });
    }
}
