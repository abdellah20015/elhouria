<?php

namespace Drupal\elhouria\Utilities;

class PathHelper {

  // Set theme base path
  public static function setThemePath(&$variables) {
    $variables['theme_path'] = \Drupal::request()->getBasePath() . '/' . $variables['directory'];
  }
}
