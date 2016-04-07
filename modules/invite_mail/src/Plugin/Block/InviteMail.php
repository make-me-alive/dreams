<?php

/**
 * Provides a 'Invite Mail' Block
 *
 * @Block(
 *   id = "invite_mail_block",
 *   admin_label = @Translation("Invite Mail"),
 * )
 */
namespace Drupal\invite_mail\Plugin\Block;

use Drupal\block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

class InviteMail extends BlockBase {

  public function blockForm($form, FormStateInterface $form_state) {
    return $form;
  }

  public function build(){

    $form = \Drupal::formBuilder()->getForm('Drupal\invite_mail\Form\InviteMailForm');
    print render($form);
    //return array('#markup' => 'Hello !!');
  }
}

?>
