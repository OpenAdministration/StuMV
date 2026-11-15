#!/usr/bin/env python3
"""Builds the Adwaita Sans font files used by tc-lib-pdf (App\\Support\\MembershipCertificate).

tc-lib-pdf neither handles variable fonts nor applies OpenType features, so the
variable AdwaitaSans-Regular.ttf is instanced into static Regular/Bold faces with
tabular figures baked into the cmap, then converted into tc-lib-pdf's format.

Usage (from the project root, needs fonttools and the composer dependencies):
    python3 resources/fonts/pdf/build.py /path/to/AdwaitaSans-Regular.ttf
"""

import subprocess
import sys
import tempfile
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[2]
WEIGHTS = {'Regular': 400, 'Bold': 700}


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


def main(source: str) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        files = []
        for style, weight in WEIGHTS.items():
            font = instantiateVariableFont(TTFont(source), {'wght': weight, 'opsz': 14}, static=True)
            bake_tabular_figures(font)
            path = Path(tmp) / f'AdwaitaSans-{style}.ttf'
            font.save(path)
            files.append(str(path))

        subprocess.run([
            'php', str(ROOT / 'vendor/tecnickcom/tc-lib-pdf-font/util/convert.php'),
            '-t', 'TrueTypeUnicode', '-o', str(OUT), '-i', ','.join(files),
        ], check=True)


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else '/usr/share/fonts/adwaita-sans-fonts/AdwaitaSans-Regular.ttf')
