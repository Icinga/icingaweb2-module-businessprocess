<?php

namespace Icinga\Module\Businessprocess\Renderer\TileRenderer;

use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\Web\Url;

class AddNewTile extends BaseElement
{
    protected $tag = 'div';

    protected $node;

    protected $defaultAttributes = array('class' => 'addnew');

    public function __construct(Renderer $renderer)
    {
        $bp = $renderer->getBusinessProcess();
        $path = $renderer->getMyPath();

        $params = array(
            'config' => $bp->getName()
        );

        // Workaround for array issues
        $url = Url::fromPath('businessprocess/process/create');
        $p = $url->getParams();
        $p->mergeValues($params);
        if (! empty($path)) {
            $p->addValues('path', $path);
        }

        $this->add(
            Link::create(
                $this->translate('Add'),
                $url,
                null,
                array(
                    'title' => $this->translate('Add a new business process node')
                )
            )
        );
    }
}