#!/usr/bin/env python3
"""Builds the font files used by tc-lib-pdf (App\\Support\\MembershipCertificate).

tc-lib-pdf neither handles variable fonts nor applies OpenType features, so the
variable AdwaitaSans-Regular.ttf and AdwaitaSans-Italic.ttf are instanced into static
Regular/Bold/Italic/BoldItalic faces, plus a
separate "AdwaitaSansTabular" face with tabular figures baked into the cmap for
columns of dates, then converted into tc-lib-pdf's format. The headings use the
static Merriweather Bold as is.

Usage (from the project root, needs fonttools and the composer dependencies):
    python3 resources/fonts/pdf/build.py /path/to/adwaita-sans-fonts/ /path/to/merriweather-fonts/
"""

import subprocess
import sys
import tempfile
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[2]
# file name -> (source file, weight, tabular figures); the converter derives the font key
# and the italic flag from the name
FACES = {
    'AdwaitaSans-Regular': ('AdwaitaSans-Regular.ttf', 400, False),
    'AdwaitaSans-Bold': ('AdwaitaSans-Regular.ttf', 700, False),
    'AdwaitaSans-Italic': ('AdwaitaSans-Italic.ttf', 400, False),
    'AdwaitaSans-BoldItalic': ('AdwaitaSans-Italic.ttf', 700, False),
    'AdwaitaSansTabular-Regular': ('AdwaitaSans-Regular.ttf', 400, True),
}


def bake_tabular_figures(font: TTFont) -> None:
    gsub = font['GSUB'].table
    mapping = {}
    for record in gsub.FeatureList.FeatureRecord:
        if record.FeatureTag != 'tnum':
            continue
        for index in record.Feature.LookupListIndex:
            for subtable in gsub.LookupList.Lookup[index].SubTable:
                mapping.update(getattr(subtable, 'mapping', {}))

    # Digits only - tnum also widens punctuation like the hyphen.
    for table in font['cmap'].tables:
        for codepoint, glyph in table.cmap.items():
            if chr(codepoint).isdigit() and codepoint < 128 and glyph in mapping:
                table.cmap[codepoint] = mapping[glyph]


def main(source: str, merriweather: str) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        files = [str(Path(merriweather) / 'Merriweather-Bold.ttf')]
        for name, (file, weight, tabular) in FACES.items():
            font = instantiateVariableFont(TTFont(Path(source) / file), {'wght': weight, 'opsz': 14}, static=True)
            if tabular:
                bake_tabular_figures(font)
            path = Path(tmp) / f'{name}.ttf'
            font.save(path)
            files.append(str(path))

        subprocess.run([
            'php', str(ROOT / 'vendor/tecnickcom/tc-lib-pdf-font/util/convert.php'),
            '-t', 'TrueTypeUnicode', '-o', str(OUT), '-i', ','.join(files),
        ], check=True)


if __name__ == '__main__':
    main(
        sys.argv[1] if len(sys.argv) > 1 else '/usr/share/fonts/adwaita-sans-fonts',
        sys.argv[2] if len(sys.argv) > 2 else '/usr/share/fonts/sorkintype-merriweather-fonts',
    )
