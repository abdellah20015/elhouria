<?php

namespace Drupal\elhouria_contact\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Contrôleur pour gérer la déconnexion.
 */
class LoginController extends ControllerBase {

  /**
   * Gère la déconnexion et redirige vers la page de connexion.
   */
  public function logout() {
    user_logout();
    return new RedirectResponse(Url::fromRoute('elhouria_contact.login')->toString());
  }
}
