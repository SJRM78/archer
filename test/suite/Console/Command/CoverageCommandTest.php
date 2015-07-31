<?php

namespace Icecave\Archer\Console\Command;

use PHPUnit_Framework_TestCase;
use Phunky;
use ReflectionObject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @covers \Icecave\Archer\Console\Command\AbstractPHPUnitCommand
 * @covers \Icecave\Archer\Console\Command\CoverageCommand
 */
class CoverageCommandTest extends PHPUnit_Framework_TestCase
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
        $this->launcher = Phunky::mock(
            'Icecave\Archer\Support\Liftoff\Launcher'
        );
        $this->command = Phunky::partialMock(
            __NAMESPACE__ . '\CoverageCommand',
            $this->fileSystem,
            $this->phpFinder,
            $this->phpunitFinder,
            $this->phpConfigurationReader,
            $this->configurationFileFinder,
            $this->processFactory,
            $this->launcher
        );

        $this->application = Phunky::mock('Icecave\Archer\Console\Application');
        $this->process = Phunky::mock('Symfony\Component\Process\Process');

        Phunky::when($this->command)
            ->getApplication(Phunky::anyParameters())
            ->thenReturn($this->application);

        Phunky::when($this->application)
            ->rawArguments(Phunky::anyParameters())
            ->thenReturn(array('foo', 'bar'));

        Phunky::when($this->phpConfigurationReader)
            ->read(Phunky::anyParameters())
            ->thenReturn(array(
                'baz' => 'qux',
                'doom' => 'splat',
            ));

        Phunky::when($this->configurationFileFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/phpunit.xml');

        Phunky::when($this->processFactory)
            ->createFromArray(Phunky::anyParameters())
            ->thenReturn($this->process);

        Phunky::when($this->phpFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/php');

        Phunky::when($this->phpunitFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/phpunit');

        $this->reflector = new ReflectionObject($this->command);
        $this->executeMethod = $this->reflector->getMethod('execute');
        $this->executeMethod->setAccessible(true);

        $this->input = Phunky::mock('Symfony\Component\Console\Input\InputInterface');

        // used for closures
        $that = $this;

        $this->stdErr = '';
        $this->errorOutput = Phunky::mock('Symfony\Component\Console\Output\OutputInterface');
        Phunky::when($this->errorOutput)
            ->write(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($data) use ($that) {
                    $that->stdErr .= $data;
                }
            );

        $this->stdOut = '';
        $this->output = Phunky::mock('Symfony\Component\Console\Output\ConsoleOutputInterface');
        Phunky::when($this->output)
            ->write(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($data) use ($that) {
                    $that->stdOut .= $data;
                }
            );
        Phunky::when($this->output)
            ->writeln(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($data) use ($that) {
                    $that->stdOut .= $data . "\n";
                }
            );
        Phunky::when($this->output)
            ->getErrorOutput(Phunky::anyParameters())
            ->thenReturn($this->errorOutput);

        Phunky::when($this->process)
            ->run(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($callback) {
                    $callback('out', "out\nout\n");
                    $callback('err', "err\nerr\n");

                    return 111;
                }
            );
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
        $this->assertSame($this->launcher, $this->command->launcher());
    }

    public function testConstructorDefaults()
    {
        $this->command = new CoverageCommand(
            null,
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
        $this->assertInstanceOf(
            'Icecave\Archer\Support\Liftoff\Launcher',
            $this->command->launcher()
        );
    }

    public function testConfigure()
    {
        $expectedInputDefinition = new InputDefinition();
        $expectedInputDefinition->addArgument(
            new InputArgument(
                'argument',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Argument(s) to pass to PHPUnit.'
            )
        );
        $expectedInputDefinition->addOption(
            new InputOption(
                'open',
                'o',
                InputOption::VALUE_NONE,
                'Open the generated report in your default web browser.'
            )
        );

        $this->assertSame('coverage', $this->command->getName());
        $this->assertSame(
            'Run the test suite for a project and generate a code coverage report.',
            $this->command->getDescription()
        );
        $this->assertEquals(
            $expectedInputDefinition,
            $this->command->getDefinition()
        );
    }

    public function testExecute()
    {
        $exitCode = $this->executeMethod->invoke(
            $this->command,
            $this->input,
            $this->output
        );
        $expectedStdout = <<<'EOD'
<info>Using PHP:</info> /path/to/php
<info>Using PHPUnit:</info> /path/to/phpunit
out
out

EOD;
        $expectedStderr = <<<'EOD'
err
err

EOD;

        $this->assertSame(111, $exitCode);
        $this->assertSame($expectedStdout, $this->stdOut);
        $this->assertSame($expectedStderr, $this->stdErr);
        Phunky::inOrder(
            Phunky::verify($this->phpFinder)->find(),
            Phunky::verify($this->phpunitFinder)->find(),
            Phunky::verify($this->phpConfigurationReader)
                ->read(Phunky::capture($actualPhpConfigurationPaths)),
            Phunky::verify($this->configurationFileFinder)->find(
                Phunky::capture($actualPhpunitConfigurationPaths),
                './vendor/icecave/archer/res/phpunit/phpunit.coverage.xml'
            ),
            Phunky::verify($this->processFactory)
                ->createFromArray(Phunky::capture($actualArguments)),
            Phunky::verify($this->process)->setTimeout(null),
            Phunky::verify($this->command)->passthru(
                $this->identicalTo($this->process),
                $this->identicalTo($this->output)
            )
        );
        Phunky::verify($this->launcher, Phunky::never())->launch(Phunky::anyParameters());
        $this->assertSame(array(
            './vendor/icecave/archer/res/php/php.ini',
            './vendor/icecave/archer/res/php/php.coverage.ini',
            './test/php.ini',
            './test/php.coverage.ini',
            './php.ini',
            './php.coverage.ini',
        ), $actualPhpConfigurationPaths);
        $this->assertSame(array(
            './phpunit.coverage.xml',
            './test/phpunit.coverage.xml',
        ), $actualPhpunitConfigurationPaths);
        $this->assertSame(array(
            '/path/to/php',
            '--define',
            'baz=qux',
            '--define',
            'doom=splat',
            '/path/to/phpunit',
            '--configuration',
            '/path/to/phpunit.xml',
            'bar',
        ), $actualArguments);
    }

    public function testExecuteWithOpen()
    {
        Phunky::when($this->process)
            ->run(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($callback) {
                    $callback('out', "out\nout\n");
                    $callback('err', "err\nerr\n");

                    return 0;
                }
            );
        Phunky::when($this->input)->getOption('open')->thenReturn(true);
        $exitCode = $this->executeMethod->invoke(
            $this->command,
            $this->input,
            $this->output
        );
        $expectedStdout = <<<'EOD'
<info>Using PHP:</info> /path/to/php
<info>Using PHPUnit:</info> /path/to/phpunit
out
out

<info>Opening coverage report... </info>done.

EOD;
        $expectedStderr = <<<'EOD'
err
err

EOD;

        $this->assertSame(0, $exitCode);
        $this->assertSame($expectedStdout, $this->stdOut);
        $this->assertSame($expectedStderr, $this->stdErr);
        Phunky::inOrder(
            Phunky::verify($this->phpFinder)->find(),
            Phunky::verify($this->phpunitFinder)->find(),
            Phunky::verify($this->phpConfigurationReader)
                ->read(Phunky::capture($actualPhpConfigurationPaths)),
            Phunky::verify($this->configurationFileFinder)->find(
                Phunky::capture($actualPhpunitConfigurationPaths),
                './vendor/icecave/archer/res/phpunit/phpunit.coverage.xml'
            ),
            Phunky::verify($this->processFactory)
                ->createFromArray(Phunky::capture($actualArguments)),
            Phunky::verify($this->process)->setTimeout(null),
            Phunky::verify($this->command)->passthru(
                $this->identicalTo($this->process),
                $this->identicalTo($this->output)
            ),
            Phunky::verify($this->launcher)->launch('./artifacts/tests/coverage/index.html')
        );
        $this->assertSame(array(
            './vendor/icecave/archer/res/php/php.ini',
            './vendor/icecave/archer/res/php/php.coverage.ini',
            './test/php.ini',
            './test/php.coverage.ini',
            './php.ini',
            './php.coverage.ini',
        ), $actualPhpConfigurationPaths);
        $this->assertSame(array(
            './phpunit.coverage.xml',
            './test/phpunit.coverage.xml',
        ), $actualPhpunitConfigurationPaths);
        $this->assertSame(array(
            '/path/to/php',
            '--define',
            'baz=qux',
            '--define',
            'doom=splat',
            '/path/to/phpunit',
            '--configuration',
            '/path/to/phpunit.xml',
            'bar',
        ), $actualArguments);
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
            '--open',
            '-o',
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

        $result = $method->invoke($this->command, '/path/to/php', '/path/to/phpunit', $input);

        $this->assertSame($expected, $result);
    }
}
