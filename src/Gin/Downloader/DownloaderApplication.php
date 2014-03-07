<?php

namespace Gin\Downloader;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Gin\Downloader\Command\YoutubeCommand;

/**
 * Youtube downloader application
 *
 * @author Gin-san <gin.san.rzlk.g@gmail.com>
 */
class DownloaderApplication extends Application
{
    protected $rootPath;

    /**
     * Constructor.
     *
     * @param string $name     The name of the application
     * @param string $version  The version of the application
     * @param string $rootPAth Application path
     *
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN', $rootPath = null)
    {
        $this->setRootPath($rootPath);

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
        // This should return the name of your command.
        return 'gin:dl:youtube';
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