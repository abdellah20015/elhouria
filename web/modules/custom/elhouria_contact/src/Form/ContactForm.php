<?php

namespace Drupal\elhouria_contact\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulaire de contact El Hourria.
 */
class ContactForm extends FormBase {

  /**
   * Le service mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Le service language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructeur.
   */
  public function __construct(MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elhouria_contact_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'formInfosCompte';
    $form['#attributes']['class'][] = 'form';

    $form['nom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('nom<span>*</span>'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'nom',
        'name' => 'nom',
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos'],
      ],
    ];

    $form['prenom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prénom<span>*</span>'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'prenom',
        'name' => 'prenom',
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos'],
      ],
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('email<span>*</span>'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'email',
        'name' => 'email',
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos'],
      ],
    ];

    $form['telephone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Téléphone<span>*</span>'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'telephone',
        'name' => 'telephone',
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos'],
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('message'),
      '#title_display' => 'before',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'message',
        'name' => 'message',
      ],
      '#wrapper_attributes' => [
        'class' => ['rowFormInfo'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Envoyer'),
      '#wrapper_attributes' => [
        'class' => ['rowFormAction'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Veuillez saisir une adresse email valide.'));
    }

    $telephone = $form_state->getValue('telephone');
    if (!preg_match('/^[0-9\+\-\s\(\)]+$/', $telephone)) {
      $form_state->setErrorByName('telephone', $this->t('Veuillez saisir un numéro de téléphone valide.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Préparer les paramètres du mail
    $module = 'elhouria_contact';
    $key = 'contact_form';
    $to = 'contact@elhourria.com';
    $params = [
      'nom' => $values['nom'],
      'prenom' => $values['prenom'],
      'email' => $values['email'],
      'telephone' => $values['telephone'],
      'message' => $values['message'],
    ];
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Envoyer l'email
    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, $values['email'], TRUE);

    if ($result['result'] === TRUE) {
      $this->messenger()->addMessage($this->t('Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.'));
    } else {
      $this->messenger()->addError($this->t('Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer.'));
    }
  }
}
