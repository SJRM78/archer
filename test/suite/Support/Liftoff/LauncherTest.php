<?php

/*
 * This file is part of the Liftoff package.
 *
 * Copyright © 2013 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Icecave\Archer\Support\Liftoff;

use Icecave\Archer\Support\Isolator;
use PHPUnit_Framework_TestCase;
use Phunky;

class LauncherTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->isolator = Phunky::mock(Isolator::className());
        $this->launcher = new Launcher($this->isolator);
    }

    public function launchData()
    {
        //                                    os             target            arguments               expectedCommand
        return array(
            'OSX'                    => array('Darwin',      '/path/to/file',  null,                   "open '/path/to/file'"),
            'OSX with arguments'     => array('Darwin',      '/path/to/file',  array('--foo', 'bar'),  "open '/path/to/file' '--args' '--foo' 'bar'"),
            'Windows'                => array('Windows NT',  '/path/to/file',  null,                   "start 'liftoff' '/path/to/file'"),
            'Windows with arguments' => array('Windows NT',  '/path/to/file',  array('--foo', 'bar'),  "start 'liftoff' '/path/to/file' '--foo' 'bar'"),
            'Unix'                   => array('Linux',       '/path/to/file',  null,                   "xdg-open '/path/to/file'"),
            'Unix with arguments'    => array('Linux',       '/path/to/file',  array('--foo', 'bar'),  "xdg-open '/path/to/file' '--foo' 'bar'"),
        );
    }

    /**
     * @dataProvider launchData
     */
    public function testLaunch($os, $target, $arguments, $expectedCommand)
    {
        $expectedDescriptorSpec = array(
            array('pipe', 'r'),
            array('pipe', 'w'),
            array('pipe', 'w'),
        );
        Phunky::when($this->isolator)->php_uname('s')->thenReturn($os);
        Phunky::when($this->isolator)
            ->proc_open($expectedCommand, $expectedDescriptorSpec, Phunky::setReference(array(222, 333, 444)))
            ->thenReturn(111);
        $this->launcher->launch($target, $arguments);

        Phunky::inOrder(
            Phunky::verify($this->isolator)->proc_open(
                $expectedCommand,
                $expectedDescriptorSpec,
                null
            ),
            Phunky::verify($this->isolator)->fclose(222),
            Phunky::verify($this->isolator)->fclose(333),
            Phunky::verify($this->isolator)->fclose(444),
            Phunky::verify($this->isolator)->proc_close(111)
        );
    }

    public function testLaunchFailure()
    {
        Phunky::when($this->isolator)
            ->proc_open(Phunky::anyParameters())
            ->thenReturn(false);

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\LaunchException',
            "Unable to launch '/path/to/file'"
        );

        $this->launcher->launch('/path/to/file');
    }
}
