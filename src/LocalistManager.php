<?php

namespace Drupal\localist_drupal;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
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
   * List of migrations to run. Place migrations from first to last.
   */
  const LOCALIST_MIGRATIONS = [
    'localist_event_types',
    'localist_audience',
    'localist_topics',
    'localist_experiences',
    'localist_groups',
    'localist_places',
    'localist_status',
    'localist_events',
  ];

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
    $groupId = NULL;
    $groupTermId = $this->localistConfig->get('localist_group');
    if ($groupTermId) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($groupTermId);
      $groupId = ($term) ? $term->field_localist_group_id->value : NULL;
    }

    return $groupId;
  }

  /**
   * Runs all Localist migrations.
   *
   * @return array
   *   Array of status of all migrations run.
   */
  public function runAllMigrations() {
    if ($this->getEndpointUrls('events')) {
      foreach (self::LOCALIST_MIGRATIONS as $migration) {
        $this->runMigration($migration);
        $messageData[$migration] = [
          'imported' => $this->getMigrationStatus($migration)['imported'],
          'last_imported' => $this->getMigrationStatus($migration)['last_imported'],
        ];
      }
      return $messageData;
    }
    else {
      $this->messenger()->addError('Localist endpoint not configured correctly. No events imported.');
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
   * Gets the migration status such as number of items imported.
   *
   * @return null|array
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
      'last_imported' => $this->timeAgo($this->getLastImportedTimestamp($migration_id)),
    ];
    return $status;

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
  public function groupIdFieldExists() {
    $fieldName = 'field_localist_group_id';
    // Get the storage for taxonomy vocabularies.
    $vocabularies = $this->entityTypeManager()->getStorage('taxonomy_vocabulary');

    // Load all vocabularies.
    $vocabularies = $vocabularies->loadMultiple();

    $vocabsWithField = [];
    // Iterate over each vocabulary and check for the field.
    foreach ($vocabularies as $vocabulary) {

      // Get the field definitions for the current vocabulary.
      $fieldDef = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());

      // Check if the field exists in the field definitions.
      if (isset($fieldDef[$fieldName])) {
        /** @var Drupal\taxonomy\Entity\Vocabulary $vocabulary */
        $vocabsWithField[] = $vocabulary->get('name');
      }

    }

    if (!empty($vocabsWithField)) {
      return $vocabsWithField;
    }

    return NULL;
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
      return $this->t('SVG file not found.');
    }
  }

  /**
   * Gets a formatted time-ago given a timestamp.
   */
  private function timeAgo($timestamp) {
    $time_difference = time() - $timestamp;

    if ($time_difference < 1) {
      return 'less than 1 second ago';
    }
    $condition = [
      12 * 30 * 24 * 60 * 60 => 'year',
      30 * 24 * 60 * 60      => 'month',
      24 * 60 * 60           => 'day',
      60 * 60                => 'hour',
      60                     => 'minute',
      1                      => 'second',
    ];

    foreach ($condition as $secs => $str) {
      $d = $time_difference / $secs;

      if ($d >= 1) {
        $t = round($d);
        return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
      }
    }
  }

}
