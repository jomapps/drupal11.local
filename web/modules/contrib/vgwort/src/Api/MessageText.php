<?php

namespace Drupal\vgwort\Api;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Unicode;

/**
 * The message text.
 */
class MessageText implements \JsonSerializable {

  /**
   * The minimum length of a non-lyric text.
   */
  public const MINIMUM_CHARACTERS = 1800;

  private const VALID_TEXT_TYPES = ['epub', 'pdf', 'plainText'];

  /**
   * Short description / heading (title).
   *
   * @var string
   */
  private readonly string $shorttext;

  /**
   * The text itself.
   *
   * Possible keys are: 'epub', 'pdf' and 'plainText'. The values are:
   * - epub: The text in PDF format (base 64 encoded!). Maximum size: 15 MB.
   * - pdf: The text in EPUB format (base 64 encoded!). Maximum size: 15 MB.
   * - plaintext: Plain text without HTML and other formatting information (base
   *   64 encoded!). Maximum size: 15 MB.
   *
   * @var string[]
   *
   * @todo Does the order of the keys matter if there are multiple? We are using
   *   ksort() to ensure the same order as the example.
   */
  private array $text;

  /**
   * @param string $shorttext
   *   Short description / heading (title).
   * @param string $text
   *   The text itself.
   * @param string $text_type
   *   (optional) The text type. Either 'epub', 'pdf', 'plainText'. Defaults to
   *   'plainText'.
   * @param bool $lyric
   *   (optional) TRUE if the text is poetic text, otherwise FALSE. Defaults to
   *   FALSE.
   */
  public function __construct(string $shorttext, string $text, string $text_type = 'plainText', private readonly bool $lyric = FALSE) {
    // The maximum length for the shorttext field is 100 and the minimum length
    // is 1. As the title field is required we do not need to check the minimum
    // length.
    $this->shorttext = Unicode::truncate(PlainTextOutput::renderFromHtml($shorttext), 100, TRUE, FALSE, 50);
    $this->addText($text, $text_type);
  }

  /**
   * Adds text to the message.
   *
   * @param string $text
   *   The text. If the type is 'plainText' any HTML will be stripped.
   * @param string $text_type
   *   (optional) The text type. Defaults to 'plainText'.
   *
   * @return $this
   */
  public function addText(string $text, string $text_type = 'plainText') {
    if (!in_array($text_type, self::VALID_TEXT_TYPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf("'%s' is not a valid text type.", $text_type));
    }
    if ($text_type === 'plainText') {
      $text = PlainTextOutput::renderFromHtml($text);
    }
    $this->text[$text_type] = base64_encode($text);
    ksort($this->text);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'lyric' => $this->lyric,
      'shorttext' => $this->shorttext,
      'text' => $this->text,
    ];
  }

  /**
   * Determines if the text meets VG Wort's minimum character requirements.
   *
   * @return bool
   *   TRUE if the text meets VG Wort's minimum character requirements, FALSE if
   *   not.
   */
  public function hasMinimumCharacters(): bool {
    // Lyrics have no minimum length.
    if ($this->lyric) {
      return TRUE;
    }

    return $this->characterCount() >= static::MINIMUM_CHARACTERS;
  }

  /**
   * Counts the number of characters in the text including spaces.
   *
   * @return int
   *   The number of characters in the text including spaces.
   */
  public function characterCount(): int {
    if (isset($this->text['plainText'])) {
      return mb_strlen(base64_decode($this->text['plainText']));
    }
    // @todo Can we implement character counting for PDFs or EPUB? For now
    //   assuming that they meet the minimum requirements.
    return self::MINIMUM_CHARACTERS;
  }

}
