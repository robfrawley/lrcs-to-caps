<?php

namespace RMF\Readers;

use RMF\Console\Style\Style;
use RuntimeException;
use Symfony\Component\Process\Process;

class FlacLrcMetadataReader
{
    public function __construct(private readonly \SplFileInfo $file)
    {
    }

    public function getLines(): array
    {
        return $this->extractMetadataLrcLines();
    }

    private function extractMetadataLrcLines(): array
    {
        Style::style()->comment(sprintf('Reading file: "%s"', $this->file->getRealPath()));

        $process = new Process(['metaflac', '--show-tag=Lyrics', $this->file->getRealPath()]);
        $process->setTimeout(10);
        $process->mustRun();

        $lines = array_filter(array_map(function($line) {
            return preg_replace('{.*(\[[0-9]{1,2}:[0-9]{2}\.[0-9]{2}\].+)}', '\1', $line);
        }, array_filter(explode("\n", $process->getOutput()))));

        if (sizeof($lines) === 0) {
            throw new RuntimeException(sprintf('Failed to extract lyrics from file %s.', $this->file->getRealPath()));
        }

        return $lines;
    }
}
