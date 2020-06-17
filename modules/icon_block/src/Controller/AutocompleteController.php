<?php

/**
 * @file
 * Contains Drupal\icon_block\Controller\AutocompleteController
 */

namespace Drupal\icon_block\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Tags;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\icon_block\IconData;

/**
 * Defines a controller for autocomplete form elements
 */
class AutocompleteController extends ControllerBase {
  /**
   * Handler for autocomplete request
   */
  public function handleIcons() {
    $response = [];

    // Get the value of 'q' from the query string
    if($input = \Drupal::request()->query->get('q')){
      $typed_string = Tags::explode($input);
      $typed_string = mb_strtolower(array_pop($typed_string));

      // Get the icon array
      $icon_data = IconData::getIconArray();

      foreach($icon_data as $icon) { //=> $data) {
        // Check if the string match
        if(strpos($icon, $typed_string) === 0) {
          $response[] = [
            'value' => $icon,
            'label' => $this->t('<i class="fa fa-:icon fa-fw fa-2x"></i> :icon', [
              ':icon' => $icon,
            ]),
          ];
        }
      }
    }

    return new JsonResponse($response);
  }
}
