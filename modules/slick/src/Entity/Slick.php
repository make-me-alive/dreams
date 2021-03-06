<?php

/**
 * @file
 * Contains \Drupal\slick\Entity\Slick.
 */

namespace Drupal\slick\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\slick\SlickInterface;

/**
 * Defines the Slick configuration entity.
 *
 * @ConfigEntityType(
 *   id = "slick",
 *   label = @Translation("Slick optionset"),
 *   list_path = "admin/config/media/slick",
 *   config_prefix = "optionset",
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "label",
 *     "weight",
 *     "group",
 *     "skin",
 *     "breakpoints",
 *     "options",
 *   }
 * )
 */
class Slick extends ConfigEntityBase implements SlickInterface {

  /**
   * The legacy CTools ID for the configurable optionset.
   *
   * @var string
   */
  protected $name;

  /**
   * The human-readable name for the optionset.
   *
   * @var string
   */
  protected $label;

  /**
   * The weight to re-arrange the order of slick optionsets.
   *
   * @var int
   */
  protected $weight;

  /**
   * The optionset group for easy selections.
   *
   * @var string
   */
  protected $group = '';

  /**
   * The skin name for the optionset.
   *
   * @var string
   */
  protected $skin = '';

  /**
   * The number of breakpoints for the optionset.
   *
   * @var int
   */
  protected $breakpoints = 0;

  /**
   * The plugin instance options.
   *
   * @var array
   */
  protected $options = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type = 'slick') {
    parent::__construct($values, $entity_type);
  }

  /**
   * Overrides Drupal\Core\Entity\Entity::id().
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkin() {
    return $this->skin;
  }

  /**
   * {@inheritdoc}
   */
  public function getBreakpoints() {
    return $this->breakpoints;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions($group = NULL, $property = NULL) {
    if ($group) {
      if (is_array($group)) {
        return NestedArray::getValue($this->options, (array) $group);
      }
      elseif (isset($property) && isset($this->options[$group])) {
        return isset($this->options[$group][$property]) ? $this->options[$group][$property] : NULL;
      }
      return $this->options[$group];
    }
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->options['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($setting_name) {
    return isset($this->options['settings'][$setting_name]) ? $this->options['settings'][$setting_name] : NULL;
  }

  /**
   * Returns the Slick responsive settings.
   */
  public function getResponsiveOptions() {
    if (empty($this->breakpoints)) {
      return FALSE;
    }
    $options = [];
    if (isset($this->options['responsives']['responsive'])) {
      $responsives = $this->options['responsives'];
      if ($responsives['responsive']) {
        foreach ($responsives['responsive'] as $delta => $responsive) {
          if (empty($responsives['responsive'][$delta]['breakpoint'])) {
            unset($responsives['responsive'][$delta]);
          }
          if (isset($responsives['responsive'][$delta])) {
            $options[$delta] = $responsive;
          }
        }
      }
    }
    return $options;
  }

  /**
   * Strip out options containing default values so to have real clean JSON.
   */
  public function removeDefaultValues(array $js) {
    $config   = [];
    $defaults = $this->load('default')->getSettings();

    // Remove wasted dependent options if disabled, empty or not.
    $this->removeWastedDependentOptions($js);
    $config = array_diff_assoc($js, $defaults);

    // Remove empty lazyLoad, or left to default ondemand, to avoid JS error.
    if (empty($config['lazyLoad'])) {
      unset($config['lazyLoad']);
    }

    // Do not pass arrows HTML to JSON object as some are enforced.
    foreach (['downArrow', 'downArrowTarget', 'downArrowOffset', 'prevArrow', 'nextArrow'] as $key) {
      unset($config[$key]);
    }

    // Clean up responsive options if similar to defaults.
    if ($responsives = $this->getResponsiveOptions()) {
      $cleaned = [];
      foreach ($responsives as $key => $responsive) {
        $cleaned[$key]['breakpoint'] = $responsives[$key]['breakpoint'];

        // Destroy responsive slick if so configured.
        if ($responsives[$key]['unslick']) {
          $cleaned[$key]['settings'] = 'unslick';
          unset($responsives[$key]['unslick']);
        }
        else {
          // Remove wasted dependent options if disabled, empty or not.
          $this->removeWastedDependentOptions($responsives[$key]['settings']);
          $cleaned[$key]['settings'] = array_diff_assoc($responsives[$key]['settings'], $defaults);
        }
      }
      $config['responsive'] = $cleaned;
    }
    return $config;
  }

  /**
   * Removes wasted dependent options, even if not empty.
   */
  public function removeWastedDependentOptions(array &$js) {
    foreach ($this->getDependentOptions() as $key => $option) {
      if (isset($js[$key]) && empty($js[$key])) {
        foreach ($option as $dependent) {
          unset($js[$dependent]);
        }
      }
    }

    if (!empty($js['useCSS']) && !empty($js['cssEaseBezier'])) {
      $js['cssEase'] = $js['cssEaseBezier'];
    }
    unset($js['cssEaseOverride'], $js['cssEaseBezier']);
  }

  /**
   * Defines the dependent options.
   */
  public static function getDependentOptions() {
    $down_arrow = ['downArrowTarget', 'downArrowOffset'];
    return [
      'arrows'     => ['prevArrow', 'nextArrow', 'downArrow'] + $down_arrow,
      'downArrow'  => $down_arrow,
      'autoplay'   => ['pauseOnHover', 'pauseOnDotsHover', 'autoplaySpeed'],
      'centerMode' => ['centerPadding'],
      'dots'       => ['dotsClass', 'appendDots'],
      'swipe'      => ['swipeToSlide'],
      'useCSS'     => ['cssEase', 'cssEaseBezier', 'cssEaseOverride'],
      'vertical'   => ['verticalSwiping'],
    ];
  }

  /**
   * Returns the HTML ID of a single slick instance.
   */
  public static function getHtmlId($string = 'slick', $id = '') {
    $slick_id = &drupal_static('slick_id', 0);

    // Do not use dynamic Html::getUniqueId, otherwise broken asnavfors.
    return $id ?: Html::getId($string . '-' . ++$slick_id);
  }

  /**
   * Returns HTML or layout related settings, none of JS to shutup notices.
   */
  public static function htmlSettings() {
    return [
      'display'      => 'main',
      'grid'         => '',
      'id'           => '',
      'nav'          => FALSE,
      'media_switch' => '',
      'optionset'    => 'default',
      'skin'         => '',
      'unslick'      => FALSE,
      'vanilla'      => FALSE,
    ];
  }

}
