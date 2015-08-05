<?php

namespace Icecave\Archer\Documentation;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\Process\ProcessFactory;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Process\ExecutableFinder;

class DocumentationGeneratorTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->executableFinder = x\mock('Symfony\Component\Process\ExecutableFinder');
        $this->processFactory = x\mock('Icecave\Archer\Process\ProcessFactory');
        $this->subject = new DocumentationGenerator(
            $this->fileSystem->mock(),
            $this->executableFinder->mock(),
            $this->processFactory->mock()
        );

        $this->process = x\mock('Symfony\Component\Process\Process');
        $this->process->isSuccessful->returns(true);
        $this->processFactory->create->returns($this->process->mock());

        $this->fileSystem->directoryExists('./artifacts/documentation/api')->returns(true);
        $this->fileSystem->directoryExists('./artifacts/documentation/api-cache')->returns(true);
    }

    public function testConstructorDefaults()
    {
        $this->subject = new DocumentationGenerator();

        $this->assertEquals(new FileSystem(), $this->subject->fileSystem());
        $this->assertEquals(new ExecutableFinder(), $this->subject->executableFinder());
        $this->assertEquals(new ProcessFactory(), $this->subject->processFactory());
    }

    public function testGenerateWithDevDependency()
    {
        $this->fileSystem->fileExists('./vendor/bin/sami.php')->returns(true);
        $this->subject->generate();

        x\inOrder(
            $this->fileSystem->delete->calledWith('./artifacts/documentation/api'),
            $this->processFactory->create
                ->calledWith('./vendor/bin/sami.php', 'update', './vendor/icecave/archer/res/sami/sami.php'),
            $this->process->run->called(),
            $this->fileSystem->delete->calledWith('./artifacts/documentation/api-cache')
        );
    }

    public function testGenerateWithGlobal()
    {
        $this->executableFinder->find('sami')->returns('/path/to/sami');
        $this->subject->generate();

        x\inOrder(
            $this->fileSystem->delete->calledWith('./artifacts/documentation/api'),
            $this->processFactory->create
                ->calledWith('/path/to/sami', 'update', './vendor/icecave/archer/res/sami/sami.php'),
            $this->process->run->called(),
            $this->fileSystem->delete->calledWith('./artifacts/documentation/api-cache')
        );
    }

    public function testGenerateWithCustomConfig()
    {
        $this->executableFinder->find('sami')->returns('/path/to/sami');
        $this->fileSystem->fileExists('./.sami')->returns(true);
        $this->subject->generate();

        x\inOrder(
            $this->fileSystem->delete->calledWith('./artifacts/documentation/api'),
            $this->processFactory->create->calledWith('/path/to/sami', 'update', './.sami'),
            $this->process->run->called(),
            $this->fileSystem->delete->calledWith('./artifacts/documentation/api-cache')
        );
    }

    public function testGenerateFailureNotFound()
    {
        $this->setExpectedException('RuntimeException', 'Unable to find Sami executable.');
        $this->subject->generate();
    }

    public function testGenerateFailureNotSuccessful()
    {
        $this->fileSystem->fileExists('./vendor/bin/sami.php')->returns(true);
        $this->process->isSuccessful->returns(false);
        $this->process->getErrorOutput->returns('Error message.');

        $this->setExpectedException('RuntimeException', 'Unable to generate documentation: Error message.');
        $this->subject->generate();
    }
}
