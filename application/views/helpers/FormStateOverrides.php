<?php

// Avoid complaints about missing namespace and invalid class name
// @codingStandardsIgnoreStart
use Icinga\Web\View;

class Zend_View_Helper_FormStateOverrides extends Zend_View_Helper_FormElement
{
    // @codingStandardsIgnoreEnd

    public function formStateOverrides($name, $value = null, $attribs = null)
    {
        $states = $attribs['states'];
        unset($attribs['states']);
        $attribs['multiple'] = '';

        $html = '';
        foreach ($states as $state => $label) {
            if ($state === 0) {
                continue;
            }

            $chosen = $state;
            if (isset($value[$state])) {
                $chosen = $value[$state];
            }

            $options = [$state => t('Keep actual state')] + $states;

            /** @var View $view */
            $view = $this->view;
            $html .= '<label><span>' . $view->escape($label) . '</span>';
            $html .= $view->formSelect(
                sprintf('%s[%d]', substr($name, 0, -2), $state),
                $chosen,
                $attribs,
                $options
            );
            $html .= '</label>';
        }

        return $html;
    }
}
