<?php

namespace Icecave\Archer\Console;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\Support\Isolator;
use PHPUnit_Framework_TestCase;
use ReflectionObject;
use Symfony\Component\Console\Input\ArrayInput;

class ApplicationTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Command\Internal\AbstractInternalCommand::setIsEnabled(null);

        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->isolator = x\mock('Icecave\Archer\Support\Isolator');
        $this->application = new Application('foo', $this->fileSystem->mock(), $this->isolator->mock());
        $this->reflector = new ReflectionObject($this->application);
    }

    public function testConstructor()
    {
        $this->assertSame('Archer', $this->application->getName());
        $this->assertSame('1.3.2', $this->application->getVersion());

        $this->assertSame('foo', $this->application->packageRoot());
        $this->assertSame($this->fileSystem->mock(), $this->application->fileSystem());
    }

    public function testConstructorDefaults()
    {
        $this->application = new Application('foo');

        $this->assertInstanceOf('Icecave\Archer\FileSystem\FileSystem', $this->application->fileSystem());
    }

    public function testEnabledCommands()
    {
        $expected = array(
            'help',
            'list',
            'coverage',
            'documentation',
            'test',
            'update',
        );

        $this->assertSame($expected, array_keys($this->application->all()));
    }

    public function testEnabledCommandsArcher()
    {
        Command\Internal\AbstractInternalCommand::setIsEnabled(null);
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->fileSystem->fileExists->returns(true);
        $this->fileSystem->read->returns('{"name": "icecave/archer"}');
        $this->application = new Application('foo', $this->fileSystem->mock(), $this->isolator->mock());
        $expected = array(
            'help',
            'list',
            'coverage',
            'documentation',
            'test',
            'update',
            'internal:update-binaries',
        );

        x\inOrder(
            $this->fileSystem->fileExists->calledWith('foo/composer.json'),
            $this->fileSystem->read->calledWith('foo/composer.json')
        );
        $this->assertSame($expected, array_keys($this->application->all()));
    }

    public function testEnabledCommandsTravis()
    {
        $this->isolator->getenv('TRAVIS')->returns('true');

        $this->application = new Application('foo', $this->fileSystem->mock(), $this->isolator->mock());
        $expected = array(
            'help',
            'list',
            'coverage',
            'documentation',
            'test',
            'update',
            'travis:build',
        );

        $this->assertSame($expected, array_keys($this->application->all()));
    }

    public function testDoRun()
    {
        $this->application = x\partialMock(
            'Icecave\Archer\Console\Application',
            array('foo', $this->fileSystem->mock(), $this->isolator->mock())
        );

        $commandName = uniqid();
        $this->application->defaultCommandName->returns($commandName);
        $this->application->rawArguments->returns(array());
        $command = x\partialMock(
            'Symfony\Component\Console\Command\Command',
            array($commandName)
        );
        $this->application->mock()->add($command->mock());
        $this->application->mock()->setAutoExit(false);
        $input = new ArrayInput(array());
        $output = x\partialMock('Symfony\Component\Console\Output\NullOutput');
        $this->application->mock()->run($input, $output->mock());
        $expectedInput = new ArrayInput(array('command' => $commandName));

        x\inOrder(
            $this->application->defaultCommandName->called(),
            $command->run->calledWith('~', $output->mock())
        );
        $this->assertSame($commandName, $command->run->argument()->getFirstArgument());
    }

    public function testRawArguments()
    {
        $method = $this->reflector->getMethod('rawArguments');
        $method->setAccessible(true);
        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = array('foo', 'bar', 'baz');
        $actual = $method->invoke($this->application);
        $_SERVER['argv'] = $argv;

        $this->assertSame(array('bar', 'baz'), $actual);
    }

    public function testDefaultCommandName()
    {
        $method = $this->reflector->getMethod('defaultCommandName');
        $method->setAccessible(true);

        $this->assertSame('test', $method->invoke($this->application));
    }
}
