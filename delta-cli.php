<?php

/* @var $project \DeltaCli\Project */

use DeltaCli\Config\Database;

$project->setName('Delta CLI Tools Example');

/**
 * Add the Slack handles of any users you want to be notified when you run relevant scripts.
 */
$project->addSlackHandle('@bgriffith');

/**
 * Optionally send notifications to an alternate Slack channel.  They'll default to #deploy.
 */
$project->setSlackChannel('#custom-channel');

/**
 * Create environments for your project.  By default, they'll use the SSH keys generated by Delta CLI,
 * but you can specify and alternate set with setSshPrivateKey().  You can add multiple hosts, if needed.
 */
$project->createEnvironment('staging')
    ->setUsername('bgriffith')
    ->addHost('staging.deltasys.com');

/**
 * If you mark and environment as a dev environment, certain restrictions and checks are relaxed.  You can
 * deploy to the environment when you don't have everything committed to git.  Notifications won't be sent.
 * Your actions are not logged.  You can do database restores without an authorization code.  Etc.
 */
$project->createEnvironment('joe-dev')
    ->setUsername('joe_developer')
    ->addHost('remote-dev-box.deltasys.com')
    ->setIsDevEnvironment(true);

/**
 * You can manually edit the config for an environment, which allows you to add databases that are not
 * auto-detected by Delta CLI.
 */
$project->getEnvironment('vagrant')->getManualConfig()
    ->addDatabase(new Database('mysql', 'manual_config', 'manual_config', 'manual_config', 'localhost'));

/**
 * There is a "deploy" script included out of the box but you'll likely need to add some steps.  Typically,
 * on a WordPress project, you'll use the WordPress template.  On other projects, you'll have a few rsyncs,
 * some permissions changes, etc.  Your deploy script automatically includes logging and sending
 * notifications.
 *
 * Notice the call to addEnvironmentSpecificStep().  Those steps will be skipped on all environments except
 * the specific one you want that step to apply to.
 */
$project->getScript('deploy')
    ->addStep(
        $project->rsync('library', 'remote/path/to/sync/to')
            ->exclude('pattern-to-exclude')
            ->exclude('another-exclude')
            ->delete()
    )
    ->addStep(
        'run-command-over-ssh',
        $project->ssh('command-to-run.sh --flags')
    )
    ->addStep($project->allowWritesToRemoteFolder('httpdocs/uploads'))
    ->addEnvironmentSpecificStep(
        'staging',
        'this-step-only-runs-on-staging',
        function () {
            echo 'You must be deploying to staging!' . PHP_EOL;
        }
    );

/**
 * You can add custom scripts as well.  Many different kinds of steps can be added to your script.
 * When calling addStep(), you can pass a string as the first argument to name the step, which can
 * be useful to make --list-steps and --skip-step=[step-name] more handy for your users.
 *
 * Common steps types include:
 * - PHP callables : Any PHP callable.  Succeeds unless an exception is thrown.  Just pass your callable to addStep().
 * - Shell command: A local shell command.  Succeeds when exit status is 0.  Pass a string to addStep().
 * - SSH command: A remote SSH command.  Succeeds when exit status is 0.  Call $project->ssh('your-command').
 * - rsync: Sync a local folder to a remote folder.  Call $project->rsync('local-path', 'remote-path').
 * - scp: Send a file with scp.  Call $project->scp('local-file', 'remote-file');
 * - script: Use another Delta CLI script as a step in your script.  Use $project->getScript('script-name').
 * There are many other kinds of steps built-in as well.
 */
$project->createScript('custom-script', 'Just an example custom script.')
    ->addStep(
        function () {
            echo "Hey!  It's custom!\n";
        }
    )
    ->addStep(
        'failing-step',
        function () {
            throw new \Exception('Ooops!');
        }
    );

/**
 * You call createEnvironmentScript() when your script is intended to take an environment argument.
 * It's equivalent to calling createScript() and then calling requiresEnvironment() on that script.
 *
 * You can manage background processes in your scripts.  killProcessMatchingName() will search for running
 * processes on the remote environment and kill them.  startBackgroundProcess() will get a process running
 * in the background on the remote environment without blocking your Delta CLI script.
 */
$project->createEnvironmentScript('manage-background-processes', 'Kill and start a background process.')
    ->addStep($project->killProcessMatchingName('example-background-process.sh'))
    ->addStep($project->startBackgroundProcess('./example-background-process.sh'));

/**
 * Some Delta CLI script steps support the concept of a "dry run" out of the box.  Whenever a script includes
 * an rsync step, for example, you can run the script with the --dry-run option to show which files _would_
 * be synced without actually modifying any files on the remote environment.
 *
 * For your custom shell commands or PHP callable based steps, you can also provide a dry run version
 * that should be non-destructive and provide some information to the user about how the script will behave
 * when --dry-run is no longer in use.
 *
 * Any steps that do not support dry runs will be skipped when --dry-run is active.
 */
$project->createScript('dry-runs-for-php-and-shell-commands', 'Custom steps can also support dry runs.')
    ->addStep(
        'shell-command-with-dry-run',
        $project->shellCommandSupportingDryRun(
            // Perform actual work
            'touch /tmp/important-file && cp /tmp/important-file /tmp/ultimate-destination',
            // Do something non-destructive when in dry-run mode
            'ls -l /tmp/important-file'
        )
    )
    ->addStep(
        'php-callable-with-dry-run',
        $project->phpCallableSupportingDryRun(
            // Perform actual work
            function () {
                file_put_contents(
                    '/tmp/file-to-write',
                    'Hey there!'
                );
            },
            // Do something non-destructive when in dry-run mode
            function () {
                if (!file_exists('/tmp/file-to-write')) {
                    echo 'file-to-write does not yet exist.' . PHP_EOL;
                } else {
                    echo 'file-to-write already exists.' . PHP_EOL;
                }
            }
        )
    );

/**
 * A script to run the unit tests that ship with Delta CLI.
 */
$project->createScript('run-tests', 'Run PHPUnit tests.')
    ->addStep('phpunit', 'phpunit -c tests/phpunit.xml tests/');

/**
 * A script to run the same tests and generate a code coverage report.
 */
$project->createScript('run-tests-with-coverage', 'Run PHPUnit tests.')
    ->addStep('phpunit', 'phpunit --coverage-html=test-coverage -c tests/phpunit.xml tests/');

/**
 * This is an example of a watch command.  The watch() step will run the script you pass to it
 * whenever a file changes in the paths you supply to addPath().  Delta CLI can notify you when
 * the script completes -- every time or only when your script fails.
 *
 * In this case, watch-tests can run in the background while developing Delta CLI and it will
 * display a desktop notification whenever a test fails.
 */
$project->createScript('watch-tests', 'Watch for changes and run git status')
    ->addStep(
        $project->watch($project->getScript('run-tests'))
            ->setOnlyNotifyOnFailure(true)
            ->addPath('library/DeltaCli')
            ->addPath('tests')
    );

/**
 * This is an example of using one script as a step in another script.
 */
$project->createScript('composing-scripts', 'An example of calling one script from another.')
    ->addStep(
        function () {
            echo 'Doing things!';
        }
    )
    ->addStep($project->getScript('custom-script'));

/**
 * This script illustrates the use of custom names on your steps.
 */
$project->createEnvironmentScript('inline-naming-of-step', 'Shows naming a step via argument to addStep().')
    ->addStep('custom-step-name', $project->ssh('ls'))
    ->addEnvironmentSpecificStep('staging', 'for-environment-steps-too', $project->ssh('ls'));

/**
 * Add a logAndSendNotifications() step to any script and its results will be added to the log
 * for the project and notifications will be sent.
 */
$project->createScript('notification-test', 'A script to test the notification API workflow.')
    ->addStep($project->logAndSendNotifications());

/**
 * If there is a potentially dangerous operation in your scripts, you can add a
 * sanityCheckPotentiallyDangerousOperation() step.  The step takes a description of the operation
 * as its only argument.  If the user tries to run your script on a non-dev environment, they'll
 * be asked to supply a randomly generated authorization code to proceed.
 */
$project->createEnvironmentScript(
    'sanity-checking-dangerous-operations',
    'Requiring random authorization code for dangerous operations.'
)
    ->addStep($project->sanityCheckPotentiallyDangerousOperation('Ruin everything.'))
    ->addStep(
        function () {
            throw new Exception('You must have authorized me to ruin everything.');
        }
    );
