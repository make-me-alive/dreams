<?php

namespace Drupal\firewall;

class FirewallStorage
{
    public static function getAll()
    {
        return db_query('SELECT ip FROM {firewall}')->fetchCol();
    }
    public static function exists($ip)
    {
        $result = db_query('SELECT 1 FROM {firewall} WHERE ip = :ip', array(
            ':ip' => $ip,
        ))->fetchField();

        return (bool) $result;
    }
    public static function add($ip)
    {
        db_insert('firewall')->fields(array(
            'ip' => $ip,
        ))->execute();
    }
    public static function delete($ip)
    {
        db_delete('firewall')->condition('ip', $ip)->execute();
    }
}
