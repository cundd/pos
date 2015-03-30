<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 22.03.15
 * Time: 11:26
 */

namespace Cundd\PersistentObjectStore\View;


use Cundd\PersistentObjectStore\Configuration\ConfigurationManager;
use Cundd\PersistentObjectStore\Server\UriBuilderInterface;

/**
 * Trait for View base controllers
 *
 * @package Cundd\PersistentObjectStore\View
 */
trait ViewControllerTrait {
    /**
     * @var \Cundd\PersistentObjectStore\View\ViewInterface
     */
    protected $view;

    /**
     * Path pattern for templates
     *
     * @var string
     */
    protected $templatePathPattern = '%sResources/Private/Template/%s.twig';

    /**
     * Class name of the View implementation
     *
     * @var string
     */
    protected $viewClass = 'Cundd\\PersistentObjectStore\\View\\Twig\\View';

    /**
     * @var UriBuilderInterface
     * @Inject
     */
    protected $uriBuilder;

    /**
     * Returns the View instance
     *
     * @return \Cundd\PersistentObjectStore\View\ViewInterface
     */
    public function getView()
    {
        if (!$this->view) {
            $viewClass = $this->viewClass;
            $this->view = new $viewClass();

           $this->initializeViewAdditions();
        }
        return $this->view;
    }

    /**
     * Returns the URI Builder instance
     *
     * @return UriBuilderInterface
     */
    public function getUriBuilder()
    {
        return $this->uriBuilder;
    }

    /**
     * Returns the template path for the given action
     *
     * @param $action
     * @return string
     */
    public function getTemplatePath($action)
    {
        $basePath           = ConfigurationManager::getSharedInstance()->getConfigurationForKeyPath('basePath');

        // Strip 'Action'
        $templateIdentifier = substr($action, 0, -6);
        $templatePath       = sprintf($this->templatePathPattern, $basePath, $templateIdentifier);

        return $templatePath;
    }

    /**
     * Initialize the additional filters of expandable views
     */
    protected function initializeViewAdditions()
    {
        if ($this->view instanceof ExpandableViewInterface) {
            $this->view->addFunction('action', function($actionName, $controller = null, $database = null, $document = null) {
                if ($controller === null) {
                    $controller = $this;
                }
                return $this->getUriBuilder()->buildUriFor($actionName, $controller, $database, $document);
            });
        }
    }

}