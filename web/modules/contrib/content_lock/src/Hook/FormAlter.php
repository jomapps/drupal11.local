<?php

namespace Drupal\content_lock\Hook;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generic form alter hook implementation for the Content Lock module.
 */
class FormAlter {
  use DependencySerializationTrait;
  use StringTranslationTrait;

  public function __construct(
    private ContentLockInterface $contentLock,
    private MessengerInterface $messenger,
    private ConfigFactoryInterface $configFactory,
    private AccountInterface $currentUser,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();

    // Check if we must lock this entity.
    $form_op = $form_state->getFormObject()->getOperation();
    if (!$this->contentLock->isLockable($entity, $form_op)) {
      return;
    }

    // We act only on edit form, not for a creation of a new entity.
    if (!is_null($entity->id())) {
      foreach (['submit', 'publish'] as $key) {
        if (isset($form['actions'][$key])) {
          $form['actions'][$key]['#submit'][] = [$this, 'formSubmit'];
        }
      }

      // We lock the content if it is currently edited by another user.
      $messages = [];
      if (!$this->contentLock->locking($entity, $form_op, $this->currentUser->id(), FALSE, NULL, $messages)) {
        $form['#disabled'] = TRUE;

        // Do not allow deletion, publishing, or unpublishing if locked.
        foreach (['delete', 'publish', 'unpublish'] as $key) {
          if (isset($form['actions'][$key])) {
            unset($form['actions'][$key]);
          }
        }

        // If moderation state is in use also disable corresponding buttons.
        if (isset($form['moderation_state'])) {
          unset($form['moderation_state']);
        }
      }
      else {
        // ContentLock::locking() returns TRUE if the content is locked by the
        // current user. Add an unlock button only for this user.
        $form['actions']['unlock'] = $this->contentLock->unlockButton($entity, $form_op, $this->requestStack->getCurrentRequest()->query->get('destination'));
      }

      // Add the messages to the form.
      $form['content_lock_messages'] = [
        '#type' => 'content_lock_messages',
        '#message_list' => $messages,
      ];
    }
  }

  /**
   * Submit handler for content_lock.
   */
  public function formSubmit($form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();

    // If the user submitting owns the lock, release it.
    $this->contentLock->release($entity, $form_state->getFormObject()->getOperation(), (int) $this->currentUser->id());

    // We need to redirect to the canonical page after saving it. If not, we
    // stay on the edit form and we re-lock the entity.
    if (!$form_state->getRedirect() || ($form_state->getRedirect() && $entity->hasLinkTemplate('edit-form') && $entity->toUrl('edit-form')->toString() == $form_state->getRedirect()->toString())) {
      $form_state->setRedirectUrl($entity->toUrl());
    }
  }

}
