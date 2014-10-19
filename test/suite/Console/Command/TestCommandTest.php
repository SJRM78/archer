<?php
namespace Icecave\Archer\Console\Command;

use Phunky;
use PHPUnit_Framework_TestCase;
use ReflectionObject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @covers \Icecave\Archer\Console\Command\AbstractPHPUnitCommand
 * @covers \Icecave\Archer\Console\Command\TestCommand
 */
class TestCommandTest extends PHPUnit_Framework_TestCase
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
            __NAMESPACE__ . '\TestCommand',
            $this->fileSystem,
            $this->phpFinder,
            $this->phpunitFinder,
            $this->phpConfigurationReader,
            $this->configurationFileFinder,
            $this->processFactory
        );

        $this->application = Phunky::mock('Icecave\Archer\Console\Application');
        $this->process = Phunky::mock('Symfony\Component\Process\Process');

        Phunky::when($this->command)
            ->getApplication(Phunky::anyParameters())
            ->thenReturn($this->application)
        ;

        Phunky::when($this->application)
            ->rawArguments(Phunky::anyParameters())
            ->thenReturn(array('foo', 'bar'))
        ;

        Phunky::when($this->phpConfigurationReader)
            ->read(Phunky::anyParameters())
            ->thenReturn(array(
                'baz' => 'qux',
                'doom' => 'splat',
            ))
        ;

        Phunky::when($this->configurationFileFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/phpunit.xml')
        ;

        Phunky::when($this->processFactory)
            ->createFromArray(Phunky::anyParameters())
            ->thenReturn($this->process)
        ;

        Phunky::when($this->phpFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/php')
        ;

        Phunky::when($this->phpunitFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('/path/to/phpunit')
        ;

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
            )
        ;

        $this->stdOut = '';
        $this->output = Phunky::mock('Symfony\Component\Console\Output\ConsoleOutputInterface');
        Phunky::when($this->output)
            ->write(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($data) use ($that) {
                    $that->stdOut .= $data;
                }
            )
        ;
        Phunky::when($this->output)
            ->writeln(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($data) use ($that) {
                    $that->stdOut .= $data . "\n";
                }
            )
        ;
        Phunky::when($this->output)
            ->getErrorOutput(Phunky::anyParameters())
            ->thenReturn($this->errorOutput)
        ;

        Phunky::when($this->process)
            ->run(Phunky::anyParameters())
            ->thenGetReturnByLambda(
                function ($callback) {
                    $callback('out', "out\nout\n");
                    $callback('err', "err\nerr\n");

                    return 111;
                }
            )
        ;
    }

    public function testConfigure()
    {
        $expectedInputDefinition = new InputDefinition();
        $expectedInputDefinition->addArgument(new InputArgument(
            'argument',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Argument(s) to pass to PHPUnit.'
        ));

        $this->assertSame('test', $this->command->getName());
        $this->assertSame(
            'Run the test suite for a project.',
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
                './vendor/icecave/archer/res/phpunit/phpunit.xml'
            ),
            Phunky::verify($this->processFactory)
                ->createFromArray(Phunky::capture($actualArguments)),
            Phunky::verify($this->process)->setTimeout(null),
            Phunky::verify($this->command)->passthru(
                $this->identicalTo($this->process),
                $this->identicalTo($this->output)
            )
        );
        $this->assertSame(array(
            './vendor/icecave/archer/res/php/php.ini',
            './test/php.ini',
            './php.ini',
        ), $actualPhpConfigurationPaths);
        $this->assertSame(array(
            './phpunit.xml',
            './phpunit.xml.dist',
            './test/phpunit.xml',
            './test/phpunit.xml.dist',
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
}
