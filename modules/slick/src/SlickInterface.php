<?php

/**
 * @file
 * Contains \Drupal\slick\SlickInterface.
 */

namespace Drupal\slick;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining Slick entity.
 */
interface SlickInterface extends ConfigEntityInterface {

  /**
   * Returns the number of breakpoints.
   *
   * @return int
   *   The number of the provided breakpoints.
   */
  public function getBreakpoints();

  /**
   * Returns the Slick skin.
   *
   * @return string
   *   The name of the Slick skin.
   */
  public function getSkin();

  /**
   * Returns the Slick options by group, or property.
   *
   * @param string $group
   *   The name of setting group: settings, responsives.
   * @param string $property
   *   The name of specific property: prevArrow, nexArrow.
   *
   * @return mixed|array|NULL
   *   Available options by $group, $property, all, or NULL.
   */
  public function getOptions($group = NULL, $property = NULL);

  /**
   * Returns the array of slick settings.
   *
   * @return array
   *   The array of settings.
   */
  public function getSettings();

  /**
   * Returns the value of a slick setting.
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  public function getSetting($setting_name);

}
