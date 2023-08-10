<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Businessprocess\Web\Form\Element\IplStateOverrides;
use Icinga\Module\Businessprocess\Web\Form\Validator\HostServiceTermValidator;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;
use ipl\Html\Attributes;
use ipl\Html\FormattedString;
use ipl\Html\HtmlElement;
use ipl\Html\ValidHtml;
use ipl\I18n\Translation;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormElement\TermInput\ValidatedTerm;
use ipl\Web\Url;

class EditNodeForm extends CompatForm
{
    use Translation;

    /** @var ?BpConfig */
    protected $bp;

    /** @var ?Node */
    protected $node;

    /** @var ?BpNode */
    protected $parent;

    /** @var SessionNamespace */
    protected $session;

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
     * Set the affected node
     *
     * @param Node $node
     *
     * @return $this
     */
    public function setNode(Node $node): self
    {
        $this->node = $node;

        $this->populate([
            'node-search' => $node->getName(),
            'node-label' => $node->getAlias()
        ]);

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

        if ($this->node !== null) {
            $stateOverrides = $this->parent->getStateOverrides($this->node->getName());
            if (! empty($stateOverrides)) {
                $this->populate([
                    'overrideStates' => 'y',
                    'stateOverrides' => $stateOverrides
                ]);
            }
        }

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

    /**
     * Identify and return the node the user has chosen
     *
     * @return Node
     */
    protected function identifyChosenNode(): Node
    {
        $userInput = $this->getPopulatedValue('node');
        $nodeName = $this->getPopulatedValue('node-search');
        $nodeLabel = $this->getPopulatedValue('node-label');

        if ($nodeName && $userInput === $nodeLabel) {
            // User accepted a suggestion and didn't change it manually
            $node = $this->bp->getNode($nodeName);
        } elseif ($userInput && (! $nodeLabel || $userInput !== $nodeLabel)) {
            // User didn't choose a suggestion or changed it manually
            $node = $this->bp->getNode(BpConfig::joinNodeName($userInput, 'Hoststatus'));
        } else {
            // If the search and user input are both empty, it can only be the initial value
            $node = $this->node;
        }

        return $node;
    }

    protected function assemble()
    {
        $this->addHtml(new HtmlElement('h2', null, FormattedString::create(
            $this->translate('Modify "%s"'),
            $this->node->getAlias() ?? $this->node->getName()
        )));

        if ($this->node instanceof ServiceNode) {
            $this->assembleServiceElements();
        } else {
            $this->assembleHostElements();
        }

        $this->addElement('submit', 'btn_submit', [
            'label' => $this->translate('Save Changes')
        ]);
    }

    protected function assembleServiceElements(): void
    {
        if ($this->bp->getBackend() instanceof MonitoringBackend) {
            $suggestionsPath = 'businessprocess/suggestions/monitoring-service';
        } else {
            $suggestionsPath = 'businessprocess/suggestions/icingadb-service';
        }

        $node = $this->identifyChosenNode();

        $this->addHtml($this->createSearchInput(
            $this->translate('Service'),
            $node->getAlias() ?? $node->getName(),
            $suggestionsPath
        ));

        $this->addElement('checkbox', 'overrideStates', [
            'ignore' => true,
            'class' => 'autosubmit',
            'label' => $this->translate('Override Service State')
        ]);
        if ($this->getPopulatedValue('overrideStates') === 'y') {
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

    protected function assembleHostElements(): void
    {
        if ($this->bp->getBackend() instanceof MonitoringBackend) {
            $suggestionsPath = 'businessprocess/suggestions/monitoring-host';
        } else {
            $suggestionsPath = 'businessprocess/suggestions/icingadb-host';
        }

        $node = $this->identifyChosenNode();

        $this->addHtml($this->createSearchInput(
            $this->translate('Host'),
            $node->getAlias() ?? $node->getName(),
            $suggestionsPath
        ));

        $this->addElement('checkbox', 'overrideStates', [
            'ignore' => true,
            'class' => 'autosubmit',
            'label' => $this->translate('Override Host State')
        ]);
        if ($this->getPopulatedValue('overrideStates') === 'y') {
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

    protected function createSearchInput(string $label, string $value, string $suggestionsPath): ValidHtml
    {
        $userInput = $this->createElement('text', 'node', [
            'ignore' => true,
            'required' => true,
            'autocomplete' => 'off',
            'label' => $label,
            'value' => $value,
            'data-enrichment-type' => 'completion',
            'data-term-suggestions' => '#node-suggestions',
            'data-suggest-url' => Url::fromPath($suggestionsPath, [
                'node' => isset($this->parent) ? $this->parent->getName() : null,
                'config' => $this->bp->getName(),
                'showCompact' => true,
                '_disableLayout' => true
            ]),
            'validators' => ['callback' => function ($_, $validator) {
                $newName = $this->identifyChosenNode()->getName();
                if ($newName === $this->node->getName()) {
                    return true;
                }

                $term = new ValidatedTerm($newName);

                (new HostServiceTermValidator())
                    ->setParent($this->parent)
                    ->isValid($term);

                if (! $term->isValid()) {
                    $validator->addMessage($term->getMessage());
                    return false;
                }

                return true;
            }]
        ]);

        $fieldset = new HtmlElement('fieldset');

        $searchInput = $this->createElement('hidden', 'node-search', ['ignore' => true]);
        $this->registerElement($searchInput);
        $fieldset->addHtml($searchInput);

        $labelInput = $this->createElement('hidden', 'node-label', ['ignore' => true]);
        $this->registerElement($labelInput);
        $fieldset->addHtml($labelInput);

        $this->registerElement($userInput);
        $this->decorate($userInput);

        $fieldset->addHtml(
            $userInput,
            new HtmlElement('div', Attributes::create([
                'id' => 'node-suggestions',
                'class' => 'search-suggestions'
            ]))
        );

        return $fieldset;
    }

    protected function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $children = $this->parent->getChildNames();
        $previousPos = array_search($this->node->getName(), $children, true);
        $node = $this->identifyChosenNode();
        $nodeName = $node->getName();

        $changes->deleteNode($this->node, $this->parent->getName());
        $changes->addChildrenToNode([$nodeName], $this->parent);

        $stateOverrides = $this->getValue('stateOverrides');
        if (! empty($stateOverrides)) {
            $changes->modifyNode($this->parent, [
                'stateOverrides' => array_merge($this->parent->getStateOverrides(), [
                    $nodeName => $stateOverrides
                ])
            ]);
        }

        if ($this->bp->getMetadata()->isManuallyOrdered() && ($newPos = count($children) - 1) > $previousPos) {
            $changes->moveNode(
                $node,
                $newPos,
                $previousPos,
                $this->parent->getName(),
                $this->parent->getName()
            );
        }

        unset($changes);
    }
}
