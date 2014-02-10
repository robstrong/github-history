<?php

namespace Strong\GithubHistory\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compile extends Command
{
    protected function configure()
    {
        $this->setName("compile")
            ->setDescription("Compile the HTML file output");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Compiling...");
    }
}
