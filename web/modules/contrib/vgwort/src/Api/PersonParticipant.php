<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Assertion\Inspector;

/**
 * A participant in a text. For example the author or publisher.
 */
class PersonParticipant extends Participant {

  /**
   * @param int|null $cardNumber
   *   The participant's card number from VG Wort.
   * @param string $firstName
   *   First name (2-40 characters).
   * @param string $surName
   *   Surname (2-255 characters).
   * @param string $involvement
   *   How the participant is involved. Either 'AUTHOR', 'TRANSLATOR', or
   *   'PUBLISHER'.
   * @param \Drupal\vgwort\Api\IdentificationCode[] $identificationCodes
   *   (optional) The participant's identification codes.
   */
  public function __construct(private readonly ?int $cardNumber, public readonly string $firstName, public readonly string $surName, string $involvement, private readonly array $identificationCodes = []) {
    parent::__construct($involvement);
    assert(Inspector::assertAllObjects($identificationCodes, IdentificationCode::class));
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    $values = [];
    if ($this->cardNumber !== NULL) {
      $values['cardNumber'] = $this->cardNumber;
    }
    $values['firstName'] = $this->firstName;
    if (!empty($this->identificationCodes)) {
      $values['identificationCodes'] = $this->identificationCodes;
    }
    $values['surName'] = $this->surName;
    $values += parent::jsonSerialize();
    return $values;
  }

  /**
   * Helper callback for usort().
   */
  public static function personSort(PersonParticipant $a, PersonParticipant $b): int {
    if ($a->surName === $b->surName) {
      if ($a->firstName === $b->firstName) {
        return $a->cardNumber <=> $b->cardNumber;
      }
      return $a->firstName <=> $b->firstName;
    }
    return $a->surName <=> $b->surName;
  }

}
