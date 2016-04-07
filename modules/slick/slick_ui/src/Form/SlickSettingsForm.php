<?php

/**
 * @file
 * Contains \Drupal\slick_ui\Form\SlickSettingsForm.
 */

namespace Drupal\slick_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the Slick admin settings form.
 */
class SlickSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'slick_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['slick.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('slick.settings');

    $form['admin_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Slick admin CSS'),
      '#description'   => $this->t('Uncheck if slick admin CSS has compatibility issues against your admin theme.'),
      '#default_value' => $config->get('admin_css'),
    ];

    $form['module_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Slick module slick.theme.css'),
      '#description'   => $this->t('Uncheck to permanently disable the module slick.theme.css, normally included along with skins.'),
      '#default_value' => $config->get('module_css'),
    ];

    $form['slick_css'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Slick library slick-theme.css'),
      '#description'   => $this->t('Uncheck to permanently disable the optional slick-theme.css, normally included along with skins.'),
      '#default_value' => $config->get('slick_css'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('slick.settings')
      ->set('admin_css', $form_state->getValue('admin_css'))
      ->set('slick_css', $form_state->getValue('slick_css'))
      ->set('module_css', $form_state->getValue('module_css'))
      ->save();

    // Invalidate the library discovery cache to update new assets.
    \Drupal::service('library.discovery')->clearCachedDefinitions();

    parent::submitForm($form, $form_state);
  }

}
