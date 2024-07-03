<?php

namespace Drupal\localist_drupal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandler;
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
   * Constructs a new LocalistManager object.
   */
  public function __construct(
    ModuleHandler $module_handler,
  ) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
    );
  }

  /**
   * Creates content type and fields based on a recipe.
   */
  public function createExampleConfig() {

    dpm(class_exists('Drupal\Core\Recipe\Recipe'));

    $modulePath = $this->moduleHandler->getModule('localist_drupal')->getPath();
    $path = $modulePath . "/recipes/localist_example";
    $recipe = Recipe::createFromDirectory($path);
    try {
      RecipeRunner::processRecipe($recipe);
    }
    catch (\Throwable $th) {
      // throw $th;.
      dpm("recipe not imported...");
    }

    $redirectUrl = Url::fromRoute('localist_drupal.settings')->toString();
    $response = new RedirectResponse($redirectUrl);

    return $response;
  }

}
