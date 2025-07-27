<?php

namespace Drupal\elhouria\Services;

use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

class FormProcessor {

  /**
   * Process forms for specific pages.
   *
   * @param array &$variables
   *   The page variables to pass data to the template.
   */
  public static function processPageForms(array &$variables) {
    $current_path = \Drupal::service('path.current')->getPath();
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);

    // Process contact form
    if ($alias === '/contact' || $current_path === '/contact') {
      $form = \Drupal::formBuilder()->getForm('Drupal\elhouria_contact\Form\ContactForm');
      $variables['form'] = $form;
    }

    // Process login form
    if ($alias === '/user/login' || $current_path === '/user/login') {
      $form = \Drupal::formBuilder()->getForm('Drupal\elhouria_contact\Form\LoginForm');
      $variables['form'] = $form;
      $variables['page']['top_content'] = [];
      $variables['page']['bottom_content'] = [];
    }

    // Process devis form for catalogue
    if ($alias === '/catalogue-des-vehicules' || $current_path === '/catalogue-des-vehicules') {
      $variables['form_action'] = Url::fromRoute('elhouria_contact.devis_submit')->toString();
    }
  }

  /**
   * Process user authentication variables for header.
   *
   * @param array &$variables
   *   The region variables to pass data to the template.
   */
  public static function processHeaderAuthentication(array &$variables) {
    if ($variables['region'] === 'header') {
      $current_user = \Drupal::currentUser();

      if ($current_user->isAuthenticated()) {
        $variables['connection_text'] = 'Déconnexion';
        $variables['connection_link'] = Url::fromRoute('elhouria_contact.logout')->toString();
        $variables['user_account_link'] = Url::fromRoute('user.page')->toString();
      } else {
        $variables['connection_text'] = 'Connexion';
        $variables['connection_link'] = Url::fromRoute('elhouria_contact.login')->toString();
        $variables['user_account_link'] = Url::fromRoute('elhouria_contact.login')->toString();
      }
    }
  }

  /**
   * Process devis form submission.
   */
  public static function processDevisSubmission($form, &$form_state) {
    $values = $form_state->getValues();

    // Get term names for taxonomy fields
    $taxonomy_fields = ['formule', 'modele', 'version'];
    foreach ($taxonomy_fields as $field) {
      if (!empty($values[$field])) {
        $term = Term::load($values[$field]);
        $values[$field] = $term ? $term->getName() : $values[$field];
      }
    }

    // Save or send email with form data
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'elhouria_contact';
    $key = 'devis_request';
    $to = \Drupal::config('system.site')->get('mail');
    $params = [
      'nom' => $values['nom'],
      'prenom' => $values['prenom'],
      'email' => $values['email'],
      'societe' => $values['societe'],
      'formule' => $values['formule'],
      'modele' => $values['modele'],
      'version' => $values['version'],
      'commentaire' => $values['commentaire'],
      'vehicle_id' => $values['vehicle_id'],
    ];

    $mailManager->mail($module, $key, $to, 'fr', $params);

    \Drupal::messenger()->addMessage('Votre demande de devis a été envoyée avec succès.');
    $form_state->setRedirect('elhouria_contact.catalogue');
  }
}
