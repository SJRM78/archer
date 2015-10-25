<?php

namespace Icecave\Archer\Console\Command;

use PHPUnit_Framework_TestCase;
use Phunky;
use ReflectionObject;

class AbstractPHPUnitCommandTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Phunky::mock(
            'Icecave\Archer\FileSystem\FileSystem'
        );
        $this->phpFinder = Phunky::mock(
            'Symfony\Component\Process\PhpExecutableFinder'
        );
        $this->phpunitFinder = Phunky::mock(
            'Icecave\Archer\Process\PHPUnitExecutableFinder'
        );
        $this->phpConfigurationReader = Phunky::mock(
            'Icecave\Archer\Configuration\PHPConfigurationReader'
        );
        $this->configurationFileFinder = Phunky::mock(
            'Icecave\Archer\Configuration\ConfigurationFileFinder'
        );
        $this->processFactory = Phunky::mock(
            'Icecave\Archer\Process\ProcessFactory'
        );
        $this->command = Phunky::partialMock(
            __NAMESPACE__ . '\AbstractPHPUnitCommand',
            $this->fileSystem,
            $this->phpFinder,
            $this->phpunitFinder,
            $this->phpConfigurationReader,
            $this->configurationFileFinder,
            $this->processFactory,
            'cmd'
        );

        $this->application = Phunky::mock('Icecave\Archer\Console\Application');
        $this->process = Phunky::mock('Symfony\Component\Process\Process');

        Phunky::when($this->command)
            ->getApplication(Phunky::anyParameters())
            ->thenReturn($this->application);

        Phunky::when($this->processFactory)
            ->createFromArray(Phunky::anyParameters())
            ->thenReturn($this->process);

        Phunky::when($this->phpFinder)
            ->find(false)
            ->thenReturn('/path/to/php');
        Phunky::when($this->phpFinder)
            ->findArguments()
            ->thenReturn(array('--option-a', '--option-b'));

        Phunky::when($this->phpunitFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/phpunit');
    }

    public function testConstructor()
    {
        Phunky::verify($this->command)->ignoreValidationErrors();

        $this->assertSame($this->fileSystem, $this->command->fileSystem());
        $this->assertSame($this->phpFinder, $this->command->phpFinder());
        $this->assertSame($this->phpunitFinder, $this->command->phpunitFinder());
        $this->assertSame($this->phpConfigurationReader, $this->command->phpConfigurationReader());
        $this->assertSame($this->configurationFileFinder, $this->command->configurationFileFinder());
        $this->assertSame($this->processFactory, $this->command->processFactory());
    }

    public function testConstructorDefaults()
    {
        $this->command = Phunky::partialMock(
            __NAMESPACE__ . '\AbstractPHPUnitCommand',
            null,
            null,
            null,
            null,
            null,
            null,
            'cmd'
        );

        $this->assertInstanceOf(
            'Icecave\Archer\FileSystem\FileSystem',
            $this->command->fileSystem()
        );
        $this->assertInstanceOf(
            'Symfony\Component\Process\PhpExecutableFinder',
            $this->command->phpFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Process\PHPUnitExecutableFinder',
            $this->command->phpunitFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Configuration\PHPConfigurationReader',
            $this->command->phpConfigurationReader()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Configuration\ConfigurationFileFinder',
            $this->command->configurationFileFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Process\ProcessFactory',
            $this->command->processFactory()
        );
    }

    public function testGetHelp()
    {
        Phunky::when($this->process)
            ->run(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($callback) {
                    $callback('out', '<phpunit help>');
                }
            );

        $expectedHelp  = '<info>This command forwards all arguments to PHPUnit.</info>';
        $expectedHelp .= PHP_EOL;
        $expectedHelp .= PHP_EOL;
        $expectedHelp .= '<phpunit help>';

        $result = $this->command->getHelp();

        $shim = null;

        Phunky::inOrder(
            Phunky::verify($this->phpFinder)->findArguments(),
            Phunky::verify($this->phpFinder)->find(false),
            Phunky::verify($this->phpunitFinder)->find(),
            Phunky::verify($this->processFactory)
                ->createFromArray(array('/path/to/php', '--option-a', '--option-b', '/path/to/phpunit', '--help')),
            Phunky::verify($this->process)->run($this->isInstanceOf('Closure'))
        );

        $this->assertSame($expectedHelp, $result);
    }

    public function testGenerateArgumentsFiltering()
    {
        Phunky::when($this->command)
            ->phpConfigurationArguments()
            ->thenReturn(array());

        Phunky::when($this->command)
            ->readPHPConfiguration()
            ->thenReturn(array());

        Phunky::when($this->command)
            ->findPHPUnitConfiguration()
            ->thenReturn('/path/to/config.xml');

        $reflector = new ReflectionObject($this->command);
        $method = $reflector->getMethod('generateArguments');
        $method->setAccessible(true);

        $input = array(
            '--quiet',
            '-q',
            '--version',
            '-V',
            '--ansi',
            '--no-ansi',
            '--no-interaction',
            '-n',
        );

        $expected = array(
            '/path/to/php',
            '/path/to/phpunit',
            '--configuration',
            '/path/to/config.xml',
            '--color',
        );

        $result = $method->invoke($this->command, array('/path/to/php'), '/path/to/phpunit', $input);

        $this->assertSame($expected, $result);
    }
}
