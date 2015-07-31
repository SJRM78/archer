<?php
namespace Icecave\Archer\Travis;

use Phunky;
use PHPUnit_Framework_TestCase;

class TravisClientTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->isolator = Phunky::mock('Icecave\Archer\Support\Isolator');
        $this->fileSystem = Phunky::mock('Icecave\Archer\FileSystem\FileSystem');
        $this->client = new TravisClient(
            $this->fileSystem,
            $this->isolator
        );
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem, $this->client->fileSystem());
    }

    public function testConstructorDefaults()
    {
        $this->client = new TravisClient();

        $this->assertInstanceOf(
            'Icecave\Archer\FileSystem\FileSystem',
            $this->client->fileSystem()
        );
    }

    public function testPublicKey()
    {
        Phunky::when($this->fileSystem)
            ->read(Phunky::anyParameters())
            ->thenReturn('{"key": "foo"}')
        ;

        $this->assertSame('foo', $this->client->publicKey('bar', 'baz'));
        Phunky::verify($this->fileSystem)
            ->read('https://api.travis-ci.org/repos/bar/baz/key')
        ;
    }

    public function testEncryptEnvironment()
    {
        Phunky::when($this->isolator)
            ->openssl_public_encrypt(
                'ARCHER_TOKEN="bar"',
                Phunky::setReference('baz'),
                'PUBLIC KEY foo'
            )
            ->thenReturn(true)
        ;
        $actual = $this->client->encryptEnvironment('RSA PUBLIC KEY foo', 'bar');
        $expected = base64_encode('baz');

        $this->assertSame($expected, $actual);
        Phunky::verify($this->isolator)->openssl_public_encrypt(
            'ARCHER_TOKEN="bar"',
            null,
            'PUBLIC KEY foo'
        );
    }

    public function testEncrypt()
    {
        Phunky::when($this->isolator)
            ->openssl_public_encrypt(
                'bar',
                Phunky::setReference('baz'),
                'PUBLIC KEY foo'
            )
            ->thenReturn(true)
        ;
        $actual = $this->client->encrypt('RSA PUBLIC KEY foo', 'bar');
        $expected = base64_encode('baz');

        $this->assertSame($expected, $actual);
        Phunky::verify($this->isolator)->openssl_public_encrypt(
            'bar',
            null,
            'PUBLIC KEY foo'
        );
    }

    public function testEncryptFailure()
    {
        Phunky::when($this->isolator)
            ->openssl_public_encrypt(Phunky::anyParameters())
            ->thenReturn(false)
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Encryption failed.'
        );
        $this->client->encrypt('RSA PUBLIC KEY foo', 'bar');
    }
}
