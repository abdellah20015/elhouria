<?php

namespace Drupal\elhouria\Services;

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Class NodeProcessor
 * Service pour traiter les champs des nœuds.
 */
class NodeProcessor
{

  /**
   * Traite les champs du nœud pour la page À propos (node/4).
   *
   * @param array $variables
   *   Les variables du preprocess.
   */
  public static function processAboutPage(array &$variables)
  {
    if (!isset($variables['node'])) {
      return;
    }
    
    $node = $variables['node'];

    if ($node instanceof Node && $node->bundle() == 'page' && $node->id() == 4) {

      $variables['about_title'] = $node->getTitle();

      $variables['about_image'] = NULL;
      if (!$node->get('field_image')->isEmpty()) {
        $image_entity = $node->get('field_image')->entity;
        if ($image_entity instanceof File) {
          $variables['about_image'] = [
            'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($image_entity->getFileUri()),
            'alt' => $node->get('field_image')->alt ?: '',
          ];
        } else {
          $image_url = self::getMediaImageUrl($node->get('field_image'));
          if ($image_url) {
            $media = $node->get('field_image')->entity;
            $variables['about_image'] = [
              'url' => $image_url,
              'alt' => $media && $media->hasField('field_media_image') ? ($media->get('field_media_image')->alt ?: '') : '',
            ];
          }
        }
      }

      $description = $node->get('field_description')->value;
      $variables['about_description'] = [];
      if ($description) {
        $paragraphs = preg_split('/\n\s*\n/', $description, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($paragraphs as $index => $paragraph) {
          $variables['about_description'][] = [
            'text' => trim($paragraph),
            'is_first' => $index === 0,
          ];
        }
      }
    }
  }

  /**
   * Récupère l'URL de l'image à partir d'un champ Media.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Le champ Media.
   * @return string
   *   L'URL absolue de l'image ou une chaîne vide si non disponible.
   */
  private static function getMediaImageUrl($field)
  {
    if (!$field->isEmpty()) {
      $media = $field->entity;
      if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $image = $media->get('field_media_image')->entity;
        if ($image instanceof File) {
          return \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
        }
      }
    }
    return '';
  }
}
