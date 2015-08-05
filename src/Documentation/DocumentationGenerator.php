<?php

namespace Icecave\Archer\Documentation;

use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\Process\ProcessFactory;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class DocumentationGenerator
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

    public function generate()
    {
        $buildPath = './artifacts/documentation/api';
        $cachePath = './artifacts/documentation/api-cache';

        if ($this->fileSystem->directoryExists($buildPath)) {
            $this->fileSystem->delete($buildPath);
        }

        $sami = './vendor/bin/sami.php';

        if (!$this->fileSystem->fileExists($sami)) {
            $sami = $this->executableFinder->find('sami');

            if (null === $sami) {
                throw new RuntimeException('Unable to find Sami executable.');
            }
        }

        $configPath = './.sami';

        if (!$this->fileSystem->fileExists($configPath)) {
            $configPath = './vendor/icecave/archer/res/sami/sami.php';
        }

        $process = $this->processFactory->create($sami, 'update', $configPath);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                sprintf(
                    'Unable to generate documentation: %s',
                    $process->getErrorOutput()
                )
            );
        }

        if ($this->fileSystem->directoryExists($cachePath)) {
            $this->fileSystem->delete($cachePath);
        }
    }

    private $fileSystem;
    private $executableFinder;
    private $processFactory;
}
