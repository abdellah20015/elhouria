<?php

namespace Drupal\elhouria\Services;

class RegionProcessor {

  public static function process(&$variables) {
    if ($variables['region'] == 'header') {
      self::processHeader($variables);
    }
    if ($variables['region'] == 'footer') {
      self::processFooter($variables);
    }
  }

  private static function processHeader(&$variables) {
    $block_manager = \Drupal::service('plugin.manager.block');
    $current_user = \Drupal::currentUser();
    $site_config = \Drupal::config('system.site');
    $theme_settings = \Drupal::config('elhouria.settings');

     $newsletter_service = \Drupal::service('newsletter.service');
    $variables['newsletter_form'] = $newsletter_service->getNewsletterForm();

    $variables['logo_url'] = $theme_settings->get('logo.url') ?: theme_get_setting('logo.url');
    $variables['site_name'] = $site_config->get('name');
    $variables['main_menu'] = $block_manager->createInstance('system_menu_block:menu-principal')->build();
    $variables['mobile_menu'] = $block_manager->createInstance('system_menu_block:menu-mobile')->build();
    $variables['is_authenticated'] = $current_user->isAuthenticated();
    $variables['user_name'] = $current_user->getDisplayName();
    $variables['user_account_link'] = $current_user->isAuthenticated() ? '/user' : '/user/login';

    if ($current_user->isAuthenticated()) {
      $variables['connection_text'] = 'Déconnexion';
      $variables['connection_link'] = '/user/logout';
    } else {
      $variables['connection_text'] = 'Connexion';
      $variables['connection_link'] = '/user/login';
    }
    $variables['account_text'] = 'Mon compte';
  }

  private static function processFooter(&$variables) {
    $block_manager = \Drupal::service('plugin.manager.block');
    $site_config = \Drupal::config('system.site');
    $theme_handler = \Drupal::service('theme_handler');

    $variables['main_menu'] = $block_manager->createInstance('system_menu_block:menu-principal')->build();
    $variables['contact_menu'] = $block_manager->createInstance('system_menu_block:menu-contact')->build();
    $variables['mobile_menu'] = $block_manager->createInstance('system_menu_block:menu-mobile')->build();
    $variables['espace_client'] = '<a class="espaceClient" href="/user" title="">espace client</a>';

    // Utiliser le service Newsletter
    $newsletter_service = \Drupal::service('newsletter.service');
    $variables['newsletter_form'] = $newsletter_service->getNewsletterForm();

    $variables['site_name'] = $site_config->get('name');
    $variables['current_year'] = date('Y');

    $active_theme = $theme_handler->getTheme(\Drupal::theme()->getActiveTheme()->getName());
    $theme_path = $active_theme->getPath();

    $variables['footer_logo_url'] = '/' . $theme_path . '/medias/images/logoFooter.png';
    $variables['inner_footer_logo_url'] = '/' . $theme_path . '/medias/images/logoFooter.png';
    $variables['coord_logo_url'] = '/' . $theme_path . '/medias/images/logoCoord.png';
    $variables['realization_text'] = 'Une réalisation';
    $variables['realization_link'] = '#';
    $variables['realization_logo'] = '/' . $theme_path . '/medias/images/logoStudio.png';
    $variables['logo_assistance'] = '/' . $theme_path . '/medias/images/icon-assistance.png';
    $variables['logo_mobile'] = '/' . $theme_path . '/medias/images/logo-mobile.png';

    self::processInnerFooter($variables);
  }

  private static function processInnerFooter(&$variables) {
    $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $query = $paragraph_storage->getQuery()
      ->condition('type', 'inner_footer')
      ->condition('status', 1)
      ->accessCheck(FALSE);
    $paragraph_ids = $query->execute();

    if (!empty($paragraph_ids)) {
      $paragraphs = $paragraph_storage->loadMultiple($paragraph_ids);
      $paragraph = reset($paragraphs);

      if ($paragraph) {
        $variables['assistance_text'] = $paragraph->get('field_assistance_text')->value;
        $variables['assistance_phone_image'] = self::getMediaImageUrl($paragraph->get('field_assistance_phone_image'));
        $variables['whatsapp_text'] = $paragraph->get('field_whatsapp_text')->value;
        $variables['whatsapp_link'] = $paragraph->get('field_whatsapp_link')->uri;
        $variables['whatsapp_image'] = self::getMediaImageUrl($paragraph->get('field_whatsapp_image'));
        $variables['social_text'] = $paragraph->get('field_social_text')->value;
        $variables['social_links'] = [];

        if (!$paragraph->get('field_social_links')->isEmpty()) {
          foreach ($paragraph->get('field_social_links')->referencedEntities() as $social_link) {
            $variables['social_links'][] = [
              'url' => $social_link->get('field_social_url')->uri,
              'icon' => self::getMediaImageUrl($social_link->get('field_social_icon')),
              'title' => $social_link->get('field_social_url')->title ?: '',
            ];
          }
        }
      }
    }
  }

  private static function getMediaImageUrl($field) {
    if (!$field->isEmpty()) {
      $media = $field->entity;
      if ($media && $media->hasField('field_media_image')) {
        $image = $media->get('field_media_image')->entity;
        if ($image) {
          return \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
        }
      }
    }
    return '';
  }
}
