<?php

namespace Icecave\Archer\Console\Command;

use Icecave\Archer\Configuration\ConfigurationFileFinder;
use Icecave\Archer\Configuration\PHPConfigurationReader;
use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\Process\PHPUnitExecutableFinder;
use Icecave\Archer\Process\ProcessFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

abstract class AbstractPHPUnitCommand extends Command
{
    /**
     * @param FileSystem|null              $fileSystem
     * @param PhpExecutableFinder|null     $phpFinder
     * @param PHPUnitExecutableFinder|null $phpunitFinder
     * @param PHPConfigurationReader|null  $phpConfigurationReader
     * @param ConfigurationFileFinder|null $configurationFileFinder
     * @param ProcessFactory|null          $processFactory
     * @param string|null                  $commandName
     */
    public function __construct(
        FileSystem $fileSystem = null,
        PhpExecutableFinder $phpFinder = null,
        PHPUnitExecutableFinder $phpunitFinder = null,
        PHPConfigurationReader $phpConfigurationReader = null,
        ConfigurationFileFinder $configurationFileFinder = null,
        ProcessFactory $processFactory = null,
        $commandName = null
    ) {
        if (null === $fileSystem) {
            $fileSystem = new FileSystem();
        }
        if (null === $phpFinder) {
            $phpFinder = new PhpExecutableFinder();
        }
        if (null === $phpunitFinder) {
            $phpunitFinder = new PHPUnitExecutableFinder();
        }
        if (null === $phpConfigurationReader) {
            $phpConfigurationReader = new PHPConfigurationReader();
        }
        if (null === $configurationFileFinder) {
            $configurationFileFinder = new ConfigurationFileFinder();
        }
        if (null === $processFactory) {
            $processFactory = new ProcessFactory();
        }

        $this->fileSystem = $fileSystem;
        $this->phpFinder = $phpFinder;
        $this->phpunitFinder = $phpunitFinder;
        $this->phpConfigurationReader = $phpConfigurationReader;
        $this->configurationFileFinder = $configurationFileFinder;
        $this->processFactory = $processFactory;

        parent::__construct($commandName);

        $this->ignoreValidationErrors();
    }

    /**
     * @return FileSystem
     */
    public function fileSystem()
    {
        return $this->fileSystem;
    }

    /**
     * @return PhpExecutableFinder
     */
    public function phpFinder()
    {
        return $this->phpFinder;
    }

    /**
     * @return PHPUnitExecutableFinder
     */
    public function phpunitFinder()
    {
        return $this->phpunitFinder;
    }

    /**
     * @return PHPConfigurationReader
     */
    public function phpConfigurationReader()
    {
        return $this->phpConfigurationReader;
    }

    /**
     * @return ConfigurationFileFinder
     */
    public function configurationFileFinder()
    {
        return $this->configurationFileFinder;
    }

    /**
     * @return ProcessFactory
     */
    public function processFactory()
    {
        return $this->processFactory;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $phpBinaryArguments = $this->phpBinaryArguments();
        $phpBinaryString = implode(' ', $phpBinaryArguments);

        $output->writeln(sprintf('<info>Using PHP:</info> %s', $phpBinaryString));
        $phpunitPath = $this->phpunitFinder()->find();
        $output->writeln(sprintf('<info>Using PHPUnit:</info> %s', $phpunitPath));

        $process = $this->processFactory()->createFromArray(
            $this->generateArguments(
                $output,
                $phpBinaryArguments,
                $phpunitPath,
                $this->rawArguments()
            )
        );
        $process->setTimeout(null);

        return $this->passthru($process, $output);
    }

    public function getHelp()
    {
        $arguments = $this->phpBinaryArguments();
        $arguments[] = $this->phpunitFinder->find();
        $arguments[] = '--help';
        $process = $this->processFactory->createFromArray($arguments);

        $help  = '<info>This command forwards all arguments to PHPUnit.</info>';
        $help .= PHP_EOL;
        $help .= PHP_EOL;

        $process->run(
            function ($type, $buffer) use (&$help) {
                if ('out' === $type) {
                    $help .= $buffer;
                }
            }
        );

        return $help;
    }

    /**
     * @param Process                $process
     * @param ConsoleOutputInterface $output
     *
     * @return integer
     */
    protected function passthru(Process $process, ConsoleOutputInterface $output)
    {
        return $process->run(
            function ($type, $buffer) use ($output) {
                if ('out' === $type) {
                    $output->write(
                        $buffer,
                        false,
                        OutputInterface::OUTPUT_RAW
                    );
                } else {
                    $output->getErrorOutput()->write(
                        $buffer,
                        false,
                        OutputInterface::OUTPUT_RAW
                    );
                }
            }
        );
    }

    /**
     * @param OutputInterface $output
     * @param array<string>   $phpBinaryArguments
     * @param string          $phpunitPath
     * @param array<string>   $phpunitArguments
     *
     * @return array<string>
     */
    protected function generateArguments(
        OutputInterface $output,
        array $phpBinaryArguments,
        $phpunitPath,
        array $phpunitArguments
    ) {
        $phpunitArguments = array_filter(
            array_map(
                function ($element) {
                    switch ($element) {
                        case '--ansi':
                        case '--quiet':
                        case '-q':
                        case '--version':
                        case '-V':
                        case '--no-ansi':
                        case '--no-interaction':
                        case '-n':
                            return null;
                    }

                    return $element;
                },
                $phpunitArguments
            )
        );

        if ($output->isDecorated()) {
            array_unshift($phpunitArguments, '--colors=always');
        } else {
            array_unshift($phpunitArguments, '--colors=never');
        }

        array_unshift(
            $phpunitArguments,
            $phpunitPath,
            '--configuration',
            $this->findPHPUnitConfiguration()
        );

        return array_merge(
            $phpBinaryArguments,
            $this->phpConfigurationArguments($this->readPHPConfiguration()),
            $phpunitArguments
        );
    }

    protected function phpBinaryArguments()
    {
        $php = $this->phpFinder->find(false);

        if (false !== strpos($php, 'phpdbg')) {
            return array($php, '-qrr');
        }

        return array($php);
    }

    /**
     * @param array<string,mixed> $configuration
     *
     * @return array<string>
     */
    protected function phpConfigurationArguments(array $configuration)
    {
        $arguments = array();
        foreach ($configuration as $key => $value) {
            $arguments[] = '-d';
            $arguments[] = sprintf('%s=%s', $key, $value);
        }

        return $arguments;
    }

    /**
     * @return array<string>
     */
    public function rawArguments()
    {
        $arguments = $this->getApplication()->rawArguments();
        array_shift($arguments);

        return $arguments;
    }

    /**
     * @return array<string,mixed>
     */
    abstract protected function readPHPConfiguration();

    /**
     * @return string
     */
    abstract protected function findPHPUnitConfiguration();

    private $fileSystem;
    private $phpFinder;
    private $phpunitFinder;
    private $phpConfigurationReader;
    private $configurationFileFinder;
    private $processFactory;
}
