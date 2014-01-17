<?php
namespace Bangpound\Drupal;

use Composer\Autoload\ClassMapGenerator as BaseGenerator;
use Symfony\Component\Finder\Finder;

class ClassMapGenerator extends BaseGenerator
{
    private static $extensions = array('php', 'inc', 'module', 'theme', 'profile');

    public static function createMap($path, $whitelist = null)
    {
        if (is_string($path)) {
            if (is_file($path)) {
                $path = array(new \SplFileInfo($path));
            } elseif (is_dir($path)) {
                $path = Finder::create()->files()->followLinks()->name('/\.('. implode('|', self::$extensions) .')$/')->in($path);
            } else {
                throw new \RuntimeException(
                    'Could not scan for classes inside "'.$path.
                    '" which does not appear to be a file nor a folder'
                );
            }
        }

        $map = array();

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($path as $file) {
            $filePath = $file->getRealPath();

            if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), self::$extensions)) {
                continue;
            }

            if ($whitelist && !preg_match($whitelist, strtr($filePath, '\\', '/'))) {
                continue;
            }

            $classes = self::findClasses($filePath);

            foreach ($classes as $class) {
                $map[$class] = $filePath;
            }
        }

        return $map;
    }

    /**
     * Extract the classes in the given file
     *
     * @param string $path The file to check
     *
     * @return array The found classes
     */
    private static function findClasses($path)
    {
        $contents = file_get_contents($path);
        $tokens   = token_get_all($contents);
        $T_TRAIT  = version_compare(PHP_VERSION, '5.4', '<') ? -1 : T_TRAIT;

        $classes = array();

        $namespace = '';
        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                continue;
            }

            $class = '';

            switch ($token[0]) {
                case T_NAMESPACE:
                    $namespace = '';
                    // If there is a namespace, extract it
                    while (($t = $tokens[++$i]) && is_array($t)) {
                        if (in_array($t[0], array(T_STRING, T_NS_SEPARATOR))) {
                            $namespace .= $t[1];
                        }
                    }
                    $namespace .= '\\';
                    break;
                case T_CLASS:
                case T_INTERFACE:
                case $T_TRAIT:
                    // Find the classname
                    while (($t = $tokens[++$i]) && is_array($t)) {
                        if (T_STRING === $t[0]) {
                            $class .= $t[1];
                        } elseif ($class !== '' && T_WHITESPACE == $t[0]) {
                            break;
                        }
                    }

                    $classes[] = ltrim($namespace.$class, '\\');
                    break;
                default:
                    break;
            }
        }

        return $classes;
    }
}
