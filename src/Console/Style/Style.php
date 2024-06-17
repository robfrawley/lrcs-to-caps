<?php

namespace RMF\Console\Style;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Style
{
    private static InputInterface $i;
    private static OutputInterface $o;
    private static SymfonyStyle $s;

    public static function setup(InputInterface $input, OutputInterface $output): SymfonyStyle {
        self::$i = $input;
        self::$o = $output;
        self::$s = new SymfonyStyle($input, $output);

        return self::style();
    }

    public static function input(): InputInterface {
        return self::$i;
    }

    public static function output(): OutputInterface {
        return self::$o;
    }

    public static function style(): SymfonyStyle {
        return self::$s;
    }
}
