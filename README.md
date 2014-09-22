# github-history

Generate an HTML document containing either an issue list or release history of a Github repository.


## Assumptions
1. The repository is using Github releases
2. The repository uses issues/pull-requests to track new code that is merged in

## Install

1. `git clone git@github.com:robstrong/github-history.git`
2. `composer.phar install` - See [here](https://getcomposer.org/) to install composer

## To compile Release History:
`php bin/ghh history`

## To compile a List of Issues:
`php bin/ghh issues`

Some good options to use with either of the above commands:

 `--repo-user (-u)      Github Repository User Name` 
 `--repository (-r)     Github Repository Name`
 `--output-path (-o)    Output Path`

Sample call to compile issues (with options listed above):

`php bin/ghh issues -u <Owner/User> -r <Repo-Name> -o output/issues/`
