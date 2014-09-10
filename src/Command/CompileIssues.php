<?php

namespace Strong\GithubHistory\Command;

use Strong\GithubHistory\Github;
use Strong\GithubHistory\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class CompileIssues extends Command
{
    protected function configure()
    {
        $this->setName("compileissues")
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
        $output->writeln("Compiling your Github Issue List...");

        //pull github data
        $gh = new Github($input, $output);
        if ($input->getOption('cache') !== 'false') {
            $gh->cache(true);
        }
        $gh->authenticate();
        
        //Remove Pull Requests
        $data   = $gh->removeFromIssueListByKey($gh->getIssues(),'pull_request');
        
        //Remove this at some point. Dumps to shell unless redirected to file.
        //print_r($data);
        
        //Set a list of labels we don't want here.
        $undesiredLabels    = array('Design', 'enhancement', 'Backlog');
        
        //Now prune the array by removing Issues containing undesired labels.
        $data               = $gh->removeIssuesHavingLabels($data, $undesiredLabels);
        
        //render HTML
        $renderer           = new Renderer($data);
        $outputPath         = $this->getApplication()->getAppPath() . '/output/';
        if ($input->getOption('output-path')) {
            $outputPath     = $input->getOption('output-path');
        }
        $renderer->setTemplatePath($this->getApplication()->getAppPath() . '/views/')
            ->writeTo($outputPath);

        $output->writeln("Done!");
    }
}
