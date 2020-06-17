<?php

namespace Drupal\partner_link\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Partner Link' Block.
 *
 * @Block(
 *   id = "partner_link_block",
 *   admin_label = @Translation("Partner Link"),
 * )
 */
class PartnerLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_mananer, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_mananer;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $config = $this->getConfiguration();
    $partner_block_display = 'partner_link';
    if (isset($config['partner_link_block_settings_display'])) {
      $partner_block_display = $config['partner_link_block_settings_display'];
    }

    $query = $term_storage->getQuery('AND');
    $query->condition('vid', "partner_link__partners");
    $query->condition('field_partner_link__enabled', 1);
    $query->condition('field_partner_link__display_bloc', 1);
    $tids = $query->execute();
    $terms = $term_storage->loadMultiple($tids);

    $partners = [];
    foreach ($terms as $term) {
      if ($term->access('view', $this->currentUser)) {
        $view_builder = $this->entityTypeManager->getViewBuilder($term->getEntityTypeId());
        $partners[] = $view_builder->view($term, $partner_block_display);
      }
    }

    $rendering = [
      '#theme' => 'partner_link_list',
      '#partners' => $partners,
    ];

    if (!isset($config['partner_link_block_settings_css']) || (isset($config['partner_link_block_settings_css']) && $config['partner_link_block_settings_css'])) {
      $rendering['#attached']['library'][] = 'partner_link/partner_link_default';
    }

    return $rendering;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['partner_link_block_settings_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#description' => $this->t('Choose how to display the partners.'),
      '#options' => [
        'partner_link' => $this->t('Display only partner links'),
        'partner_link_icon' => $this->t('Display partner icons'),
        'partner_link_both' => $this->t('Display both (icons and links)'),
      ],
      '#default_value' => isset($config['partner_link_block_settings_display']) ? $config['partner_link_block_settings_display'] : 'partner_link',
    ];

    $form['partner_link_block_settings_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add default style'),
      '#default_value' => isset($config['partner_link_block_settings_css']) ? $config['partner_link_block_settings_css'] : 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $display = $form_state->getValue('partner_link_block_settings_display');
    $this->setConfigurationValue('partner_link_block_settings_display', $display);
    $css = $form_state->getValue('partner_link_block_settings_css');
    $this->setConfigurationValue('partner_link_block_settings_css', $css);
  }

}
