<?php

namespace App\Support;

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

    private const float META_FONT_SIZE = 10.0;

    private const string FONT = 'adwaitasans';

    /** Tabular figures, so dates line up in their columns. */
    private const string FONT_TABULAR = 'adwaitasanstabular';

    /** Table columns holding dates. */
    private const array DATE_COLUMNS = [2, 3];

    private const float LETTER_FONT_SIZE = 11.0;

    /** Line height of the letter text and its closing, relative to the font size. */
    private const float LETTER_LINE_HEIGHT = 1.4;

    /** With the letter's leading five lines fit the address zone, as many as a recipient has fields. */
    private const float ADDRESS_FONT_SIZE = self::LETTER_FONT_SIZE;

    private const float INFO_FONT_SIZE = 9.5;

    private const float RETURN_FONT_SIZE = 8.0;

    private const float SENDER_FONT_SIZE = 14.0;

    /** DIN 5008 form B, in mm from the top left paper corner. */
    private const float LETTERHEAD_RULE_Y = 33.0;

    /** Name baseline and logo bottom both sit this far above the letterhead rule. */
    private const float LETTERHEAD_GAP = 3.0;

    /** Zusatz- und Vermerkzone of DIN 5008 form B, 45 to 62.7mm. */
    private const float NOTES_ZONE_Y = 45.0;

    private const float RECIPIENT_Y = 62.7;

    private const float ADDRESS_WIDTH = 80.0;

    private const float INFO_X = 125.0;

    private const float INFO_Y = 50.0;

    /** Two blank lines (4.23mm each) below the 90mm address field. */
    private const float SUBJECT_Y = 98.46;

    /** One line in DIN 5008 terms. */
    private const float DIN_LINE = 4.23;

    private const float LOGO_HEIGHT = 14.0;

    private const float LOGO_MAX_WIDTH = 60.0;

    private const int LOGO_DPI = 600;

    private const float ICON_SIZE = 3.2;

    /** Room for a handwritten signature, three blank lines in DIN 5008. */
    private const float SIGNATURE_SPACE = 15.0;

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

    /**
     * @param  array<int, array{role: mixed, from: mixed, until: mixed}>  $memberships
     */
    public function render(string $title, string $fullName, ?string $community, array $memberships, ?CoverLetter $letter = null): string
    {
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
            $this->drawLetter($letter);
            $this->newPage();
        }

        // the cover letter carries the date already
        $this->drawHeader($title, $fullName, $community, withDate: $letter === null);
        $this->drawTable($memberships);
        $this->drawPageNumbers();

        return $this->pdf->getOutPDFString();
    }

    private function drawLetter(CoverLetter $letter): void
    {
        $right = $this->pageWidth - self::MARGIN_RIGHT;
        $contact = $letter->contact;

        $this->drawFoldMarks();

        $logoWidth = $this->drawLogo($letter->logoPath, $right);
        $this->font('B', self::SENDER_FONT_SIZE);
        $this->beginTag('P');
        $metrics = $this->pdf->font->getCurrentFont();
        $ascent = $metrics['ascent'] * $metrics['usize'] / $metrics['size'];
        $this->text($letter->senderName, self::MARGIN_LEFT, self::LETTERHEAD_RULE_Y - self::LETTERHEAD_GAP - $ascent, $this->contentWidth - $logoWidth - 5.0);
        $this->endTag();
        $this->artifact($this->pdf->graph->getLine(
            self::MARGIN_LEFT,
            self::LETTERHEAD_RULE_Y,
            $right,
            self::LETTERHEAD_RULE_Y,
            ['lineWidth' => 0.2, 'lineColor' => self::COLOR_BLACK],
        ));

        // a hyphen inserted into an address or a contact detail reads as part of it
        $this->hyphenate(false);
        $this->font('', self::RETURN_FONT_SIZE);
        $returnAddress = $this->returnAddress(array_filter([$letter->senderName, $contact?->streetLine(), $contact?->cityLine()]));
        $this->beginTag('P');
        // top line of the notes zone; notes like "Einschreiben" would go below it, above the address
        $this->text($returnAddress, self::MARGIN_LEFT, self::NOTES_ZONE_Y, self::ADDRESS_WIDTH);
        $this->endTag();

        $this->font('', self::ADDRESS_FONT_SIZE);
        $this->beginTag('P');
        $linespace = $this->letterLineHeight() - $this->measure('X', self::ADDRESS_WIDTH);
        $this->text(trim($letter->recipient), self::MARGIN_LEFT, self::RECIPIENT_Y, self::ADDRESS_WIDTH, linespace: $linespace);
        $this->endTag();

        $infoBottom = $this->drawInfoBlock($letter, $right);
        $this->hyphenate(true);

        $date = Date::now()->format(app()->getLocale() === 'de' ? 'd.m.Y' : 'Y-m-d');
        // right-aligned on the line above the subject, unless the info block reaches further down
        $this->font('', self::LETTER_FONT_SIZE);
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
        $this->font('', self::LETTER_FONT_SIZE);
        $html = '<style>p, li { text-align: justify; line-height: '.self::LETTER_LINE_HEIGHT.'; }</style>'.CoverLetter::sanitizeBody($letter->body);
        $this->pdf->addHTMLCell($html, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
        $end = $this->pdf->getLastHTMLCellEnd();
        $this->cursorY = $end['y'];

        $this->drawSignature($letter);
    }

    /** Closing, room to sign, and the signer below it - kept together on one page. */
    private function drawSignature(CoverLetter $letter): void
    {
        $lines = array_filter([$letter->signerName, $letter->signerFunction]);
        $this->font('', self::LETTER_FONT_SIZE);
        $lineHeight = $this->letterLineHeight();

        // the HTML block already ends with its paragraph spacing
        if (! $this->hasSpace((1 + count($lines)) * $lineHeight + self::SIGNATURE_SPACE)) {
            $this->newPage();
        }

        $this->beginTag('P');
        $this->text(__('profile.cover_letter_closing'), self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
        $this->endTag();
        $this->cursorY += $lineHeight + self::SIGNATURE_SPACE;

        foreach ($lines as $line) {
            $this->beginTag('P');
            $this->text($line, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
            $this->endTag();
            $this->cursorY += $lineHeight;
        }
    }

    /**
     * Sender details in the right column of the letterhead, each with an icon
     * behind a vertical rule. Returns where the block ends.
     */
    private function drawInfoBlock(CoverLetter $letter, float $right): float
    {
        $contact = $letter->contact;
        $items = array_filter([
            'network' => array_filter([$letter->senderUnit]),
            'house' => array_filter([$contact?->streetLine(), $contact?->cityLine()]),
            'phone' => array_filter([$letter->phone]),
            'mail' => array_filter([$letter->email]),
            'globe' => array_filter([$contact?->websiteLabel()]),
        ]);

        if ($items === []) {
            return self::INFO_Y;
        }

        $links = [
            'phone' => 'tel:'.preg_replace('/[^\d+]/', '', (string) $letter->phone),
            'mail' => 'mailto:'.$letter->email,
            'globe' => (string) $contact?->website,
        ];

        $ruleX = $right - self::ICON_SIZE - 2.0;
        $textRight = $ruleX - 2.0;
        $y = self::INFO_Y;

        foreach ($items as $icon => $lines) {
            $this->font($icon === 'network' ? 'B' : '', self::INFO_FONT_SIZE);
            $lineHeight = $this->measure('X', $this->contentWidth);

            $link = $links[$icon] ?? null;
            $this->beginTag('P');
            if ($link !== null) {
                $this->beginTag('Link');
            }

            $this->text(implode("\n", $lines), self::INFO_X, $y, $textRight - self::INFO_X, self::COLOR_BLACK, 'R');
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
            ['lineWidth' => 0.2, 'lineColor' => self::COLOR_BLACK],
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
                // the library's own SVG parser paints nothing for many real-world logos
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

            $width = min(self::LOGO_MAX_WIDTH, self::LOGO_HEIGHT * $size[0] / $size[1]);
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

    private function rasterizeSvg(string $raw): string
    {
        $image = new \Imagick;
        $image->setBackgroundColor(new \ImagickPixel('transparent'));
        $image->readImageBlob($raw);
        $image->setImageFormat('png');
        $image->resizeImage(0, (int) round(self::LOGO_HEIGHT / 25.4 * self::LOGO_DPI), \Imagick::FILTER_LANCZOS, 1);

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
            $body .= '<'.$element[1].$element[2].' fill="none" stroke="'.self::COLOR_BLACK
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

        $meta = implode(' | ', array_filter([$fullName, $community, $withDate ? date('Y-m-d') : null]));
        $this->font('', self::META_FONT_SIZE);
        $this->beginTag('P');
        $this->text($meta, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth, self::COLOR_ZINC_600);
        $this->endTag();
        $this->cursorY += $this->lastHeight() + 4 * self::SPACE;
    }

    /**
     * @param  array<int, array{role: mixed, from: mixed, until: mixed}>  $memberships
     */
    private function drawTable(array $memberships): void
    {
        $columns = $this->columnLayout();

        $this->beginTag('Table');

        // The head waits for the first row of each page, so it never ends up
        // alone at the foot of a page.
        $headPending = true;
        $headTagged = false;

        foreach ($memberships as $row) {
            $cells = [
                (string) $row['role']->getFirstAttribute('description'),
                (string) $row['role']->committee()->getFirstAttribute('description'),
                Date::parse($row['from'])->format('Y-m-d'),
                $row['until'] != '' ? Date::parse($row['until'])->format('Y-m-d') : __('profile.today'),
            ];

            $rowHeight = 0.0;
            foreach ($columns as $i => $column) {
                $this->cellFont($i);
                $rowHeight = max($rowHeight, $this->measure($cells[$i], $column['width'] - 2 * self::CELL_PADDING));
            }
            $rowHeight += 2 * self::CELL_PADDING;

            if (! $this->hasSpace(($headPending ? $this->tableHeadHeight() : 0.0) + $rowHeight)) {
                $this->newPage();
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
                    $column['x'] + self::CELL_PADDING,
                    $top + self::CELL_PADDING,
                    $column['width'] - 2 * self::CELL_PADDING,
                );
                $this->endTag();
            }
            $this->endTag();

            $this->cursorY = $top + $rowHeight;
            $this->rule($this->cursorY, 0.1);
            $this->columnRules($columns, $top, $this->cursorY);
        }

        // Without rows the table still gets its head, so it is not an empty Table.
        if ($headPending) {
            $this->drawTableHead($columns, ! $headTagged);
        }

        $this->endTag();
    }

    /**
     * Both date columns are as wide as a date plus padding; role and committee
     * share the rest.
     *
     * @return list<array{x: float, width: float, label: string}>
     */
    private function columnLayout(): array
    {
        $this->font('', self::TABLE_FONT_SIZE, self::FONT_TABULAR);
        $dateWidth = ceil($this->measureWidth('0000-00-00')) + 2 * self::CELL_PADDING + 1.0;
        $textWidth = ($this->contentWidth - 2 * $dateWidth) / 2;

        $labels = [
            [__('profile.role'), $textWidth],
            [__('profile.committee'), $textWidth],
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

        foreach ($columns as $column) {
            $x = $column['x'] + self::CELL_PADDING;
            $width = $column['width'] - self::CELL_PADDING;

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
        $this->font('', 10);

        foreach (array_values($pages) as $index => $page) {
            $pid = (int) $page['pid'];
            $this->pdf->setCurrentPage($pid);

            $this->pushStyle(['fillColor' => self::COLOR_ZINC_600]);
            $content = $this->pdf->getTextCell(
                ($index + 1).' / '.$total,
                self::MARGIN_LEFT,
                $this->pageHeight - self::FOOTER_EDGE_GAP - $this->measure('X', $this->contentWidth),
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
        return $this->cursorY + $need <= $this->pageHeight - self::MARGIN_BOTTOM;
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
    /** Line pitch of the letter text in mm. */
    private function letterLineHeight(): float
    {
        return self::LETTER_FONT_SIZE * self::LETTER_LINE_HEIGHT * 25.4 / 72;
    }

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
