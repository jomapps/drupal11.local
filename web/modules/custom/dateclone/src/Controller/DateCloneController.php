<?php

namespace Drupal\dateclone\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for DateClone module functionality.
 */
class DateCloneController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DateCloneController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * AJAX endpoint for cloning events with new dates.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with created node IDs.
   */
  public function clone(Request $request) {
    $nid = (int) $request->get('nid');
    $data = $request->get('data');
    
    if (!$nid || !is_array($data)) {
      return new JsonResponse(['error' => 'Invalid request data'], 400);
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    
    if (!$node || $node->getType() !== 'event') {
      return new JsonResponse(['error' => 'Event node not found'], 404);
    }

    $ids = [];
    foreach ($data as $value) {
      $id = $this->cloneNode($node, $value);
      if ($id !== NULL) {
        $ids[] = [
          'nid' => $id,
          'uid' => $value['uid'] ?? 'dt-' . count($ids),
        ];
      }
    }

    return new JsonResponse($ids);
  }

  /**
   * Access callback for clone endpoint.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access dateclone')
      ->andIf(AccessResult::allowedIfHasPermission($account, 'create event content'));
  }

  /**
   * Clones multiple nodes based on provided values.
   *
   * @param int $nid
   *   The node ID to clone.
   * @param array $values
   *   Array of date/time values for cloning.
   *
   * @return bool
   *   TRUE if successful.
   */
  public function cloneNodes($nid, array $values) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    
    if (!$node || $node->getType() !== 'event') {
      return FALSE;
    }

    foreach ($values as $value) {
      $this->cloneNode($node, $value);
    }
    
    return TRUE;
  }

  /**
   * Clones a single node with a new date.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to clone.
   * @param array $value
   *   Array containing date, time, and uid.
   *
   * @return int|null
   *   The new node ID or NULL on failure.
   */
  private function cloneNode(NodeInterface $node, array $value) {
    if (!isset($value['date']) || !isset($value['time']) || empty($value['date']) || empty($value['time'])) {
      return NULL;
    }

    // Validate date format
    $date_string = $value['date'] . ' ' . substr($value['time'], 0, 5);
    $startdate = date_create_from_format('Y-m-d H:i', $date_string);

    if (!$startdate) {
      return NULL;
    }

    try {
      // Create DrupalDateTime object and convert to storage timezone
      $dtime = new DrupalDateTime($date_string);
      $dtime->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $dtime_format = $dtime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      // Create duplicate node
      $cloned_node = $node->createDuplicate();
      
      // Set the new start date
      $cloned_node->set('field_startdate', $dtime_format);
      
      // Update changed time
      $cloned_node->set('changed', time());
      
      // Save the cloned node
      $cloned_node->save();

      return $cloned_node->id();
    }
    catch (\Exception $e) {
      \Drupal::logger('dateclone')->error('Error cloning node @nid: @message', [
        '@nid' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
