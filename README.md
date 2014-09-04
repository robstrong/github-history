# github-history

Generate an HTML document containing the issue list of a Github repository.


## Assumptions
1. The repository is using Github releases
2. The repository uses issues/pull-requests to track new code that is merged in

## Install

1. `git clone git@github.com:robstrong/github-history.git`
2. `composer.phar install` - See [here](https://getcomposer.org/) to install composer

## Usage
`php bin/ghh compile`

Some good options to use with the above command:

 `--repo-user (-u)      Github Repository User Name` 
 `--repository (-r)     Github Repository Name`
 `--output-path (-o)    Output Path`

Sample of a compile call with options:

`php bin/ghh compile -u &lt;Owner/User&gt; -r &lt;Repo-Name&gt; -o output/issues/`
