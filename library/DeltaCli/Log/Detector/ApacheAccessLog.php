<?php

namespace DeltaCli\Log\Detector;

use DeltaCli\Environment;
use DeltaCli\Log\LogInterface;

class ApacheAccessLog extends AbstractRemoteFile
{
    public function getName()
    {
        return 'apache-access-log';
    }

    public function getRemotePath(Environment $environment)
    {
        return 'logs/access_log';
    }

    public function getWatchByDefault()
    {
        return LogInterface::DONT_WATCH_BY_DEFAULT;
    }
}
