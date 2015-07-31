<?php

namespace Icecave\Archer\Process;

use Icecave\Archer\FileSystem\FileSystem;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class PHPUnitExecutableFinder
{
    /**
     * @param FileSystem|null       $fileSystem
     * @param ExecutableFinder|null $executableFinder
     * @param ProcessFactory|null   $processFactory
     */
    public function __construct(
        FileSystem $fileSystem = null,
        ExecutableFinder $executableFinder = null,
        ProcessFactory $processFactory = null
    ) {
        if (null === $fileSystem) {
            $fileSystem = new FileSystem();
        }
        if (null === $executableFinder) {
            $executableFinder = new ExecutableFinder();
        }
        if (null === $processFactory) {
            $processFactory = new ProcessFactory();
        }

        $this->fileSystem = $fileSystem;
        $this->executableFinder = $executableFinder;
        $this->processFactory = $processFactory;
    }

    /**
     * @return FileSystem
     */
    public function fileSystem()
    {
        return $this->fileSystem;
    }

    /**
     * @return ExecutableFinder
     */
    public function executableFinder()
    {
        return $this->executableFinder;
    }

    /**
     * @return ProcessFactory
     */
    public function processFactory()
    {
        return $this->processFactory;
    }

    /**
     * @return string
     */
    public function find()
    {
        if ($this->fileSystem()->fileExists('vendor/bin/phpunit')) {
            return 'vendor/bin/phpunit';
        }

        if ($this->environmentIsTravis()) {
            return $this->findForTravis();
        }

        return $this->findForGeneric();
    }

    /**
     * @return string
     */
    protected function findForGeneric()
    {
        $phpunit = $this->executableFinder()->find('phpunit');

        if (null === $phpunit) {
            throw new RuntimeException('Unable to find PHPUnit executable.');
        }

        return $phpunit;
    }

    /**
     * @return string
     */
    protected function findForTravis()
    {
        $process = $this->processFactory()->create('rbenv', 'which', 'phpunit');
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                sprintf(
                    'Unable to find PHPUnit executable: %s',
                    $process->getErrorOutput()
                )
            );
        }

        return trim($process->getOutput());
    }

    /**
     * @return boolean
     */
    protected function environmentIsTravis()
    {
        if (array_key_exists('TRAVIS', $_SERVER)) {
            return 'true' === $_SERVER['TRAVIS'];
        }

        return false;
    }

    private $fileSystem;
    private $executableFinder;
    private $processFactory;
}
