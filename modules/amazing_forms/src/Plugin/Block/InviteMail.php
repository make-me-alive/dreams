<?php
// this page is for creating a custom block and fetching the created form (contributeform.php) into it

/**
 * Provides a 'Invite Mail' Block
 *
 * @Block(
 *   id = "invite_mail_block",
 *   admin_label = @Translation("Invite Mail"),
 * )
 */
namespace Drupal\amazing_forms\Plugin\Block;

use Drupal\block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

class InviteMail extends BlockBase {

  // public function blockForm($form, FormStateInterface $form_state) {
  //   return $form;
  // }
// fetch the created form into a block
  public function build(){

    $form = \Drupal::formBuilder()->getForm('Drupal\amazing_forms\Controller\ContributeForm');
    // print render($form);
      return $form;
    //  return 'amazing_forms_contribute_form';
    //return array('#markup' => 'Hello !!');
  }
}
