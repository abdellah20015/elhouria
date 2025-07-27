<?php

namespace Drupal\elhouria_contact\TwigExtension;

use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour le module de contact.
 */
class ContactTwigExtension extends AbstractExtension {

  /**
   * Le service menu link tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * Constructeur.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree) {
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('get_contact_menu', [$this, 'getContactMenu']),
    ];
  }

  /**
   * Récupère les items du menu de contact.
   */
  public function getContactMenu() {
    $menu_name = 'menu-contact';
    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth(1);

    $tree = $this->menuLinkTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    $contact_info = [];
    foreach ($tree as $element) {
      $link = $element->link;
      $contact_info[] = $link->getTitle();
    }

    return $contact_info;
  }
}
