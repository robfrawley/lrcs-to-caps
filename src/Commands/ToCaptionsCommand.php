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
            ->addOption('write-path', ['P'], InputOption::VALUE_REQUIRED, 'Output directory path.')
            ->addOption('write-file', ['F'], InputOption::VALUE_REQUIRED, 'Output directory path.')
            ->addOption('offset', ['o'], InputOption::VALUE_REQUIRED, 'Add or subtract time from lyric lines using format [+|-]mm:ss.ms', '+00:00.00')
            ->addOption('add-title-sequence', ['T'], InputOption::VALUE_REQUIRED, 'Add video intro subtitles.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Style::setup($input, $output);

        $file = new \SplFileInfo($input->getArgument('input_file'));

        if (!$file->isFile() || !$file->isReadable()) {
            Style::style()->error(sprintf('Input file does not exist or is not readable: "%s"', $file->getPathname()));
            return Command::FAILURE;
        }

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

        $writePath = new \SplFileInfo(
            Style::option('write-path') ?? (new \SplFileInfo(Style::argument('input_file')))->getPath()
        );
        $writeFile = new \SplFileInfo(sprintf(
            '%s/%s',
            $writePath->getRealPath(),
            Style::option('write-file') ?? (new \SplFileInfo(Style::argument('input_file')))->getBasename()
        ));

        if ('srt' !== strtolower($writeFile->getExtension())) {
            $writeFile = new \SplFileInfo(sprintf('%s.srt', $writeFile->getPathname()));
        }

        if (!$writePath->isDir() && !@mkdir($writePath->getPathname(), 0777, true)) {
            Style::style()->error(sprintf('Write path does not exist and could not be created: "%s"', $writePath->getPathname()));
            return Command::FAILURE;
        }

        Style::style()->note(sprintf('Output path: "%s"', $writeFile->getPathname()));

        if ($writeFile->isFile()) {
            Style::style()->caution(sprintf('Existing file found at output location of "%s"', $writeFile->getPathname()));
            $response = Style::style()->choice('How would you like to proceed?', [
                'o' => 'Continue and overwrite existing output file',
                'c' => 'Continue and rename output file to include a version number',
                'x' => 'Terminate script'
            ], 'x');

            switch ($response) {
                case 'x':
                    Style::style()->note('Exiting due to user request...');
                    return Command::INVALID;

                case 'c':
                    while (true) {
                        $randWriteVers = isset($randWriteVers) ? $randWriteVers + 1 : 1;
                        $randWriteExts = $writeFile->getExtension();
                        $randWriteFile = new \SplFileInfo(vsprintf('%s/%s.v%02d.%s', [
                            $writeFile->getPath(),
                            $writeFile->getBasename('.' . $randWriteExts),
                            $randWriteVers,
                            $randWriteExts,
                        ]));

                        if (!$randWriteFile->isFile()) {
                            $writeFile = $randWriteFile;
                            Style::style()->comment(sprintf('Outputting file as version %d...', $randWriteVers));
                            break;
                        }
                    }
                    break;

                case 'o':
                    Style::style()->comment('Overwriting existing file...');
                    break;
            }

        }

        try {
            $srt->build();
            $srt->save($writeFile->getPathname());
            Style::style()->success(sprintf('Wrote SRT file: "%s"', $writeFile->getRealPath()));
        } catch(Exception $e) {
            Style::style()->error(sprintf('Failed to save SRT file to "%s"!', $writeFile->getPathname()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processLyrics(LyricGroup $lyrics): LyricGroup
    {
        Style::style()->info(sprintf('Processing lyrics (found %d lines) ...', $lyrics->count()));

        $offset = Style::option('offset');
        Style::style()->comment(sprintf('Offsetting lyric times by "%s" ...', $offset));
        $lyrics->offset($offset);

        $intro = Style::option('add-title-sequence');
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
