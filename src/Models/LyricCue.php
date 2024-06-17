<?php

namespace RMF\Models;

use DateTime;
use Captioning\Format\SubripCue;

class LyricCue
{
    public function __construct(private LyricLine $thisLyric, private LyricLine $nextLyric)
    {
    }

    public function getAsSubripCue(): ?SubripCue
    {
        $nextLine = $this->nextLyric;
        $thisLine = $this->thisLyric;
        $lineText = $thisLine->getText();

        if ($lineText === null) {
            return null;
        }

        $lineStart = $thisLine->getTime();
        $lineStops = $nextLine?->getTime() ?: DateTime::createFromFormat('i:s.u', '99:99.99');

        $timeStart = substr($lineStart->format('00:i:s.u'), 0, 12);
        $timeStops = substr($lineStops->format('00:i:s.u'), 0, 12);

        return new SubripCue($timeStart, $timeStops, $lineText);
    }
}
