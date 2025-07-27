<?php

namespace Drupal\elhouria_contact\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

class DevisController extends ControllerBase {

  public function submit(Request $request) {
    $data = $request->request->all();

    // Validate required fields
    $required = ['nom', 'prenom', 'email', 'formule', 'modele', 'version'];
    foreach ($required as $field) {
      if (empty($data[$field])) {
        $this->messenger()->addError('Tous les champs obligatoires doivent être remplis.');
        return new RedirectResponse($request->headers->get('referer'));
      }
    }

    // Send email
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'elhouria_contact';
    $key = 'devis_request';
    $to = \Drupal::config('system.site')->get('mail');
    $params = $data;

    $result = $mailManager->mail($module, $key, $to, 'fr', $params);

    if ($result['result']) {
      $this->messenger()->addMessage('Votre demande de devis a été envoyée avec succès.');
    } else {
      $this->messenger()->addError('Erreur lors de l\'envoi du formulaire.');
    }

    return new RedirectResponse($request->headers->get('referer'));
  }
}
