<?php

namespace Bolt\Logger;

use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * Buffering PSR-3 console logger.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class BufferingConsoleLogger extends ConsoleLogger
{
    /** @var array */
    private $logs = [];

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        $this->logs[] = [$level, $message, $context];
        parent::log($level, $message, $context);
    }

    /**
     * @return array
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @return array
     */
    public function cleanLogs()
    {
        $logs = $this->logs;
        $this->logs = [];

        return $logs;
    }
}
