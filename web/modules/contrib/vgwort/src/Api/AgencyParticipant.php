<?php

namespace Drupal\vgwort\Api;

class AgencyParticipant extends Participant {

  /**
   * @param string $code
   *   The agency's abbreviation. 2-4 characters.
   * @param string $involvement
   *   How the participant is involved. Either 'AUTHOR', 'TRANSLATOR', or
   *   'PUBLISHER'.
   */
  public function __construct(public readonly string $code, string $involvement) {
    parent::__construct($involvement);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'code' => $this->code,
    ] + parent::jsonSerialize();
  }

  /**
   * Helper callback for usort().
   */
  public static function agencySort(AgencyParticipant $a, AgencyParticipant $b): int {
    return $a->code <=> $b->code;
  }

}
