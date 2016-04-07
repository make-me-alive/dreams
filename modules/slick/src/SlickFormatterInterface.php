<?php

/**
 * @file
 * Contains \Drupal\slick\SlickFormatterInterface.
 */

namespace Drupal\slick;

/**
 * Defines re-usable services and functions for slick field plugins.
 */
interface SlickFormatterInterface {

  /**
   * Returns the slick field formatter and custom coded settings.
   *
   * @param array $items
   *   The items to prepare settings for.
   * @param array $settings
   *   The original settings provided by UI.
   *
   * @return array
   *   The combined settings of a slick field formatter.
   */
  public function buildSettings($items, $langcode, $settings = []);

}
