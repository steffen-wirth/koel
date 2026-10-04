<?php

namespace App\Services\TagEditors;

use App\Models\SongCredit;
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
        'bpm' => 'TBPM',
        'musical_key' => 'TKEY',
    ];

    /** MusicBrainz identifiers kept in TXXX frames, by their conventional description. */
    private const TXXX_MBIDS = [
        'album_mbid' => 'MusicBrainz Album Id',
        'artist_mbid' => 'MusicBrainz Artist Id',
        'albumartist_mbid' => 'MusicBrainz Album Artist Id',
    ];

    private const UFID_OWNER = 'http://musicbrainz.org';

    private const CREDIT_FRAMES = ['TCOM', 'TEXT', 'TPE3', 'TPE4', 'TIPL', 'IPLS', 'TMCL'];

    /** Credit roles that list their people in the "involvement" frame, with the role as its label. */
    private const INVOLVEMENT_ROLES = ['producer', 'arranger', 'engineer', 'recording', 'mix'];

    private const PERFORMER_ROLES = ['instrument', 'vocal', 'performer'];

    private const NEW_TAG_PADDING = 1024;

    /**
     * Edit the given tags of an MP3 file. An empty (or zero) value removes the tag.
     *
     * @param array<string, string|int|null|list<SongCredit>> $tags
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

            foreach (self::TXXX_MBIDS as $key => $description) {
                if (array_key_exists($key, $tags)) {
                    $frames = $this->replaceTxxx($frames, $version, $description, (string) $tags[$key]);
                }
            }

            if (array_key_exists('mbid', $tags)) {
                $frames = $this->replaceUfid($frames, $version, (string) $tags['mbid']);
            }

            if (array_key_exists('credits', $tags)) {
                $frames = $this->replaceCredits($frames, $version, $tags['credits']);
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
    private function encode(int $version, string $text, ?int $forceEncoding = null): array
    {
        if ($version === 4) {
            return [3, $text];
        }

        if ($forceEncoding === 1) {
            return [1, "\xFF\xFE" . mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')];
        }

        $latin1 = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

        if (mb_convert_encoding($latin1, 'UTF-8', 'ISO-8859-1') === $text) {
            return [0, $latin1];
        }

        return [1, "\xFF\xFE" . mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')];
    }

    /**
     * @param list<array{string, string}> $frames
     * @param iterable<SongCredit> $credits
     * @return list<array{string, string}>
     */
    private function replaceCredits(array $frames, int $version, iterable $credits): array
    {
        $names = static fn (array $roles) => collect($credits)
            ->filter(static fn (SongCredit $credit) => in_array($credit->role, $roles, true))
            ->map(static fn (SongCredit $credit) => $credit->name)
            ->unique()
            ->values()
            ->all();

        $involved = [];
        $performers = [];

        foreach ($credits as $credit) {
            if (in_array($credit->role, self::INVOLVEMENT_ROLES, true)) {
                $involved[] = [$credit->role, $credit->name];
            } elseif (in_array($credit->role, self::PERFORMER_ROLES, true)) {
                $performers[] = [$credit->instrument ?: $credit->role, $credit->name];
            }
        }

        $frames = array_values(array_filter(
            $frames,
            static fn (array $frame) => !in_array($frame[0], self::CREDIT_FRAMES, true),
        ));

        $new = [
            $this->multiTextFrame($version, 'TCOM', $names(['composer', 'writer'])),
            $this->multiTextFrame($version, 'TEXT', $names(['lyricist'])),
            $this->multiTextFrame($version, 'TPE3', $names(['conductor'])),
            $this->multiTextFrame($version, 'TPE4', $names(['remixer'])),
        ];

        if ($version === 4) {
            $new[] = $this->multiTextFrame($version, 'TIPL', array_merge(...$involved));
            $new[] = $this->multiTextFrame($version, 'TMCL', array_merge(...$performers));
        } else {
            // ID3v2.3 only knows one list of involved people.
            $new[] = $this->multiTextFrame($version, 'IPLS', array_merge(...$involved, ...$performers));
        }

        foreach (array_filter($new) as $frame) {
            $frames[] = [substr($frame, 0, 4), $frame];
        }

        return $frames;
    }

    /** @param list<string> $values */
    private function multiTextFrame(int $version, string $id, array $values): ?string
    {
        if (!$values) {
            return null;
        }

        [$encoding] = $this->encode($version, implode('', $values));
        $terminator = $encoding === 1 ? "\x00\x00" : "\x00";
        $body = implode($terminator, array_map(
            fn (string $value) => $this->encode($version, $value, $encoding)[1],
            $values,
        ));

        return $this->frame($version, $id, chr($encoding) . $body);
    }

    /**
     * @param list<array{string, string}> $frames
     * @return list<array{string, string}>
     */
    private function replaceTxxx(array $frames, int $version, string $description, string $value): array
    {
        $frames = array_values(array_filter(
            $frames,
            fn (array $frame) => $frame[0] !== 'TXXX' || $this->txxxDescription($frame[1]) !== $description,
        ));

        if ($value !== '') {
            [$encoding, $encodedDescription] = $this->encode($version, $description);
            $terminator = $encoding === 1 ? "\x00\x00" : "\x00";
            $frame = $this->frame(
                $version,
                'TXXX',
                chr($encoding) . $encodedDescription . $terminator . $this->encode($version, $value, $encoding)[1],
            );
            $frames[] = ['TXXX', $frame];
        }

        return $frames;
    }

    /**
     * @param list<array{string, string}> $frames
     * @return list<array{string, string}>
     */
    private function replaceUfid(array $frames, int $version, string $mbid): array
    {
        $frames = array_values(array_filter(
            $frames,
            static fn (array $frame) => $frame[0] !== 'UFID'
            || !str_starts_with(substr($frame[1], 10), self::UFID_OWNER . "\x00"),
        ));

        if ($mbid !== '') {
            $frames[] = ['UFID', $this->frame($version, 'UFID', self::UFID_OWNER . "\x00" . $mbid)];
        }

        return $frames;
    }

    private function txxxDescription(string $rawFrame): string
    {
        $body = substr($rawFrame, 10);
        $encoding = ord($body[0] ?? "\x00");
        $rest = substr($body, 1);

        if ($encoding === 1 || $encoding === 2) {
            // UTF-16: the description ends at the first aligned double null.
            for ($i = 0; ($i + 1) < strlen($rest); $i += 2) {
                if ($rest[$i] === "\x00" && $rest[$i + 1] === "\x00") {
                    $text = substr($rest, 0, $i);

                    return $encoding === 1 && str_starts_with($text, "\xFE\xFF")
                        ? mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE')
                        : mb_convert_encoding(preg_replace('/^\xFF\xFE/', '', $text), 'UTF-8', 'UTF-16LE');
                }
            }

            return '';
        }

        $text = explode("\x00", $rest, 2)[0];

        return $encoding === 0 ? mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1') : $text;
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
