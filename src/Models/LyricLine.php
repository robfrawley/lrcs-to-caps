<?php

namespace RMF\Models;

use DateTime;
use RuntimeException;

class LyricLine
{
    private DateTime $time;

    private ?string $text;

    public function __construct(public string $line)
    {
        $this->time = $this->extractTimeFromLine();
        $this->text = $this->extractTextFromLine();
    }

    public function getLine(): string
    {
        return $this->line;
    }

    public function getTime(): DateTime
    {
        return $this->time;
    }

    public function hasText(): bool
    {
        return $this->text !== null;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function offset(string $timeText): self
    {
        if (false === preg_match('{(?<sign>[+-])?(?:0?(?<minutes>[0-9]{1,2}):)?0?(?<seconds>[0-9]{2})(?:\.(?<centiseconds>[0-9]{0,2}))?}', $timeText, $matches)) {
            throw new RuntimeException(sprintf('Failed to parse time offset of "%s"...', $timeText));
        }

        $interval = \DateInterval::createFromDateString(vsprintf('%d minutes %d seconds %d microseconds', [
            (int) ($matches['minutes'] ?: 0),
            (int) ($matches['seconds'] ?: 0),
            str_pad($matches['centiseconds'] ?: 0, 6, 0, STR_PAD_RIGHT)
        ]));

        if ($matches['sign'] === '-') {
            $this->time->sub($interval);
        } else {
            $this->time->add($interval);
        }

        return $this;
    }

    /**
     * @return LyricLine[]
     */
    public static function createLines(LyricLine|string ...$lines): array
    {
        return array_map(function ($line) {
            return $line instanceof LyricLine ? $line : new LyricLine($line);
        }, $lines);
    }

    private function extractTimeFromLine(): DateTime
    {
        return DateTime::createFromFormat('i:s.u', self::getMatches("/\[(\d+:\d+\.\d+)\]/", $this->getLine(), 2)[1]);
    }

    private function extractTextFromLine(): ?string
    {
        try {
            return self::getMatches("/\[(\d+:\d+\.\d+)\][ ]*(.*)/", $this->line, 3)[2] ?: null;
        } catch (RuntimeException $e) {
            return null;
        }
    }

    private static function getMatches(string $pattern, string $subject, int $requiredMatchCount = 0): array
    {
        $matches = [];
        preg_match($pattern, $subject, $matches);

        if (sizeof($matches) < $requiredMatchCount) {
            throw new RuntimeException("Failed to match pattern: $pattern, subject: $subject, required match count: $requiredMatchCount");
        }

        return $matches;
    }
}
