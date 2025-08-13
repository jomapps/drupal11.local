<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Represents a newMessage for VG Wort's REST API.
 *
 * @see https://tom.vgwort.de/api/external/swagger-ui/index.html#/message-external-rest-controller/newMessageUsingPOST_1
 */
class NewMessage implements \JsonSerializable {

  /**
   * @param string $privateidentificationid
   *   Identification ID of the counter mark. Either the private counter ID
   *   identification code (in case of a VG WORT counter id) or the publisher
   *   key.
   * @param \Drupal\vgwort\Api\MessageText $messageText
   *   The message text.
   * @param \Drupal\vgwort\Api\Participant[] $participants
   *   The authors and publisher of the message. At least one author or
   *   translator must be specified. Both authors and translators can be
   *   specified in a report.
   * @param \Drupal\vgwort\Api\Webrange[] $webranges
   *   Publication location(s) where the text can be found.
   * @param bool $distributionRight
   *   Distribution right (§ 17 UrhG). See
   *   https://www.gesetze-im-internet.de/urhg/__17.html/
   * @param bool $publicAccessRight
   *   Right of public access (§ 19a UrhG). See
   *   https://www.gesetze-im-internet.de/urhg/__19a.html.
   * @param bool $reproductionRight
   *   Reproduction Rights (§ 16 UrhG). See
   *   https://www.gesetze-im-internet.de/urhg/__16.html.
   * @param bool $rightsGrantedConfirmation
   *   Declaration of Granting of Rights. The right of reproduction (§ 16 UrhG),
   *   right of distribution (§ 17 UrhG), right of public access (§ 19a UrhG)
   *   and the declaration of granting rights must be confirmed.
   * @param bool $otherRightsOfPublicReproduction
   *   Other Public Communication Rights (§§ 19, 20, 21, 22 UrhG). See
   *   https://www.gesetze-im-internet.de/urhg/__19.html
   *   https://www.gesetze-im-internet.de/urhg/__20.html
   *   https://www.gesetze-im-internet.de/urhg/__21.html
   *   https://www.gesetze-im-internet.de/urhg/__22.html
   * @param bool $withoutOwnParticipation
   *   Indication of whether the publisher is involved in the work.
   */
  public function __construct(
    private readonly string $privateidentificationid,
    private readonly MessageText $messageText,
    private readonly array $participants,
    private readonly array $webranges,
    private readonly bool $distributionRight = FALSE,
    private readonly bool $publicAccessRight = FALSE,
    private readonly bool $reproductionRight = FALSE,
    private readonly bool $rightsGrantedConfirmation = FALSE,
    private readonly bool $otherRightsOfPublicReproduction = FALSE,
    private readonly bool $withoutOwnParticipation = FALSE,
  ) {
    assert(Inspector::assertAllObjects($participants, Participant::class));
    assert(Inspector::assertAllObjects($webranges, Webrange::class));
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'distributionRight' => $this->distributionRight,
      'messagetext' => $this->messageText,
      'otherRightsOfPublicReproduction' => $this->otherRightsOfPublicReproduction,
      'participants' => $this->participants,
      'privateidentificationid' => $this->privateidentificationid,
      'publicAccessRight' => $this->publicAccessRight,
      'reproductionRight' => $this->reproductionRight,
      'rightsGrantedConfirmation' => $this->rightsGrantedConfirmation,
      'webranges' => $this->webranges,
      'withoutOwnParticipation' => $this->withoutOwnParticipation,
    ];
  }

  /**
   * Validates the message to send to VG Wort.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An empty array if valid, otherwise a list of reasons why VG Wort will
   *   reject the message.
   */
  public function validate(): array {
    $reasons = [];
    if (!$this->messageText->hasMinimumCharacters()) {
      $reasons[] = new TranslatableMarkup(
        'The minimum numbers of characters in order to be counted by VG Wort is @minimum_count. The current count is @current_count.',
        ['@minimum_count' => MessageText::MINIMUM_CHARACTERS, '@current_count' => $this->messageText->characterCount()]
      );
    }
    if (!Participant::listHasAuthor($this->participants)) {
      $reasons[] = new TranslatableMarkup('In order to be counted by VG Wort there must be at least one author.');
    }
    return $reasons;
  }

}
