<?php

namespace Icecave\Archer\Support\Liftoff;

use Icecave\Archer\Support\Isolator;

/**
 * Launches files in their default GUI application.
 *
 * This class is a partial implementation of eloquent/liftoff, provided here to prevent
 * circular dependencies and namespace clashes.
 *
 * Please see https://github.com/eloquent/liftoff for a usable implementation.
 */
class Launcher
{
    /**
     * Create a new Launcher instance.
     *
     * @param Isolator|null $isolator
     */
    public function __construct(Isolator $isolator = null)
    {
        $this->isolator = Isolator::get($isolator);
    }

    /**
     * Launch a file or URI in its default GUI application.
     *
     * @param string             $target    The path or URI to launch.
     * @param array<string>|null $arguments An array of arguments to pass to the
     *                                      associated application.
     *
     * @throws Exception\LaunchException If the launch command fails, or is
     *                                   unavailable.
     */
    public function launch($target, array $arguments = null)
    {
        if (null === $arguments) {
            $arguments = array();
        }

        $os = $this->isolator->php_uname('s');

        if ('win' === strtolower(substr($os, 0, 3))) {
            $this->launchWindows($target, $arguments);
        } elseif ('Darwin' === $os) {
            $this->launchOsx($target, $arguments);
        } else {
            $this->launchUnix($target, $arguments);
        }
    }

    /**
     * @param string        $target
     * @param array<string> $arguments
     *
     * @throws Exception\LaunchException
     */
    protected function launchOsx($target, array $arguments)
    {
        if (count($arguments) > 0) {
            array_unshift($arguments, '--args');
        }
        array_unshift($arguments, $target);

        $this->launchCommand('open', $arguments);
    }

    /**
     * @param string        $target
     * @param array<string> $arguments
     *
     * @throws Exception\LaunchException
     */
    protected function launchUnix($target, array $arguments)
    {
        array_unshift($arguments, $target);

        $this->launchCommand('xdg-open', $arguments);
    }

    /**
     * @param string        $target
     * @param array<string> $arguments
     *
     * @throws Exception\LaunchException
     */
    protected function launchWindows($target, array $arguments)
    {
        array_unshift($arguments, 'liftoff', $target);

        $this->launchCommand('start', $arguments);
    }

    /**
     * @param string        $command
     * @param array<string> $arguments
     *
     * @throws Exception\LaunchException
     */
    protected function launchCommand($command, array $arguments)
    {
        $command = implode(
            ' ',
            array_merge(
                array($command),
                array_map('escapeshellarg', $arguments)
            )
        );

        $handle = $this->isolator->proc_open(
            $command,
            array(
                array('pipe', 'r'),
                array('pipe', 'w'),
                array('pipe', 'w'),
            ),
            $pipes
        );
        if (false === $handle) {
            throw new Exception\LaunchException($arguments[0]);
        }

        foreach ($pipes as $pipe) {
            $this->isolator->fclose($pipe);
        }
        $this->isolator->proc_close($handle);
    }

    private $isolator;
}
