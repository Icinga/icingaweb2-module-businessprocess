<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Exception\IcingaException;
use Icinga\Web\View;

abstract class Component
{
    /** @var View */
    private $view;

    /** @var View */
    private static $discoveredView;

    /**
     * @param View $view
     * @return $this
     */
    public function setView(View $view)
    {
        $this->view = $view;
        return $this;
    }

    /**
     * @return View
     */
    public function view()
    {
        if ($this->view === null) {
            $this->view = $this->discoveredView();
        }
        return $this->view;
    }

    /**
     * @return View
     */
    protected function discoveredView()
    {
        if (self::$discoveredView === null) {
            $viewRenderer = Icinga::app()->getViewRenderer();
            if ($viewRenderer->view === null) {
                $viewRenderer->initView();
            }

            self::$discoveredView = $viewRenderer->view;
        }

        return self::$discoveredView;
    }

    /**
     * @return string
     */
    abstract function render();

    public function wantHtml($any, $separator = '')
    {
        if ($any instanceof Component) {
            return $any;
        } elseif (is_string($any)) {
            return $this->view()->escape($any);
        } elseif (is_array($any)) {
            $safe = array();
            foreach ($any as $el) {
                $safe .= $this->wantHtml($el);
            }

            return implode($separator, $safe);
        } else {
            // TODO: Should we add a dedicated Exception class?
            throw new IcingaException(
                'String, Web Component or Array of such expected, got "%s"',
                $this->getPhpTypeName($any)
            );
        }
    }

    public function getPhpTypeName($any)
    {
        if (is_object($any)) {
            return get_class($any);
        } else {
            return gettype($any);
        }
    }

    /**
     * @param Exception|string $error
     * @return string
     */
    protected function renderError($error)
    {
        if ($error instanceof Exception) {
            $file = preg_split('/[\/\\\]/', $error->getFile(), -1, PREG_SPLIT_NO_EMPTY);
            $file = array_pop($file);
            $msg = sprintf(
                '%s (%s:%d)',
                $error->getMessage(),
                $file,
                $error->getLine()
            );
        } elseif (is_string($error)) {
            $msg = $error;
        } else {
            $msg = 'Got an invalid error';
        }




        $view = $this->view();
        return sprintf(
            $view->translate('ERROR: %s'),
            $view->escape($msg)
        );
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
}