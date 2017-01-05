<?php

namespace DeltaCli\Command;

use DeltaCli\Command;
use DeltaCli\Debug;
use DeltaCli\Environment;
use DeltaCli\Project;
use DeltaCli\Script;
use DeltaCli\Script\Step\FindDatabases;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseShell extends Command
{
    /**
     * @var Project
     */
    private $project;

    public function __construct(Project $project)
    {
        parent::__construct(null);

        $this->project = $project;
    }

    protected function configure()
    {
        $this
            ->setName('db:shell')
            ->setDescription('Open a database command-line shell.')
            ->addArgument('environment', InputArgument::REQUIRED, 'The environment where you want to open a shell.')
            ->addOption('hostname', null, InputOption::VALUE_REQUIRED, 'The specific hostname you want to connect to.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $env = $this->project->getSelectedEnvironment();

        if ($input->getOption('hostname')) {
            $host = $env->getSelectedHost($input->getOption('hostname'));
        } else {
            $hosts = $env->getHosts();
            $host  = reset($hosts);
        }

        $findDatabasesStep = $this->project->findDatabases()
            ->setSelectedEnvironment($env);

        $script = $this->generateScript($env, $findDatabasesStep);
        $script->run(new ArrayInput([]), $output);

        $databases = $findDatabasesStep->getDatabases();
        $database  = reset($databases);

        $tunnel = $host->getSshTunnel();
        $port   = $tunnel->setUp();

        if (false === $port) {
            $databaseCommand = $database->getShellCommand();
        } else {
            $databaseCommand = $database->getShellCommand($tunnel->getHostname(), $port);
        }

        $database->renderShellHelp($output);

        $command = $tunnel->assembleSshCommand($databaseCommand, '-t');
        Debug::log("Opening DB shell with `{$command}`...");
        passthru($command);

        $tunnel->tearDown();
    }

    private function generateScript(Environment $env, FindDatabases $findDatabasesStep)
    {
        $script = new Script(
            $this->project,
            'open-db-shell',
            'Script that runs prior to opening DB shell and sends notifications.'
        );

        $script
            ->setEnvironment($env)
            ->addStep($this->project->logAndSendNotifications())
            ->addStep($findDatabasesStep)
            ->addStep(
                'validate-databases',
                function () use ($findDatabasesStep) {
                    $databases = $findDatabasesStep->getDatabases();

                    if (0 === count($databases)) {
                        echo 'No databases found.' . PHP_EOL;
                        exit;
                    } else if (1 < count($databases)) {
                        echo 'More than one database found and no mechanism to select which one yet.' . PHP_EOL;
                        exit;
                    }
                }
            )
            ->addStep(
                'open-db-shell',
                function () use ($findDatabasesStep, $env) {
                    $databases = $findDatabasesStep->getDatabases();
                    $database  = reset($databases);
                    echo "Opening database shell for '{$database->getDatabaseName()}' on {$env->getName()}." . PHP_EOL;
                }
            );

        return $script;
    }
}
