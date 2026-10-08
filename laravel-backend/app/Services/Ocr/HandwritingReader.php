<?php

namespace App\Services\Ocr;

/**
 * Turns a photo of handwriting (a homework page, a board) into text. The app
 * depends on this, not on a vendor, so the reader can be swapped.
 */
interface HandwritingReader
{
    /** Whether the reader has what it needs (an API key) to work. */
    public function isConfigured(): bool;

    /**
     * @param  string  $imageBase64  the image, base64 encoded
     * @param  string  $mediaType  image/jpeg, image/png, image/webp or image/gif
     * @return string the text, line breaks kept; empty when the image has none
     */
    public function read(string $imageBase64, string $mediaType): string;
}
