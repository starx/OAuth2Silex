<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
namespace OAuth2ServerExamples\Controllers;

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Routing\Generator\UrlGenerator;

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

    /**
     * @return \Twig_Environment
     */
    protected function twig()
    {
        return $this->getApp()["twig"];
    }

    /**
     * @return FormFactory
     */
    protected function formFactory()
    {
        return $this->getApp()["form.factory"];
    }

    /**
     * @return UrlGenerator
     */
    protected function urlGenerator()
    {
        return $this->getApp()['url_generator'];
    }
}