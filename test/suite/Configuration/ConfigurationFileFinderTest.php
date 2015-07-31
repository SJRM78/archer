<?php
namespace Icecave\Archer\Configuration;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\FileSystem\FileSystem;
use PHPUnit_Framework_TestCase;

class ConfigurationFileFinderTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->subject = new ConfigurationFileFinder($this->fileSystem->mock());
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem->mock(), $this->subject->fileSystem());
    }

    public function testConstructorDefaults()
    {
        $this->subject = new ConfigurationFileFinder();

        $this->assertEquals(new FileSystem(), $this->subject->fileSystem());
    }

    public function testFindFirst()
    {
        $this->fileSystem->fileExists->returns(true);

        $this->assertSame('foo', $this->subject->find(array('foo', 'bar'), 'baz'));
    }

    public function testFindLast()
    {
        $this->fileSystem->fileExists->returns(false, true);

        $this->assertSame('bar', $this->subject->find(array('foo', 'bar'), 'baz'));
    }

    public function testFindDefault()
    {
        $this->fileSystem->fileExists->returns(false);

        $this->assertSame('baz', $this->subject->find(array('foo', 'bar'), 'baz'));
    }
}
