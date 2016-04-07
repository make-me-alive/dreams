<?php

namespace Drupal\firewall;

class ListController
{
    public function content()
    {
        $items = array();
        foreach (FirewallStorage::getAll() as $ip) {
            $items[] = array(
                '#type' => 'link',
                '#title' => $ip,
                '#route_name' => 'firewall.delete',
                '#route_parameters' => array(
                    'ip' => $ip,
                ),
            );
        }
        $items[] = array(
            '#type' => 'link',
            '#title' => t('Add'),
            '#route_name' => 'firewall.add',
        );

        return array(
            '#theme' => 'item_list',
            '#items' => $items,
        );
    }
}
