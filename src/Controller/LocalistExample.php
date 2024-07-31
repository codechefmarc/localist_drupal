<?php

namespace Drupal\localist_drupal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class to handle creating the Localist example content type and fields.
 */
class LocalistExample extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Drupal module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new LocalistManager object.
   */
  public function __construct(
    ModuleHandler $module_handler,
    MessengerInterface $messenger,
  ) {
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('messenger'),
    );
  }

  /**
   * Creates content type and fields based on a recipe.
   */
  public function createExampleConfig() {

    $modulePath = $this->moduleHandler->getModule('localist_drupal')->getPath();
    $path = $modulePath . "/recipes/localist_example";
    $recipe = Recipe::createFromDirectory($path);
    try {
      RecipeRunner::processRecipe($recipe);
    }
    catch (\Throwable $th) {
      $message = $this->t('Example not created. Please check the error logs.');
      $this->messenger()->addError($message);
    }

    $message = $this->t('Example content type Localist Event and taxonomy vocabulary Localist Places created. Also, the migrations in the Localist settings form have been updated to add these examples.');
    $this->messenger()->addStatus($message);
    $redirectUrl = Url::fromRoute('localist_drupal.settings')->toString();
    $response = new RedirectResponse($redirectUrl);

    return $response;
  }

}
