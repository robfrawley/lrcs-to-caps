<?php

namespace RMF\Models;

use DateInterval;

/**
 * Class LyricGroup
 */
class LyricGroup implements \Countable
{
    /**
     * @var LyricLine[]
     */
    private array $lines;

    public function __construct(LyricLine|string ...$lines)
    {
        $this->lines = LyricLine::createLines(...$lines);
    }

    public function count(): int
    {
        return sizeof($this->lines);
    }

    public function addLinesToStart(LyricLine|string ...$lines): void
    {
        $this->lines = array_merge(LyricLine::createLines(...$lines), $this->lines);
    }

    public function offset(string $timeText): void
    {
        for($i = 0; $i < sizeof($this->lines); $i++) {
            $this->lines[$i]->offset($timeText);
        }
    }

    public function forEachLineAsCue(): \Generator
    {
        for ($i = 0; $i < sizeof($this->lines); $i++) {
            yield new LyricCue(
                $this->lines[$i],
                $this->getNextLineIndex($i)
            );
        }
    }

    private function getNextLineIndex(int $i): LyricLine
    {
        if (array_key_exists($i + 1, $this->lines)) {
            return $this->lines[$i + 1];
        }

        $time = clone $this->lines[$i]->getTime();
        $time->add(DateInterval::createFromDateString('10 seconds'));

        return new LyricLine(
            sprintf('[%s] ', substr($time->format('i:s.u'), 0, 8))
        );
    }
}
