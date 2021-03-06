<?php

/**
 * @file
 * Contains \Drupal\slick_ui\Controller\SlickListBuilder.
 */

namespace Drupal\slick_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\slick\SlickManagerInterface;

/**
 * Provides a listing of Slick optionsets.
 */
class SlickListBuilder extends DraggableListBuilder {

  /**
   * The slick manager.
   *
   * @var \Drupal\slick\SlickManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new SlickListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\slick\SlickManagerInterface $manager
   *   The slick manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, SlickManagerInterface $manager) {
    parent::__construct($entity_type, $storage);
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('slick.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slick_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = array(
      'label'       => t('Optionset'),
      'breakpoints' => t('Breakpoints'),
      'group'       => t('Group'),
      'skin'        => t('Skin'),
    );

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $skins = $this->manager->getSkins()['skins'];
    $skin  = $entity->getSkin();

    $row['label'] = Html::escape($this->getLabel($entity));
    $row['breakpoints']['#markup'] = $entity->getBreakpoints();
    $row['group']['#markup'] = $entity->getGroup()?: t('all');
    $row['skin']['#markup'] = Html::escape($skin);

    // Has to do this separately as concat with HTML tag is no joy.
    if (isset($skins[$skin]['description'])) {
      $row['skin']['#markup'] = SafeMarkup::format('@skin:<p class="description">@description</p>', ['@skin' => $skin, '@description' => $skins[$skin]['description']]);
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if (isset($operations['edit'])) {
      $operations['edit']['title'] = t('Configure');
    }

    $operations['duplicate'] = array(
      'title'  => t('Duplicate'),
      'weight' => 15,
      'url'    => $entity->urlInfo('duplicate-form'),
    );

    if ($entity->id() == 'default') {
      unset($operations['delete']);
    }

    return $operations;
  }

  /**
   * Adds some descriptive text to the slick optionsets list.
   *
   * @return array
   *   Renderable array.
   *
   * @see admin/config/development/configuration/single/export
   */
  public function render() {
    $build['description'] = array(
      '#markup' => $this->t("<p>Manage the Slick optionsets. Optionsets are Config Entities.</p><p>By default, when this module is enabled, a single optionset is created from configuration. Install Slick example module to speed up by cloning them. Use the Operations column to edit, clone and delete optionsets.</p>"),
    );

    $build[] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    drupal_set_message($this->t('The optionsets order has been updated.'));
  }

}
