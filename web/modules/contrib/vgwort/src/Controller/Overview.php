<?php

namespace Drupal\vgwort\Controller;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\views\field\JobState;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\vgwort\Api\AgencyParticipant;
use Drupal\vgwort\Api\Participant;
use Drupal\vgwort\Api\PersonParticipant;
use Drupal\vgwort\EntityInfo;
use Drupal\vgwort\EntityJobMapper;
use Drupal\vgwort\ParticipantListManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Overview extends ControllerBase {

  public function __construct(
    private readonly EntityInfo $entityInfo,
    private readonly EntityJobMapper $jobMapper,
    private readonly ParticipantListManager $participantListManager,
    private readonly DateFormatterInterface $dateFormatter,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('vgwort.entity_info'),
      $container->get('vgwort.entity_job_mapper'),
      $container->get('vgwort.participant_list_manager'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
    );
  }

  public function __invoke(RouteMatchInterface $route_match, string $entity_type_id): array {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);

    $this->entityInfo->validateAndWarn($entity);

    $build = [];
    $build['#title'] = $this->t('VG Wort overview for %title', ['%title' => $entity->label()]);
    $build['table'] = $this->buildInfoTable($entity);
    $build['#attached'] = [
      'library' => ['core/drupal.dialog.ajax'],
    ];

    // This page depends on queue information and potentially data on references
    // therefore it is not possible to cache this info.
    CacheableMetadata::createFromObject($entity)->setCacheMaxAge(0)->applyTo($build);
    return $build;
  }

  /**
   * Generates a table of VG Wort information.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to generate info for.
   *
   * @return array
   *   The table render array.
   */
  private function buildInfoTable(FieldableEntityInterface $entity): array {
    if ($entity instanceof RevisionableInterface) {
      $revisions_sent = $this->jobMapper->getRevisionsSent($entity);
      if ($last_revision_sent_id = array_key_last($revisions_sent)) {
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
        $storage = $this->entityTypeManager()->getStorage($entity->getEntityTypeId());
        /** @var \Drupal\Core\Entity\RevisionableInterface $last_revision_sent */
        $last_revision_sent = $storage->loadRevision($last_revision_sent_id);
        // @todo can we turn this into a link or maybe a date. It might be hard
        //   to support an entity type.
        $participants = $this->participantListManager->getParticipants($last_revision_sent);
      }
    }

    // List participants sent to VG Wort unless we're yet to send.
    if (!isset($participants)) {
      $participants = $this->participantListManager->getParticipants($entity);
    }

    $job = $this->jobMapper->getJob($entity);
    if ($job instanceof Job) {
      $state = $job->getState();
      $state_options = JobState::getOptions();
      $label = $state_options[$state] ?? $state;
      if ($job->getProcessedTime() > 0) {
        $queue_time_header = $this->t('Queue processed time');
        $queue_time = $job->getProcessedTime();
      }
      else {
        $queue_time_header = $this->t('Queue available time');
        $queue_time = (int) $job->getAvailableTime();
      }
      $queue_time = $queue_time > 0 ? $this->dateFormatter->format($queue_time) : '';
      $queue_message = $job->getMessage();
    }
    else {
      $state = 'not-queued';
      if ($entity instanceof EditorialContentEntityBase && !$entity->isPublished()) {
        $label = $this->t('Not yet queued because the entity is not published');
      }
      else {
        $label = $this->t('Not yet queued');
      }
    }

    $rows = [];
    $rows[] = [
      ['data' => $this->t('Counter ID'), 'header' => TRUE],
      $entity->vgwort_counter_id->value,
    ];

    $template = <<<TWIG
    {{ sent_participants }}
    {% if current_participants and current_participants != sent_participants %}
    <br /><br /><span style="font-size: smaller">{% trans %}This has changed since being sent. It is not possible to update this information on VG Wort.<br />The current value is:{% endtrans %} {{ current_participants }}</span>
    {% endif %}
    TWIG;

    $themed_participants = $this->themeParticipantList($participants);
    $themed_current_participants = $this->themeParticipantList($this->participantListManager->getParticipants($entity));

    $rows[] = [
      ['data' => $this->formatPlural(count($themed_participants[Participant::AUTHOR]['#items'] ?? [1]), 'Author', 'Authors'), 'header' => TRUE],
      [
        'data' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => [
            'sent_participants' => $themed_participants[Participant::AUTHOR] ?? '',
            'current_participants' => $themed_current_participants[Participant::AUTHOR] ?? '',
          ],
        ],
      ],
    ];

    if (isset($themed_participants[Participant::TRANSLATOR])) {
      $rows[] = [
        ['data' => $this->formatPlural(count($themed_participants[Participant::TRANSLATOR]['#items'] ?? [1]), 'Translator', 'Translators'), 'header' => TRUE],
        [
          'data' => [
            '#type' => 'inline_template',
            '#template' => $template,
            '#context' => [
              'sent_participants' => $themed_participants[Participant::TRANSLATOR],
              'current_participants' => $themed_current_participants[Participant::TRANSLATOR],
            ],
          ],
        ],
      ];
    }

    $rows[] = [
      ['data' => $this->t('Queue status'), 'header' => TRUE],
      [
        'data' => [
          '#theme' => 'advancedqueue_state_icon',
          '#state' => [
            'state' => $state,
            'label' => $label,
          ],
        ],
      ],
    ];
    if (isset($queue_time_header)) {
      $rows[] = [
        ['data' => $queue_time_header, 'header' => TRUE],
        $queue_time ?? '',
      ];
      $rows[] = [
        ['data' => $this->t('Queue message'), 'header' => TRUE],
        $queue_message ?? '',
      ];
    }

    if (isset($last_revision_sent_id)) {
      $rows[] = [
        ['data' => $this->t('Revision sent'), 'header' => TRUE],
        $last_revision_sent_id,
      ];
    }

    // Build the operation links.
    $operation_links = [];

    $view_text_url = Url::fromRoute("entity.{$entity->getEntityTypeId()}.vgwort.text", [$entity->getEntityTypeId() => $entity->id()]);
    $operation_links['view_text'] = [
      'title' => $this->t('View text sent'),
      'url' => $view_text_url,
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 880,
        ]),
      ],
    ];

    $job_url = Url::fromRoute('view.advancedqueue_jobs.page_1', ['arg_0' => 'vgwort']);
    $operation_links['queue_admin'] = [
      'title' => $this->t('Administer queue'),
      'url' => $job_url,
    ];

    $rows[] = [
      ['data' => $this->t('Operations'), 'header' => TRUE],
      [
        'data' => [
          '#type' => 'operations',
          '#links' => $operation_links,
        ],
      ],
    ];

    // Put the table together.
    return [
      '#type' => 'table',
      '#rows' => $rows,
    ];
  }

  /**
   * @param \Drupal\vgwort\Api\Participant[] $participants
   *   The list of participants to theme.
   *
   * @return array
   *   The theme list of participants keyed by participant involvement.
   */
  private function themeParticipantList(array $participants): array {
    $list = [];
    foreach ($participants as $participant) {
      $list[$participant->getInvolvement()][] = match(TRUE) {
        $participant instanceof PersonParticipant => $participant->firstName . ' ' . $participant->surName,
        $participant instanceof AgencyParticipant => $this->t('@code agency', ['@code' => $participant->code]),
      };
    }
    return array_map(function (array $participants) {
      if (count($participants) === 1) {
        return [
          '#plain_text' => reset($participants),
        ];
      }
      return [
        '#theme' => 'item_list',
        '#items' => $participants,
        '#context' => ['list_style' => 'comma-list'],
      ];
    }, $list);
  }

}
