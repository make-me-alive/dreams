<?php

/**
 * @file
 * Contains \Drupal\slick\Plugin\Field\FieldFormatter\SlickTextFormatter.
 */

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\slick\SlickDefault;
use Drupal\slick\SlickFormatterTrait;

/**
 * Plugin implementation of the 'slick_text' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_text",
 *   label = @Translation("Slick carousel"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class SlickTextFormatter extends SlickFormatterBase {
  use SlickFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::baseSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Early opt-out if the field is empty.
    if (!isset($items[0])) {
      return [];
    }

    $build = $this->formatter->buildSettings($items, $langcode, $this->getSettings());
    $build['settings']['vanilla'] = TRUE;

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $key => $item) {
      $slide = [
        '#type'     => 'processed_text',
        '#text'     => $item->value,
        '#format'   => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
      $build['items'][$key] = $slide;
      unset($slide);
    }

    return $this->manager()->build($build);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = [
      'current_view_mode' => $this->viewMode,
      'settings'          => $this->getSettings(),
    ];

    $this->admin()->openingForm($element, $definition);
    $this->admin()->closingForm($element, $definition);
    $element['layout']['#access'] = FALSE;
    return $element;
  }

}
