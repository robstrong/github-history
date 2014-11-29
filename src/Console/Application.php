<?php

namespace Strong\GithubHistory\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Strong\GithubHistory\Command;

class Application extends BaseApplication
{
    protected $appPath;

    public function __construct()
    {
        parent::__construct("GithubHistory", "0.1.0");
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        $this->add(new Command\Releases);
        $this->add(new Command\Issues);
    }

    public function setAppPath($path)
    {
        $this->appPath = $path;
        return $this;
    }

    public function getAppPath()
    {
        return $this->appPath;
    }
}
