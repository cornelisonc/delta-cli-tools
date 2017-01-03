<?php

namespace DeltaCli\Script;

use DeltaCli\Project;
use DeltaCli\Script;

class DatabaseCopy extends Script
{
    public function __construct(Project $project)
    {
        parent::__construct(
            $project,
            'db:copy',
            'Copy a database from one environment to another.'
        );
    }

    protected function configure()
    {
        parent::configure();
    }

    protected function addSteps()
    {
        $this
            ->addStep(
                function () {
                    throw new \Exception('Coming Soon');
                }
            );
    }
}