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
        if (is_null($this->client)) {
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
            //if the commit message matches "Merge pull request #xxx" then add the issue
            if (strpos($commit['commit']['message'], "Merge pull request #") !== false) {
                $issueId = substr(
                    $commit['commit']['message'],
                    20,
                    strpos($commit['commit']['message'], ' ', 20) -
                    strlen($commit['commit']['message'])
                );
                $issue = $this->getIssue($issueId);
                $releases['releases'][$currentTag]['issues'][] = $issue;
            }
        }

        return $releases;
    }

    public function getIssues()
    {
        $formattedData['issues'] = $this->getPager()->fetchAll(
            $this->getClient()->api('issues'),
            'all',
            array(
                $this->getRepoUser(),
                $this->getRepository(),
            )
        );
        return $formattedData;
    }
    
    /**
     * Can be used to remove fetched items that are pull requests. (or other).
     * Remove issues that are actually Pull Requests.
     * @param data : Array : Associative array with top-level key "issues".
     *               For example, $data['issues'][0], $data['issues'][1]
     * @param remKey : String : What key will determine whether or not the element gets unset.
     * @return Array. On error, it returns the original data argument.
     */
    public function removeFromIssueListByKey($data, $remKey = 'pull_request')
    {
        try {
            if (! is_array($data['issues'])) {
                throw new Exception('Did not get usable array as arg. 1');
            }
            $issueCount = count($data['issues']);
        
            for ($i = 0; $i < $issueCount; $i++) {
                if (array_key_exists($remKey, $data['issues'][$i])) {
                    unset($data['issues'][$i]);
                }
            }
        } catch (Exception $e) {
            
        }
        return $data;
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
                array('closed'),
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
        $token = file_get_contents($this->localKeyLocation);

        $this->getClient()->authenticate($token, null, Client::AUTH_HTTP_TOKEN);
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

        $key = $this->getOrCreateGithubKey();
        file_put_contents($this->localKeyLocation, $key['token']);
        chmod($this->localKeyLocation, 0700);

        $this->getClient()->authenticate($key, Client::AUTH_HTTP_TOKEN);
    }

    protected function getOrCreateGithubKey()
    {
        $note = 'Github-History on ' . gethostname();
        $auths = $this->getPager()->fetchAll(
            $this->getClient()->api('authorizations'),
            'all'
        );
        $key = false;
        foreach ($auths as $auth) {
            if ($auth['note'] == $note) {
                $key = $auth;
                break;
            }
        }

        if (!$key) {
            $key = $this->getClient()->api('authorizations')->create(
                array(
                    'note'      => $note,
                    'scopes'    => array('public_repo', 'repo')
                )
            );
        }
        return $key;
    }
}
