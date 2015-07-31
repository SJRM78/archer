<?php

namespace Icecave\Archer\FileSystem;

use PHPUnit_Framework_TestCase;
use Phunky;

class FileSystemTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->isolator = Phunky::mock('Icecave\Archer\Support\Isolator');
        $this->fileSystem = new FileSystem($this->isolator);
    }

    public function testExists()
    {
        Phunky::when($this->isolator)
            ->file_exists(Phunky::anyParameters())
            ->thenReturn(true)
            ->thenReturn(false);

        $this->assertTrue($this->fileSystem->exists('foo'));
        $this->assertFalse($this->fileSystem->exists('bar'));
        Phunky::inOrder(
            Phunky::verify($this->isolator)->file_exists('foo'),
            Phunky::verify($this->isolator)->file_exists('bar')
        );
    }

    public function testExistsFailure()
    {
        Phunky::when($this->isolator)
            ->file_exists(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->exists('foo');
    }

    public function testFileExists()
    {
        Phunky::when($this->isolator)
            ->is_file(Phunky::anyParameters())
            ->thenReturn(true)
            ->thenReturn(false);

        $this->assertTrue($this->fileSystem->fileExists('foo'));
        $this->assertFalse($this->fileSystem->fileExists('bar'));
        Phunky::inOrder(
            Phunky::verify($this->isolator)->is_file('foo'),
            Phunky::verify($this->isolator)->is_file('bar')
        );
    }

    public function testFileExistsFailure()
    {
        Phunky::when($this->isolator)
            ->is_file(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->fileExists('foo');
    }

    public function testDirectoryExists()
    {
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true)
            ->thenReturn(false);

        $this->assertTrue($this->fileSystem->directoryExists('foo'));
        $this->assertFalse($this->fileSystem->directoryExists('bar'));
        Phunky::inOrder(
            Phunky::verify($this->isolator)->is_dir('foo'),
            Phunky::verify($this->isolator)->is_dir('bar')
        );
    }

    public function testDirectoryExistsFailure()
    {
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->directoryExists('foo');
    }

    public function testRead()
    {
        Phunky::when($this->isolator)
            ->file_get_contents(Phunky::anyParameters())
            ->thenReturn('bar');

        $this->assertSame('bar', $this->fileSystem->read('foo'));
        Phunky::verify($this->isolator)->file_get_contents('foo');
    }

    public function testReadFailure()
    {
        Phunky::when($this->isolator)
            ->file_get_contents(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->read('foo');
    }

    public function testListPaths()
    {
        Phunky::when($this->isolator)
            ->scandir(Phunky::anyParameters())
            ->thenReturn(array('.', '..', 'bar', 'baz'));

        $this->assertSame(array('bar', 'baz'), $this->fileSystem->listPaths('foo'));
        Phunky::verify($this->isolator)->scandir('foo');
    }

    public function testListPathsFailure()
    {
        Phunky::when($this->isolator)
            ->scandir(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->listPaths('foo');
    }

    public function testWrite()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('foo');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        $this->fileSystem->write('foo/bar', 'baz');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->dirname('foo/bar'),
            Phunky::verify($this->isolator)->is_dir('foo'),
            Phunky::verify($this->isolator)->file_put_contents('foo/bar', 'baz')
        );
        Phunky::verify($this->isolator, Phunky::never())->mkdir(Phunky::anyParameters());
    }

    public function testWriteCreateParentDirectory()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('foo');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(false);
        $this->fileSystem->write('foo/bar', 'baz');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->dirname('foo/bar'),
            Phunky::verify($this->isolator)->is_dir('foo'),
            Phunky::verify($this->isolator)->mkdir('foo', 0777, true),
            Phunky::verify($this->isolator)->file_put_contents('foo/bar', 'baz')
        );
    }

    public function testWriteFailure()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('foo');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        Phunky::when($this->isolator)
            ->file_put_contents(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->write('foo/bar', 'baz');
    }

    public function testWriteFailureDirname()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->write('foo/bar', 'baz');
    }

    public function testCopy()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('bar');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        $this->fileSystem->copy('foo', 'bar/baz');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->dirname('bar/baz'),
            Phunky::verify($this->isolator)->is_dir('bar'),
            Phunky::verify($this->isolator)->copy('foo', 'bar/baz')
        );
        Phunky::verify($this->isolator, Phunky::never())->mkdir(Phunky::anyParameters());
    }

    public function testCopyCreateParentDirectory()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('bar');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(false);
        $this->fileSystem->copy('foo', 'bar/baz');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->dirname('bar/baz'),
            Phunky::verify($this->isolator)->is_dir('bar'),
            Phunky::verify($this->isolator)->mkdir('bar', 0777, true),
            Phunky::verify($this->isolator)->copy('foo', 'bar/baz')
        );
    }

    public function testCopyFailure()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('foo');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        Phunky::when($this->isolator)
            ->copy(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->copy('foo', 'bar/baz');
    }

    public function testCopyFailureDirname()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->copy('foo', 'bar/baz');
    }

    public function testMove()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('bar');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        $this->fileSystem->move('foo', 'bar/baz');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->dirname('bar/baz'),
            Phunky::verify($this->isolator)->is_dir('bar'),
            Phunky::verify($this->isolator)->rename('foo', 'bar/baz')
        );
        Phunky::verify($this->isolator, Phunky::never())->mkdir(Phunky::anyParameters());
    }

    public function testMoveCreateParentDirectory()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('bar');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(false);
        $this->fileSystem->move('foo', 'bar/baz');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->dirname('bar/baz'),
            Phunky::verify($this->isolator)->is_dir('bar'),
            Phunky::verify($this->isolator)->mkdir('bar', 0777, true),
            Phunky::verify($this->isolator)->rename('foo', 'bar/baz')
        );
    }

    public function testMoveFailure()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenReturn('foo');
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        Phunky::when($this->isolator)
            ->rename(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->move('foo', 'bar/baz');
    }

    public function testMoveFailureDirname()
    {
        Phunky::when($this->isolator)
            ->dirname(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\ReadException'
        );
        $this->fileSystem->move('foo', 'bar/baz');
    }

    public function testCreateDirectory()
    {
        $this->fileSystem->createDirectory('foo');

        Phunky::verify($this->isolator)->mkdir('foo', 0777, true);
    }

    public function testCreateDirectoryFailure()
    {
        Phunky::when($this->isolator)
            ->mkdir(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->createDirectory('foo');
    }

    public function testChmod()
    {
        $this->fileSystem->chmod('foo', 0755);

        Phunky::verify($this->isolator)->chmod('foo', 0755);
    }

    public function testChmodFailure()
    {
        Phunky::when($this->isolator)
            ->chmod(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->chmod('foo', 0755);
    }

    public function testDeleteFile()
    {
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(false);
        $this->fileSystem->delete('foo');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->is_dir('foo'),
            Phunky::verify($this->isolator)->unlink('foo')
        );
        Phunky::verify($this->isolator, Phunky::never())->rmdir(Phunky::anyParameters());
    }

    public function testDeleteFileFailure()
    {
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(false);
        Phunky::when($this->isolator)
            ->unlink(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->delete('foo');
    }

    public function testDeleteDirectory()
    {
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true)
            ->thenReturn(false);
        Phunky::when($this->isolator)
            ->scandir(Phunky::anyParameters())
            ->thenReturn(array('.', '..', 'bar', 'baz'));
        $this->fileSystem->delete('foo');

        Phunky::inOrder(
            Phunky::verify($this->isolator)->is_dir('foo'),
            Phunky::verify($this->isolator)->scandir('foo'),
            Phunky::verify($this->isolator)->is_dir('foo/bar'),
            Phunky::verify($this->isolator)->unlink('foo/bar'),
            Phunky::verify($this->isolator)->is_dir('foo/baz'),
            Phunky::verify($this->isolator)->unlink('foo/baz'),
            Phunky::verify($this->isolator)->rmdir('foo')
        );
        Phunky::verify($this->isolator, Phunky::never())->unlink('foo');
    }

    public function testDeleteDirectoryFailure()
    {
        Phunky::when($this->isolator)
            ->is_dir(Phunky::anyParameters())
            ->thenReturn(true);
        Phunky::when($this->isolator)
            ->scandir(Phunky::anyParameters())
            ->thenReturn(array('.', '..'));
        Phunky::when($this->isolator)
            ->rmdir(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->setExpectedException(
            __NAMESPACE__ . '\Exception\WriteException'
        );
        $this->fileSystem->delete('foo');
    }
}
