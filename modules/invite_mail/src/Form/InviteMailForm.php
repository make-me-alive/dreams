<?php

namespace Drupal\invite_mail\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Database\Schema;


class  InviteMailForm  extends FormBase {

      public function getFormId()
      {
        return 'invite_mail_form';
      }

      public function buildForm(array $form, FormStateInterface $form_state) {

            $form['email'] = array(
              '#type' => 'email',
              '#required' => true,
            );

            $form['submit'] = array(
              '#type' => 'submit',
              '#value' => $this->t('JOIN'),
            );

        return $form;
      }

      public function validateForm(array &$form, FormStateInterface $form_state) {

      }

      public function submitForm(array &$form, FormStateInterface $form_state) {
        $form_state->setRedirect('<front>');
      }

}

?>
