#!/usr/bin/env python3
"""Detect the tempo and the musical key of an audio file with Essentia and print them as JSON."""

import json
import sys

import essentia.standard as es

SAMPLE_RATE = 44100


def main(path):
    audio = es.MonoLoader(filename=path, sampleRate=SAMPLE_RATE)()

    bpm, _, _, _, _ = es.RhythmExtractor2013(method="multifeature")(audio)
    key, scale, strength = es.KeyExtractor()(audio)

    print(
        json.dumps(
            {
                "bpm": round(float(bpm)),
                "key": key + ("m" if scale == "minor" else ""),
                "key_strength": round(float(strength), 3),
            }
        )
    )


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit("Usage: analyze-audio.py <file>")

    main(sys.argv[1])
