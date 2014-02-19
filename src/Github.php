<?php

namespace Strong\GithubHistory;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Helper\DialogHelper;
use Github\Client;
use Github\HttpClient\CachedHttpClient;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Github
{
    protected $localKeyLocation;
    protected $client;
    protected $input;
    protected $cached = false;
    protected $output;
    protected $dialog;
    protected $repoUser;
    protected $repository;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->localKeyLocation = $_SERVER['HOME'] . '/.gh-hist.yml';
        $this->input = $input;
        $this->output = $output;

        if ($input->getOption('repo-user')) {
            $this->setRepoUser($input->getOption('repo-user'));
        }
        if ($input->getOption('repository')) {
            $this->setRepository($input->getOption('repository'));
        }
    }

    public function setRepoUser($repoUser)
    {
        $this->repoUser = $repoUser;
        return $this;
    }

    public function getRepoUser()
    {
        if (is_null($this->repoUser)) {
            $this->repoUser = $this->getDialog()->ask(
                $this->output,
                'Enter the GitHub repository user: '
            );
        }
        return $this->repoUser;
    }

    public function getRepository()
    {
        if (is_null($this->repository)) {
            $this->repository = $this->getDialog()->ask(
                $this->output,
                'Enter the GitHub repository: '
            );
        }
        return $this->repository;
    }

    public function setRepository($repository)
    {
        $this->repository = $repository;
        return $this;
    }

    protected function getClient()
    {
        if  (is_null($this->client)) {
            if ($this->cached) {
                $this->setClient(
                    new Client(new CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache')))
                );
            } else {
                $this->setClient(
                    new Client
                );
            }
        }
        return $this->client;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
        return $this;
    }

    public function authenticate()
    {
        if (!$this->authWithLocalKey()) {
            $this->promptForGithubCredentials();
        }
    }

    public function cache($option)
    {
        $this->cached = $option;
        return $this;
    }

    public function getData()
    {
        $formattedData = array(
            'repo'      => $this->getRepoUser() . '/' . $this->getRepository(),
            'releases'  => array(),
        );

        //get releases
        $releases = $this->getClient()->api('repo')->releases()->all(
            $this->getRepoUser(),
            $this->getRepository()
        );

        foreach ($releases as $release) {
            $formattedData['releases'][$this->formatTag($release['tag_name'])] = array(
                'release'   => $release,
                'issues'    => array()
            );
        }

        //get closed issues, then filter those based on label
        $pager = new \Github\ResultPager($this->getClient());
        $issues = $pager->fetchAll(
            $this->getClient()->api('issue'), 
            'all',
            array(
                $this->getRepoUser(), 
                $this->getRepository(), 
                array(
                    'state'     => 'closed',
                    'filter'    => 'all',
                    'direction' => 'asc',
                )
            )
        );
        foreach ($issues as $issue) {
            foreach ($issue['labels'] as $label) {
                if (isset($formattedData['releases'][$this->formatTag($label['name'])])) {
                    $formattedData['releases'][$this->formatTag($label['name'])]['issues'][] = $issue;
                }
            }
        }

        return $formattedData;
    }

    protected function formatTag($tag)
    {
        $tag = ltrim($tag, "v");
        while (substr_count($tag, ".") < 2) {
            $tag = $tag . '.0';
        }
        return $tag;
    }

    protected function authWithLocalKey()
    {
        if (!is_file($this->localKeyLocation)) {
            return false;
        }
        $data = Yaml::parse(file_get_contents($this->localKeyLocation));

        if (!isset($data['github_key'])) {
            return false;
        }

        $this->getClient()->authenticate($data['github_key'], null, Client::AUTH_HTTP_TOKEN);
        $user = $this->getClient()->api('current_user')->show();

        return true;
    }

    protected function getDialog()
    {
        if (is_null($this->dialog)) {
            $this->dialog = new DialogHelper();
        }
        return $this->dialog;
    }

    protected function promptForGithubCredentials()
    {
        //prompt for user name and pass
        $user = $this->getDialog()->ask(
            $this->output,
            'Please enter your Github login: '
        );
        $password = $this->getDialog()->askHiddenResponse(
            $this->output,
            'Please enter your Github password. The password will not be stored, it will be traded for an API key: '
        );

        $this->setGithubApiKey($user, $password);
    }

    protected function setGithubApiKey($user, $password)
    {
        $this->getClient()->authenticate($user, $password, Client::AUTH_HTTP_PASSWORD);

        $key = $this->getClient()->api('authorizations')->create(
            array(
                'note'      => 'Github-History on ' . gethostname(),
                'scopes'    => array('repo')
            )
        );

        file_put_contents($this->localKeyLocation, Yaml::dump(array('github_key' => $key['token'])));
        chmod($this->localKeyLocation, 0700);

        $this->getClient()->authenticate($key['token'], Client::AUTH_HTTP_TOKEN);
    }
}
