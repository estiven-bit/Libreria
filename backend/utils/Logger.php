<?php

class Logger
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    private function write(string $level, string $event, array $context): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'event' => $event,
            'context' => $context,
        ];

        file_put_contents($this->file, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }
}
