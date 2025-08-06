<?php

namespace Drupal\content_lock\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionHandler;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Hook implementations for the Content Lock module.
 */
class ContentLockHooks {
  use StringTranslationTrait;

  public function __construct(
    private ContentLockInterface $contentLock,
    private MessengerInterface $messenger,
    private ConfigFactoryInterface $configFactory,
    private AccountInterface $currentUser,
    private TimeInterface $time,
    private Connection $database,
    private EntityTypeManagerInterface $entityTypeManager,
    private DateFormatterInterface $dateFormatter,
    private LoggerChannelFactoryInterface $logger,
  ) {
  }

  /**
   * Implements hook_user_predelete().
   *
   * Delete content locks entries when a user gets deleted. If a user has
   * permission to cancel or delete a user then it is not necessary to check
   * whether they can break locks.
   */
  #[Hook('user_predelete', order: Order::First)]
  public function userPredelete(UserInterface $account): void {
    $this->contentLock->releaseAllUserLocks((int) $account->id());
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel($edit, UserInterface $account, $method): void {
    $this->contentLock->releaseAllUserLocks((int) $account->id());
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for views.
   *
   * When a view is saved, prevent using a cache if the content_lock data is
   * displayed.
   */
  #[Hook('view_presave')]
  public function viewPresave(ViewEntityInterface $view): void {
    $viewDependencies = $view->getDependencies();
    if (in_array('content_lock', $viewDependencies['module'] ?? [], TRUE)) {
      $changed_cache = FALSE;
      $displays = $view->get('display');
      foreach ($displays as &$display) {
        if (isset($display['display_options']['cache']['type']) && $display['display_options']['cache']['type'] !== 'none') {
          $display['display_options']['cache']['type'] = 'none';
          $changed_cache = TRUE;
        }
      }
      if ($changed_cache) {
        $view->set('display', $displays);
        $warning = $this->t('The selected caching mechanism does not work with views including content lock information. The selected caching mechanism was changed to none accordingly for the view %view.', ['%view' => $view->label()]);
        $this->messenger->addWarning($warning);
      }
    }
  }

  /**
   * Implements hook_content_lock_entity_lockable().
   */
  #[Hook('content_lock_entity_lockable', module: 'trash')]
  public function trashContentEntityLockable(EntityInterface $entity, array $config, ?string $form_op = NULL): bool {
    return !trash_entity_is_deleted($entity);
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];

    if ($this->contentLock->isLockable($entity)) {
      $lock = $this->contentLock->fetchLock($entity);

      if ($lock && $this->currentUser->hasPermission('break content lock')) {
        $entity_type = $entity->getEntityTypeId();
        $route_parameters = [
          'entity' => $entity->id(),
          'langcode' => $this->contentLock->isTranslationLockEnabled($entity_type) ? $entity->language()
            ->getId() : LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'form_op' => '*',
        ];
        $url = 'content_lock.break_lock.' . $entity->getEntityTypeId();
        $operations['break_lock'] = [
          'title' => $this->t('Break lock'),
          'url' => Url::fromRoute($url, $route_parameters),
          'weight' => 50,
        ];
      }
    }

    return $operations;
  }

  /**
   * Implements hook_entity_delete().
   *
   * Releases locks when an entity is deleted. Note that users are prevented
   * from deleting locked content by content_lock_entity_access() if they do not
   * have the break lock permission.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (!$this->contentLock->isLockable($entity)) {
      return;
    }

    $data = $this->contentLock->fetchLock($entity, include_stale_locks: TRUE);
    if ($data !== FALSE) {
      $this->contentLock->release($entity);
    }
  }

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    $result = AccessResult::neutral();
    if ($operation === 'delete') {
      // Check if we must lock this entity.
      $result->addCacheableDependency($this->configFactory->get('content_lock.settings'));
      if ($this->contentLock->hasLockEnabled($entity->getEntityTypeId())) {
        // The result is dependent on user IDs.
        $result->cachePerUser();
        // If the entity type is lockable this access result cannot be cached as
        // you can lock an entity just by visiting the edit form.
        $result->setCacheMaxAge(0);
        $data = $this->contentLock->fetchLock($entity);
        if ($data !== FALSE && $account->id() !== $data->uid) {
          // If the entity is locked, and current user is not the lock's owner.
          if ($account->id() !== $data->uid && !$account->hasPermission('break content lock')) {
            $result = $result->andIf(AccessResult::forbidden('The entity is locked'));
          }
        }
      }
    }

    return $result;
  }

  /**
   * Implements hook_cron().
   *
   * Breaks batches of stale locks whenever the cron hooks are
   * run. Inspired by original content_lock_cron() (leftover from the
   * checkout module).
   */
  #[Hook('cron')]
  public function cron(): void {
    $timeout = $this->configFactory->get('content_lock.settings')->get('timeout');
    if ($timeout < 1) {
      return;
    }

    $last_valid_time = $this->time->getCurrentTime() - $timeout;

    // We call release() for each lock so that the
    // hook_content_lock_released may be invoked.
    $query = $this->database->select('content_lock', 'c');
    $query->fields('c')
      ->condition('c.timestamp', $last_valid_time, '<');
    // Track the number successful locks released.
    $count = 0;
    // Track the number of removed locks on entities that do not exist.
    $removed = 0;
    foreach ($query->execute() as $obj) {
      $entity = $this->entityTypeManager->getStorage($obj->entity_type)->load($obj->entity_id);
      if ($entity instanceof EntityInterface) {
        if ($entity instanceof TranslatableInterface) {
          $entity = $entity->hasTranslation($obj->langcode) ? $entity->getTranslation($obj->langcode) : $entity;
        }
        $this->contentLock->release($entity, $obj->form_op, $obj->uid);
        $count++;
      }
      else {
        $removed += $this->database->delete('content_lock')
          ->condition('entity_id', $obj->entity_id)
          ->condition('entity_type', $obj->entity_type)
          ->execute();
      }
    }

    if ($count) {
      $period = $this->dateFormatter->formatInterval($timeout);
      $this->logger->get('content_lock')->notice(
        'Released @count stale content lock(s) which lasted at least @period.',
        ['@count' => $count, '@period' => $period]
      );
    }
    if ($removed) {
      $this->logger->get('content_lock')->notice(
        'Removed @removed content lock(s) on entities which no longer exist.',
        ['@removed' => $removed]
      );
    }
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public function userLogout(AccountInterface $account): void {
    // Only do the database check if the original drupal session manager is
    // used. Otherwise, it's not sure if sessions table has correct data.
    // @phpstan-ignore-next-line
    if (\Drupal::service('session_handler.storage') instanceof SessionHandler) {
      $session_count = (int) $this->database->select('sessions')
        ->condition('uid', $account->id())
        ->countQuery()
        ->execute()->fetchField();
    }
    else {
      $session_count = FALSE;
    }
    // Only remove all locks of user if it's the last session of the user.
    if ($session_count === 1) {
      $this->contentLock->releaseAllUserLocks((int) $account->id());
    }
  }

}
