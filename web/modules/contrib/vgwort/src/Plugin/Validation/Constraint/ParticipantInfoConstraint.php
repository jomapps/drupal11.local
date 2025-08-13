<?php

namespace Drupal\vgwort\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * VG Wort participant info constraint.
 *
 * @Constraint(
 *   id = "vgwort_participant_info",
 *   label = @Translation("VG Wort participant info", context = "Validation"),
 *   type = { "vgwort_participant_info" }
 * )
 */
class ParticipantInfoConstraint extends Constraint {

  /**
   * The violation message when the firstname is missing.
   *
   * @var string
   */
  public string $requiredFirstnameMessage = 'The Firstname field is required.';

  /**
   * The violation message when the surname is missing.
   *
   * @var string
   */
  public string $requiredSurnameMessage = 'The Surname field is required.';

  /**
   * The violation message when the agency code is provided and names are too.
   *
   * @var string
   */
  public string $onlyAgencyMessage = 'Firstname, Surname and Card number cannot be provided when an Agency code is used.';

}
