<?php

namespace Bangpound\Composer;

use Composer\Installer;
use Composer\Script\Event;
use Composer\Factory;
use Symfony\Component\Finder\Finder;

class DrupalAutoloadScript
{
    public static function dumpAutoload(Event $event)
    {
        $io = $event->getIO();

        $io->write('Searching for autoload.json files');
        $finder = Finder::create()
            ->files()
            ->depth(0)
            ->followLinks()
            ->name('autoload.json')
            ->in(array('.', 'profiles/*', 'sites/*'))
        ;

        $cwd = getcwd();
        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            chdir($file->getPathInfo());
            $io->write(sprintf('Dumping autoload for <info>%s</info>', $file->getPathInfo()));
            $composer = Factory::create($io, 'autoload.json');

            $installationManager = $composer->getInstallationManager();
            $localRepo = $composer->getRepositoryManager()->getLocalRepository();
            $package = $composer->getPackage();
            $config = $composer->getConfig();

            $composer->getAutoloadGenerator()->dump($config, $localRepo, $package, $installationManager, 'composer');
            chdir($cwd);
        }
    }
}
