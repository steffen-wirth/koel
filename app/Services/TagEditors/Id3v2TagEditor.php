<?php

namespace App\Services\TagEditors;

use RuntimeException;

/**
 * Edits specific frames of an ID3v2.3/2.4 tag. All other frames (pictures, synced lyrics, MBIDs, …) are copied
 * byte by byte.
 */
class Id3v2TagEditor
{
    private const TEXT_FRAMES = [
        'title' => 'TIT2',
        'artist' => 'TPE1',
        'album' => 'TALB',
        'album_artist' => 'TPE2',
        'track' => 'TRCK',
        'disc' => 'TPOS',
        'genre' => 'TCON',
    ];

    private const NEW_TAG_PADDING = 1024;

    /**
     * Edit the given tags of an MP3 file. An empty (or zero) value removes the tag.
     *
     * @param array<string, string|int|null> $tags
     */
    public function edit(string $path, array $tags): void
    {
        $in = fopen($path, 'rb');

        throw_unless($in, new RuntimeException("Could not open $path."));

        try {
            [$version, $frames, $oldTagSize, $audioOffset] = $this->readTag($in);

            foreach (self::TEXT_FRAMES as $key => $id) {
                if (array_key_exists($key, $tags)) {
                    $frames = $this->replace($frames, [$id], $this->textFrame($version, $id, (string) $tags[$key]));
                }
            }

            if (array_key_exists('year', $tags)) {
                $frames = $this->replace(
                    $frames,
                    ['TYER', 'TDRC'],
                    $this->textFrame($version, $version === 4 ? 'TDRC' : 'TYER', (string) $tags['year']),
                );
            }

            if (array_key_exists('lyrics', $tags)) {
                // A leftover synced lyrics frame would take precedence over the edited lyrics when scanning.
                $frames = $this->replace(
                    $frames,
                    ['USLT', 'SYLT'],
                    $this->lyricsFrame($version, (string) $tags['lyrics']),
                );
            }

            $this->writeFile($path, $in, $version, $frames, $oldTagSize, $audioOffset);
        } finally {
            fclose($in);
        }
    }

    /** @return array{int, list<array{string, string}>, int, int} version, [id, raw frame] list, tag size, audio offset */
    private function readTag($in): array
    {
        $header = (string) fread($in, 10);

        if (!str_starts_with($header, 'ID3')) {
            return [3, [], 0, 0];
        }

        $version = ord($header[3]);
        $flags = ord($header[5]);

        throw_unless(in_array($version, [3, 4], true), new RuntimeException("Unsupported ID3v2.$version tag."));
        throw_if($flags & 0x80, new RuntimeException('Unsynchronised ID3v2 tags are not supported.'));

        $size = $this->decodeSyncsafe(substr($header, 6, 4));
        $body = (string) fread($in, $size);
        $pos = 0;

        if ($flags & 0x40) {
            // Extended header, dropped on write. Its size is syncsafe and includes itself in 2.4 only.
            $extSize = $version === 4 ? $this->decodeSyncsafe(substr($body, 0, 4)) : unpack('N', $body)[1] + 4;
            $pos = $extSize;
        }

        $frames = [];

        while (($pos + 10) <= $size && preg_match('/^[A-Z0-9]{4}$/', $id = substr($body, $pos, 4))) {
            $sizeBytes = substr($body, $pos + 4, 4);
            $frameSize = $version === 4 ? $this->decodeSyncsafe($sizeBytes) : unpack('N', $sizeBytes)[1];

            if (($pos + 10 + $frameSize) > $size) {
                break;
            }

            $frames[] = [$id, substr($body, $pos, 10 + $frameSize)];
            $pos += 10 + $frameSize;
        }

        return [$version, $frames, $size, 10 + $size + ($flags & 0x10 ? 10 : 0)];
    }

    /**
     * @param list<array{string, string}> $frames
     * @param list<string> $ids
     * @return list<array{string, string}>
     */
    private function replace(array $frames, array $ids, ?string $newFrame): array
    {
        $frames = array_values(array_filter($frames, static fn (array $frame) => !in_array($frame[0], $ids, true)));

        if ($newFrame !== null) {
            $frames[] = [substr($newFrame, 0, 4), $newFrame];
        }

        return $frames;
    }

    private function textFrame(int $version, string $id, string $value): ?string
    {
        if ($value === '' || $value === '0' && $id === 'TRCK') {
            return null;
        }

        [$encoding, $text] = $this->encode($version, $value);

        return $this->frame($version, $id, chr($encoding) . $text);
    }

    private function lyricsFrame(int $version, string $lyrics): ?string
    {
        if ($lyrics === '') {
            return null;
        }

        [$encoding, $text] = $this->encode($version, $lyrics);
        $emptyDescriptor = $encoding === 1 ? "\xFF\xFE\x00\x00" : "\x00";

        return $this->frame($version, 'USLT', chr($encoding) . 'eng' . $emptyDescriptor . $text);
    }

    /** @return array{int, string} ID3 encoding byte and the encoded text */
    private function encode(int $version, string $text): array
    {
        if ($version === 4) {
            return [3, $text];
        }

        $latin1 = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        if (mb_convert_encoding($latin1, 'UTF-8', 'ISO-8859-1') === $text) {
            return [0, $latin1];
        }

        return [1, "\xFF\xFE" . mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')];
    }

    private function frame(int $version, string $id, string $body): string
    {
        $size = $version === 4 ? $this->encodeSyncsafe(strlen($body)) : pack('N', strlen($body));

        return $id . $size . "\x00\x00" . $body;
    }

    /**
     * @param resource $in
     * @param list<array{string, string}> $frames
     */
    private function writeFile(string $path, $in, int $version, array $frames, int $oldTagSize, int $audioOffset): void
    {
        $framesData = implode('', array_column($frames, 1));
        // Keep the tag size (so the audio doesn't move) if the frames still fit in.
        $padding = max($oldTagSize - strlen($framesData), 0) ?: self::NEW_TAG_PADDING;

        $tagSize = strlen($framesData) + $padding;
        $header = 'ID3' . chr($version) . "\x00\x00" . $this->encodeSyncsafe($tagSize);

        $newPath = "$path.new";
        $out = fopen($newPath, 'wb');

        throw_unless($out, new RuntimeException("Could not open $newPath."));

        try {
            fwrite($out, $header . $framesData . str_repeat("\x00", $padding));
            fseek($in, $audioOffset);
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($out);
        }

        throw_unless(rename($newPath, $path), new RuntimeException("Could not replace $path."));
    }

    private function decodeSyncsafe(string $bytes): int
    {
        return (ord($bytes[0]) << 21) | (ord($bytes[1]) << 14) | (ord($bytes[2]) << 7) | ord($bytes[3]);
    }

    private function encodeSyncsafe(int $number): string
    {
        return (
            chr(($number >> 21) & 0x7F) . chr(($number >> 14) & 0x7F) . chr(($number >> 7) & 0x7F) . chr($number & 0x7F)
        );
    }
}
