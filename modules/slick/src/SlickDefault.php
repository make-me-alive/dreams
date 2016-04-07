<?php

/**
 * @file
 * Contains \Drupal\slick\SlickDefault.
 */

namespace Drupal\slick;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 *
 * @see FormatterBase::defaultSettings()
 * @see StylePluginBase::defineOptions().
 */
class SlickDefault {

  /**
   * Returns basic plugin settings.
   */
  public static function baseSettings() {
    return [
      'cache'               => -1,
      'display'             => 'main',
      'current_view_mode'   => '',
      'optionset'           => 'default',
      'optionset_thumbnail' => '',
      'override'            => FALSE,
      'overridables'        => [],
      'preloader'           => FALSE,
      'skin'                => '',
      'skin_arrows'         => '',
      'skin_dots'           => '',
      'skin_thumbnail'      => '',
      'thumbnail_caption'   => '',
    ];
  }

  /**
   * Returns extended field formatter and Views settings.
   */
  public static function extendedSettings() {
    return [
      'box_style'              => '',
      'caption'                => [],
      'image_style'            => '',
      'layout'                 => '',
      'media_switch'           => '',
      'ratio'                  => '',
      'responsive_image_style' => '',
      'thumbnail_style'        => '',
      'thumbnail_hover'        => FALSE,
      'vanilla'                => FALSE,
    ] + self::baseSettings();
  }

  /**
   * Returns fieldable entity formatter and Views settings.
   */
  public static function fieldableSettings() {
    return [
      'class'          => '',
      'dimension'      => '',
      'grid'           => '',
      'grid_medium'    => '',
      'grid_small'     => '',
      'image'          => '',
      'link'           => '',
      'overlay'        => '',
      'preserve_keys'  => FALSE,
      'title'          => '',
      'view_mode'      => '',
      'visible_slides' => '',
    ] + self::extendedSettings();
  }

}
