<?php

namespace Drupal\firewall;

use Drupal\Core\Form\ConfirmFormBase;

class DeleteForm extends ConfirmFormBase
{
    protected $ip;
    public function getFormID()
    {
        return 'firewall_delete';
    }
    public function getQuestion()
    {
        return t('Are you sure you want to delete %ip?', array(
            '%ip' => $this->ip,
        ));
    }
    public function getConfirmText()
    {
        return t('Delete');
    }
    public function getCancelRoute()
    {
        return array(
            'route_name' => 'firewall.list',
        );
    }
    public function buildForm(array $form, array &$form_state, $ip = '')
    {
        $this->ip = $ip;

        return parent::buildForm($form, $form_state);
    }
    public function submitForm(array &$form, array &$form_state)
    {
        FirewallStorage::delete($this->ip);
        watchdog('firewall', 'Deleted IP address %ip.', array(
            '%ip' => $this->ip,
        ));
        drupal_set_message(t('Deleted IP address %ip.', array(
            '%ip' => $this->ip,
        )));
        $form_state['redirect_route']['route_name'] = 'firewall.list';
    }
}
