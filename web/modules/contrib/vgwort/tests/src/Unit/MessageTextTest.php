<?php

declare(strict_types=1);

namespace Drupal\Tests\vgwort\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\vgwort\Traits\PrettyJsonTrait;
use Drupal\vgwort\Api\MessageText;

/**
 * @coversDefaultClass \Drupal\vgwort\Api\MessageText
 *
 * @group vgwort
 */
class MessageTextTest extends UnitTestCase {
  use PrettyJsonTrait;

  public function testSerialization(): void {
    $text = new MessageText('The <blink>title</blink>', '<strong>The text</strong>');
    $expected_value = <<<JSON
{
    "lyric": false,
    "shorttext": "The title",
    "text": {
        "plainText": "VGhlIHRleHQ="
    }
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($text));

    $text = new MessageText('The title', 'The text 2', 'plainText', TRUE);
    $text->addText('The text 2', 'pdf');
    $expected_value = <<<JSON
{
    "lyric": true,
    "shorttext": "The title",
    "text": {
        "pdf": "VGhlIHRleHQgMg==",
        "plainText": "VGhlIHRleHQgMg=="
    }
}
JSON;

    $this->assertSame($expected_value, $this->jsonEncode($text));

  }

  public function testInvalidTextType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("'nope' is not a valid text type.");
    new MessageText('The title', 'The text', 'nope');
  }

  public function testVeryLongTitles(): void {
    $long_title = str_repeat('A long title ', 100);
    $text = new MessageText($long_title, '<strong>The text</strong>');
    $data = json_decode($this->jsonEncode($text), TRUE);
    $this->assertGreaterThan(100, strlen($long_title));
    $this->assertSame(97, strlen($data['shorttext']));
    // Ensure the truncation does not break a word,
    $this->assertMatchesRegularExpression('/ long$/', $data['shorttext']);

    // Ensure that long strings are truncated even if there is no work break.
    $long_title = str_repeat('enormous', 100);
    $text = new MessageText($long_title, '<strong>The text</strong>');
    $data = json_decode($this->jsonEncode($text), TRUE);
    $this->assertGreaterThan(100, strlen($long_title));
    $this->assertSame(100, strlen($data['shorttext']));
  }

}
