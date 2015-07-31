<?php

namespace Icecave\Archer\Process;

use PHPUnit_Framework_TestCase;
use Phunky;
use RuntimeException;

class PHPUnitExecutableFinderTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Phunky::mock('Icecave\Archer\FileSystem\FileSystem');
        $this->executableFinder = Phunky::mock('Symfony\Component\Process\ExecutableFinder');
        $this->processFactory = Phunky::mock('Icecave\Archer\Process\ProcessFactory');
        $this->finder = new PHPUnitExecutableFinder(
            $this->fileSystem,
            $this->executableFinder,
            $this->processFactory
        );
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem, $this->finder->fileSystem());
        $this->assertSame($this->executableFinder, $this->finder->executableFinder());
        $this->assertSame($this->processFactory, $this->finder->processFactory());
    }

    public function testConstructorDefaults()
    {
        $this->finder = new PHPUnitExecutableFinder();

        $this->assertInstanceOf(
            'Symfony\Component\Process\ExecutableFinder',
            $this->finder->executableFinder()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Process\ProcessFactory',
            $this->finder->processFactory()
        );
    }

    public function testFindLocal()
    {
        $server = $_SERVER;
        unset($_SERVER['TRAVIS']);
        Phunky::when($this->fileSystem)->fileExists('vendor/bin/phpunit')->thenReturn(true);

        $this->assertSame('vendor/bin/phpunit', $this->finder->find());
    }

    public function testFindGeneric()
    {
        $server = $_SERVER;
        unset($_SERVER['TRAVIS']);
        Phunky::when($this->executableFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn('foo');
        $actual = $this->finder->find();
        $_SERVER = $server;

        $this->assertSame('foo', $actual);
        Phunky::verify($this->executableFinder)->find('phpunit');
    }

    public function testFindGenericFailure()
    {
        $server = $_SERVER;
        unset($_SERVER['TRAVIS']);
        Phunky::when($this->executableFinder)
            ->find(Phunky::anyParameters())
            ->thenReturn(null);
        $error = null;
        try {
            $this->finder->find();
        } catch (RuntimeException $error) {
        }
        $_SERVER = $server;

        $this->assertInstanceOf('RuntimeException', $error);
        $this->assertSame('Unable to find PHPUnit executable.', $error->getMessage());
    }

    public function testFindTravis()
    {
        $server = $_SERVER;
        $_SERVER['TRAVIS'] = 'true';
        $process = Phunky::mock('Symfony\Component\Process\Process');
        Phunky::when($this->processFactory)
            ->create(Phunky::anyParameters())
            ->thenReturn($process);
        Phunky::when($process)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(true);
        Phunky::when($process)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn('foo');
        $actual = $this->finder->find();
        $_SERVER = $server;

        $this->assertSame('foo', $actual);
        Phunky::inOrder(
            Phunky::verify($this->processFactory)->create('rbenv', 'which', 'phpunit'),
            Phunky::verify($process)->isSuccessful(),
            Phunky::verify($process)->getOutput()
        );
    }

    public function testFindTravisFailure()
    {
        $server = $_SERVER;
        $_SERVER['TRAVIS'] = 'true';
        $process = Phunky::mock('Symfony\Component\Process\Process');
        Phunky::when($this->processFactory)
            ->create(Phunky::anyParameters())
            ->thenReturn($process);
        Phunky::when($process)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(false);
        Phunky::when($process)
            ->getErrorOutput(Phunky::anyParameters())
            ->thenReturn('Foo.');
        $error = null;
        try {
            $this->finder->find();
        } catch (RuntimeException $error) {
        }
        $_SERVER = $server;

        $this->assertInstanceOf('RuntimeException', $error);
        $this->assertSame('Unable to find PHPUnit executable: Foo.', $error->getMessage());
    }
}
