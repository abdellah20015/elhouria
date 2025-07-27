<?php

namespace Drupal\elhouria\Services;

use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\elhouria_devis\Controller\UserController;
use Drupal\taxonomy\Entity\Term;

/**
 * Service pour traiter les pages utilisateur.
 */
class UserPageProcessor {

  private const TAB_PATHS = [
    '/user/vehicules' => 'vehicules',
    '/user/documents' => 'documents',
    '/user' => 'informations',
  ];

  private const TAB_TITLES = [
    'vehicules' => 'Mes véhicules',
    'documents' => 'Mes documents',
    'informations' => 'Mes informations',
  ];

  private const USER_MENU_ITEMS = [
    [
      'title' => 'Mes informations',
      'url' => '/user',
      'class' => 'rubInformation',
      'tab' => 'informations',
    ],
    [
      'title' => 'Mes véhicules',
      'url' => '/user/vehicules',
      'class' => 'mesVehicule',
      'tab' => 'vehicules',
    ],
    [
      'title' => 'Mes documents',
      'url' => '/user/documents',
      'class' => 'mesDocuments',
      'tab' => 'documents',
    ],
    [
      'title' => 'Déconnexion',
      'url' => '/user/logout',
      'class' => 'deconnexion',
      'tab' => null,
    ],
  ];

  /**
   * Traite les pages utilisateur.
   */
  public static function process(array &$variables): void {
    $current_path = \Drupal::service('path.current')->getPath();

    if (!str_starts_with($current_path, '/user')) {
      return;
    }

    self::setupUserPageBase($variables, $current_path);
    self::setupUserData($variables);
    self::processUserTabs($variables, $current_path);
  }

  /**
   * Configuration de base pour les pages utilisateur.
   */
  private static function setupUserPageBase(array &$variables, string $current_path): void {
    $variables['#attached']['library'][] = 'elhouria_devis/sweetalert';
    $variables['#attached']['library'][] = 'elhouria_devis/user_documents';

    // Gestion des messages
    $status_messages = \Drupal::messenger()->messagesByType('status');
    if ($status_messages) {
      \Drupal::state()->set('elhouria_devis.message', reset($status_messages));
    }

    $current_tab = self::getCurrentTab($current_path);
    $variables['current_tab'] = $current_tab;
    $variables['current_tab_title'] = self::TAB_TITLES[$current_tab] ?? 'Mes informations';
    $variables['user_menu_items'] = self::getUserMenuItems($current_tab);
  }

  /**
   * Récupère l'onglet actuel basé sur le chemin.
   */
  private static function getCurrentTab(string $current_path): string {
    return self::TAB_PATHS[$current_path] ?? 'informations';
  }

  /**
   * Récupère les éléments du menu utilisateur.
   */
  private static function getUserMenuItems(string $current_tab): array {
    return array_map(function($item) use ($current_tab) {
      return [
        'title' => $item['title'],
        'url' => $item['url'],
        'class' => $item['class'],
        'active' => $item['tab'] === $current_tab,
      ];
    }, self::USER_MENU_ITEMS);
  }

  /**
   * Configure les données utilisateur.
   */
  private static function setupUserData(array &$variables): void {
    $user = \Drupal::currentUser();
    if (!$user->isAuthenticated()) {
      return;
    }

    $account = User::load($user->id());
    $variables['user_data'] = [
      'nom' => self::getFieldValue($account, 'field_nom'),
      'prenom' => self::getFieldValue($account, 'field_prenom'),
      'email' => $account->getEmail() ?? '',
      'telephone' => self::getFieldValue($account, 'field_telephone'),
    ];
  }

  /**
   * Récupère la valeur d'un champ en gérant les valeurs vides.
   */
  private static function getFieldValue(User $account, string $field_name): string {
    $field = $account->get($field_name);
    return $field->isEmpty() ? '' : $field->value;
  }

  /**
   * Traite les différents onglets utilisateur.
   */
  private static function processUserTabs(array &$variables, string $current_path): void {
    $processors = [
      '/user/vehicules' => 'processVehiclesTab',
      '/user/documents' => 'processDocumentsTab',
      '/user' => 'processInformationsTab',
    ];

    $processor = $processors[$current_path] ?? null;
    if ($processor) {
      self::$processor($variables);
    }
  }

  /**
   * Traite l'onglet véhicules.
   */
  private static function processVehiclesTab(array &$variables): void {
    $user = \Drupal::currentUser();
    $user_controller = new UserController();
    $nids = self::getUserNodeIds($user->id());

    $vehicles = [];
    if ($nids) {
      $nodes = Node::loadMultiple($nids);
      $vehicles = array_filter(array_map(function($node) use ($user_controller) {
        return ($node->bundle() === 'vehicule' && $node->isPublished())
          ? self::buildVehicleData($node, $user_controller)
          : null;
      }, $nodes));
    }

    $variables['vehicles'] = array_values($vehicles);
  }

  /**
   * Traite l'onglet documents.
   */
  private static function processDocumentsTab(array &$variables): void {
    $user = \Drupal::currentUser();
    $user_controller = new UserController();
    $nids = self::getUserNodeIds($user->id());

    $documents = [];
    $categories = [];

    if ($nids) {
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        if ($node->bundle() === 'vehicule' && $node->isPublished()) {
          $node_documents = $user_controller->getVehicleDocuments($node);
          $documents = array_merge($documents, $node_documents);
          $categories = array_merge($categories, self::getVehicleCategories($node));
        }
      }
    }

    // Filtrage par catégorie
    $selected_category = \Drupal::request()->query->get('category', '');
    if ($selected_category) {
      $documents = array_filter($documents, fn($doc) => $doc['category_id'] == $selected_category);
    }

    $variables['documents'] = $documents;
    $variables['categories'] = array_values(array_unique($categories, SORT_REGULAR));
    $variables['selected_category'] = $selected_category;
  }

  /**
   * Traite l'onglet informations.
   */
  private static function processInformationsTab(array &$variables): void {
    $variables['user_form'] = \Drupal::formBuilder()->getForm('Drupal\elhouria_devis\Form\UserInfoForm');
  }

  /**
   * Récupère les IDs des nœuds associés à un utilisateur.
   */
  private static function getUserNodeIds(int $uid): array {
    return \Drupal::database()->select('elhouria_devis', 'd')
      ->fields('d', ['nid'])
      ->condition('d.uid', $uid)
      ->distinct()
      ->execute()
      ->fetchCol();
  }

  /**
   * Construit les données d'un véhicule.
   */
  private static function buildVehicleData(Node $node, UserController $user_controller): array {
    return [
      'node_id' => $node->id(),
      'title' => $node->getTitle(),
      'image' => $user_controller->getImage($node, 'field_image_vehicule'),
      'image_alt' => self::getImageAlt($node, 'field_image_vehicule'),
      'marque_logo' => $user_controller->getMarqueLogo($node),
      'marque_logo_alt' => self::getMarqueName($node),
      'marque_name' => self::getMarqueName($node),
      'type_vehicule' => $user_controller->getTermName($node, 'field_type_vehicule'),
      'boite_vitesse' => $user_controller->getTermName($node, 'field_boite_vitesse'),
      'motricite' => self::getNodeFieldValue($node, 'field_motricite'),
      'carburant' => $user_controller->getTermName($node, 'field_carburant'),
      'puissance' => self::getNodeFieldValue($node, 'field_puissance'),
      'caracteristiques_tabs' => $user_controller->getCaracteristiquesTabs($node),
      'documents' => $user_controller->getVehicleDocuments($node),
      'url' => $node->toUrl()->toString(),
    ];
  }

  /**
   * Récupère le texte alternatif d'une image.
   */
  private static function getImageAlt(Node $node, string $field_name): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return $node->getTitle();
    }

    $media_entity = $node->get($field_name)->entity;
    if (!$media_entity || !$media_entity->hasField('field_media_image')) {
      return $node->getTitle();
    }

    return $media_entity->get('field_media_image')->alt ?? $node->getTitle();
  }

  /**
   * Récupère le nom de la marque.
   */
  private static function getMarqueName(Node $node): string {
    return ($node->hasField('field_marque') && !$node->get('field_marque')->isEmpty())
      ? $node->get('field_marque')->entity->getName()
      : '';
  }

  /**
   * Récupère la valeur d'un champ de nœud.
   */
  private static function getNodeFieldValue(Node $node, string $field_name): string {
    return ($node->hasField($field_name) && !$node->get($field_name)->isEmpty())
      ? $node->get($field_name)->value
      : '';
  }

  /**
   * Récupère les catégories d'un véhicule.
   */
  private static function getVehicleCategories(Node $node): array {
    if (!$node->hasField('field_type_vehicule') || $node->get('field_type_vehicule')->isEmpty()) {
      return [];
    }

    $term = $node->get('field_type_vehicule')->entity;
    return ($term instanceof Term)
      ? [['tid' => $term->id(), 'name' => $term->getName()]]
      : [];
  }
}
