<?php

namespace Strong\GithubHistory;

class Renderer
{
    protected $templatePath;
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function writeTo($outputPath)
    {
        $content = $this->getTwig()->render('index.html.twig', $this->data);
        file_put_contents($this->formatOutputLocation($outputPath), $content);
    }

    protected function formatOutputLocation($outputPath)
    {
        if (substr($outputPath, -4) == 'html') {
            return $outputPath;
        }
        return rtrim($outputPath,'/') . '/index.html';
    }

    public function setTemplatePath($path)
    {
        $this->templatePath = $path;
        return $this;
    }

    public function getTemplatePath()
    {
        return $this->templatePath;
    }

    protected function getTwig()
    {
        if (!isset($this->twig)) {
            $loader = new \Twig_Loader_Filesystem($this->getTemplatePath());
            $this->twig = new \Twig_Environment($loader);
        }
        return $this->twig;
    }
}
