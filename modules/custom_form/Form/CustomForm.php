 <?php
/**
   * @file
   * Contains \Drupal\custom_form\Form\CustomForm.
   */

  namespace Drupal\custom_form\Form;

  use Drupal\Core\Form\FormBase;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\Component\Utility\UrlHelper;

  /**
   * Contribute form.
   */
  class CustomForm extends FormBase {
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
return 'custom_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
      $form['email_address'] = array(
        '#type' => 'email',
        '#title' => $this->t('Your Email Address')
      );
      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

      if (!filter_var($form_state->getValue('email_address', FILTER_VALIDATE_EMAIL))) {
        $form_state->setErrorByName('email_address', $this->t('The Email Address you have provided is invalid.'));
      }

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      drupal_set_message($this->t('Your Email Address is @email', array('@email' => $form_state->getValue('email_address'))));
    }
  }
?>
