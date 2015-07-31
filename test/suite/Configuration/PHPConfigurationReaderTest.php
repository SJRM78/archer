<?php
namespace Icecave\Archer\Configuration;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\Support\Isolator;
use PHPUnit_Framework_TestCase;

class PHPConfigurationReaderTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->isolator = x\mock(Isolator::className());
        $this->subject = new PHPConfigurationReader($this->fileSystem->mock(), $this->isolator->mock());
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem->mock(), $this->subject->fileSystem());
    }

    public function testConstructorDefaults()
    {
        $this->subject = new PHPConfigurationReader();

        $this->assertEquals(new FileSystem(), $this->subject->fileSystem());
    }

    public function testReadSingle()
    {
        $this->fileSystem->fileExists->returns(true, false);
        $this->isolator->parse_ini_file->returns(array('foo' => 'bar'));

        $this->assertSame(array('foo' => 'bar'), $this->subject->read(array('doom', 'splat')));
        $this->isolator->parse_ini_file->never()->calledWith('splat');
    }

    public function testReadMultiple()
    {
        $this->fileSystem->fileExists->returns(true);
        $this->isolator->parse_ini_file->returns(array('foo' => 'bar'), array('baz' => 'qux'));

        $this->assertSame(array('foo' => 'bar', 'baz' => 'qux'), $this->subject->read(array('doom', 'splat')));
    }

    public function testReadNone()
    {
        $this->fileSystem->fileExists->returns(false);

        $this->assertSame(array(), $this->subject->read(array('doom', 'splat')));
        $this->isolator->parse_ini_file->never()->called();
    }
}
