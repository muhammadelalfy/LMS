<?php

namespace App\Services\Ocr;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;

/**
 * Reads handwriting with Claude's vision. Arabic handwriting is why: the
 * on-device recognisers do not read it. Needs ANTHROPIC_API_KEY on the
 * server; the key never reaches the app.
 */
class ClaudeHandwritingReader implements HandwritingReader
{
    private const INSTRUCTIONS = <<<'TXT'
        Transcribe all the text in this image: handwriting and print. It is a school homework page or board, most likely in Arabic (Egyptian teachers and students), with some English and numbers.
        Rules:
        - Output only the transcribed text. No introduction, explanation, translation, or markdown.
        - Keep the original language and the line breaks. Keep numbers and symbols as written.
        - Do not fix spelling or complete unfinished words.
        - Write ‹؟› in place of a word you cannot read.
        - If the image contains no text, output nothing.
        TXT;

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function read(string $imageBase64, string $mediaType): string
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));

        try {
            $message = $client->messages->create(
                model: (string) config('services.anthropic.ocr_model'),
                maxTokens: 4096,
                // Copying text needs little reasoning, so keep the effort low.
                outputConfig: ['effort' => 'low'],
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => $imageBase64]],
                        ['type' => 'text', 'text' => self::INSTRUCTIONS],
                    ],
                ]],
            );
        } catch (APIException $error) {
            report($error);
            abort(502, 'تعذر قراءة الصورة الآن. حاول مرة أخرى بعد قليل.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
