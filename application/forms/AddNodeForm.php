<?php

namespace Icinga\Module\Businessprocess\Forms;

use Exception;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Common\Sort;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Storage\Storage;
use Icinga\Module\Businessprocess\Web\Form\Element\IplStateOverrides;
use Icinga\Module\Businessprocess\Web\Form\Validator\HostServiceTermValidator;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Stdlib\Str;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormElement\TermInput;
use ipl\Web\Url;

class AddNodeForm extends CompatForm
{
    use Sort;
    use Translation;

    /** @var Storage */
    protected $storage;

    /** @var ?BpConfig */
    protected $bp;

    /** @var ?BpNode */
    protected $parent;

    /** @var SessionNamespace */
    protected $session;

    /**
     * Set the storage to use
     *
     * @param Storage $storage
     *
     * @return $this
     */
    public function setStorage(Storage $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Set the affected configuration
     *
     * @param BpConfig $bp
     *
     * @return $this
     */
    public function setProcess(BpConfig $bp): self
    {
        $this->bp = $bp;

        return $this;
    }

    /**
     * Set the affected sub-process
     *
     * @param ?BpNode $node
     *
     * @return $this
     */
    public function setParentNode(BpNode $node = null): self
    {
        $this->parent = $node;

        return $this;
    }

    /**
     * Set the user's session
     *
     * @param SessionNamespace $session
     *
     * @return $this
     */
    public function setSession(SessionNamespace $session): self
    {
        $this->session = $session;

        return $this;
    }

    protected function assemble()
    {
        if ($this->parent !== null) {
            $title = sprintf($this->translate('Add a node to %s'), $this->parent->getAlias());
            $nodeTypes = [
                'host' => $this->translate('Host'),
                'service' => $this->translate('Service'),
                'process' => $this->translate('Existing Process'),
                'new-process' => $this->translate('New Process')
            ];
        } else {
            $title = $this->translate('Add a new root node');
            if (! $this->bp->isEmpty()) {
                $nodeTypes = [
                    'process' => $this->translate('Existing Process'),
                    'new-process' => $this->translate('New Process')
                ];
            } else {
                $nodeTypes = [];
            }
        }

        $this->addHtml(new HtmlElement('h2', null, Text::create($title)));

        if (! empty($nodeTypes)) {
            $this->addElement('select', 'node_type', [
                'label' => $this->translate('Node type'),
                'options' => array_merge(
                    ['' => ' - ' . $this->translate('Please choose') . ' - '],
                    $nodeTypes
                ),
                'disabledOptions' => [''],
                'class' => 'autosubmit',
                'required' => true,
                'ignore' => true
            ]);

            $nodeType = $this->getPopulatedValue('node_type');
        } else {
            $nodeType = 'new-process';
        }

        if ($nodeType === 'new-process') {
            $this->assembleNewProcessElements();
        } elseif ($nodeType === 'process') {
            $this->assembleExistingProcessElements();
        } elseif ($nodeType === 'host') {
            $this->assembleHostElements();
        } elseif ($nodeType === 'service') {
            $this->assembleServiceElements();
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Add Process')
        ]);
    }

    protected function assembleNewProcessElements(): void
    {
        $this->addElement('text', 'name', [
            'required'     => true,
            'ignore'       => true,
            'label'        => $this->translate('ID'),
            'description'  => $this->translate('This is the unique identifier of this process'),
            'validators'   => [
                'callback' => function ($value, $validator) {
                    if ($this->parent !== null ? $this->parent->hasChild($value) : $this->bp->hasRootNode($value)) {
                        $validator->addMessage(
                            sprintf($this->translate('%s is already defined in this process'), $value)
                        );

                        return false;
                    }

                    return true;
                }
            ]
        ]);

        $this->addElement('text', 'alias', [
            'label'       => $this->translate('Display Name'),
            'description' => $this->translate(
                'Usually this name will be shown for this node. Equals ID if not given'
            ),
        ]);

        $this->addElement('select', 'operator', [
            'required'     => true,
            'label'        => $this->translate('Operator'),
            'multiOptions' => Node::getOperators()
        ]);

        $display = 1;
        if (! $this->bp->isEmpty() && $this->bp->getMetadata()->isManuallyOrdered()) {
            $rootNodes = self::applyManualSorting($this->bp->getRootNodes());
            $display = end($rootNodes)->getDisplay() + 1;
        }
        $this->addElement('select', 'display', [
            'required'     => true,
            'label'        => $this->translate('Visualization'),
            'description'  => $this->translate('Where to show this process'),
            'value'        => $this->parent !== null ? '0' : "$display",
            'multiOptions' => [
                "$display" => $this->translate('Toplevel Process'),
                '0' => $this->translate('Subprocess only'),
            ]
        ]);

        $this->addElement('text', 'infoUrl', [
            'label'       => $this->translate('Info URL'),
            'description' => $this->translate('URL pointing to more information about this node')
        ]);
    }

    protected function assembleExistingProcessElements(): void
    {
        $termValidator = function (array $terms) {
            foreach ($terms as $term) {
                /** @var TermInput\ValidatedTerm $term */
                $nodeName = $term->getSearchValue();
                if ($nodeName[0] === '@') {
                    if ($this->parent === null) {
                        $term->setMessage($this->translate('Imported nodes cannot be used as root nodes'));
                    } elseif (strpos($nodeName, ':') === false) {
                        $term->setMessage($this->translate('Missing node name'));
                    } else {
                        [$config, $nodeName] = Str::trimSplit(substr($nodeName, 1), ':', 2);
                        if (! $this->storage->hasProcess($config)) {
                            $term->setMessage($this->translate('Config does not exist or access has been denied'));
                        } else {
                            try {
                                $bp = $this->storage->loadProcess($config);
                            } catch (Exception $e) {
                                $term->setMessage(
                                    sprintf($this->translate('Cannot load config: %s'), $e->getMessage())
                                );
                            }

                            if (isset($bp)) {
                                if (! $bp->hasNode($nodeName)) {
                                    $term->setMessage($this->translate('No node with this name found in config'));
                                } else {
                                    $term->setLabel($bp->getNode($nodeName)->getAlias());
                                }
                            }
                        }
                    }
                } elseif (! $this->bp->hasNode($nodeName)) {
                    $term->setMessage($this->translate('No node with this name found in config'));
                } else {
                    $term->setLabel($this->bp->getNode($nodeName)->getAlias());
                }

                if ($this->parent !== null && $this->parent->hasChild($term->getSearchValue())) {
                    $term->setMessage($this->translate('Already defined in this process'));
                }

                if ($this->parent !== null && $term->getSearchValue() === $this->parent->getName()) {
                    $term->setMessage($this->translate('Results in a parent/child loop'));
                }
            }
        };

        $this->addElement(
            (new TermInput('children'))
                ->setRequired()
                ->setVerticalTermDirection()
                ->setLabel($this->translate('Process Nodes'))
                ->setSuggestionUrl(Url::fromPath('businessprocess/suggestions/process', [
                    'node' => isset($this->parent) ? $this->parent->getName() : null,
                    'config' => $this->bp->getName(),
                    'showCompact' => true,
                    '_disableLayout' => true
                ]))
                ->on(TermInput::ON_ENRICH, $termValidator)
                ->on(TermInput::ON_ADD, $termValidator)
                ->on(TermInput::ON_PASTE, $termValidator)
                ->on(TermInput::ON_SAVE, $termValidator)
        );
    }

    protected function assembleHostElements(): void
    {
        if ($this->bp->getBackend() instanceof MonitoringBackend) {
            $suggestionsPath = 'businessprocess/suggestions/monitoring-host';
        } else {
            $suggestionsPath = 'businessprocess/suggestions/icingadb-host';
        }

        $this->addElement($this->createChildrenElementForObjects(
            $this->translate('Hosts'),
            $suggestionsPath
        ));

        $this->addElement('checkbox', 'host_override', [
            'ignore' => true,
            'class' => 'autosubmit',
            'label' => $this->translate('Override Host State')
        ]);
        if ($this->getPopulatedValue('host_override') === 'y') {
            $this->addElement(new IplStateOverrides('stateOverrides', [
                'label' => $this->translate('State Overrides'),
                'options' => [
                    0 => $this->translate('UP'),
                    1 => $this->translate('DOWN'),
                    99 => $this->translate('PENDING')
                ]
            ]));
        }
    }

    protected function assembleServiceElements(): void
    {
        if ($this->bp->getBackend() instanceof MonitoringBackend) {
            $suggestionsPath = 'businessprocess/suggestions/monitoring-service';
        } else {
            $suggestionsPath = 'businessprocess/suggestions/icingadb-service';
        }

        $this->addElement($this->createChildrenElementForObjects(
            $this->translate('Services'),
            $suggestionsPath
        ));

        $this->addElement('checkbox', 'service_override', [
            'ignore' => true,
            'class' => 'autosubmit',
            'label' => $this->translate('Override Service State')
        ]);
        if ($this->getPopulatedValue('service_override') === 'y') {
            $this->addElement(new IplStateOverrides('stateOverrides', [
                'label' => $this->translate('State Overrides'),
                'options' => [
                    0 => $this->translate('OK'),
                    1 => $this->translate('WARNING'),
                    2 => $this->translate('CRITICAL'),
                    3 => $this->translate('UNKNOWN'),
                    99 => $this->translate('PENDING'),
                ]
            ]));
        }
    }

    protected function createChildrenElementForObjects(string $label, string $suggestionsPath): TermInput
    {
        $termValidator = function (array $terms) {
            (new HostServiceTermValidator())
                ->setParent($this->parent)
                ->isValid($terms);
        };

        return (new TermInput('children'))
            ->setRequired()
            ->setLabel($label)
            ->setVerticalTermDirection()
            ->setSuggestionUrl(Url::fromPath($suggestionsPath, [
                'node' => isset($this->parent) ? $this->parent->getName() : null,
                'config' => $this->bp->getName(),
                'showCompact' => true,
                '_disableLayout' => true
            ]))
            ->on(TermInput::ON_ENRICH, $termValidator)
            ->on(TermInput::ON_ADD, $termValidator)
            ->on(TermInput::ON_PASTE, $termValidator)
            ->on(TermInput::ON_SAVE, $termValidator);
    }

    protected function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $nodeType = $this->getValue('node_type');
        if (! $nodeType || $nodeType === 'new-process') {
            $properties = $this->getValues();
            if (! $properties['alias']) {
                unset($properties['alias']);
            }

            if ($this->parent !== null) {
                $properties['parentName'] = $this->parent->getName();
            }

            $changes->createNode(BpConfig::escapeName($this->getValue('name')), $properties);
        } else {
            $children = array_unique(array_map(function ($term) {
                return $term->getSearchValue();
            }, $this->getElement('children')->getTerms()));

            if ($nodeType === 'host' || $nodeType === 'service') {
                $stateOverrides = $this->getValue('stateOverrides');
                if (! empty($stateOverrides)) {
                    $childOverrides = [];
                    foreach ($children as $nodeName) {
                        $childOverrides[$nodeName] = $stateOverrides;
                    }

                    $changes->modifyNode($this->parent, [
                        'stateOverrides' => array_merge($this->parent->getStateOverrides(), $childOverrides)
                    ]);
                }
            }

            if ($this->parent !== null) {
                $changes->addChildrenToNode($children, $this->parent);
            } else {
                foreach ($children as $nodeName) {
                    $changes->copyNode($nodeName);
                }
            }
        }

        unset($changes);
    }
}
