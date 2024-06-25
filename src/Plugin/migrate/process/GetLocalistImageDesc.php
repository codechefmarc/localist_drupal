<?php

declare(strict_types=1);

namespace Drupal\localist_drupal\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\localist_drupal\LocalistManager;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves photo description from Localist based on a photo ID.
 *
 * @MigrateProcessPlugin(
 *   id = "get_localist_image_desc",
 *   handle_multiples = TRUE
 * )
 *
 * @code
 *   field_event_type:
 *     plugin: get_localist_image_desc
 *     source: image_id
 * @endcode
 */
class GetLocalistImageDesc extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Localist Manager.
   *
   * @var \Drupal\localist_drupal\LocalistManager
   */
  protected $localistManager;

  /**
   * The Http client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a new GetLocalistImageDesc object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param Drupal\localist_drupal\LocalistManager $localist_manager
   *   The Localist manager service.
   * @param GuzzleHttp\Client $http_client
   *   The http client.
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    LocalistManager $localist_manager,
    Client $http_client,
  ) {
    $this->localistManager = $localist_manager;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('localist_drupal.manager'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $photoDesc = NULL;
    $endPointURL = $this->localistManager->getEndpointUrls('photos');
    $url = "$endPointURL[0]/$value";
    $response = $this->httpClient->get($url);
    if ($response) {
      $data = json_decode($response->getBody()->getContents(), TRUE);
      $photoDesc = $data['photo']['caption'] ?? NULL;
    }
    return $photoDesc;
  }

}
