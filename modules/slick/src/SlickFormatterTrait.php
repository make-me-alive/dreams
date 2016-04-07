<?php

/**
 * @file
 * Contains \Drupal\slick\SlickFormatterTrait.
 */

namespace Drupal\slick;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * A Trait common for slick formatters.
 */
trait SlickFormatterTrait {

  /**
   * Returns the slick field formatter service.
   */
  public function formatter() {
    return $this->formatter;
  }

  /**
   * Returns the slick service shortcut.
   */
  public function manager() {
    return $this->formatter->manager();
  }

  /**
   * Returns the slick admin service shortcut.
   */
  public function admin() {
    return \Drupal::service('slick.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->admin()->settingsSummary($this);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

}
