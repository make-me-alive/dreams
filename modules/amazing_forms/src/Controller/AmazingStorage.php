<?php

namespace Drupal\amazing_forms;

class BdContactStorage
{
    public static function getAll()
    {
        $result = db_query('SELECT * FROM {bd_contact}')->fetchAllAssoc('id');

        return $result;
    }

    public static function exists($id)
    {
        return (bool) $this->get($id);
    }

    public static function get($id)
    {
        $result = db_query('SELECT * FROM {bd_contact} WHERE id = :id', array(':id' => $id))->fetchAllAssoc('id');
        if ($result) {
            return $result[$id];
        } else {
            return false;
        }
    }

    public static function add($name, $message)
    {
        db_insert('bd_contact')->fields(array(
      'name' => $name,
      'message' => $message,
    ))->execute();
    }

    public static function edit($id, $name, $message)
    {
        db_update('bd_contact')->fields(array(
      'name' => $name,
      'message' => $message,
    ))
    ->condition('id', $id)
    ->execute();
    }

    public static function delete($id)
    {
        db_delete('bd_contact')->condition('id', $id)->execute();
    }
}
