<?php

namespace Drupal\elfsight_whatsapp_chat\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * {@inheritdoc}
 */
class ElfsightWhatsappChatController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    $url = 'https://apps.elfsight.com/embed/whatsapp-chat/?utm_source=portals&utm_medium=drupal&utm_campaign=whatsapp-chat&utm_content=sign-up';

    require_once __DIR__ . '/embed.php';

    return [
      'response' => 1,
    ];
  }

}
