<?php

/**
 * @file
 * Contains \Drupal\slick_views\Plugin\views\style\SlickViews.
 */

namespace Drupal\slick_views\Plugin\views\style;

use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\slick\Entity\Slick;
use Drupal\slick\SlickDefault;
use Drupal\slick\SlickManagerInterface;

/**
 * Slick style plugin.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "slick",
 *   title = @Translation("Slick carousel"),
 *   help = @Translation("Display the results in a Slick carousel."),
 *   theme = "slick_wrapper",
 *   register_theme = FALSE,
 *   display_types = {"normal"}
 * )
 */
class SlickViews extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * Constructs a SlickManager object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SlickManagerInterface $manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('slick.manager'));
  }

  /**
   * Returns the slick admin.
   */
  public function admin() {
    return \Drupal::service('slick.admin');
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = ['thumbnail' => ['default' => ''], 'id' => ['default' => '']];
    foreach (SlickDefault::fieldableSettings() as $key => $value) {
      $options[$key] = ['default' => $value];
    }
    return $options + parent::defineOptions();
  }

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $admin       = $this->admin();
    $field_names = $this->displayHandler->getFieldLabels();
    $definitions = [
      'vanilla',
      'captions',
      'layouts',
      'images',
      'links',
      'overlays',
      'titles',
      'thumbnails',
      'thumb_captions',
      'classes',
      'id',
    ];

    $definition = [];
    foreach ($this->displayHandler->getOption('fields') as $field => $handler) {
      if (isset($handler['type'])) {
        switch ($handler['type']) {
          case 'image':
          case 'media':
            $definition['images'][$field] = $field_names[$field];
            $definition['overlays'][$field] = $field_names[$field];
            $definition['thumbnails'][$field] = $field_names[$field];
            break;

          case 'list_key':
            $definition['layouts'][$field] = $field_names[$field];
            break;

          case 'entity_reference':
          case 'text':
          case 'string':
          case 'link':
            $definition['links'][$field] = $field_names[$field];
            $definition['titles'][$field] = $field_names[$field];
            if ($handler['type'] != 'link') {
              $definition['thumb_captions'][$field] = $field_names[$field];
            }
            break;
        }

        if (in_array($handler['type'], ['list_key', 'entity_reference', 'text', 'string'])) {
          $definition['classes'][$field] = $field_names[$field];
        }
      }

      // Content: title is not really a field, unless title.module installed.
      if (isset($handler['field'])) {
        if ($handler['field'] == 'title') {
          $definition['classes'][$field] = $field_names[$field];
          $definition['titles'][$field] = $field_names[$field];
          $definition['thumb_captions'][$field] = $field_names[$field];
        }

        if ($handler['field'] == 'view_node') {
          $definition['links'][$field] = $field_names[$field];
        }
      }

      // Captions can be anything to get custom works going.
      $definition['captions'][$field] = $field_names[$field];
    }

    $definition['settings'] = $this->options;
    $definition['current_view_mode'] = $this->view->current_display;
    foreach ($definitions as $key) {
      $definition[$key] = isset($definition[$key]) ? $definition[$key] : [];
    }

    $admin->openingForm($form, $definition);
    $admin->gridForm($form, $definition);
    $admin->fieldableForm($form, $definition);
    $admin->closingForm($form, $definition);

    $title = '<p class="form--slick__header form--slick__title">';
    $title .= $this->t('Check Vanilla slick for custom markups.<small>Otherwise slick markups are added. Add the supported fields to appear here.</small>');
    $title .= '</p>';
    $form['opening']['#markup'] = '<div class="form--slick form--views form--half form--vanilla has-tooltip">' . $title;
  }

  /**
   * Overrides StylePluginBase::render().
   */
  public function render() {
    $view      = $this->view;
    $settings  = $this->options;
    $view_name = $view->storage->id();
    $view_mode = $view->current_display;
    $count     = count($view->result);
    $id        = Slick::getHtmlId("slick-views-{$view_name}-{$view_mode}", $settings['id']);
    $asnavfor  = $settings['optionset_thumbnail'];
    $classes   = [
      Html::cleanCssIdentifier('slick--view--' . $view_name),
      Html::cleanCssIdentifier('slick--view--' . $view_name . '--' . $view_mode),
    ];

    $settings += [
      'count'             => $count,
      'current_view_mode' => $view_mode,
      'nav'               => !$settings['vanilla'] && $asnavfor && isset($view->result[1]),
      'caption'           => array_filter($settings['caption']),
      'view_name'         => $view_name,
      'id'                => $id,
      'cache_metadata'    => ['keys'=> [$id, $view_mode, $settings['optionset']]],
      'attributes'        => ['class' => $classes],
    ];

    $elements = [];
    foreach ($this->renderGrouping($view->result, $settings['grouping']) as $rows) {
      $build = $this->buildElements($rows, $settings);
      $build['settings'] = $settings;
      $elements = $this->manager->build($build);
      unset($build);
    }
    return $elements;
  }

  /**
   * Returns slick contents.
   */
  public function buildElements($rows, $settings = []) {
    $build = [];
    $view  = $this->view;
    $keys  = array_keys($view->field);

    foreach ($rows as $index => $row) {
      $view->row_index = $index;

      $slide = $thumb = [];
      $slide['settings'] = $settings;

      if (!empty($settings['class'])) {
        $classes = $this->getFieldString($row, $settings['class'], $index);
        $slide['settings']['class'] = empty($classes[$index]) ? [] : $classes[$index];
      }

      // Use Vanilla slick if so configured, ignoring Slick markups.
      if ($settings['vanilla']) {
        $slide['slide'] = $view->rowPlugin->render($row);
      }
      else {
        // Add main image and thumbnail fields if so configured.
        $slide['slide'] = $this->getFieldRendered($index, $settings['image']);

        // Add caption fields if so configured.
        $slide['caption']['title']   = $this->getFieldRendered($index, $settings['title'], TRUE);
        $slide['caption']['link']    = $this->getFieldRendered($index, $settings['link']);
        $slide['caption']['overlay'] = $this->getFieldRendered($index, $settings['overlay']);

        // Add extra caption fields if so configured.
        if ($captions = $settings['caption']) {
          $caption_items = [];
          foreach ($captions as $key => $caption) {
            $caption_rendered = $this->getField($index, $caption);
            if (empty($caption_rendered)) {
              continue;
            }

            if (in_array($caption, array_values($keys))) {
              $caption_items[$key]['#markup'] = $caption_rendered;
            }
          }
          $slide['caption']['data'] = $caption_items;
        }

        // Add layout field, may be a list field, or builtin layout options.
        if ($layout = $settings['layout']) {
          if (strpos($layout, 'field_') !== FALSE) {
            $settings['layout'] = strip_tags($this->getField($index, $layout));
          }
          $slide['settings']['layout'] = $settings['layout'];
        }

        if ($settings['nav']) {
          $thumb['slide']   = $this->getFieldRendered($index, $settings['thumbnail']);
          $thumb['caption'] = $this->getFieldRendered($index, $settings['thumbnail_caption']);
          $build['thumb']['items'][$index] = $thumb;
        }
      }

      $build['items'][$index] = $slide;
      unset($slide, $thumb);
    }
    unset($view->row_index);
    return $build;
  }

  /**
   * Returns the rendered field, either string or array.
   */
  public function getFieldRendered($index, $field_name = '', $restricted = FALSE) {
    if (!empty($field_name) && $output = $this->getField($index, $field_name)) {
      return is_array($output) ? $output : ['#markup' => ($restricted ? Xss::filterAdmin($output) : $output)];
    }
    return [];
  }

  /**
   * Returns the renderable array of field containing rendered and raw data.
   */
  public function getFieldRenderable($row, $field_name, $multiple = FALSE) {
    $field = $this->view->field[$field_name]->handlerType . '_' . $field_name;
    return $multiple && isset($row->{$field})? $row->{$field} : (isset($row->{$field}[0]) ? $row->{$field}[0] : []);
  }

  /**
   * Returns the string values for the expected Title, ET, List, Term.
   */
  public function getFieldString($row, $field_name, $idx) {
    $values   = [];
    $renderer = $this->manager->getRenderer();

    // Content title/List/Text, either as link or plain text.
    if ($value = $this->getFieldString($idx, $field_name)) {
      $value = is_string($value) ? $value : (isset($value[0]['value'])? $value[0]['value'] : '');
      $values[$idx] = empty($value) ? '' : Html::cleanCssIdentifier(Unicode::strtolower($value));
    }

    // Term reference/ET, either as link or plain text.
    if ($renderable = $this->getFieldRenderable($row, $field_name, TRUE)) {
      $value = [];
      foreach ($renderable as $key => $render) {
        $class = isset($render['rendered']['#title']) ? $render['rendered']['#title'] : $renderer->render($render['rendered']);
        $class = strip_tags($class);
        $value[$key] = Html::cleanCssIdentifier(Unicode::strtolower($class));
      }
      $values[$idx] = empty($value) ? '' : implode(' ', $value);
    }
    return $values;
  }

}
