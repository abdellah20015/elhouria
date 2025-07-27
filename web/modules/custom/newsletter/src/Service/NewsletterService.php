<?php

namespace Drupal\newsletter\Service;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;

class NewsletterService {

  protected $formBuilder;
  protected $renderer;

  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  public function getNewsletterForm() {
    $form = $this->formBuilder->getForm('Drupal\newsletter\Form\NewsletterForm');
    return $this->renderer->render($form);
  }
}
