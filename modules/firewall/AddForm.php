<?php

namespace Drupal\firewall;

use Drupal\Core\Form\FormInterface;

class AddForm implements FormInterface
{
    public function getFormID()
    {
        return 'firewall_add';
    }
    public function buildForm(array $form, array &$form_state)
    {
        $form['ip'] = array(
            '#type' => 'textfield',
            '#title' => t('IP address'),
        );
        $form['actions'] = array(
            '#type' => 'actions',
        );
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Add'),
        );

        return $form;
    }
    public function validateForm(array &$form, array &$form_state)
    {
        $ip = $form_state['values']['ip'];
        if (FirewallStorage::exists($ip)) {
            form_set_error('ip', $form_state, t('This IP address is already listed.'));
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) == false) {
            form_set_error('ip', $form_state, t('Enter a valid IP address.'));
        }
    }
    public function submitForm(array &$form, array &$form_state)
    {
        $ip = $form_state['values']['ip'];
        FirewallStorage::add($ip);
        watchdog('firewall', 'Added IP address %ip.', array(
            '%ip' => $ip,
        ));
        drupal_set_message(t('Added IP address %ip.', array(
            '%ip' => $ip,
        )));
        $form_state['redirect_route']['route_name'] = 'firewall.list';
    }
}
