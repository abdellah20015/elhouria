<?php

namespace Drupal\elhouria\Services;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

class ViewProcessor {

  private static $processors = [
    'slider_vehicules' => 'processSlider',
    'recherche_vehicule' => [
      'block' => 'processSearch',
      'page' => 'processSearchPage',
      'page_1' => 'processSearchPage'
    ],
    'nos_offres_moment' => 'processOffresduMoment',
    'catalogue_des_vehicules' => 'processCatalogue',
  ];

  private static $requiredClientTypes = ['Particulier', 'Profession libérale'];

  /** Point d'entrée principal - traite les vues selon leur type */
  public static function process(&$variables) {
    $view = $variables['view'];
    $processor = self::getProcessor($view);
    if ($processor && method_exists(self::class, $processor)) {
      self::$processor($variables);
    }
  }

  /** Traite les variables pour l'affichage d'un nœud véhicule individuel */
  public static function processNode(&$variables) {
    $node = $variables['node'];

    // Vérifier si c'est un nœud de type véhicule
    if ($node->bundle() !== 'vehicule') {
      return;
    }

    // Récupérer les données taxonomiques
    $variables = array_merge($variables, self::getTaxonomyData());

    // Traiter les données du véhicule
    $variables['prix'] = self::formatPrice($node->hasField('field_prix_mensuel') && !$node->get('field_prix_mensuel')->isEmpty() ?
                                         $node->get('field_prix_mensuel')->value : 0);

    $variables['marque_logo'] = self::getMarqueLogo($node);
    $variables['marque_logo_alt'] = $node->hasField('field_marque') && !$node->get('field_marque')->isEmpty() ?
                                   $node->get('field_marque')->entity->getName() : '';

    $variables['type_vehicule'] = self::getTermName($node, 'field_type_vehicule');
    $variables['boite_vitesse'] = self::getTermName($node, 'field_boite_vitesse');
    $variables['motricite'] = $node->hasField('field_motricite') && !$node->get('field_motricite')->isEmpty() ?
                             $node->get('field_motricite')->value : '';
    $variables['carburant'] = self::getTermName($node, 'field_carburant');
    $variables['puissance'] = $node->hasField('field_puissance') && !$node->get('field_puissance')->isEmpty() ?
                             $node->get('field_puissance')->value : '';

    $variables['images'] = self::getVehicleImages($node);
    $variables['caracteristiques_tabs'] = self::getCaracteristiquesTabs($node);
    $variables['modele'] = ['tid' => $node->hasField('field_modele') && !$node->get('field_modele')->isEmpty() ?
                                   $node->get('field_modele')->entity->id() : ''];

    // URL d'action pour le formulaire
    $variables['form_action'] = '/devis/submit';
  }

  /** Détermine quelle méthode utiliser selon l'ID de la vue */
  private static function getProcessor($view) {
    $viewId = $view->id();
    $displayId = $view->current_display;

    $processor = self::$processors[$viewId] ?? null;

    if (is_array($processor)) {
      return $processor[$displayId] ?? null;
    }

    return $processor;
  }

  /** Traite le block de recherche - récupère les services disponibles */
  private static function processSearch(&$variables) {
    $variables['services'] = self::getServices();
  }

  /** Traite la page de recherche - récupère les services et traite les résultats */
  private static function processSearchPage(&$variables) {
    $variables['services'] = self::getServices();

    // Traitement des résultats si ils existent
    if (!empty($variables['view']->result)) {
      $variables['rows'] = self::buildRows($variables['view']->result, function($node, $index) {
        $marque_logo = self::getMarqueLogo($node);
        $image = self::getImage($node, 'field_image_vehicule');

        return [
          'node_id' => $node->id(),
          'title' => $node->getTitle(),
          'image' => $image,
          'image_alt' => $node->hasField('field_image_vehicule') && !$node->get('field_image_vehicule')->isEmpty() ?
                        ($node->get('field_image_vehicule')->entity->field_media_image->alt ?? $node->getTitle()) :
                        $node->getTitle(),
          'marque_logo' => $marque_logo,
          'marque_logo_alt' => $node->hasField('field_marque') && !$node->get('field_marque')->isEmpty() ?
                              $node->get('field_marque')->entity->getName() : '',
          'marque_name' => $node->hasField('field_marque') && !$node->get('field_marque')->isEmpty() ?
                          $node->get('field_marque')->entity->getName() : '',
          'prix' => self::formatPrice($node->hasField('field_prix_mensuel') && !$node->get('field_prix_mensuel')->isEmpty() ?
                                     $node->get('field_prix_mensuel')->value : 0),
          'prix_raw' => $node->hasField('field_prix_mensuel') && !$node->get('field_prix_mensuel')->isEmpty() ?
                       $node->get('field_prix_mensuel')->value : 0,
          'type_vehicule' => self::getTermName($node, 'field_type_vehicule'),
          'url' => $node->toUrl()->toString(),
        ];
      });
    }
  }

  /** Traite le slider des véhicules avec prix et services */
  private static function processSlider(&$variables) {
    $variables['rows'] = self::buildRows($variables['view']->result, function($node, $index) {
      return self::buildBaseRow($node, 'full') + [
        '#nouveau' => $node->hasField('field_nouveau') && !$node->get('field_nouveau')->isEmpty() ?
                     ($node->get('field_nouveau')->value ? 'nouveau' : '') : '',
        '#prix' => self::formatPrice($node->hasField('field_prix_mensuel') && !$node->get('field_prix_mensuel')->isEmpty() ?
                                    $node->get('field_prix_mensuel')->value : 0),
        '#services' => $node->hasField('field_services_inclus') && !$node->get('field_services_inclus')->isEmpty() ?
                      self::formatServices($node->get('field_services_inclus')->value) : '',
      ];
    });
  }

  /** Traite les offres du moment - filtre les nouveaux véhicules pour certains types de clients */
  private static function processOffresduMoment(&$variables) {
    $variables['rows'] = self::buildRows($variables['view']->result, function($node, $index) {
      return self::buildBaseRow($node, 'teaser') + [
        '#prix' => self::formatPrice($node->hasField('field_prix_mensuel') && !$node->get('field_prix_mensuel')->isEmpty() ?
                                    $node->get('field_prix_mensuel')->value : 0),
        'node_id' => $node->id(),
        'url' => $node->toUrl()->toString(),
      ];
    }, fn($node) => $node->hasField('field_nouveau') && !$node->get('field_nouveau')->isEmpty() ?
                   ($node->get('field_nouveau')->value && self::hasRequiredClientTypes($node)) : false);

    $variables['client_types_text'] = self::getClientTypesText();
  }

  /** Traite le catalogue - prépare toutes les données des véhicules pour l'affichage */
  private static function processCatalogue(&$variables) {
    $variables['client_types_text'] = self::getDynamicClientTypesText();
    $variables = array_merge($variables, self::getTaxonomyData());
    $variables['rows'] = self::buildRows($variables['view']->result, function($node, $index) {
      return self::buildBaseRow($node, 'teaser') + [
        'node_id' => $node->id(),
        'title' => $node->getTitle(),
        'image' => self::getImage($node, 'field_image_vehicule'),
        'image_alt' => $node->hasField('field_image_vehicule') && !$node->get('field_image_vehicule')->isEmpty() ?
                      ($node->get('field_image_vehicule')->entity->field_media_image->alt ?? $node->getTitle()) :
                      $node->getTitle(),
        'marque_logo' => self::getMarqueLogo($node),
        'marque_logo_alt' => $node->hasField('field_marque') && !$node->get('field_marque')->isEmpty() ?
                            $node->get('field_marque')->entity->getName() : '',
        'prix' => self::formatPrice($node->hasField('field_prix_mensuel') && !$node->get('field_prix_mensuel')->isEmpty() ?
                                   $node->get('field_prix_mensuel')->value : 0),
        'type_vehicule' => self::getTermName($node, 'field_type_vehicule'),
        'boite_vitesse' => self::getTermName($node, 'field_boite_vitesse'),
        'motricite' => $node->hasField('field_motricite') && !$node->get('field_motricite')->isEmpty() ?
                      $node->get('field_motricite')->value : '',
        'carburant' => self::getTermName($node, 'field_carburant'),
        'puissance' => $node->hasField('field_puissance') && !$node->get('field_puissance')->isEmpty() ?
                      $node->get('field_puissance')->value : '',
        'images' => self::getVehicleImages($node),
        'caracteristiques_tabs' => self::getCaracteristiquesTabs($node),
        'modele' => ['tid' => $node->hasField('field_modele') && !$node->get('field_modele')->isEmpty() ?
                             $node->get('field_modele')->entity->id() : ''],
        'url' => $node->toUrl()->toString(),
      ];
    });
  }

  /** Récupère les services disponibles */
  private static function getServices() {
    $pids = \Drupal::entityQuery('paragraph')
      ->condition('type', 'services')
      ->condition('status', 1)
      ->sort('created', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    return array_filter(array_map(function($pid) {
      if ($paragraph = Paragraph::load($pid)) {
        return \Drupal::entityTypeManager()->getViewBuilder('paragraph')->view($paragraph, 'default');
      }
      return null;
    }, $pids));
  }

  /** Construit les lignes en appliquant un callback sur chaque noeud avec filtre optionnel */
  private static function buildRows($results, $callback, $filter = null) {
    return array_values(array_filter(array_map(function($result, $index) use ($callback, $filter) {
      if (!$node = Node::load($result->nid)) return null;
      if ($filter && !$filter($node)) return null;
      return $callback($node, $index);
    }, $results, array_keys($results))));
  }

  /** Crée la structure de base d'une ligne avec les éléments communs */
  private static function buildBaseRow($node, $viewMode) {
    return [
      '#theme' => 'node',
      '#node' => $node,
      '#view_mode' => $viewMode,
      '#title' => $node->getTitle(),
      '#image' => self::getImage($node, 'field_image_vehicule'),
      '#marque_logo' => self::getMarqueLogo($node),
    ];
  }

  /** Récupère le texte des types de client selon l'URL ou les paramètres */
  private static function getDynamicClientTypesText() {
    $request = \Drupal::request();
    $path = $request->getPathInfo();
    if (preg_match('/\/catalogue\/(\d+)/', $path, $matches) && $term = Term::load($matches[1])) {
      return $term->getName();
    }
    $client_type_id = $request->query->get('field_type_client');
    if ($client_type_id && $term = Term::load($client_type_id)) {
      return $term->getName();
    }
    return self::getClientTypesText();
  }

  /** Récupère toutes les données taxonomiques nécessaires pour les formulaires */
  private static function getTaxonomyData() {
    $vocabularies = [
      'display' => ['type_vehicule', 'marque', 'modele'],
      'form' => ['formule', 'modele', 'version']
    ];
    $data = [];
    foreach ($vocabularies['display'] as $vocab) {
      $data[$vocab . '_terms'] = self::getTermsData($vocab, $vocab === 'marque');
    }
    foreach ($vocabularies['form'] as $vocab) {
      $data[$vocab . '_options'] = self::getTermsData($vocab);
    }
    return $data;
  }

  /** Récupère les données d'un vocabulaire avec option d'inclure les logos */
  private static function getTermsData($vocabulary, $includeLogo = false) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vocabulary]);

    return array_map(function($term) use ($includeLogo) {
      $item = ['tid' => $term->id(), 'name' => $term->getName()];
      if ($includeLogo && $term->hasField('field_logo_marque') && !$term->get('field_logo_marque')->isEmpty()) {
        $media = $term->get('field_logo_marque')->entity;
        if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
          $logo_entity = $media->get('field_media_image')->entity;
          if ($logo_entity) {
            $item['logo'] = \Drupal::service('file_url_generator')->generateAbsoluteString($logo_entity->getFileUri());
          }
        }
      }
      return $item;
    }, $terms);
  }

  /** Récupère le nom d'un terme de taxonomie depuis un champ */
  private static function getTermName($node, $fieldName) {
    return $node->hasField($fieldName) && !$node->get($fieldName)->isEmpty() ?
           ($node->get($fieldName)->entity?->getName() ?? '') : '';
  }

  /** Récupère l'URL d'une image depuis un champ média */
  private static function getImage($node, $fieldName) {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return '';
    }
    $media = $node->get($fieldName)->entity;
    if (!$media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return '';
    }
    $image = $media->get('field_media_image')->entity;
    return $image ? \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri()) : '';
  }

  /** Récupère toutes les images d'un véhicule */
  private static function getVehicleImages($node) {
    if (!$node->hasField('field_images_vehicule') || $node->get('field_images_vehicule')->isEmpty()) {
      return [];
    }
    $images = [];
    foreach ($node->get('field_images_vehicule') as $item) {
      if ($item->entity && $item->entity->hasField('field_media_image') && !$item->entity->get('field_media_image')->isEmpty()) {
        $image = $item->entity->get('field_media_image')->entity;
        if ($image) {
          $images[] = [
            'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri()),
            'alt' => $item->entity->get('field_media_image')->alt ?? '',
          ];
        }
      }
    }
    return $images;
  }

  /** Récupère le logo de la marque */
  private static function getMarqueLogo($node) {
    if (!$node->hasField('field_marque') || $node->get('field_marque')->isEmpty()) {
      return '';
    }
    $marque = $node->get('field_marque')->entity;
    if (!$marque || !$marque->hasField('field_logo_marque') || $marque->get('field_logo_marque')->isEmpty()) {
      return '';
    }
    $media = $marque->get('field_logo_marque')->entity;
    if (!$media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return '';
    }
    $logo = $media->get('field_media_image')->entity;
    return $logo ? \Drupal::service('file_url_generator')->generateAbsoluteString($logo->getFileUri()) : '';
  }

  /** Structure les caractéristiques en onglets */
  private static function getCaracteristiquesTabs($node) {
    if (!$node->hasField('field_caracteristiques') || $node->get('field_caracteristiques')->isEmpty()) {
      return [];
    }
    $tabs = [];
    $delta = 0;
    foreach ($node->get('field_caracteristiques') as $item) {
      if ($paragraph = $item->entity) {
        $tabs[] = [
          'label' => $paragraph->hasField('field_label') && !$paragraph->get('field_label')->isEmpty() ?
                    $paragraph->get('field_label')->value : 'Caractéristique ' . ($delta + 1),
          'items' => [[
            'label' => $paragraph->hasField('field_label') && !$paragraph->get('field_label')->isEmpty() ?
                      $paragraph->get('field_label')->value : '',
            'valeur' => $paragraph->hasField('field_valeur') && !$paragraph->get('field_valeur')->isEmpty() ?
                       $paragraph->get('field_valeur')->value : '',
          ]],
        ];
        $delta++;
      }
    }
    return $tabs;
  }

  /** Vérifie si le véhicule correspond aux types de client requis */
  private static function hasRequiredClientTypes($node) {
    if (!$node->hasField('field_type_client') || $node->get('field_type_client')->isEmpty()) {
      return false;
    }
    $client_types = array_map(fn($item) => $item->entity?->getName(),
                             iterator_to_array($node->get('field_type_client')));
    return !empty(array_intersect(self::$requiredClientTypes, array_filter($client_types)));
  }

  /** Génère le texte des types de client autorisés */
  private static function getClientTypesText() {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'type_client']);
    $term_names = array_filter(array_map(function($term) {
      $name = $term->getName();
      return in_array($name, self::$requiredClientTypes) ? $name : null;
    }, $terms));
    return empty($term_names) ? 'Particulier & Profession libérale' : implode(' & ', $term_names);
  }

  /** Formate un prix avec séparateurs */
  private static function formatPrice($prix) {
    return number_format($prix ?: 0, 0, ',', ' ');
  }

  /** Nettoie et formate le HTML des services */
  private static function formatServices($services_html) {
    if (!$services_html) return '';
    $services = html_entity_decode($services_html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (strpos($services, '&lt;') !== false) {
      $services = html_entity_decode($services, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $services = trim($services);
    if (!empty($services) && !preg_match('/^\s*<ul/', $services)) {
      $services = preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $services, $matches) ?
        '<ul>' . implode('', $matches[0]) . '</ul>' : '<ul><li>' . $services . '</li></ul>';
    }
    return empty(trim(strip_tags($services))) ? '' : $services;
  }
}
