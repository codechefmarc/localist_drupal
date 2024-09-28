<?php

namespace Drupal\localist_drupal\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\localist_drupal\Service\LocalistManager;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin to provide a list of source URLs from Localist.
 *
 * Available configuration keys:
 * - urls: a URL, a list of URLs, or a callback that returns a list of URLs
 * - data_fetcher_plugin: id of valid DataFetcherPluginInterface - eg. http,file
 * - data_parser_plugin: id of valid DataParserPluginInterface - eg. json, xml
 * - localist_endpoint: The endpoint to use for Localist - see README.md.
 *
 * Examples:
 *
 * @code
 * source:
 *   plugin: localist_url
 *   localist_endpoint: 'events'
 *   data_fetcher_plugin: http
 *   data_parser_plugin: json
 *   item_selector: data
 * @endcode
 *
 * The above will import events from Localist.
 *
 * @MigrateSource(
 *   id = "localist_url"
 * )
 */
class LocalistUrl extends Url implements ContainerFactoryPluginInterface {

  /**
   * The Localist Manager.
   *
   * @var \Drupal\localist_drupal\LocalistManager
   */
  protected $localistManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    LocalistManager $localist_manager,
    ) {
    $this->localistManager = $localist_manager;
    // Support a callback to return arrays of URLs.
    if (!empty($configuration['localist_endpoint'])) {
      $endpointType = $configuration['localist_endpoint'];
      $configuration['urls'] = $this->localistManager->getEndpointUrls($endpointType);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL,
    ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('localist_drupal.manager')
    );
  }

  /**
   * Returns source URLs.
   *
   * @return array
   *   The list of source Urls.
   */
  public function getSourceUrls(): array {
    return $this->sourceUrls;
  }

}
