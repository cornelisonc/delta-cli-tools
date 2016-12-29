<?php

namespace DeltaCli\Script;

use DeltaCli\FileTransferPaths;
use DeltaCli\Project;
use DeltaCli\Script;
use DeltaCli\Script\Step\Scp as ScpStep;

class Scp extends Script
{
    private $file1;

    private $file2;

    private $recursive;

    public function __construct(Project $project)
    {
        parent::__construct(
            $project,
            'scp',
            'Use scp to transfer a file to or from an environment.'
        );
    }

    public function setFile1($file1)
    {
        $this->file1 = $file1;

        return $this;
    }

    public function setFile2($file2)
    {
        $this->file2 = $file2;

        return $this;
    }

    public function setRecursive($recursive)
    {
        $this->recursive = $recursive;

        return $this;
    }

    protected function configure()
    {
        $this->addSetterArgument('file1');
        $this->addSetterArgument('file2');

        $this->addSetterOption('recursive', 'r');

        parent::configure();
    }

    protected function addSteps()
    {
        $paths = new FileTransferPaths($this->getProject(), $this->file1, $this->file2);
        $scp   = new ScpStep($paths->getLocalPath(), $paths->getRemotePath(), $paths->getDirection());
        $env   = $this->getProject()->getTunneledEnvironment($paths->getRemoteEnvironment());
        $this->setEnvironment($env);

        if ($this->recursive) {
            $scp->setIsDirectory(true);
        }

        $this
            ->addStep($scp)
            ->addStep($this->getProject()->logAndSendNotifications());
    }
}
