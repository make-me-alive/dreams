<?php

/*this is my complete form page*/

namespace Drupal\amazing_forms\Controller;

/*
 * @file
 * Contains \Drupal\amazing_forms\Form\ContributeForm.

 */
// namespace Drupal\amazing_forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Contribute form.
 */
class ContributeForm  extends FormBase
{
    /**
        * {@inheritdoc}
        */
       public function getFormId()
       {
           return 'amazing_forms_contribute_form';
       }

       /**
        * {@inheritdoc}
        * adding required fields in my custom made form.
        */
       public function buildForm(array $form, FormStateInterface $form_state)
       {
           $form['email_address'] = array(
          '#type' => 'email',
          '#title' => $this->t('Your Email Address'),
        );

           $form['submit'] = array(
          '#type' => 'submit',
          '#value' => t('Submit'),
        );

           return  $form;
       }

      /**
       * {@inheritdoc}
       * adding validation functionalities in form.
       */
      public function validateForm(array &$form, FormStateInterface $form_state)
      {
          if (!filter_var($form_state->getValue('email_address', FILTER_VALIDATE_EMAIL))) {
              $form_state->setErrorByName('email_address',
          $this->t('The Email Address you have provided is invalid.'));
          }

          $email = $form_state->getValue('email_address');
          $validatemail = '/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/';
          if (!preg_match($validatemail, $email)) {
              $form_state->setErrorByName('email_address',
          $this->t('The Email Address you have provided is invalid.'));
          }
          /*
           * {@inheritdoc}
           * checking whether the user is same or not
           */

        $result = db_query('SELECT * FROM {amazing_forms_table} WHERE email = :email', array(':email' => $email))->fetchAllAssoc('email');

          if ($result) {
              $form_state->setErrorByName('email_address',
            $this->t('The Email Address you have provided is already registered !! try again with another'));
          }
      }
      /**
       * {@inheritdoc}
       * checking whether the username is same or not if same add a unique id then store else just save the
       * damn fields ..
       */
      public function submitForm(array &$form, FormStateInterface $form_state)
      {
          $email = $form_state->getValue('email_address');
          $temp = split('@', $email);
          $username = $temp[0];
          $result = db_query('SELECT username FROM {amazing_forms_table} WHERE username = :username ', array(':username' => $username))->fetchAllAssoc('username');

          foreach ($result as $value) {
              $repeatinguser = ($value->username);
          }

          if ($username == $repeatinguser) {
              $id = db_query(' SELECT max(id) FROM {amazing_forms_table}')->fetchField();
              ++$id;
              $repeatinguser = "{$id}{$repeatinguser}";
              db_insert('amazing_forms_table')->fields(array(
                    'username' => $repeatinguser,
                    'email' => $email,
                      ))->execute();

              $to = $email;
              $subject = 'successfully registered to studiodreams';
              $message = "your username for login is:  $repeatinguser <br/>";
              $from = 'From :studiodreams@drupal.com ';
              mail($to, $subject, $message, $from);
          } else {
              function add($username, $email)
              {
                  db_insert('amazing_forms_table')->fields(array(
                 'username' => $username,
                 'email' => $email,
                   ))->execute();

                  $to = $email;
                  $subject = 'successfully registered to studiodreams';
                  $message = "your username for login is:  $username <br/>";
                  $from = 'From :studiodreams@drupal.com ';
                  mail($to, $subject, $message, $from);
              }
              add($username, $email);
          }

          drupal_set_message(
         $this->t('Your Email Address is @email which has been successfully registered. An email has been sent to you.', array('@email' => $email)));

            //  $form_state->setRedirect('<front>');
      }
}
