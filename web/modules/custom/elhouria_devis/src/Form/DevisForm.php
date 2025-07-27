<?php

namespace Drupal\elhouria_devis\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

class DevisForm extends FormBase {

  public function getFormId() {
    return 'elhouria_devis_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    $form['#attributes']['class'][] = 'formContact formDevis';
    // Attacher la bibliothèque SweetAlert
    $form['#attached']['library'][] = 'elhouria_devis/sweetalert';

    $form['title'] = [
      '#type' => 'markup',
      '#markup' => '<div class="titleForm"><h2>Demander un devis pour ce véhicule</h2><p>Devis gratuit et sans engagement</p></div>',
    ];

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<div class="introForm"><p>La mensualité du véhicule souhaité n’est pas disponible en ligne.</p><p>Merci de compléter le formulaire, nous vous transmettrons notre offre dans les plus brefs délais.</p></div>',
    ];

    $form['nom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nom<span>*</span>'),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['id' => 'nom'],
    ];

    $form['prenom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prénom<span>*</span>'),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['id' => 'prenom'],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email<span>*</span>'),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['id' => 'email'],
    ];

    $form['societe'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Société'),
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['id' => 'societe'],
    ];

    $form['formule'] = [
      '#type' => 'select',
      '#title' => $this->t('Formule<span>*</span>'),
      '#options' => $this->getTaxonomyOptions('formule'),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['class' => ['formSelect'], 'id' => 'formule'],
    ];

    $form['modele'] = [
      '#type' => 'select',
      '#title' => $this->t('Modèle<span>*</span>'),
      '#options' => $this->getTaxonomyOptions('modele'),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['class' => ['formSelect'], 'id' => 'modele'],
      '#default_value' => $nid ? $this->getNodeModele($nid) : '',
    ];

    $form['version'] = [
      '#type' => 'select',
      '#title' => $this->t('Version<span>*</span>'),
      '#options' => $this->getTaxonomyOptions('version'),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['colForm']],
      '#attributes' => ['class' => ['formSelect'], 'id' => 'version'],
    ];

    $form['commentaire'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Commentaire'),
      '#wrapper_attributes' => ['class' => ['colForm2']],
      '#attributes' => ['id' => 'commentaire'],
    ];

    $form['vehicle_id'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['form_id'] = [
      '#type' => 'hidden',
      '#value' => 'devis_form',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Envoyer ma demande'),
      '#wrapper_attributes' => ['class' => ['rowAction']],
    ];

    $form['#action'] = '/devis/submit';

    return $form;
  }

  private function getTaxonomyOptions($vocabulary) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vocabulary]);
    $options = ['' => '-'];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->getName();
    }
    return $options;
  }

  private function getNodeModele($nid) {
    $node = \Drupal\node\Entity\Node::load($nid);
    if ($node && $node->hasField('field_modele') && !$node->get('field_modele')->isEmpty()) {
      return $node->get('field_modele')->entity->id();
    }
    return '';
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $user = \Drupal::currentUser();

    Database::getConnection()->insert('elhouria_devis')
      ->fields([
        'uid' => $user->id(),
        'nid' => $values['vehicle_id'],
        'nom' => $values['nom'],
        'prenom' => $values['prenom'],
        'email' => $values['email'],
        'societe' => $values['societe'],
        'formule' => $values['formule'],
        'modele' => $values['modele'],
        'version' => $values['version'],
        'commentaire' => $values['commentaire'],
        'created' => time(),
      ])
      ->execute();

    \Drupal::messenger()->addStatus($this->t('Votre demande de devis a été envoyée avec succès.'));
    $form_state->setRedirect('<current>');
  }
}
