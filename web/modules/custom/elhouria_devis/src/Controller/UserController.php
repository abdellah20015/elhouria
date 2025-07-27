<?php

namespace Drupal\elhouria_devis\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;

class UserController extends ControllerBase {

  public function submit(Request $request) {
    $user = \Drupal::currentUser();
    $account = User::load($user->id());

    if (!$request->isMethod('POST')) {
      return new RedirectResponse('/user');
    }

    $data = $this->getFormData($request);
    $validation_error = $this->validateFormData($data, $account);

    if ($validation_error) {
      return $this->handleError($validation_error, $request);
    }

    $this->updateUserAccount($account, $data);

    return $this->handleSuccess($request);
  }

  private function getFormData(Request $request): array {
    return [
      'nom' => $request->request->get('nom'),
      'prenom' => $request->request->get('prenom'),
      'email' => $request->request->get('email'),
      'telephone' => $request->request->get('telephone'),
    ];
  }

  private function validateFormData(array $data, $account): ?string {
    if (empty($data['nom']) || empty($data['prenom']) || empty($data['email']) || empty($data['telephone'])) {
      return 'Tous les champs obligatoires doivent être remplis.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      return 'L\'adresse email n\'est pas valide.';
    }

    $existing_users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $data['email']]);

    if (!empty($existing_users)) {
      $existing_user = reset($existing_users);
      if ($existing_user->id() != $account->id()) {
        return 'Cette adresse email est déjà utilisée par un autre utilisateur.';
      }
    }

    return null;
  }

  private function updateUserAccount($account, array $data): void {
    $account->set('field_nom', $data['nom']);
    $account->set('field_prenom', $data['prenom']);
    $account->setEmail($data['email']);
    $account->set('field_telephone', $data['telephone']);
    $account->save();
  }

  private function handleError(string $message, Request $request) {
    return $request->isXmlHttpRequest()
      ? new JsonResponse(['error' => $message], 400)
      : $this->redirectWithError($message);
  }

  private function handleSuccess(Request $request) {
    $message = 'Vos informations ont été mises à jour avec succès.';

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse(['success' => $message]);
    }

    \Drupal::messenger()->addStatus($this->t($message));
    \Drupal::state()->set('elhouria_devis.message', $this->t($message));

    return new RedirectResponse('/user');
  }

  private function redirectWithError(string $message): RedirectResponse {
    \Drupal::messenger()->addError($this->t($message));
    return new RedirectResponse('/user');
  }

  public function vehicles() {
    return [
      '#theme' => 'page__user__vehicules',
      '#current_tab' => 'vehicules',
      '#current_tab_title' => $this->t('Mes véhicules'),
      '#user_menu_items' => $this->getUserMenuItems('vehicules'),
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'library' => ['elhouria_devis/vehicule_popup'],
      ],
    ];
  }

  public function documents() {
    return [
      '#theme' => 'page__user__documents',
      '#current_tab' => 'documents',
      '#current_tab_title' => $this->t('Mes documents'),
      '#user_menu_items' => $this->getUserMenuItems('documents'),
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'library' => ['elhouria_devis/user_documents'],
      ],
    ];
  }

  private function getUserMenuItems($active_tab): array {
    return [
      [
        'title' => $this->t('Mes informations'),
        'url' => '/user',
        'class' => 'rubInformation',
        'active' => $active_tab == 'informations',
      ],
      [
        'title' => $this->t('Mes véhicules'),
        'url' => '/user/vehicules',
        'class' => 'mesVehicule',
        'active' => $active_tab == 'vehicules',
      ],
      [
        'title' => $this->t('Mes documents'),
        'url' => '/user/documents',
        'class' => 'mesDocuments',
        'active' => $active_tab == 'documents',
      ],
      [
        'title' => $this->t('Déconnexion'),
        'url' => '/user/logout',
        'class' => 'deconnexion',
        'active' => false,
      ],
    ];
  }

  public function getMarqueLogo($node): string {
    $marque = $node->get('field_marque')->entity ?? null;
    $media = $marque?->get('field_logo_marque')->entity ?? null;
    $logo = $media?->get('field_media_image')->entity ?? null;

    return $logo
      ? \Drupal::service('file_url_generator')->generateAbsoluteString($logo->getFileUri())
      : '';
  }

  public function getImage($node, $fieldName): string {
    $media = $node->get($fieldName)->entity ?? null;
    $image = $media?->get('field_media_image')->entity ?? null;

    return $image
      ? \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri())
      : '';
  }

  public function getTermName($node, $fieldName): string {
    return $node->get($fieldName)->entity?->getName() ?? '';
  }

  public function getCaracteristiquesTabs($node): array {
    $field_data = $node->get('field_caracteristiques');

    if ($field_data->isEmpty()) {
      return [];
    }

    $tabs = [];
    $delta = 0;

    foreach ($field_data as $item) {
      $paragraph = $item->entity;
      $label = $paragraph->get('field_label')->value ?? "Caractéristique " . ($delta + 1);
      $valeur = $paragraph->get('field_valeur')->value ?? '';

      $tabs[] = [
        'label' => $label,
        'items' => [[
          'label' => $label,
          'valeur' => $valeur,
        ]],
      ];
      $delta++;
    }

    return $tabs;
  }

  public function getVehicleDocuments($node): array {
    $field_documents = $node->get('field_documents');

    if ($field_documents->isEmpty()) {
      return [];
    }

    $documents = [];

    foreach ($field_documents as $item) {
      $media = Media::load($item->target_id);
      $file = $media?->get('field_media_document')->entity;

      if (!$file instanceof File) {
        continue;
      }

      $documents[] = [
        'description' => $media->get('name')->value ?? $file->getFilename(),
        'category' => $node->get('field_type_vehicule')->entity?->getName() ?? 'Document',
        'category_id' => $node->get('field_type_vehicule')->target_id ?? 0,
        'type' => strtoupper(pathinfo($file->getFileUri(), PATHINFO_EXTENSION)),
        'size' => format_size($file->getSize()),
        'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
      ];
    }

    return $documents;
  }
}
