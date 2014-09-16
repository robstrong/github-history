<?php

namespace Strong\GithubHistory\Command;

use Strong\GithubHistory\Github;
use Strong\GithubHistory\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class Compile extends Command
{
    protected function configure()
    {
        $this->setName("compile")
            ->setDescription("Compile the HTML file output")
            ->addOption(
                'repo-user',
                'u',
                InputOption::VALUE_REQUIRED,
                'Github Repository User Name'
            )
            ->addOption(
                'repository',
                'r',
                InputOption::VALUE_REQUIRED,
                'Github Repository Name'
            )
            ->addOption(
                'output-path',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output Path'
            )
            ->addOption(
                'cache',
                'c',
                InputOption::VALUE_REQUIRED,
                'Cache Github requests (true/false)',
                'true'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Compiling...");

        //pull github data
        $gh = new Github($input, $output);
        if ($input->getOption('cache') !== 'false') {
            $gh->cache(true);
        }
        $gh->authenticate();
        $data = $gh->getData();

        //render HTML
        $renderer = new Renderer($data);
        $outputPath = $this->getApplication()->getAppPath() . '/output/';
        if ($input->getOption('output-path')) {
            $outputPath = $input->getOption('output-path');
        }
        $renderer->setTemplatePath($this->getApplication()->getAppPath() . '/views/')
            ->writeTo($outputPath);

        $output->writeln("Done!");
    }
}