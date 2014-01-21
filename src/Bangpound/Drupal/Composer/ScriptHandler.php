<?php

namespace Bangpound\Drupal\Composer;

use Bangpound\Drupal\Autoload\ClassMapGenerator;
use Composer\Script\Event;
use Symfony\Component\Finder\Finder;

class ScriptHandler
{
    public static function dumpAutoload(Event $event)
    {
        $io = $event->getIO();

        $generator = new ClassMapGenerator();
        $dirs = array(
            'includes', 'misc', 'modules', 'scripts', 'themes',
            'authorize.php', 'cron.php', 'index.php', 'install.php', 'update.php', 'xmlrpc.php',
        );
        $io->write('Dumping classmap for <info>DRUPAL_ROOT</info>');
        $generator->dump($dirs, 'classmap.php');

        $finder = Finder::create()
            ->directories()
            ->depth(0)
            ->followLinks()
            ->in(array('profiles', 'sites'))
        ;

        $cwd = getcwd();
        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            chdir($file->getPathInfo() .'/'. $file->getRelativePathname());
            $io->write(sprintf('Dumping classmap for <info>%s</info>', $file->getPathInfo() .'/'. $file->getRelativePathname()));
            $dirs = array();
            if (file_exists('modules')) {
                $dirs[] = 'modules';
            }
            if (file_exists('themes')) {
                $dirs[] = 'themes';
            }
            $generator->dump($dirs, 'classmap.php');
            chdir($cwd);
        }
    }
}
