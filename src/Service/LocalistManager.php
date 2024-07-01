<?php

namespace Drupal\localist_drupal\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for Localist functions.
 */
class LocalistManager extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $localistConfig;

  /**
   * Localist endpoint base.
   *
   * @var string
   */
  protected $endpointBase;

  /**
   * The Http client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal migration manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * Drupal module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Drupal time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Group vocabulary and field name are required for group sync.
   */

  const GROUP_VOCABULARY = 'localist_groups';
  const GROUP_ID_FIELD = 'field_localist_group_id';

  /**
   * Constructs a new LocalistManager object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Client $http_client,
    EntityTypeManager $entity_type_manager,
    MigrationPluginManager $migration_manager,
    ModuleHandler $module_handler,
    TimeInterface $time,
    MessengerInterface $messenger,
    Connection $database,
    EntityFieldManager $entity_field_manager,
    DateFormatter $date_formatter,
  ) {
    $this->localistConfig = $config_factory->get('localist_drupal.settings');
    $this->endpointBase = $this->localistConfig->get('localist_endpoint');
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationManager = $migration_manager;
    $this->moduleHandler = $module_handler;
    $this->time = $time;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->entityFieldManager = $entity_field_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.migration'),
      $container->get('module_handler'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Gets the endpoint URLs for migration.
   *
   * @param string $endpointType
   *   The endpoint to fetch.
   *
   * @return array
   *   An array of endpoint URLs with dynamic parameters.
   */
  public function getEndpointUrls($endpointType) {
    $endpointsWithParams = [];

    switch ($endpointType) {
      case 'events':
        // Group ID is required.
        $groupId = $this->getGroupTaxonomyEntity();

        if ($groupId) {
          $eventsURL = "$this->endpointBase/api/2/events";

          // Gets the latest version from the API by changing the URL each time.
          $version = time();

          // Localist supports only getting 365 days from today.
          $endDate = date('Y-m-d', strtotime("+364 days"));

          $endpointsWithParams[] = "$eventsURL?end=$endDate&group_id=$groupId&v=$version&pp=100";
        }

        break;

      case 'places':
        $placesURL = "$this->endpointBase/api/2/places?pp=100";
        $endpointsWithParams = $this->getMultiPageUrls($placesURL);

        break;

      case 'filters':
        $endpointsWithParams[] = "$this->endpointBase/api/2/events/filters";

        break;

      case 'groups':
        $groupsURL = "$this->endpointBase/api/2/groups?pp=100";
        $endpointsWithParams = $this->getMultiPageUrls($groupsURL);

        break;

      case 'photos':
        $endpointsWithParams[] = "$this->endpointBase/api/2/photos";

        break;

      case 'tickets':
        $endpointsWithParams[] = "$this->endpointBase/api/2/events";

        break;

      default:
        $endpointsWithParams = [];
        break;
    }
    return $endpointsWithParams;
  }

  /**
   * Gets the total number of pages from a Localist API endpoint.
   *
   * @param string $url
   *   The endpoint to fetch.
   *
   * @return array
   *   Endpoint URLs with pages attached.
   */
  private function getMultiPageUrls($url) {
    $endpointUrls = [];
    $response = $this->httpClient->get($url);
    $data = json_decode($response->getBody(), TRUE);

    $i = 1;
    while ($i <= $data['page']['total']) {
      $endpointUrls[] = "$url&page=$i";
      $i++;
    }

    return $endpointUrls;
  }

  /**
   * Gets an entity object for the selected group taxonomy term.
   *
   * @return array
   *   Endpoint URLs with pages attached.
   */
  public function getGroupTaxonomyEntity() {
    $groupTermId = $this->localistConfig->get('localist_group');
    if ($groupTermId) {
      /** @var Drupal\taxonomy\Entity\Term $term */
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($groupTermId);
      if ($term && $term->hasField('field_localist_group_id')) {
        return $term->field_localist_group_id->value;
      }
    }

    return NULL;
  }

  /**
   * Runs all Localist migrations.
   *
   * @return array
   *   Array of status of all migrations run.
   */
  public function runAllMigrations() {
    if ($this->preflightChecks()) {
      $migrationsToRun = $this->getMigrationsToRun();
      foreach ($migrationsToRun['found'] as $migration) {
        $this->runMigration($migration);
        $messageData['migrations'][$migration] = [
          'imported' => $this->getMigrationStatus($migration)['imported'],
          'last_imported' => $this->getMigrationStatus($migration)['last_imported'],
        ];
      }
      if (isset($migrationsToRun['not_found'])) {
        foreach ($migrationsToRun['not_found'] as $notFound) {
          $messageData['not_found'][] = $notFound;
        }
      }

      return $messageData;
    }
    else {
      $this->messenger()->addError('One of the preflight checks failed. Check the Preflight Check status on the settings form.');
    }

  }

  /**
   * Runs specific migration.
   *
   * @param string $migration
   *   The migration ID.
   */
  public function runMigration($migration) {
    // Loop over the list of the migrations and check if they require
    // execution.
    // Prevent non-existent migrations from breaking cron.
    $migrationInstance = $this->migrationManager->createInstance($migration);
    if ($migrationInstance) {
      // Check if the migration status is IDLE, if not, make it so.
      $status = $migrationInstance->getStatus();
      if ($status !== MigrationInterface::STATUS_IDLE) {
        $migrationInstance->setStatus(MigrationInterface::STATUS_IDLE);
      }

      /*
       * @todo Possibly implement the following flags, if needed.
       * Runs migration with the --update flag.
       * $migration_update = $migration->getIdMap();
       * $migration_update->prepareUpdate();
       * Runs migration with the --sync flag.
       * The problem here is if editor adds layout builder, this will wipe those
       * changes out before recreating. So, this not be a good idea.
       * $migrationInstance->set('syncSource', TRUE);
       */

      $message = new MigrateMessage();
      $executable = new MigrateExecutable($migrationInstance, $message);
      $executable->import();

      /* If using migrate_plus module, update the migrate_last_imported value
       * for the migration.
       */

      if ($this->moduleHandler->moduleExists('migrate_plus')) {
        $migrate_last_imported_store = $this->keyValue('migrate_last_imported');
        $migrate_last_imported_store->set($migrationInstance->id(), round($this->time->getCurrentMicroTime() * 1000));
      }
    }
  }

  /**
   * Returns an array of pre-checked migrations.
   */
  private function getMigrationsToRun() {
    $migrations = [];
    // Get the group migration.
    $groupMigration = $this->localistConfig->get('localist_group_migration');
    if ($this->getMigrationStatus($groupMigration)) {
      $migrations['found'][] = $groupMigration;
    }

    // Get the dependency migrations.
    $dependencyMigrations = $this->localistConfig->get('localist_dependency_migrations');
    foreach ($dependencyMigrations as $depMigration) {
      if ($this->getMigrationStatus($depMigration)) {
        $migrations['found'][] = $depMigration;
      }
      elseif ($depMigration) {
        $migrations['not_found'][] = $depMigration;
      }
    }

    // Get the events migration.
    $eventsMigration = $this->localistConfig->get('localist_event_migration');
    if ($this->getMigrationStatus($eventsMigration)) {
      $migrations['found'][] = $eventsMigration;
    }
    elseif ($eventsMigration) {
      $migrations['not_found'][] = $eventsMigration;
    }

    return $migrations;
  }

  /**
   * Gets the migration status such as number of items imported.
   *
   * @return array
   *   Array is imported count and last imported timestamp.
   */
  public function getMigrationStatus($migration_id) {
    $migration = $this->migrationManager->createInstance($migration_id);
    if (!$migration) {
      return [];
    }
    $map = $migration->getIdMap();
    $status = [
      'imported' => $map->importedCount(),
      'last_imported' => $this->dateFormatter->formatTimeDiffSince($this->getLastImportedTimestamp($migration_id)) . " ago",
    ];
    return $status;

  }

  /**
   * Performs all preflight checks for other functions to proceed.
   */
  public function preflightChecks() {
    if (
      // Localist sync enabled.
      $this->localistConfig->get('enable_localist_sync') &&
      // Endpoint is returning JSON data.
      $this->checkEndpoint() &&
      // Check group migration config file exists.
      $this->getMigrationStatus($this->localistConfig->get('localist_group_migration')) &&
      // Check group taxonomy vocabulary and ID field.
      $this->checkGroupTaxonomy() &&
      // Check that groups have been imported.
      $this->getMigrationStatus($this->localistConfig->get('localist_group_migration'))['imported'] > 0 &&
      // Check we have a group ID to send to Localist.
      $this->getGroupTaxonomyEntity()
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the last imported timestamp for a given migration.
   */
  private function getLastImportedTimestamp($migration_id) {
    $migration = $this->migrationManager->createInstance($migration_id);

    if ($migration instanceof MigrationInterface) {
      // Get the migrate map table name.
      $map_table = 'migrate_map_' . $migration->id();

      // Query the migrate map table for the last imported timestamp.
      $query = $this->database->select($map_table, 'm')
        ->fields('m', ['last_imported'])
        ->orderBy('last_imported', 'DESC')
        ->range(0, 1);

      $result = $query->execute()->fetchField();

      if ($result) {
        return $result;
      }
    }

    return NULL;
  }

  /**
   * Checks the Localist endpoint to make sure we are receiving a JSON feed.
   */
  public function checkEndpoint() {
    $returnVal = FALSE;
    if ($endpoint = $this->localistConfig->get('localist_endpoint')) {
      $endpointUrl = $endpoint . "/api/2/events";
      try {
        $response = $this->httpClient->get($endpointUrl);
        $returnVal = str_contains($response->getHeader("Content-Type")[0], 'json') ? TRUE : FALSE;
      }
      catch (\Throwable $th) {

      }

    }

    return $returnVal;

  }

  /**
   * Status of the group vocabulary which are required for the group  migration.
   */
  public function checkGroupTaxonomy() {
    // Get the storage for taxonomy vocabularies.
    $vocabularies = $this->entityTypeManager()->getStorage('taxonomy_vocabulary');

    // Load group vocabulary.
    $groupVocab = $vocabularies->load(self::GROUP_VOCABULARY);
    if ($groupVocab) {
      $fieldDef = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $groupVocab->id());
      // Check if the correct field exists.
      if (isset($fieldDef[self::GROUP_ID_FIELD])) {
        /** @var Drupal\taxonomy\Entity\Vocabulary $vocabulary */
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns ticket info for a given event ID.
   */
  public function getTicketInfo($eventId) {
    $ticketData = [];
    $response = NULL;
    $ticketEndpoint = $this->getEndpointUrls('tickets');
    $version = time();
    $url = "$ticketEndpoint[0]/$eventId/tickets?v=$version";
    try {
      $response = $this->httpClient->get($url);
    }
    catch (\Throwable $th) {
    }

    if ($response) {
      $data = json_decode($response->getBody()->getContents(), TRUE);
      foreach ($data['tickets'] as $ticket) {
        $ticketData[] = [
          'name' => $ticket['ticket']['name'],
          'desc' => $ticket['ticket']['description'],
          'id' => $ticket['ticket']['id'],
          'price' => $ticket['ticket']['price'],
        ];
      }
    }
    return $ticketData;
  }

  /**
   * Gets the path to icon files.
   * */
  public function getIcon($filename) {
    $modulePath = $this->moduleHandler->getModule('localist_drupal')->getPath();
    $svgPath = $modulePath . "/assets/icons/$filename";
    if (file_exists($svgPath)) {
      return $svgPath;
    }
    else {
      return $this->t(":filename file not found.", [':filename' => $filename]);
    }
  }

}
