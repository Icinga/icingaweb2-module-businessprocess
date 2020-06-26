<?php

// Avoid complaints about missing namespace and invalid class name
// @codingStandardsIgnoreStart
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

            $html .= '<label><span>' . $this->view->escape($label) . '</span>';
            $html .= $this->view->formSelect(
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
