<?php

namespace DeltaCli\Script;

use DeltaCli\Project;
use DeltaCli\Script;

class DatabaseDiagram extends Script
{
    public function __construct(Project $project)
    {
        parent::__construct(
            $project,
            'db:diagram',
            'Generate a DB diagram using Graphviz.'
        );
    }

    protected function configure()
    {
        $this->requireEnvironment();
        parent::configure();
    }

    protected function addSteps()
    {
        $findDbsStep = $this->getProject()->findDatabases();

        $this
            ->addStep($findDbsStep)
            ->addStep(
                'generate-diagram',
                function () use ($findDbsStep) {
                    $hosts = $this->getEnvironment()->getHosts();
                    $host  = reset($hosts);

                    $database = $findDbsStep->getSelectedDatabase($this->getProject()->getInput());
                    $database->setSshTunnel($host->getSshTunnel());
                    return $this->getProject()->generateDatabaseDiagram($database)->run();
                }
            );
    }
}