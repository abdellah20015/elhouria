<?php

namespace Drupal\elhouria_contact\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulaire de connexion personnalisé pour El Hourria.
 */
class LoginForm extends FormBase {

  /**
   * Le service d'authentification utilisateur.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * Constructeur.
   */
  public function __construct(UserAuthInterface $user_auth) {
    $this->userAuth = $user_auth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.auth')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elhouria_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes'] = [
      'class' => ['inner-connection', 'form'],
      'action' => '',
    ];

    $form['login'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifiant<span>*</span>'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'login',
        'name' => 'login',
      ],
      '#wrapper_attributes' => [],
      '#prefix' => '<div class="rowConnexion">',
      '#suffix' => '</div>',
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Mot de passe<span>*</span>'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'login',
        'name' => 'password',
      ],
      '#wrapper_attributes' => [],
      '#prefix' => '<div class="rowConnexion">',
      '#suffix' => '</div>',
    ];

    $form['messages'] = [
      '#type' => 'markup',
      '#markup' => '<p class="msg-error"></p>',
      '#prefix' => '<div class="rowConnexion">',
      '#suffix' => '</div>',
    ];

    // Afficher les messages d'erreur s'il y en a
    $messages = \Drupal::messenger()->all();
    if (!empty($messages)) {
      $message_output = '';
      foreach ($messages as $type => $messages_of_type) {
        foreach ($messages_of_type as $message) {
          $message_output = '<p class="msg-error">' . (string) $message . '</p>';
        }
      }
      $form['messages']['#markup'] = $message_output;
      \Drupal::messenger()->deleteAll();
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Connexion'),
      '#attributes' => [
        'value' => 'Connexion',
      ],
      '#prefix' => '<div class="rowConnexion">',
      '#suffix' => '</div>',
    ];

    $form['forgot_password'] = [
      '#type' => 'markup',
      '#markup' => '<a href="/user/password">Mot de passe oublié</a>',
      '#prefix' => '<div class="rowConnexion">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('login');
    $password = $form_state->getValue('password');

    // Valider les identifiants
    $uid = $this->userAuth->authenticate($username, $password);
    if (!$uid) {
      $form_state->setErrorByName('login', $this->t('Identifiant ou mot de passe incorrect.'));
    } else {
      $form_state->set('uid', $uid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
      $uid = $form_state->get('uid');
      if ($uid) {
        // Connecter l'utilisateur
        user_login_finalize(\Drupal\user\Entity\User::load($uid));
        $this->messenger()->addMessage($this->t('Connexion réussie. Bienvenue !', ['%username' => $form_state->getValue('login')]));
        $form_state->setRedirect('<front>');
      }
  }
}
