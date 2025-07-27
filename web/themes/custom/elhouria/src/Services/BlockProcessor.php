<?php

namespace Drupal\elhouria\Services;

class BlockProcessor {

  /**
   * Process the FAQ block for the /faq page.
   *
   * @param array &$variables
   *   The page variables to pass data to the template.
   */
  public static function processFaqBlock(array &$variables) {
    $current_path = \Drupal::service('path.current')->getPath();

    if ($current_path === '/faq') {
      $block_id = '10';
      $block_manager = \Drupal::service('plugin.manager.block');
      $block = $block_manager->createInstance('block_content:' . $block_id, []);

      if ($block && $block->access(\Drupal::currentUser())) {
        $block_content = \Drupal::entityTypeManager()
          ->getStorage('block_content')
          ->loadByProperties(['info' => 'FOIRE AUX QUESTIONS']);

        $block_content = reset($block_content);

        if ($block_content) {
          $faqs = [];
          if ($block_content->hasField('field_faq_reference') && !$block_content->get('field_faq_reference')->isEmpty()) {
            foreach ($block_content->get('field_faq_reference')->referencedEntities() as $paragraph) {
              $question = $paragraph->hasField('field_question') ? $paragraph->get('field_question')->value : '';
              $reponse = $paragraph->hasField('field_reponse') ? $paragraph->get('field_reponse')->value : '';

              if ($question && $reponse) {
                $faqs[] = [
                  'question' => $question,
                  'reponse' => $reponse,
                ];
              }
            }
          }
          $variables['faqs'] = $faqs;
          $variables['faq_block_title'] = $block_content->label();
        }
      }
    }
  }
}
