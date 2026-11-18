<?php

namespace App\Support;

use App\Enums\ExportDateFormat;
use App\Models\RealmContact;
use Collator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/**
 * Draws the membership certificate directly onto the page as tagged PDF/UA-2,
 * following the layout of ref-it/helga's PlanPdfRenderer (DIN 5008 margins,
 * zinc rules, one table font size). Every text run sits in a structure element
 * and every rule is an artifact, so screen readers get heading and table
 * semantics and skip the decoration.
 */
final class MembershipCertificatePdf
{
    /** Base unit of the vertical rhythm, in mm (5pt). */
    private const float SPACE = 1.75;

    private const float MARGIN_LEFT = 25.0;

    private const float MARGIN_RIGHT = 20.0;

    /** Left edge of the letterhead and the address field. */
    private const float LETTERHEAD_LEFT = 20.0;

    private const float MARGIN_TOP = 20.0;

    private const float MARGIN_BOTTOM = 25.0;

    /** Distance from the bottom paper edge to the bottom of the page number. */
    private const float FOOTER_EDGE_GAP = 10.0;

    private const float CELL_PADDING = 2.0;

    private const float HEAD_PADDING_BOTTOM = 1.2;

    private const float LINE_SPACING = 0.8;

    /** Head and body cells alike, so a row never reads louder than its head. */
    private const float TABLE_FONT_SIZE = 10.0;

    private const float HEADING_SIZE = 15.0;

    /** Committee headings above their table. */
    private const float GROUP_HEADING_SIZE = 12.0;

    private const float META_FONT_SIZE = 10.0;

    private const string FONT = 'adwaitasans';

    /** Tabular figures, for dates, addresses and phone numbers. */
    private const string FONT_TABULAR = 'adwaitasanstabular';

    /** Table columns holding dates. */
    private const array DATE_COLUMNS = [1, 2];

    private const float LETTER_FONT_SIZE = 11.0;

    /** Line height of the letter text, relative to the font size. */
    private const float LETTER_LINE_HEIGHT = 1.4;

    private const float ADDRESS_FONT_SIZE = 10.0;

    private const float DATE_FONT_SIZE = 10.0;

    /** Six lines fill the 27.3mm address zone. */
    private const float ADDRESS_LINE_PITCH = 27.3 / 6;

    private const float INFO_FONT_SIZE = 9.0;

    private const float RETURN_FONT_SIZE = 7.0;

    private const float SENDER_FONT_SIZE = 16.0;

    /** DIN 5008 form B, in mm from the top left paper corner. */
    private const float LETTERHEAD_RULE_Y = 33.0;

    /** Name baseline and logo bottom both sit this far above the letterhead rule. */
    private const float LETTERHEAD_GAP = 3.0;

    /** Zusatz- und Vermerkzone of DIN 5008 form B, 45 to 62.7mm. */
    private const float NOTES_ZONE_Y = 45.0;

    private const float RECIPIENT_Y = 62.7;

    private const float ADDRESS_WIDTH = 85.0;

    private const float INFO_X = 125.0;

    private const float INFO_Y = 50.0;

    /** Two blank lines (4.23mm each) below the 90mm address field. */
    private const float SUBJECT_Y = 98.46;

    /** One line in DIN 5008 terms. */
    private const float DIN_LINE = 4.23;

    private const float LOGO_MAX_WIDTH = 60.0;

    /** Minimum space between the sender name and the logo. */
    private const float LOGO_GAP = 8.0;

    private const int LOGO_DPI = 1200;

    private const float ICON_SIZE = 3.2;

    /** Room for a handwritten signature, three blank lines in DIN 5008. */
    private const float SIGNATURE_SPACE = 15.0;

    private const float SIGNATURE_LINE_WIDTH = 75.0;

    private const float FOOTER_FONT_SIZE = 8.0;

    private const float PAGE_NUMBER_FONT_SIZE = 10.0;

    private const float HEADER_SUBTITLE_SIZE = self::LETTER_FONT_SIZE;

    /** Space between the footer and the rule above it. */
    private const float FOOTER_RULE_GAP = 3.0;

    /** Between the address, contact and bank columns of the letter footer. */
    private const float FOOTER_COLUMN_GAP = 3.0;

    private const string COLOR_BLACK = '#000000';

    private const string COLOR_ZINC_500 = '#71717b';

    private const string COLOR_ZINC_600 = '#52525c';

    private const string RULE_COLOR = self::COLOR_ZINC_500;

    /** TeX patterns from hyph-utf8 in resources/hyphenation, by locale. */
    private const array HYPHENATION_PATTERNS = [
        'de' => 'hyph-de-1996.tex',
        'en' => 'hyph-en-us.tex',
    ];

    private TaggedPdf $pdf;

    /** @var array<string, int|string> */
    private array $hyphenPatterns = [];

    private float $pageWidth;

    private float $pageHeight;

    private float $contentWidth;

    private float $cursorY;

    /** From the cap height of the title to the baseline of the subtitle below it. */
    private float $logoHeight = 0.0;

    /** Where the footer of the letter pages begins; the certificate has none. */
    private ?float $footerTop = null;

    /** On the letter pages the page number sits above the footer rule. */
    private ?float $letterPageNumberTop = null;

    private int $letterPages = 0;

    private ExportDateFormat $dateFormat = ExportDateFormat::Iso;

    /**
     * @param  array<int, array{role: mixed, from: mixed, until: mixed}>  $memberships
     */
    public function render(string $title, string $fullName, ?string $community, array $memberships, ?CoverLetter $letter = null, ExportDateFormat $dateFormat = ExportDateFormat::Iso): string
    {
        $this->dateFormat = $dateFormat;

        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', resource_path('fonts/pdf'));
        }

        // PHP only defines it when built with zlib, but tc-lib-pdf-image uses it unconditionally.
        if (! defined('IMAGETYPE_SWC')) {
            define('IMAGETYPE_SWC', 13);
        }

        // allowedPaths replaces the library's defaults, so the fonts are listed too
        $this->pdf = new TaggedPdf('mm', true, true, true, 'pdfua2', null, [
            'allowedPaths' => [resource_path('fonts/pdf'), resource_path('hyphenation')],
        ]);
        $this->hyphenPatterns = $this->hyphenationPatterns();
        $this->hyphenate(true);
        $this->pdf->setCreator((string) config('app.name'));
        $this->pdf->setTitle($title.' – '.$fullName);
        $this->pdf->setLanguage(str_replace('_', '-', app()->getLocale()));

        $page = $this->pdf->addPage(['format' => 'A4']);
        $this->pageWidth = $page['width'];
        $this->pageHeight = $page['height'];
        $this->contentWidth = $this->pageWidth - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $this->cursorY = self::MARGIN_TOP;

        if ($letter !== null) {
            $footer = $this->letterFooter($letter);
            $this->footerTop = $this->pageHeight - self::FOOTER_EDGE_GAP - $this->footerHeight($footer);
            $this->font('', self::PAGE_NUMBER_FONT_SIZE, self::FONT_TABULAR);
            $this->letterPageNumberTop = $this->footerTop - self::FOOTER_RULE_GAP - self::SPACE - $this->measure('X', $this->contentWidth);
            $this->drawLetter($letter);
            $this->drawLetterFooter($footer);
            $this->letterPages = count($this->pdf->page->getPages());
            $this->footerTop = null;
            $this->newPage();
        }

        // the cover letter carries the date already
        $this->drawHeader($title, $fullName, $community, withDate: $letter === null);
        $this->drawGroupedTables($memberships);
        $this->drawPageNumbers();

        return $this->pdf->getOutPDFString();
    }

    private function drawLetter(CoverLetter $letter): void
    {
        $right = $this->pageWidth - self::MARGIN_RIGHT;
        $contact = $letter->contact;

        $this->drawFoldMarks();

        // a hyphen inserted into a name, an address or a contact detail reads as part of it
        $this->hyphenate(false);

        $this->logoHeight = $this->letterheadHeight();
        $logoWidth = $this->drawLogo($letter->logoPath, $right);
        $nameWidth = $right - self::LETTERHEAD_LEFT - ($logoWidth > 0 ? $logoWidth + self::LOGO_GAP : 0);
        $halign = $letter->fromPerson ? 'R' : 'L';
        $baseline = self::LETTERHEAD_RULE_Y - self::LETTERHEAD_GAP;
        $subtitleLine = 0.0;
        if ($letter->headerSubtitle !== null) {
            $this->font('', self::HEADER_SUBTITLE_SIZE);
            $baseline = $this->textAbove($letter->headerSubtitle, $baseline, $nameWidth, $halign);
            $subtitleLine = $this->measure('X', $nameWidth);
        }

        // without either the sender name stands in, so the letterhead is never empty
        $title = $letter->headerTitle ?? ($letter->headerSubtitle === null ? $letter->senderName : null);
        if ($title !== null) {
            $this->font('B', self::SENDER_FONT_SIZE);
            // spaced by the subtitle's line, as the title's would leave a gap
            if ($letter->headerSubtitle !== null) {
                $baseline -= $subtitleLine + self::SPACE;
            }

            $this->textAbove($title, $baseline, $nameWidth, $halign);
        }
        $this->artifact($this->pdf->graph->getLine(
            self::LETTERHEAD_LEFT,
            self::LETTERHEAD_RULE_Y,
            $right,
            self::LETTERHEAD_RULE_Y,
            ['lineWidth' => 0.2, 'lineColor' => self::RULE_COLOR],
        ));

        $this->font('', self::RETURN_FONT_SIZE, self::FONT_TABULAR);
        $returnAddress = $this->returnAddress(array_filter([$letter->senderName, $contact?->streetLine(), $contact?->cityLine()]));
        $this->beginTag('P');
        // top line of the notes zone; notes like "Einschreiben" would go below it, above the address
        $this->text($returnAddress, self::LETTERHEAD_LEFT, self::NOTES_ZONE_Y, self::ADDRESS_WIDTH, self::COLOR_ZINC_600);
        $this->endTag();

        $this->font('', self::ADDRESS_FONT_SIZE, self::FONT_TABULAR);
        $this->beginTag('P');
        $linespace = self::ADDRESS_LINE_PITCH - $this->measure('X', self::ADDRESS_WIDTH);
        $this->text(trim($letter->recipient), self::LETTERHEAD_LEFT, self::RECIPIENT_Y, self::ADDRESS_WIDTH, linespace: $linespace);
        $this->endTag();

        $infoBottom = $this->drawInfoBlock($letter, $right);
        $this->hyphenate(true);

        $date = $this->dateFormat->forDay()->format(Date::now());
        // right-aligned on the line above the subject, unless the info block reaches further down
        $this->font('', self::DATE_FONT_SIZE, self::FONT_TABULAR);
        $dateY = max(self::SUBJECT_Y - 2 * self::DIN_LINE, $infoBottom + self::SPACE);
        $this->beginTag('P');
        $this->text(implode(', ', array_filter([$contact?->city, $date])), self::INFO_X, $dateY, $right - self::INFO_X, halign: 'R');
        $this->endTag();
        $this->cursorY = max(self::SUBJECT_Y, $dateY + $this->lastHeight() + self::DIN_LINE);

        // DIN 5008: bold, without a "Subject:" label, followed by two blank lines
        if ($letter->subject !== null) {
            $this->font('B', self::LETTER_FONT_SIZE);
            $this->beginTag('P');
            $this->text($letter->subject, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
            $this->endTag();
            $this->cursorY += $this->lastHeight() + 2 * self::DIN_LINE;
        }

        // Rich text from the editor; the HTML renderer tags paragraphs and lists itself.
        foreach (LetterPlaceholders::splitAtSignatures(CoverLetter::sanitizeBody($letter->body)) as [$part, $signature]) {
            if (! CoverLetter::bodyIsEmpty($part)) {
                $this->font('', self::LETTER_FONT_SIZE);
                $html = '<style>p, li { text-align: justify; line-height: '.self::LETTER_LINE_HEIGHT.'; } hr { color: '.self::COLOR_ZINC_600.'; }</style>'.$part;
                $this->pdf->addHTMLCell($html, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
                $this->cursorY = $this->pdf->getLastHTMLCellEnd()['y'];
            }

            if ($signature !== null) {
                $this->drawSignature($signature);
            }
        }
    }

    /**
     * Room for a handwritten signature, a line, and the lines below it - kept together on one page.
     *
     * @param  list<string>  $lines
     */
    private function drawSignature(array $lines): void
    {
        $this->font('', self::LETTER_FONT_SIZE);
        $this->hyphenate(false);
        $lineHeight = self::LETTER_FONT_SIZE * self::LETTER_LINE_HEIGHT * 25.4 / 72;
        $heights = array_map(fn (string $line): float => $this->measure($line, self::SIGNATURE_LINE_WIDTH), $lines);
        // without lines below, the line of text after it comes along
        $below = $lines === [] ? $lineHeight : array_sum($heights) + (count($lines) - 1) * ($lineHeight - $this->measure('X', $this->contentWidth));
        if (! $this->hasSpace(self::SIGNATURE_SPACE + self::SPACE + $below)) {
            $this->newPage();
        }

        $this->cursorY += self::SIGNATURE_SPACE;
        $this->artifact($this->pdf->graph->getLine(
            self::MARGIN_LEFT,
            $this->cursorY,
            self::MARGIN_LEFT + self::SIGNATURE_LINE_WIDTH,
            $this->cursorY,
            ['lineWidth' => 0.2, 'lineColor' => self::COLOR_ZINC_600],
        ));
        $this->cursorY += self::SPACE;

        foreach ($lines as $line) {
            $this->beginTag('P');
            $this->text($line, self::MARGIN_LEFT, $this->cursorY, self::SIGNATURE_LINE_WIDTH);
            $this->endTag();
            $this->cursorY += $this->lastHeight() + $lineHeight - $this->measure('X', $this->contentWidth);
        }

        $this->hyphenate(true);
    }

    /**
     * Address, contact and bank columns, each a list of [style, text, link] entries set one below the other.
     *
     * @return list<list<array{string, string, ?string}>>
     */
    private function letterFooter(CoverLetter $letter): array
    {
        $contact = $letter->contact;
        $column = function (string $heading, array $lines): array {
            $lines = array_values(array_filter($lines, fn (array $line): bool => (string) $line[0] !== ''));

            return [
                ...($heading !== '' && $lines !== [] ? [['B', $heading, null]] : []),
                ...array_map(fn (array $line): array => ['', (string) $line[0], $line[1] ?? null], $lines),
            ];
        };

        // the realm's own details, not the ones a letter overrides
        $phone = (string) $contact?->phone;
        $email = (string) $contact?->email;

        $office = implode(', ', array_filter([
            $contact?->office_building,
            $contact?->office_room ? __('profile.cover_letter_office_room', ['room' => $contact->office_room]) : null,
        ]));

        // a column left out moves the ones after it to the left
        return array_values(array_filter([
            [['B', $letter->senderName, null], ...$column('', [[$office], [$contact?->streetLine()], [$contact?->cityLine()]])],
            ! $letter->fromPerson ? $column(__('profile.cover_letter_footer_contact'), [
                [$phone, 'tel:'.preg_replace('/[^\d+]/', '', $phone)],
                [$email, 'mailto:'.$email],
                [$contact?->websiteLabel(), $contact?->website],
            ]) : [],
            $contact?->iban ? $column(__('profile.cover_letter_footer_bank'), [
                [$contact->bank_name],
                ['IBAN: '.$contact->ibanLabel()],
                [$contact->bic ? 'BIC: '.$contact->bic : null],
            ]) : [],
        ]));
    }

    private function footerColumnWidth(): float
    {
        return ($this->contentWidth - 2 * self::FOOTER_COLUMN_GAP) / 3;
    }

    /**
     * Height of an entry plus the gap a line break would leave, so all entries share one pitch.
     *
     * @param  array{string, string, ?string}  $entry
     */
    private function footerEntryHeight(array $entry): float
    {
        $this->footerFont($entry[0]);

        return $this->measure($entry[1], $this->footerColumnWidth()) + self::LINE_SPACING;
    }

    /** @param  list<list<array{string, string, ?string}>>  $columns */
    private function footerHeight(array $columns): float
    {
        $this->hyphenate(false);

        return max(array_map(
            fn (array $entries): float => array_sum(array_map($this->footerEntryHeight(...), $entries)),
            $columns,
        )) - self::LINE_SPACING;
    }

    /**
     * The same footer on every page of the letter, below a rule.
     *
     * @param  list<list<array{string, string, ?string}>>  $columns
     */
    private function drawLetterFooter(array $columns): void
    {
        $this->hyphenate(false);
        $top = (float) $this->footerTop;
        $width = $this->footerColumnWidth();

        foreach ($this->pdf->page->getPages() as $page) {
            $pid = (int) $page['pid'];
            // links go on the current page
            $this->pdf->setCurrentPage($pid);
            $this->pdf->addArtifactContent($this->pdf->graph->getLine(
                self::MARGIN_LEFT,
                $top - self::FOOTER_RULE_GAP,
                $this->pageWidth - self::MARGIN_RIGHT,
                $top - self::FOOTER_RULE_GAP,
                ['lineWidth' => 0.2, 'lineColor' => self::RULE_COLOR],
            ), $pid, 'Pagination', 'Footer');

            foreach ($columns as $index => $entries) {
                $x = self::MARGIN_LEFT + $index * ($width + self::FOOTER_COLUMN_GAP);
                $y = $top;
                foreach ($entries as $entry) {
                    [$style, $text, $link] = $entry;
                    $this->footerFont($style);
                    // the colour goes into the content, as the style stack only reaches the current page
                    $content = $this->pdf->graph->add(['fillColor' => self::COLOR_ZINC_600])
                        .$this->pdf->getTextCell($text, $x, $y, $width, linespace: self::LINE_SPACING, halign: 'L', drawcell: false)
                        .$this->pdf->graph->pop();
                    $box = $this->pdf->getLastTextBBox();
                    $this->pdf->addArtifactContent($content, $pid, 'Pagination', 'Footer');
                    // the text is an artifact, so the link becomes a structure element of its own
                    if ($link !== null) {
                        $this->pdf->addTaggedLink($box['x'], $box['y'], $box['w'], $box['h'], $link);
                    }

                    $y += $this->footerEntryHeight($entry);
                }
            }
        }

        $this->hyphenate(true);
    }

    /**
     * Sender details in the right column of the letterhead, each with an icon
     * behind a vertical rule. Returns where the block ends.
     */
    private function drawInfoBlock(CoverLetter $letter, float $right): float
    {
        if ($letter->fromPerson) {
            return self::INFO_Y;
        }

        $contact = $letter->contact;
        $items = array_filter([
            'network' => array_filter([$letter->senderUnit]),
            // the postal address is in the return address and the footer already
            'house' => array_filter([
                $letter->building,
                $letter->room ? __('profile.cover_letter_office_room', ['room' => $letter->room]) : null,
            ]),
            'phone' => array_filter([$letter->phone]),
            'mail' => array_filter([$letter->email]),
            'globe' => array_filter([RealmContact::urlLabel((string) $letter->website)]),
        ]);

        if ($items === []) {
            return self::INFO_Y;
        }

        $links = [
            'phone' => 'tel:'.preg_replace('/[^\d+]/', '', (string) $letter->phone),
            'mail' => 'mailto:'.$letter->email,
            'globe' => (string) $letter->website,
        ];

        $ruleX = $right - self::ICON_SIZE - 2.0;
        $textRight = $ruleX - 2.0;
        $y = self::INFO_Y;

        foreach ($items as $icon => $lines) {
            $this->font($icon === 'network' ? 'B' : '', self::INFO_FONT_SIZE, $icon === 'network' ? self::FONT : self::FONT_TABULAR);
            $lineHeight = $this->measure('X', $this->contentWidth);

            $link = $links[$icon] ?? null;
            $this->beginTag('P');
            if ($link !== null) {
                $this->beginTag('Link');
            }

            $this->text(implode("\n", $lines), self::INFO_X, $y, $textRight - self::INFO_X, self::COLOR_ZINC_600, 'R');
            $height = $this->lastHeight();
            if ($link !== null) {
                $box = $this->pdf->getLastTextBBox();
                $this->pdf->addTaggedLink($box['x'], $box['y'], $box['w'], $box['h'], $link);
                $this->endTag();
            }

            $this->endTag();

            $this->drawIcon($icon, $right - self::ICON_SIZE, $y + ($lineHeight - self::ICON_SIZE) / 2);
            $y += $height + self::SPACE;
        }

        $this->artifact($this->pdf->graph->getLine(
            $ruleX,
            self::INFO_Y,
            $ruleX,
            $y - self::SPACE,
            ['lineWidth' => 0.2, 'lineColor' => self::RULE_COLOR],
        ));

        return $y - self::SPACE;
    }

    /**
     * Parts joined on one line while they fit, otherwise broken between parts.
     *
     * @param  array<int, string>  $parts
     */
    private function returnAddress(array $parts): string
    {
        $lines = [];
        foreach ($parts as $part) {
            $last = array_key_last($lines);
            if ($last !== null && $this->measureWidth($lines[$last].' | '.$part) <= self::ADDRESS_WIDTH) {
                $lines[$last] .= ' | '.$part;
            } else {
                $lines[] = $part;
            }
        }

        return implode("\n", $lines);
    }

    /** Two lines of the letterhead, measured as drawLetter() sets them, whether or not there is a subtitle. */
    private function letterheadHeight(): float
    {
        $this->font('', self::HEADER_SUBTITLE_SIZE);
        $subtitleLine = $this->measure('X', $this->contentWidth);
        $this->font('B', self::SENDER_FONT_SIZE);
        $metrics = $this->pdf->font->getCurrentFont();

        return $subtitleLine + self::SPACE + $metrics['capheight'] * $metrics['usize'] / $metrics['size'];
    }

    /**
     * A paragraph whose last line sits on $baseline; lines a long text wraps to grow upwards.
     * Returns the baseline of its first line.
     */
    private function textAbove(string $text, float $baseline, float $width, string $halign = 'L'): float
    {
        $metrics = $this->pdf->font->getCurrentFont();
        $ascent = $metrics['ascent'] * $metrics['usize'] / $metrics['size'];
        $top = $baseline - $ascent - ($this->measure($text, $width) - $this->measure('X', $width));

        $this->beginTag('P');
        $this->text($text, self::LETTERHEAD_LEFT, $top, $width, halign: $halign);
        $this->endTag();

        return $top + $ascent;
    }

    /** DIN 5008 fold marks at 105mm and 210mm, hole mark at 148.5mm. */
    private function drawFoldMarks(): void
    {
        foreach ([[105.0, 4.0], [148.5, 6.0], [210.0, 4.0]] as [$y, $length]) {
            $this->artifact($this->pdf->graph->getLine(5.0, $y, 5.0 + $length, $y, ['lineWidth' => 0.2, 'lineColor' => self::RULE_COLOR]));
        }
    }

    /**
     * The realm logo, right-aligned in the letterhead. A logo that cannot be
     * read must never break the export, so it is reported and left out.
     */
    private function drawLogo(?string $path, float $right): float
    {
        $disk = Storage::disk('public');
        if ($path === null || ! $disk->exists($path)) {
            return 0.0;
        }

        try {
            $raw = (string) $disk->get($path);

            if (str_contains(strtolower(substr($raw, 0, 1024)), '<svg')) {
                $width = $this->drawSvgLogo($raw, $right);
                if ($width !== null) {
                    return $width;
                }

                // the library's own SVG parser paints nothing for some logos, these get rasterized
                if (! class_exists(\Imagick::class)) {
                    return 0.0;
                }
                $raw = $this->rasterizeSvg($raw);
            } elseif (str_ends_with(strtolower($path), '.webp')) {
                $raw = $this->webpToPng($raw);
            }

            $size = @getimagesizefromstring($raw);
            if ($size === false || $size[1] <= 0) {
                return 0.0;
            }

            $width = min(self::LOGO_MAX_WIDTH, $this->logoHeight * $size[0] / $size[1]);
            $height = $width * $size[1] / $size[0];
            $iid = $this->pdf->image->add('@'.$raw);

            // decorative: the realm name is printed beside it
            $this->artifact($this->pdf->image->getSetImage(
                $iid,
                $right - $width,
                self::LETTERHEAD_RULE_Y - self::LETTERHEAD_GAP - $height,
                $width,
                $height,
                $this->pageHeight,
            ));

            return $width;
        } catch (\Throwable $e) {
            report($e);

            return 0.0;
        }
    }

    /** Embeds an SVG logo as vector graphic; null when the library paints nothing of it. */
    private function drawSvgLogo(string $raw, float $right): ?float
    {
        $dom = new \DOMDocument;
        if (! @$dom->loadXML($raw, LIBXML_NONET) || ! $dom->documentElement instanceof \DOMElement) {
            return null;
        }

        $root = $dom->documentElement;
        $box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox'))) ?: [];
        [$naturalWidth, $naturalHeight] = count($box) === 4
            ? [(float) $box[2], (float) $box[3]]
            : [(float) $root->getAttribute('width'), (float) $root->getAttribute('height')];
        if ($naturalWidth <= 0 || $naturalHeight <= 0) {
            return null;
        }

        $this->splitStrokeFirst($dom);

        $width = min(self::LOGO_MAX_WIDTH, $this->logoHeight * $naturalWidth / $naturalHeight);
        $height = $width * $naturalHeight / $naturalWidth;

        // an SVG names no font, so the importer would register the current one again
        $this->font('', self::INFO_FONT_SIZE);
        $oid = $this->pdf->addSVG('@'.$dom->saveXML(), $right - $width, self::LETTERHEAD_RULE_Y - self::LETTERHEAD_GAP - $height, $width, $height);
        $content = $this->pdf->getSetSVG($oid);

        // without a fill or stroke operator nothing would show
        if (! preg_match('/(^|\s)(f\*?|F|S|s|B\*?|b\*?)(\s|$)/', $content)) {
            return null;
        }

        // decorative: the realm name is printed beside it
        $this->artifact($content);

        return $width;
    }

    /**
     * The library ignores paint-order, so an outline meant to lie behind the fill
     * (e.g. a halo for dark backgrounds) would cover it. Such elements become two:
     * the outline alone, then the fill alone on top.
     */
    private function splitStrokeFirst(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//*[@style or @paint-order]') ?: []) as $element) {
            if (! $element instanceof \DOMElement) {
                continue;
            }

            $style = $element->getAttribute('style');
            $order = preg_match('/paint-order\s*:\s*([^;]+)/', $style, $match) ? $match[1] : $element->getAttribute('paint-order');
            if (! str_starts_with(trim($order), 'stroke')) {
                continue;
            }

            $style = trim((string) preg_replace('/paint-order\s*:[^;]*;?/', '', $style), '; ');
            $element->removeAttribute('paint-order');

            $outline = $element->cloneNode(true);
            if ($outline instanceof \DOMElement) {
                $outline->removeAttribute('id');
                $outline->setAttribute('style', ltrim($style.';fill:none', ';'));
                $element->parentNode?->insertBefore($outline, $element);
            }

            $element->setAttribute('style', ltrim($style.';stroke:none', ';'));
        }
    }

    private function rasterizeSvg(string $raw): string
    {
        $target = (int) round($this->logoHeight / 25.4 * self::LOGO_DPI);

        // read once for the natural size, then again at the density that yields the target height
        $probe = new \Imagick;
        $probe->setResolution(72, 72);
        $probe->readImageBlob($raw);
        $density = 72 * $target / max(1, $probe->getImageHeight());

        $image = new \Imagick;
        $image->setBackgroundColor(new \ImagickPixel('transparent'));
        $image->setResolution($density, $density);
        $image->readImageBlob($raw);
        $image->setImageFormat('png');
        $image->resizeImage(0, $target, \Imagick::FILTER_LANCZOS, 1);

        return $image->getImageBlob();
    }

    private function webpToPng(string $raw): string
    {
        $image = imagecreatefromstring($raw);
        if ($image === false) {
            throw new \RuntimeException('Unreadable logo');
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** Draws a Lucide icon of the project as an artifact - the text beside it says the same. */
    private function drawIcon(string $name, float $x, float $y): void
    {
        $blade = (string) file_get_contents(resource_path("views/flux/icon/{$name}.blade.php"));
        preg_match_all('#<(path|rect|circle|line|polyline)\b([^>]*)/>#', $blade, $matches, PREG_SET_ORDER);

        $body = '';
        foreach ($matches as $element) {
            $body .= '<'.$element[1].$element[2].' fill="none" stroke="'.self::COLOR_ZINC_600
                .'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">'.$body.'</svg>';

        // an icon names no font, so the SVG importer would register the current one again
        $this->font('', self::INFO_FONT_SIZE);
        $oid = $this->pdf->addSVG('@'.$svg, $x, $y, self::ICON_SIZE, self::ICON_SIZE);
        $this->artifact($this->pdf->getSetSVG($oid));
    }

    private function drawHeader(string $title, string $fullName, ?string $community, bool $withDate): void
    {
        $this->font('B', self::HEADING_SIZE);
        $this->beginTag('H1');
        $this->text($title, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
        $this->endTag();
        $this->cursorY += $this->lastHeight() + self::SPACE;

        $meta = implode(' | ', array_filter([$fullName, $community, $withDate ? $this->dateFormat->format(Date::now()) : null]));
        $this->font('', self::META_FONT_SIZE, self::FONT_TABULAR);
        $this->beginTag('P');
        $this->text($meta, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth, self::COLOR_ZINC_600);
        $this->endTag();
        $this->cursorY += $this->lastHeight() + 4 * self::SPACE;
    }

    /**
     * One table per committee under its name; committees alphabetically,
     * each committee's roles by start.
     *
     * @param  array<int, array{role: mixed, from: mixed, until: mixed}>  $memberships
     */
    private function drawGroupedTables(array $memberships): void
    {
        $columns = $this->columnLayout();

        if ($memberships === []) {
            // still a table head, so it is not an empty Table
            $this->beginTag('Table');
            $this->drawTableHead($columns, true);
            $this->endTag();

            return;
        }

        $start = fn (array $row): string => Date::parse($row['from'])->format('Y-m-d');
        $collator = new Collator(app()->getLocale());
        // "Fachschaft 2" before "Fachschaft 10"
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
        $groups = collect($memberships)
            ->sortBy($start)
            ->groupBy(fn (array $row): string => (string) $row['role']->committee()->getFirstAttribute('description'))
            ->sortKeysUsing(fn (string $a, string $b): int => (int) $collator->compare($a, $b));

        foreach ($groups as $committee => $rows) {
            $this->drawTable((string) $committee, $rows->values()->all(), $columns);
            $this->cursorY += 3 * self::SPACE;
        }
    }

    /**
     * @param  list<array{role: mixed, from: mixed, until: mixed}>  $rows
     * @param  list<array{x: float, width: float, label: string}>  $columns
     */
    private function drawTable(string $committee, array $rows, array $columns): void
    {
        $this->font('B', self::GROUP_HEADING_SIZE);
        $headingHeight = $this->measure($committee, $this->contentWidth) + self::SPACE;

        // the heading never stays behind alone without its head and first row
        if (! $this->hasSpace($headingHeight + $this->tableHeadHeight() + $this->rowHeight($this->cells($rows[0]), $columns))) {
            $this->newPage();
        }

        $this->drawGroupHeading($committee, true);
        $this->beginTag('Table');

        // The head waits for the first row of each page, so it never ends up
        // alone at the foot of a page.
        $headPending = true;
        $headTagged = false;

        foreach ($rows as $row) {
            $cells = $this->cells($row);
            $rowHeight = $this->rowHeight($cells, $columns);

            if (! $this->hasSpace(($headPending ? $this->tableHeadHeight() : 0.0) + $rowHeight)) {
                $this->newPage();
                $this->drawGroupHeading($committee, false);
                $headPending = true;
            }

            if ($headPending) {
                $this->drawTableHead($columns, ! $headTagged);
                $headPending = false;
                $headTagged = true;
            }

            $top = $this->cursorY;
            $this->beginTag('TR');
            foreach ($columns as $i => $column) {
                $this->cellFont($i);
                $this->beginTag('TD', [], true);
                $this->text(
                    $cells[$i],
                    $column['x'] + $this->inset($i),
                    $top + self::CELL_PADDING,
                    $column['width'] - $this->inset($i) - self::CELL_PADDING,
                );
                $this->endTag();
            }
            $this->endTag();

            $this->cursorY = $top + $rowHeight;
            $this->rule($this->cursorY, 0.1);
            $this->columnRules($columns, $top, $this->cursorY);
        }

        $this->endTag();
    }

    /** Repeated on a continuation page as an artifact, like the table head. */
    private function drawGroupHeading(string $committee, bool $tagged): void
    {
        $this->font('B', self::GROUP_HEADING_SIZE);

        if ($tagged) {
            $this->beginTag('H2');
            $this->text($committee, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
            $this->endTag();
        } else {
            $this->artifact($this->pdf->getTextCell($committee, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth, linespace: self::LINE_SPACING, halign: 'L', drawcell: false));
        }

        $this->cursorY += $this->lastHeight() + self::SPACE;
    }

    /**
     * @param  array{role: mixed, from: mixed, until: mixed}  $row
     * @return list<string>
     */
    private function cells(array $row): array
    {
        return [
            (string) $row['role']->getFirstAttribute('description'),
            $this->dateFormat->format($row['from']),
            $row['until'] != '' ? $this->dateFormat->format($row['until']) : __('profile.today'),
        ];
    }

    /**
     * @param  list<string>  $cells
     * @param  list<array{x: float, width: float, label: string}>  $columns
     */
    private function rowHeight(array $cells, array $columns): float
    {
        $height = 0.0;
        foreach ($columns as $i => $column) {
            $this->cellFont($i);
            $height = max($height, $this->measure($cells[$i], $column['width'] - $this->inset($i) - self::CELL_PADDING));
        }

        return $height + 2 * self::CELL_PADDING;
    }

    /**
     * Both date columns are as wide as the widest date plus padding; the role
     * takes the rest.
     *
     * @return list<array{x: float, width: float, label: string}>
     */
    private function columnLayout(): array
    {
        $this->font('', self::TABLE_FONT_SIZE, self::FONT_TABULAR);
        $widest = max(array_map(
            fn (int $month): float => $this->measureWidth($this->dateFormat->format(Date::create(2000, $month, 28))),
            range(1, 12),
        ));
        $dateWidth = ceil($widest) + 2 * self::CELL_PADDING + 1.0;
        $labels = [
            [__('profile.role'), $this->contentWidth - 2 * $dateWidth],
            [__('profile.from'), $dateWidth],
            [__('profile.until'), $dateWidth],
        ];

        $columns = [];
        $x = self::MARGIN_LEFT;
        foreach ($labels as [$label, $width]) {
            $columns[] = ['x' => $x, 'width' => $width, 'label' => $label];
            $x += $width;
        }

        return $columns;
    }

    private function tableHeadHeight(): float
    {
        $this->font('B', self::TABLE_FONT_SIZE);

        return $this->measure('Xg', $this->contentWidth) + self::HEAD_PADDING_BOTTOM;
    }

    /**
     * Repeats on later pages are artifacts, so screen readers announce the
     * column labels once instead of reading them as content on every page.
     *
     * @param  list<array{x: float, width: float, label: string}>  $columns
     */
    private function drawTableHead(array $columns, bool $tagged): void
    {
        $top = $this->cursorY;
        $height = $this->tableHeadHeight();

        if ($tagged) {
            $this->beginTag('TR');
        }

        foreach ($columns as $i => $column) {
            $x = $column['x'] + $this->inset($i);
            $width = $column['width'] - $this->inset($i);

            if (! $tagged) {
                $this->pushStyle(['fillColor' => self::COLOR_ZINC_600]);
                $this->artifact($this->pdf->getTextCell($column['label'], $x, $top, $width, linespace: self::LINE_SPACING, halign: 'L', drawcell: false));
                $this->popStyle();

                continue;
            }

            // Scope is a Table attribute, so its owner has to be declared too -
            // without /O veraPDF cannot determine the column headers.
            $this->beginTag('TH', ['O' => 'Table', 'Scope' => 'Column'], true);
            $this->text($column['label'], $x, $top, $width, self::COLOR_ZINC_600);
            $this->endTag();
        }

        if ($tagged) {
            $this->endTag();
        }

        $this->cursorY = $top + $height;
        $this->rule($this->cursorY, 0.18);
        $this->columnRules($columns, $top, $this->cursorY);
    }

    /**
     * Separators between columns; the outer edges stay open.
     *
     * @param  list<array{x: float, width: float, label: string}>  $columns
     */
    private function columnRules(array $columns, float $top, float $bottom): void
    {
        foreach (array_slice($columns, 1) as $column) {
            $this->artifact($this->pdf->graph->getLine(
                $column['x'],
                $top,
                $column['x'],
                $bottom,
                ['lineWidth' => 0.1, 'lineColor' => self::RULE_COLOR],
            ));
        }
    }

    /** The first column lines up with the committee heading above. */
    private function inset(int $column): float
    {
        return $column === 0 ? 0.0 : self::CELL_PADDING;
    }

    private function rule(float $y, float $width): void
    {
        $this->artifact($this->pdf->graph->getLine(
            self::MARGIN_LEFT,
            $y,
            $this->pageWidth - self::MARGIN_RIGHT,
            $y,
            ['lineWidth' => $width, 'lineColor' => self::RULE_COLOR],
        ));
    }

    private function drawPageNumbers(): void
    {
        $pages = $this->pdf->page->getPages();
        $total = count($pages);

        $this->font('', self::PAGE_NUMBER_FONT_SIZE, self::FONT_TABULAR);
        $height = $this->measure('X', $this->contentWidth);

        foreach (array_values($pages) as $index => $page) {
            $pid = (int) $page['pid'];
            $this->pdf->setCurrentPage($pid);

            $this->pushStyle(['fillColor' => self::COLOR_ZINC_600]);
            $content = $this->pdf->getTextCell(
                ($index + 1).' / '.$total,
                self::MARGIN_LEFT,
                $index < $this->letterPages ? (float) $this->letterPageNumberTop : $this->pageHeight - self::FOOTER_EDGE_GAP - $height,
                $this->contentWidth,
                halign: 'R',
                drawcell: false,
            );
            $this->pdf->addArtifactContent($content, $pid, 'Pagination', 'Footer');
            $this->popStyle();
        }
    }

    /**
     * Patterns for the current locale, or none. Parsing takes a few
     * milliseconds and never changes, so it is cached per file version.
     *
     * @return array<string, int|string>
     */
    private function hyphenationPatterns(): array
    {
        $locale = strtolower(explode('_', str_replace('-', '_', app()->getLocale()))[0]);
        $file = self::HYPHENATION_PATTERNS[$locale] ?? null;
        $path = $file !== null ? resource_path('hyphenation/'.$file) : null;

        if ($path === null || ! is_file($path)) {
            return [];
        }

        return Cache::rememberForever(
            'pdf.hyphenation.'.$locale.'.'.filemtime($path),
            fn (): array => $this->pdf->loadTexHyphenPatterns($path),
        );
    }

    private function hyphenate(bool $enabled): void
    {
        $this->pdf->setTexHyphenPatterns($enabled ? $this->hyphenPatterns : []);
    }

    private function hasSpace(float $need): bool
    {
        $bottom = $this->footerTop !== null ? $this->letterPageNumberTop - self::DIN_LINE : $this->pageHeight - self::MARGIN_BOTTOM;

        return $this->cursorY + $need <= $bottom;
    }

    private function newPage(): void
    {
        $this->pdf->addPage(['format' => 'A4']);
        $this->cursorY = self::MARGIN_TOP;
    }

    private function font(string $style, float $size, string $family = self::FONT): void
    {
        $this->pdf->font->insert($this->pdf->pon, $family, $style, $size);
    }

    /** Tabular figures for phone numbers and IBAN; there is no bold tabular cut. */
    private function footerFont(string $style): void
    {
        $this->font($style, self::FOOTER_FONT_SIZE, $style === '' ? self::FONT_TABULAR : self::FONT);
    }

    private function cellFont(int $column): void
    {
        $this->font('', self::TABLE_FONT_SIZE, in_array($column, self::DATE_COLUMNS, true) ? self::FONT_TABULAR : self::FONT);
    }

    private function text(string $txt, float $x, float $y, float $width, string $color = self::COLOR_BLACK, string $halign = 'L', float $linespace = self::LINE_SPACING): void
    {
        if ($txt === '') {
            return;
        }

        // addTextCellXY takes the glyph colour from the graphics style stack
        $this->pushStyle(['fillColor' => $color]);
        $this->pdf->addTextCellXY(
            $txt,
            posx: $x,
            posy: $y,
            width: $width,
            linespace: $linespace,
            halign: $halign,
            jlast: false,
            drawcell: false,
        );
        $this->popStyle();
    }

    /**
     * @param  array<string, string>  $attr
     */
    private function beginTag(string $role, array $attr = [], bool $required = false): void
    {
        $this->pdf->beginStructElem($role, $this->pdf->page->getPageId(), null, $attr, $required);
    }

    private function endTag(): void
    {
        $this->pdf->endStructElem();
    }

    /** Purely visual content, which assistive technology has to skip. */
    private function artifact(string $content): void
    {
        $this->pdf->addArtifactContent($content, $this->pdf->page->getPageId());
    }

    /**
     * @param  array<string, string|float>  $style
     */
    private function pushStyle(array $style): void
    {
        $this->pdf->page->addContent($this->pdf->graph->add($style));
    }

    private function popStyle(): void
    {
        $this->pdf->page->addContent($this->pdf->graph->pop());
    }

    /** Height the text would need in the given width, without drawing it. */
    private function measure(string $txt, float $width): float
    {
        if ($txt === '') {
            return 0.0;
        }

        $this->pdf->getTextCell($txt, 0, 0, $width, 0, linespace: self::LINE_SPACING, drawcell: false);

        return $this->pdf->getLastCellBBox()['h'];
    }

    private function measureWidth(string $txt): float
    {
        $this->pdf->getTextCell($txt, 0, 0, $this->contentWidth, 0, linespace: self::LINE_SPACING, drawcell: false);

        return $this->pdf->getLastTextBBox()['w'];
    }

    private function lastHeight(): float
    {
        return $this->pdf->getLastCellBBox()['h'];
    }
}
