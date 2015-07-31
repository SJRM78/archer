<?php

namespace Icecave\Archer\Console\Command;

use PHPUnit_Framework_TestCase;
use Phunky;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;

class DocumentationCommandTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->generator = Phunky::mock(
            'Icecave\Archer\Documentation\DocumentationGenerator'
        );
        $this->launcher = Phunky::mock(
            'Icecave\Archer\Support\Liftoff\Launcher'
        );
        $this->command = new DocumentationCommand(
            $this->generator,
            $this->launcher
        );

        $this->output = Phunky::mock(
            'Symfony\Component\Console\Output\OutputInterface'
        );
    }

    public function testConstructor()
    {
        $this->assertSame($this->generator, $this->command->generator());
        $this->assertSame($this->launcher, $this->command->launcher());
    }

    public function testConstructorDefaults()
    {
        $this->command = new DocumentationCommand();

        $this->assertInstanceOf(
            'Icecave\Archer\Documentation\DocumentationGenerator',
            $this->command->generator()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Support\Liftoff\Launcher',
            $this->command->launcher()
        );
    }

    public function testConfigure()
    {
        $expectedDefinition = new InputDefinition();
        $expectedDefinition->addOption(
            new InputOption(
                'open',
                'o',
                InputOption::VALUE_NONE,
                'Open the generated documentation in your default web browser.'
            )
        );

        $this->assertSame('documentation', $this->command->getName());
        $this->assertSame(
            'Generate documentation for a project.',
            $this->command->getDescription()
        );
        $this->assertEquals($expectedDefinition, $this->command->getDefinition());
    }

    public function testExecute()
    {
        $this->input = new StringInput('');
        $this->command->run($this->input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->output)->write(
                '<info>Generating documentation... </info>'
            ),
            Phunky::verify($this->generator)->generate(),
            Phunky::verify($this->output)->writeln('done.')
        );
        Phunky::verify($this->launcher, Phunky::never())->launch(Phunky::anyParameters());
    }

    public function testExecuteWithOpen()
    {
        $this->input = new StringInput('--open');
        $this->command->run($this->input, $this->output);

        $doneVerification = Phunky::verify($this->output, Phunky::times(2))->writeln('done.');
        Phunky::inOrder(
            Phunky::verify($this->output)->write(
                '<info>Generating documentation... </info>'
            ),
            Phunky::verify($this->generator)->generate(),
            $doneVerification,
            Phunky::verify($this->output)->write(
                '<info>Opening documentation... </info>'
            ),
            Phunky::verify($this->launcher)->launch('./artifacts/documentation/api/index.html'),
            $doneVerification
        );
    }
}
