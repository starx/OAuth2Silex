<?php
namespace OAuth2ServerExamples\Controllers;

class AbstractController
{
    protected $app;
    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function getApp() {
        return $this->app;
    }
}