<?php

namespace Drupal\elhouria_devis\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Security\TrustedCallbackInterface;

class UserInfoForm extends FormBase implements TrustedCallbackInterface {

  public function getFormId() {
    return 'elhouria_user_info_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $account = User::load($user->id());

    // Supprimer toutes les classes et attributs par défaut de Drupal
    $form['#attributes'] = [];
    $form['#attached']['library'][] = 'elhouria_devis/sweetalert';

    // Créer le formulaire avec la structure HTML exacte
    $form['nom'] = [
      '#type' => 'textfield',
      '#title' => 'nom<span>*</span>',
      '#title_display' => 'before',
      '#default_value' => $account->get('field_nom')->value ?? '',
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'text',
        'name' => 'nom',
        'id' => 'nom'
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos']
      ],
      '#pre_render' => [[$this, 'preRenderField']],
    ];

    $form['prenom'] = [
      '#type' => 'textfield',
      '#title' => 'Prénom<span>*</span>',
      '#title_display' => 'before',
      '#default_value' => $account->get('field_prenom')->value ?? '',
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'text',
        'name' => 'prenom',
        'id' => 'prenom'
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos']
      ],
      '#pre_render' => [[$this, 'preRenderField']],
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => 'email<span>*</span>',
      '#title_display' => 'before',
      '#default_value' => $account->getEmail(),
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'text',
        'name' => 'email',
        'id' => 'email'
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos']
      ],
      '#pre_render' => [[$this, 'preRenderField']],
    ];

    $form['telephone'] = [
      '#type' => 'textfield',
      '#title' => 'Téléphone<span>*</span>',
      '#title_display' => 'before',
      '#default_value' => $account->get('field_telephone')->value ?? '',
      '#required' => TRUE,
      '#attributes' => [
        'type' => 'text',
        'name' => 'telephone',
        'id' => 'telephone'
      ],
      '#wrapper_attributes' => [
        'class' => ['colFormInfos']
      ],
      '#pre_render' => [[$this, 'preRenderField']],
    ];

    // Ajouter un bouton de soumission caché
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['style' => 'display: none;'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Modifier mes informations'),
      '#attributes' => ['style' => 'display: none;'],
    ];

    return $form;
  }

  public function preRenderField($element) {
    // Supprimer complètement les classes Drupal par défaut
    unset($element['#attributes']['class']);
    unset($element['#label_attributes']['class']);

    // Garder seulement les attributs nécessaires
    $element['#label_attributes'] = [
      'for' => $element['#attributes']['id']
    ];

    // S'assurer que le label permet le HTML
    $element['#title_display'] = 'before';

    return $element;
  }

  public static function trustedCallbacks() {
    return ['preRenderField'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $account = User::load($user->id());

    $values = $form_state->getValues();
    $nom = $values['nom'] ?? '';
    $prenom = $values['prenom'] ?? '';
    $email = $values['email'] ?? '';
    $telephone = $values['telephone'] ?? '';

    // Validation des champs
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
      $form_state->setErrorByName('form', $this->t('Tous les champs obligatoires doivent être remplis.'));
      return;
    }

    // Validation manuelle de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('L\'adresse email n\'est pas valide.'));
      return;
    }

    // Vérifier si l'email existe déjà pour un autre utilisateur
    $existing_users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    if (!empty($existing_users)) {
      $existing_user = reset($existing_users);
      if ($existing_user->id() != $account->id()) {
        $form_state->setErrorByName('email', $this->t('Cette adresse email est déjà utilisée par un autre utilisateur.'));
        return;
      }
    }

    // Mise à jour des informations utilisateur
    $account->set('field_nom', $nom);
    $account->set('field_prenom', $prenom);
    $account->setEmail($email);
    $account->set('field_telephone', $telephone);
    $account->save();

    \Drupal::messenger()->addStatus($this->t('Vos informations ont été mises à jour avec succès.'));
    \Drupal::state()->set('elhouria_devis.message', $this->t('Vos informations ont été mises à jour avec succès.'));
    $form_state->setRedirect('user.page');
  }
}
