<?php

namespace Drupal\partner_link\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure partner link settings for this site.
 */
class PartnerLinkSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'partner_link_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['partner_link.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('partner_link.settings');

    $form['check_broken_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the cron to check broken links'),
      '#default_value' => $config->get('check_broken_links'),
      '#description' => $this->t('A cron will check periodically if your partners links are not broken and disable them if they are.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('partner_link.settings');

    $values = $form_state->cleanValues()->getValues();
    $config->set('check_broken_links', $values['check_broken_links'])->save();

    parent::submitForm($form, $form_state);
  }

}
