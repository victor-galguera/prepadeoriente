<?php

namespace Drupal\partner_link\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The PartnersController class.
 */
class PartnersController extends ControllerBase {

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
  public function __construct(EntityTypeManagerInterface $entity_type_mananer, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_mananer;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function content() {

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery('AND');
    $query->condition('vid', "partner_link__partners");
    $query->condition('field_partner_link__enabled', 1);
    $tids = $query->execute();

    $terms = $term_storage->loadMultiple($tids);

    $partners = [];
    foreach ($terms as $term) {
      if ($term->access('view', $this->currentUser)) {
        $view_builder = $this->entityTypeManager->getViewBuilder($term->getEntityTypeId());
        $partners[] = $view_builder->view($term, 'default');
      }
    }

    $build = [
      '#theme' => 'partner_link_list',
      '#partners' => $partners,
    ];

    return $build;
  }

}
