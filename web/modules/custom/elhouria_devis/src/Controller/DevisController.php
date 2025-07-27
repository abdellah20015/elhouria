<?php

namespace Drupal\elhouria_devis\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DevisController extends ControllerBase {

  public function submit(Request $request) {
    $values = $request->request->all();
    $user = \Drupal::currentUser();

    // Validation des champs requis
    if (empty($values['nom']) || empty($values['prenom']) || empty($values['email']) ||
        empty($values['formule']) || empty($values['modele']) || empty($values['version']) ||
        empty($values['vehicle_id'])) {
      $this->messenger()->addError($this->t('Tous les champs obligatoires doivent être remplis.'));
      return new RedirectResponse($request->headers->get('referer', '/'));
    }

    // Insertion dans la base de données
    Database::getConnection()->insert('elhouria_devis')
      ->fields([
        'uid' => $user->id(),
        'nid' => $values['vehicle_id'],
        'nom' => $values['nom'],
        'prenom' => $values['prenom'],
        'email' => $values['email'],
        'societe' => $values['societe'] ?? '',
        'formule' => $values['formule'],
        'modele' => $values['modele'],
        'version' => $values['version'],
        'commentaire' => $values['commentaire'] ?? '',
        'created' => time(),
      ])
      ->execute();

    // Ajouter le message Drupal pour SweetAlert
    $this->messenger()->addStatus($this->t('Votre demande de devis a été envoyée avec succès.'));

    // Attacher la bibliothèque SweetAlert
    $response = new RedirectResponse($request->headers->get('referer', '/'));
    $response->headers->set('X-Drupal-Dynamic-Libraries', 'elhouria_devis/sweetalert');

    // Passer le message au JavaScript
    \Drupal::state()->set('elhouria_devis.message', $this->t('Votre demande de devis a été envoyée avec succès.'));

    return $response;
  }
}
