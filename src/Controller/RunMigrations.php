<?php

namespace Drupal\localist_drupal\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\localist_drupal\LocalistManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Runs Localist migrations on request.
 */
class RunMigrations extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $localistConfig;

  /**
   * The Localist Manager.
   *
   * @var \Drupal\localist_drupal\LocalistManager
   */
  protected $localistManager;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new RunMigrations object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LocalistManager $localist_manager,
    MessengerInterface $messenger,
  ) {
    $this->localistConfig = $config_factory->get('localist_drupal.settings');
    $this->localistManager = $localist_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('localist_drupal.manager'),
      $container->get('messenger'),
    );
  }

  /**
   * Runs all Localist migrations.
   */
  public function runAllMigrations() {

    if ($this->localistConfig->get('enable_localist_sync')) {

      $messageData = $this->localistManager->runAllMigrations();
      if ($messageData) {
        $eventsImported = $messageData['localist_events']['imported'];
        $message = "Synchronized $eventsImported events.";
        $this->messenger()->addStatus($message);
      }
    }
    else {
      $message = "Localist sync is not enabled. No sync was performed.";
      $this->messenger()->addError($message);
    }

    $redirectUrl = Url::fromRoute('localist_drupal.settings')->toString();
    $response = new RedirectResponse($redirectUrl);
    return $response;

  }

  /**
   * Runs the group migration.
   */
  public function syncGroups() {

    if ($this->localistConfig->get('enable_localist_sync')) {
      // Check endpoint before running migration.
      if ($this->localistManager->checkEndpoint()) {
        $this->localistManager->runMigration('localist_groups');
        $this->localistManager->removeOldExperiences();
        $this->messenger()->addStatus('Successfully imported Localist groups.');
      }
      else {
        $this->messenger()->addError('Error getting groups. Check that the endpoint is correct.');
      }
    }
    else {
      $message = "Localist sync is not enabled. No sync was performed.";
      $this->messenger()->addError($message);
    }

    $redirectUrl = Url::fromRoute('localist_drupal.settings')->toString();
    $response = new RedirectResponse($redirectUrl);
    return $response;

  }

}
