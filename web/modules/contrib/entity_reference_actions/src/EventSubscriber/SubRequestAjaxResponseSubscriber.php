<?php

namespace Drupal\entity_reference_actions\EventSubscriber;

use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Drupal\entity_reference_actions\Render\EmptyAttachmentsProcessor;

/**
 * Decorator for AjaxResponseSubscriber.
 *
 * For subrequests from the ERA module we don't want to process attachments,
 * because they are processed later in the main request.
 */
class SubRequestAjaxResponseSubscriber extends AjaxResponseSubscriber {

  /**
   * @var \Drupal\Core\EventSubscriber\AjaxResponseSubscriber
   */
  protected $ajaxResponseSubscriber;

  /**
   * @var \Drupal\Core\EventSubscriber\AjaxResponseSubscriber
   */
  protected $eraAjaxResponseSubscriber;

  /**
   * {@inheritdoc}
   */
  public function __construct(AjaxResponseSubscriber $ajax_response_subscriber) {
    $this->ajaxResponseSubscriber = $ajax_response_subscriber;
    if (floatval(\Drupal::VERSION) < 10.3) {
      $argument = new EmptyAttachmentsProcessor();
    }
    else {
      $argument = function () {
        return new EmptyAttachmentsProcessor();
      };
    }
    $this->eraAjaxResponseSubscriber = new AjaxResponseSubscriber($argument);
  }

  /**
   * {@inheritdoc}
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest() && $event->getRequest()->query->get('era_subrequest')) {
      $this->eraAjaxResponseSubscriber->onResponse($event);
      return;
    }

    $this->ajaxResponseSubscriber->onResponse($event);
  }

}
