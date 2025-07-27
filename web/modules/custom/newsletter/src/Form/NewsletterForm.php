<?php

namespace Drupal\newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;

class NewsletterForm extends FormBase {

  public function getFormId() {
    return 'newsletter_subscription_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="newsletter-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['email'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'type' => 'email',
        'placeholder' => 'Votre email',
        'required' => 'required',
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => '',
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'wrapper' => 'newsletter-form-wrapper',
        'effect' => 'none',
      ],
    ];

    $form['message'] = [
      '#type' => 'markup',
      '#markup' => '<div id="newsletter-message"></div>',
    ];

    return $form;
  }

  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#newsletter-message',
        '<div id="newsletter-message" class="error">Email invalide</div>'));
      $response->addCommand(new InvokeCommand('#newsletter-message', 'delay', [10000]));
      $response->addCommand(new InvokeCommand('#newsletter-message', 'fadeOut', []));
    } else {
      // Vérifier si l'email existe déjà
      $email = $form_state->getValue('email');
      $existing = \Drupal::database()->select('newsletter_subscriptions', 'ns')
        ->fields('ns', ['id'])
        ->condition('email', $email)
        ->execute()
        ->fetchField();

      if ($existing) {
        $response->addCommand(new ReplaceCommand('#newsletter-message',
          '<div id="newsletter-message" class="warning">Cet email est déjà inscrit!</div>'));
      } else {
        $response->addCommand(new ReplaceCommand('#newsletter-message',
          '<div id="newsletter-message" class="success">Inscription réussie!</div>'));
        $response->addCommand(new InvokeCommand('[name="email"]', 'val', ['']));
      }

      // Faire disparaître le message après 10 secondes
      $response->addCommand(new InvokeCommand('#newsletter-message', 'delay', [10000]));
      $response->addCommand(new InvokeCommand('#newsletter-message', 'fadeOut', []));
    }

    return $response;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    // Vérifier si l'email existe déjà
    $existing = \Drupal::database()->select('newsletter_subscriptions', 'ns')
      ->fields('ns', ['id'])
      ->condition('email', $email)
      ->execute()
      ->fetchField();

    // Sauvegarder seulement si l'email n'existe pas déjà
    if (!$existing) {
      \Drupal::database()->insert('newsletter_subscriptions')
        ->fields([
          'email' => $email,
          'subscribed_at' => time(),
          'status' => 1,
        ])
        ->execute();
    }
  }
}
