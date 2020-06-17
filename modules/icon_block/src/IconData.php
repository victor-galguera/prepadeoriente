<?php

/**
 * @file
 * Contains \Drupal\icon_block\IconData
 */

namespace Drupal\icon_block;

use Symfony\Component\Yaml\Yaml;

/**
 * Class to fetch icon array
 */
class IconData {
  public static function getIconArray() {
    // Check for icons in the cache
    if(!$icons = \Drupal::cache('data')->get('icon_block.list')) {
        $icons = [];
        $path = drupal_get_path('module', 'icon_block') . '/metadata/icons.yml';
        if(!file_exists($path)) {
            return [];
        }
        // Scan through the icons.yml file to get icon data
        foreach(Yaml::parse(file_get_contents($path)) as $name => $data) {
          $brands = FALSE;
          // Loop through the style
          foreach($data['styles'] as $style) {
            if($style == 'brands') {
              $brands = TRUE;
              break;
            }
          }
          // if the icon style has 'brands' then ignore that icon
          if($brands) {
            continue;
          }
          // Add the icon to the array
          $icons[] = $name;
          /*$icons = $icons + [
            $name => $name,
          ];*/
        }
        // Add the icon array to the cache
        \Drupal::cache('data')->set('icon_block.list', $icons, strtotime('+1 week'), ['icon_block', 'list']);
      }else{
        // Get the icons array from the cache
        $icons = $icons->data;
      }
      return (array) $icons;
  }

}
