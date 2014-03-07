<?php

namespace Gin\Downloader\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Gin\Downloader\Extractor\Youtube;

class YoutubeCommand extends Command
{
    protected function configure()
    {
        $this->setName('gin:dl:youtube')
            ->setDescription('Script PHP to download Youtube.com videos')
            ->addArgument('v', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Youtube code ?v=__CODE__')
            ->setHelp(<<<EOT
Usage:
    $ php ./php-yt-dl [CODE|...]
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $codes = $input->getArgument('v');

        foreach ($codes as $code) {
            $code = $this->checkCode($code);
            $this->definedCookiePath();

            $yt   = new Youtube($code);
            $urls = $yt->extract();

            foreach ($urls as $q => $data) {
                $output->writeln($q);
                $output->writeln("\t" . $data['url']);
            }
        }
    }

    protected function checkCode($code)
    {
        if (filter_var($code, FILTER_VALIDATE_URL) !== false) {
            $response = parse_url($code, PHP_URL_QUERY);
            if (!$response['query']) {
               throw new InvalidArgumentException("A Youtube code must be defined '?v=__CODE__'");
            }
            $query = [];
            parse_str($response['query'], $query);
            if (!isset($query['v'])) {
               throw new InvalidArgumentException("A Youtube code must be defined '?v=__CODE__'");
            }
            $code = $query['v'];
        }

        return $code;
    }

    protected function definedCookiePath()
    {
        if (!defined("YT_COOKIE_PATH")) {
            $dir = __DIR__ . '/cookie';
            if (!is_dir($dir)) {
                if (!mkdir($dir)) {
                    throw new RuntimeException("Unable to create y");
                }
            }
            $file = $dir . '/ytcookie';
            define("YT_COOKIE_PATH", $file);
        }
    }
}