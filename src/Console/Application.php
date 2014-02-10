<?php

namespace Strong\GithubHistory\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Strong\GithubHistory\Command;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct("GithubHistory", "0.0.1");

    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        $this->add(new Command\Compile);
    }
}
