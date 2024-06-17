<?php

namespace RMF\Commands;

use Captioning\Format\SubripFile;
use Exception;
use RMF\Console\Style\Style;
use RMF\Models\LyricGroup;
use RMF\Models\LyricLine;
use RMF\Readers\FlacLrcMetadataReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ToCaptionsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('to-captions')
            ->setAliases(['2caps'])
            ->setHelp('A CLI tool to convert lyric (LRC) metadata or files to a number of video caption file formats.')
            ->addArgument('input_file', InputArgument::REQUIRED, 'Compatible file (such as a LRC file or a FLAC file with LRC metadata.')
            ->addArgument('output_path', InputArgument::OPTIONAL, 'Output directory path.', getcwd())
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Add or subtract time from lyric lines using format [+|-]mm:ss.ms', '+00:00.00')
            ->addOption('add-video-intro', null, InputOption::VALUE_REQUIRED, 'Add video intro subtitles.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Style::setup($input, $output);

        $file = new \SplFileInfo($input->getArgument('input_file'));

        switch ($file->getExtension()) {
            case 'flac':
                return $this->flacToCaptions($file);

            default:
                Style::style()->error('Unsupported file type provided!');
                return Command::INVALID;
        }
    }

    private function flacToCaptions(\SplFileInfo $file): int
    {
        Style::style()->info('Performing LRC extraction from FLAC metadata...');

        $reader = new FlacLrcMetadataReader($file);
        $lyrics = new LyricGroup(...$reader->getLines());

        return $this->lyricsToSrt($this->processLyrics($lyrics));
    }

    private function lyricsToSrt(LyricGroup $lyrics): int
    {
        $srt = new SubripFile();

        foreach($lyrics->forEachLineAsCue() as $cue) {
            $srtCue = $cue->getAsSubripCue();

            if (!$srtCue) {
                continue;
            }

            try {
                $srt->addCue($srtCue);
            } catch (Exception $e) {
                Style::style()->warning(sprintf('Failed to add cue for lyric line "%s" (%s)...', $srtCue->getText(), $e->getMessage()));
            }
        }

        $outputFile = Style::input()->getArgument('output_path').DIRECTORY_SEPARATOR.'output.srt';

        try {
            $srt->build();
            $srt->save($outputFile);
            Style::style()->success(sprintf('Wrote SRT file: "%s"', $outputFile));
        } catch(Exception $e) {
            Style::style()->error(sprintf('Failed to save SRT file to "%s"!', $outputFile));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processLyrics(LyricGroup $lyrics): LyricGroup
    {
        Style::style()->info(sprintf('Processing lyrics (found %d lines) ...', $lyrics->count()));

        $offset = Style::input()->getOption('offset');
        Style::style()->comment(sprintf('Offsetting lyric times by "%s" ...', $offset));
        $lyrics->offset($offset);

        $intro = Style::input()->getOption('add-video-intro');
        if ($intro) {
            Style::style()->comment(sprintf('Adding video intro subtitle cues for "%s" ...', $intro));
            Style::style()->comment(sprintf('Offsetting lyric times by "%s" ...', '+00:14.50'));
            $lyrics->offset('+00:14.50');
            $lyrics->addLinesToStart(
                new LyricLine('[00:00.00] TOOL'),
                new LyricLine('[00:04.83] '),
                new LyricLine(sprintf('[00:05.25] %s, Performed by Danny Carey', $intro)),
                new LyricLine('[00:10.16] '),
                new LyricLine(sprintf('[00:10.16] %s, Transcribed by Rob Frawley 2nd', $intro)),
                new LyricLine('[00:15.16] '),
            );
        }

        return $lyrics;
    }
}
