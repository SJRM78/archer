<?php
namespace Icecave\Archer\Git;

use Phunky;
use PHPUnit_Framework_TestCase;

class GitConfigReaderTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->process = Phunky::mock('Symfony\Component\Process\Process');
        $this->processFactory = Phunky::mock(
            'Icecave\Archer\Process\ProcessFactory'
        );
        $this->reader = new GitConfigReader(
            'foo',
            $this->processFactory
        );

        Phunky::when($this->processFactory)
            ->create(Phunky::anyParameters())
            ->thenReturn($this->process)
        ;
        Phunky::when($this->process)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(true)
        ;

        $configuration = <<<EOD
user.name=Test Ease
user.email=testease@gmail.com
color.ui=auto
github.user=testease
credential.helper=cache --timeout=21600
push.default=simple
core.repositoryformatversion=0
core.filemode=true
core.bare=false
core.logallrefupdates=true
core.ignorecase=true
core.precomposeunicode=false
remote.origin.url=git@github.com:IcecaveStudios/archer.git
remote.origin.fetch=+refs/heads/*:refs/remotes/origin/*
branch.master.remote=origin
branch.master.merge=refs/heads/master
branch.3.0.0.remote=origin
branch.3.0.0.merge=refs/heads/3.0.0

EOD;
        Phunky::when($this->process)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn($configuration)
        ;
    }

    public function testConstructor()
    {
        $this->assertSame('foo', $this->reader->repositoryPath());
        $this->assertSame($this->processFactory, $this->reader->processFactory());
    }

    public function testConstructorDefaults()
    {
        $this->reader = new GitConfigReader(
            'foo'
        );

        $this->assertInstanceOf(
            'Icecave\Archer\Process\ProcessFactory',
            $this->reader->processFactory()
        );
    }

    public function testIsGitHubRepository()
    {
        $this->assertTrue($this->reader->isGitHubRepository());
    }

    public function testIsGitHubRepositoryNonGitHubURL()
    {
        $configuration = <<<EOD
remote.origin.url=derp

EOD;
        Phunky::when($this->process)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn($configuration)
        ;

        $this->assertFalse($this->reader->isGitHubRepository());
    }

    public function testGet()
    {
        $this->assertSame('Test Ease', $this->reader->get('user.name'));
        $this->assertSame('testease@gmail.com', $this->reader->get('user.email'));
        $this->assertSame('ambiguous', $this->reader->get('user.gender', 'ambiguous'));
        $this->assertNull($this->reader->get('user.gender'));
    }

    public function testRepositoryOwner()
    {
        $this->assertSame('IcecaveStudios', $this->reader->repositoryOwner());
    }

    public function testRepositoryOwnerFailure()
    {
        $configuration = <<<EOD
remote.origin.url=derp

EOD;
        Phunky::when($this->process)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn($configuration)
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Origin URL "derp" is not a GitHub repository.'
        );
        $this->reader->repositoryOwner();
    }

    public function testRepositoryName()
    {
        $this->assertSame('archer', $this->reader->repositoryName());
    }

    public function testRepositoryNameFailure()
    {
        $configuration = <<<EOD
remote.origin.url=derp

EOD;
        Phunky::when($this->process)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn($configuration)
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Origin URL "derp" is not a GitHub repository.'
        );
        $this->reader->repositoryName();
    }

    public function testParseFailure()
    {
        Phunky::when($this->process)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(false)
        ;
        Phunky::when($this->process)
            ->getErrorOutput(Phunky::anyParameters())
            ->thenReturn('Bar.')
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Unable to read git configuration: Bar.'
        );
        $this->reader->get('user.name');
    }
}
