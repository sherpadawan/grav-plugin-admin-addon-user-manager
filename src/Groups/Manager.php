<?php
namespace AdminAddonUserManager\Groups;

use Grav\Common\Grav;
use Grav\Plugin\AdminAddonUserManagerPlugin;
use Grav\Common\Assets;
use RocketTheme\Toolbox\Event\Event;
use AdminAddonUserManager\Manager as IManager;
use AdminAddonUserManager\Pagination\ArrayPagination;
use \Grav\Common\Utils;
use AdminAddonUserManager\Group;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Manager implements IManager, EventSubscriberInterface {

  private $grav;
  private $plugin;

  public function __construct(Grav $grav, AdminAddonUserManagerPlugin $plugin) {
    $this->grav = $grav;
    $this->plugin = $plugin;

    $this->grav['events']->addSubscriber($this);
  }

  public static function getSubscribedEvents() {
    return [
      'onAdminData' => ['onAdminData', 0],
    ];
  }

  public function onAdminData($e) {
    $type = $e['type'];

    if (preg_match('|group-manager/|', $type)) {
      $obj = Group::load(preg_replace('|group-manager/|', '', $type));
      $obj->merge($_POST['data']);
      $e['data_type'] = $obj;
    }
  }

  /**
   * Returns the required permission to access the manager
   *
   * @return string
   */
  public function getRequiredPermission() {
    return 'admin.groups';
  }

  /**
   * Returns the location of the manager
   * It will be accessible at this path
   *
   * @return string
   */
  public function getLocation() {
    return 'group-manager';
  }

  /**
   * Returns the plugin hooked nav array
   *
   * @return array
   */
  public function getNav() {
    return [
      'label' => 'Group Manager',
      'location' => $this->getLocation(),
      'icon' => 'fa-group',
      'authorize' => $this->getRequiredPermission(),
      'badge' => [
        'count' => count($this->groups())
      ]
    ];
  }

  /**
   * Initialiaze required assets
   *
   * @param \Grav\Common\Assets $assets
   * @return void
   */
  public function initializeAssets(Assets $assets) {
    $this->grav['assets']->addCss('plugin://' . $this->plugin::SLUG . '/assets/groups/style.css');
  }

  /**
   * Handle task requests
   *
   * @param \RocketTheme\Toolbox\Event\Event $event
   * @return boolean
   */
  public function handleTask(Event $event) {
    $method = $event['method'];

    if ($method === 'taskGroupDelete') {
      Group::remove($this->grav['uri']->paths()[2]);
      $this->grav->redirect($this->plugin->getPreviousUrl());
      return true;
    }

    return false;
  }

  /**
   * Logic of the manager goes here
   *
   * @return array The array to be merged to Twig vars
   */
  public function handleRequest() {
    $vars = [];

    $twig = $this->grav['twig'];
    $uri = $this->grav['uri'];

    $group = $this->grav['uri']->paths();
    if (count($group) == 3) {
      $group = $group[2];
    } else {
      $group = false;
    }

    if ($group) {
      $vars['exists'] = Group::groupExists($group);
      $vars['group'] = $group = Group::load($group);
    } else {
      $vars['fields'] = $this->plugin->getModalsConfiguration()['add_group']['fields'];

      // Pagination
      $perPage = $this->plugin->getPluginConfigValue('pagination.per_page', 10);
      $pagination = new ArrayPagination($this->groups(), $perPage);
      $pagination->paginate($uri->param('page'));

      $vars['pagination'] = [
        'current' => $pagination->getCurrentPage(),
        'count' => $pagination->getPagesCount(),
        'total' => $pagination->getRowsCount(),
        'perPage' => $pagination->getRowsPerPage(),
        'startOffset' => $pagination->getStartOffset(),
        'endOffset' => $pagination->getEndOffset()
      ];
      $vars['groups'] = $pagination->getPaginatedRows();
    }

    return $vars;
  }

  public function groups() {
    return $this->plugin->getConfigValue('groups', []);
  }

}