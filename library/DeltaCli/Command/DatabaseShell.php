<?php

namespace DeltaCli\Command;

use DeltaCli\Command;
use DeltaCli\Debug;
use DeltaCli\Environment;
use DeltaCli\Project;
use DeltaCli\Script;
use DeltaCli\Script\Step\FindDatabases;
use DeltaCli\Script\Step\Result;
use DeltaCli\Script\Step\Scp;
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

    /**
     * @var FindDatabases
     */
    private $findDatabasesStep;

    public function __construct(Project $project)
    {
        $this->project = $project;

        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('db:shell')
            ->setDescription('Open a database command-line shell.')
            ->addArgument('environment', InputArgument::REQUIRED, 'The environment where you want to open a shell.')
            ->addOption('hostname', null, InputOption::VALUE_REQUIRED, 'The specific hostname you want to connect to.');

        $this->findDatabasesStep = $this->project->findDatabases()
            ->configure($this->getDefinition());
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

        $this->project->findDatabases()
            ->setSelectedEnvironment($env);

        $script = $this->generateScript($env, $this->findDatabasesStep, $input);
        $script->run(new ArrayInput([]), $output);

        $database = $this->findDatabasesStep->getSelectedDatabase($input);
        $tunnel   = $host->getSshTunnel();

        $tunnel->setUp();

        $database->renderShellHelp($output);

        $command = $tunnel->assembleSshCommand($database->getShellCommand(), '-t');
        Debug::log("Opening DB shell with `{$command}`...");
        deltacli_wrap_command($command);
        passthru($command);

        $tunnel->tearDown();
    }

    private function generateScript(Environment $env, FindDatabases $findDatabasesStep, InputInterface $input)
    {
        $script = new Script(
            $this->project,
            'open-db-shell',
            'Script that runs prior to opening DB shell and sends notifications.'
        );

        $script->setApplication($this->getApplication());

        $configureStep = new Script\Step\PhpCallable(
            function () use (&$configureStep, $findDatabasesStep, $env, $input) {
                $database = $findDatabasesStep->getSelectedDatabase($input);
                $config   = $database->getShellConfigurationFile();

                if ($config) {
                    $scpStep = new Scp($config, basename($config));
                    $scpStep->setSelectedEnvironment($env);
                    return $scpStep->run();
                }

                return new Result($configureStep, Result::SKIPPED, 'No database shell configuration available.');
            }
        );

        $script
            ->setEnvironment($env)
            ->addStep($findDatabasesStep)
            ->addStep($this->project->logAndSendNotifications()->setSendNotificationsOnScriptFailure(false))
            ->addStep('configure-shell', $configureStep)
            ->addStep(
                'open-db-shell',
                function () use ($findDatabasesStep, $env, $input) {
                    $database  = $findDatabasesStep->getSelectedDatabase($input);
                    echo "Opening database shell for '{$database->getDatabaseName()}' on {$env->getName()}." . PHP_EOL;
                }
            );

        return $script;
    }
}
