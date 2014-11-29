<?php

namespace Strong\GithubHistory\Command;

use Strong\GithubHistory\Github;
use Strong\GithubHistory\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class Issues extends Command
{
    protected function configure()
    {
        $this->setName("issues")
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
                'label-filter',
                'l',
                InputOption::VALUE_REQUIRED,
                'List of labels to filter out of issue list',
                'false'
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
        
        /**
         * If option 'l' was provided, its a comma-delimited list of labels to filter out.
         * Take the list and convert it to an array. Otherwise init an empty array.
         * In the event of leading or trailing spaces in the comma-delimited list, 
         *   run trim on each element.
         */
        if ($input->getOption('label-filter') !== 'false') {
            $labelsList     = trim($input->getOption('label-filter'));
            $labelsArray    = explode(",", $labelsList);
            $unwantedLabels = array_map("trim", $labelsArray);
            
        } else {
            $unwantedLabels = array();
        }
        
        //Now prune the array by removing Issues containing undesired labels.
        $data               = $gh->removeIssuesHavingLabels($data, $unwantedLabels);
        
        //render HTML
        $renderer           = new Renderer($data);
        $outputPath         = $this->getApplication()->getAppPath() . '/output/';
        if ($input->getOption('output-path')) {
            $outputPath     = $input->getOption('output-path');
        }
        
        //Set path to find the issues template file
        $renderer->setTemplatePath($this->getApplication()->getAppPath() . '/views/issues/')
            ->writeTo($outputPath);
        $output->writeln("Done!");
    }
}
