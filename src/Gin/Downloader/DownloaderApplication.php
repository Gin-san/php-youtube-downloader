<?php

namespace Gin\Downloader;

use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Gin\Downloader\Command\YoutubeCommand;

/**
 * Youtube downloader application
 *
 * @author Gin-san <gin.san.rzlk.g@gmail.com>
 */
class DownloaderApplication extends Application
{
    protected $rootPath;
    public static $SCRIPT_FILENAME;

    /**
     * Constructor.
     *
     * @param string $name     The name of the application
     * @param string $version  The version of the application
     * @param string $rootPath Application path
     * @param string $filename Application filename
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN', $rootPath = null, $filename = "")
    {
        if (empty($filename)) {
            throw new RuntimeException("A script name must be defined.");
        }
        $this->setRootPath($rootPath);
        self::$SCRIPT_FILENAME = strpos($filename, './') !== 0 ? './' . $filename : $filename;

        parent::__construct($name, $version);
    }

    /**
     * Gets the name of the command based on input.
     *
     * @param InputInterface $input The input interface
     *
     * @return string The command name
     */
    protected function getCommandName(InputInterface $input)
    {
        return self::$SCRIPT_FILENAME;
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \Exception When doRun returns Exception
     *
     * @api
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output) {
            $output = new DownloaderConsoleOutput();
        }

        return parent::run($input, $output);
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return integer 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        // if no arguments display help
        if ($input->getFirstArgument() === null) {
            $input = new ArrayInput(array('--help' => true));
        }

        return parent::doRun($input, $output);
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        // Keep the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = (new YoutubeCommand())->setRootPath($this->getRootPath());

        return $defaultCommands;
    }

    /**
     * Overridden so that the application doesn't expect the command
     * name to be the first argument.
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        // clear out the normal first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    /**
     * Set Application path
     *
     * @param string $rootPath Application path
     *
     * @return Gin\Downloader\DownloaderApplication $this
     */
    public function setRootPath($rootPath)
    {
        $this->rootPath = $rootPath;

        return $this;
    }

    /**
     * Get application path
     *
     * @return string Application path
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }
}
