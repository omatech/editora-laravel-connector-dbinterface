<?php

namespace Omatech\Editora\Connector\Cache;

use Illuminate\Console\Application;
use Symfony\Component\Process\Process;

/**
 * Runs editora:warm --if-changed as a detached PHP process in the application's directory. A web request cannot run a pass
 * itself: it may take minutes, and PHP-FPM ends a request after its time limit (125 seconds on Laravel Cloud). The process
 * gets its own session (setsid), so it outlives the PHP-FPM worker that starts it, and answers to no terminal.
 */
class WarmProcess
{
    public function start(): void
    {
        // An application's own test suite visits pages too: no pass starts from its tests.
        if (app()->runningUnitTests()) {
            return;
        }
        $command = Application::formatCommandString('editora:warm --if-changed --log');
        Process::fromShellCommandline(
            '{ if command -v setsid >/dev/null 2>&1; then exec setsid '.$command.'; else exec nohup '.$command.'; fi; } </dev/null >/dev/null 2>&1 &',
            base_path(),
        )->run();
    }
}
