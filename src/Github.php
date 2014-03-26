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
        $tagsToSha = array();
        $tags = $this->getTags();
        foreach ($tags as $tag) {
            $tagsToSha[$this->formatTag($tag['name'])] = $tag['commit']['sha'];
        }

        $releases = $this->getReleases($tagsToSha);

        //get all closed pull requests and pull out the issue id and the head SHA
        $shaToIssueNumbers = $this->getShaToIssueNumbers();

        //go through the commit history starting with oldest tag SHA
        $commits = $this->getCommits();
        $shaToTag = array_flip($tagsToSha);
        $currentTag = current($tagsToSha);
        $tagToIssues = array();
        foreach ($commits as $commit) {
            //if sha matches a tag sha, update the current tag
            if (isset($shaToTag[$commit['sha']])) {
                $currentTag = $shaToTag[$commit['sha']];
            }
            //if sha matches the sha of a pull request, add that issue to the current tag
            if (isset($shaToIssueNumbers[$commit['sha']])) {
                $issue = $this->getIssue($shaToIssueNumbers[$commit['sha']]);
                $releases['releases'][$currentTag]['issues'][] = $issue;
            }
        }

        return $releases;
    }

    protected function getIssue($issueNum)
    {
        return $this->getClient()->api('issues')->show(
            $this->getRepoUser(), 
            $this->getRepository(), 
            $issueNum
        );
    }

    protected function getPager()
    {
        if (!isset($this->pager)) {
            $this->pager = new \Github\ResultPager($this->getClient());
        }
        return $this->pager;
    }

    protected function getCommits()
    {
        return $this->getPager()->fetchAll(
            $this->getClient()->api('repo')->commits(), 
            'all',
            array(
                $this->getRepoUser(), 
                $this->getRepository(), 
                array()
            )
        );
    }

    protected function getShaToIssueNumbers()
    {
        $pulls = $this->getPager()->fetchAll(
            $this->getClient()->api('pull_request'), 
            'all',
            array(
                $this->getRepoUser(), 
                $this->getRepository(), 
                'closed',
            )
        );
        $shaToIssueId = array();
        foreach ($pulls as $pull) {
            $shaToIssueId[$pull['head']['sha']] = $pull['number'];
        }

        return $shaToIssueId;
    }

    protected function getTags()
    {
        return $this->getClient()->api('repo')->tags(
            $this->getRepoUser(),
            $this->getRepository()
        );
    }

    //returns all releases with the tag sha added to it
    protected function getReleases($tagsToSha)
    {

        $releases = $this->getClient()->api('repo')->releases()->all(
            $this->getRepoUser(),
            $this->getRepository()
        );

        foreach ($releases as $release) {
            $tag = $this->formatTag($release['tag_name']);
            $formattedData['releases'][$tag] = array(
                'release'   => $release,
                'issues'    => array(),
                'sha'       => $tagsToSha[$tag],
            );
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

        chmod($this->localKeyLocation, 0700);

        $this->getClient()->authenticate($key['token'], Client::AUTH_HTTP_TOKEN);
    }
}
