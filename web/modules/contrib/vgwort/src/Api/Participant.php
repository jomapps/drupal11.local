<?php

namespace Drupal\vgwort\Api;

/**
 * A participant in a text. For example the author or publisher.
 */
abstract class Participant implements \JsonSerializable {

  public const AUTHOR = 'AUTHOR';

  public const TRANSLATOR = 'TRANSLATOR';

  public const PUBLISHER = 'PUBLISHER';

  /**
   * @param string $involvement
   *   How the participant is involved. Either 'AUTHOR', 'TRANSLATOR', or
   *   'PUBLISHER'.
   */
  public function __construct(private readonly string $involvement) {
    if (!in_array($involvement, [static::AUTHOR, static::PUBLISHER, static::TRANSLATOR], TRUE)) {
      throw new \InvalidArgumentException(sprintf("'%s' is not a valid involvement.", $involvement));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'involvement' => $this->involvement,
    ];
  }

  /**
   * Helper callback for uasort() to sort participants.
   */
  public static function sort(Participant $a, Participant $b): int {
    if ($a instanceof AgencyParticipant && $b instanceof AgencyParticipant) {
      $comp = AgencyParticipant::agencySort($a, $b);
    }
    if ($a instanceof PersonParticipant && $b instanceof PersonParticipant) {
      $comp = PersonParticipant::personSort($a, $b);
    }
    if (isset($comp)) {
      if ($comp === 0) {
        return $a->involvement <=> $b->involvement;
      }
      return $comp;
    }
    // Person participants before agency participants.
    return $b instanceof PersonParticipant ? 1 : -1;
  }

  /**
   * Gets the participant's involvement.
   *
   * @return string
   *   The participant's involvement; either author, publisher, or translator.
   */
  public function getInvolvement(): string {
    return $this->involvement;
  }

  /**
   * Determines if the provided list has at least one author participant.
   *
   * @param \Drupal\vgwort\Api\Participant[] $participants
   *   The list of participants.
   *
   * @return bool
   *   TRUE if the participant list has at least one author participant, FALSE
   *   if not.
   */
  public static function listHasAuthor(array $participants): bool {
    foreach ($participants as $participant) {
      if ($participant->getInvolvement() === static::AUTHOR) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
