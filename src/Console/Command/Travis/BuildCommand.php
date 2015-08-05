<?php

namespace Icecave\Archer\Console\Command\Travis;

use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\GitHub\GitHubClient;
use Icecave\Archer\Support\Isolator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends AbstractTravisCommand
{
    public function __construct(
        GitHubClient $githubClient = null,
        FileSystem $fileSystem = null,
        Isolator $isolator = null
    ) {
        if (null === $githubClient) {
            $githubClient = new GitHubClient();
        }

        if (null === $fileSystem) {
            $fileSystem = new FileSystem();
        }

        $this->githubClient = $githubClient;
        $this->fileSystem = $fileSystem;

        parent::__construct($isolator);
    }

    /**
     * @return GitHubClient
     */
    public function githubClient()
    {
        return $this->githubClient;
    }

    /**
     * @param Application|null $application
     */
    public function setApplication(Application $application = null)
    {
        parent::setApplication($application);

        if ($application) {
            $this->githubClient->setUserAgent(
                $application->getName() . '/' . $application->getVersion()
            );
        }
    }

    protected function configure()
    {
        $this->setName('travis:build');
        $this->setDescription('Build and execute tests under Travis CI.');

        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'The path to the root of the project.',
            '.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $archerRoot       = $this->getApplication()->packageRoot();
        $packageRoot      = $input->getArgument('path');
        $travisPhpVersion = $this->isolator->getenv('TRAVIS_PHP_VERSION');
        $publishVersion   = $this->isolator->getenv('ARCHER_PUBLISH_VERSION');
        $currentBranch    = $this->isolator->getenv('TRAVIS_BRANCH');
        $authToken        = $this->isolator->getenv('ARCHER_TOKEN');
        $buildNumber      = $this->isolator->getenv('TRAVIS_BUILD_NUMBER');
        $repoSlug         = $this->isolator->getenv('TRAVIS_REPO_SLUG');
        $isPullRequest    = is_numeric($this->isolator->getenv('TRAVIS_PULL_REQUEST'));

        list($repoOwner, $repoName) = explode('/', $repoSlug);

        $isPublishVersion = $travisPhpVersion === $publishVersion;

        if ($authToken && $isPublishVersion && !$isPullRequest) {
            $this->githubClient()->setAuthToken($authToken);
            $publishArtifacts = $this->githubClient()->defaultBranch($repoOwner, $repoName) === $currentBranch;
        } else {
            $publishArtifacts = false;
        }

        if ($publishArtifacts) {
            // Run tests with reports
            $testsExitCode = 255;
            $this->isolator->passthru($archerRoot . '/bin/archer coverage', $testsExitCode);
        } else {
            // Run default tests
            $testsExitCode = 255;
            $this->isolator->passthru($archerRoot . '/bin/archer test', $testsExitCode);
        }

        $codecovExitCode = 0;

        if ($publishArtifacts) {
            $output->write('Publishing Codecov data... ');

            $codecovExitCode = 255;
            $this->isolator->passthru(
                sprintf(
                    'curl -s https://codecov.io/bash > .codecov.tmp && bash .codecov.tmp -f %s && rm -f .codecov.tmp',
                    escapeshellarg($packageRoot . '/artifacts/tests/coverage/coverage.xml')
                ),
                $codecovExitCode
            );

            if (0 === $codecovExitCode) {
                $output->writeln('done.');
            } else {
                $output->writeln('failed.');
            }
        }

        $documentationExitCode = 0;
        $publishExitCode = 0;
        if ($publishArtifacts) {
            // Generate documentation
            if ($this->isolator->is_dir($packageRoot . '/src')) {
                $documentationExitCode = 255;
                $this->isolator->passthru($archerRoot . '/bin/archer documentation', $documentationExitCode);
            }

            // Publish artifacts
            $command  = $archerRoot . '/bin/woodhouse';
            $command .= ' publish %s';
            $command .= ' %s/artifacts:artifacts';
            $command .= ' --message "Publishing artifacts from build %d."';
            $command .= ' --auth-token-env ARCHER_TOKEN';
            $command .= ' --no-interaction';
            $command .= ' --verbose';
            $command .= ' --coverage-image artifacts/images/coverage.png';
            $command .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
            $command .= ' --image-theme buckler/buckler';

            $command = sprintf(
                $command,
                escapeshellarg($repoSlug),
                $packageRoot,
                $buildNumber
            );

            $publishExitCode = 255;
            $this->isolator->passthru($command, $publishExitCode);
        }

        if ($testsExitCode !== 0) {
            return $testsExitCode;
        }
        if ($codecovExitCode !== 0) {
            return $codecovExitCode;
        }
        if ($documentationExitCode !== 0) {
            return $documentationExitCode;
        }

        return $publishExitCode;
    }

    private $githubClient;
    private $fileSystem;
}
