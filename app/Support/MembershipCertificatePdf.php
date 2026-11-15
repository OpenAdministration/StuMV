<?php

namespace App\Support;

use Illuminate\Support\Facades\Date;
use Com\Tecnick\Pdf\Tcpdf;

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

    private const string COLOR_BLACK = '#000000';

    private const string COLOR_ZINC_500 = '#71717b';

    private const string COLOR_ZINC_600 = '#52525c';

    private const string RULE_COLOR = self::COLOR_ZINC_500;

    private Tcpdf $pdf;

    private float $pageWidth;

    private float $pageHeight;

    private float $contentWidth;

    private float $cursorY;

    /**
     * @param  array<int, array{role: mixed, from: mixed, until: mixed}>  $memberships
     */
    public function render(string $title, string $fullName, ?string $community, array $memberships): string
    {
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', resource_path('fonts/pdf'));
        }

        $this->pdf = new Tcpdf('mm', true, true, true, 'pdfua2');
        $this->pdf->setCreator((string) config('app.name'));
        $this->pdf->setTitle($title.' – '.$fullName);
        $this->pdf->setLanguage(str_replace('_', '-', app()->getLocale()));

        $page = $this->pdf->addPage(['format' => 'A4']);
        $this->pageWidth = $page['width'];
        $this->pageHeight = $page['height'];
        $this->contentWidth = $this->pageWidth - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $this->cursorY = self::MARGIN_TOP;

        $this->drawHeader($title, $fullName, $community);
        $this->drawTable($memberships);
        $this->drawPageNumbers();

        return $this->pdf->getOutPDFString();
    }

    private function drawHeader(string $title, string $fullName, ?string $community): void
    {
        $this->font('B', self::HEADING_SIZE);
        $this->beginTag('H1');
        $this->text($title, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
        $this->endTag();
        $this->cursorY += $this->lastHeight() + self::SPACE;

        $meta = implode(' | ', array_filter([$fullName, $community, date('Y-m-d')]));
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

            $this->font('', self::TABLE_FONT_SIZE);
            $rowHeight = 0.0;
            foreach ($columns as $i => $column) {
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
            $this->font('', self::TABLE_FONT_SIZE);
            $this->beginTag('TR');
            foreach ($columns as $i => $column) {
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
        $this->font('', self::TABLE_FONT_SIZE);
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

    private function hasSpace(float $need): bool
    {
        return $this->cursorY + $need <= $this->pageHeight - self::MARGIN_BOTTOM;
    }

    private function newPage(): void
    {
        $this->pdf->addPage(['format' => 'A4']);
        $this->cursorY = self::MARGIN_TOP;
    }

    private function font(string $style, float $size): void
    {
        $this->pdf->font->insert($this->pdf->pon, self::FONT, $style, $size);
    }

    private function text(string $txt, float $x, float $y, float $width, string $color = self::COLOR_BLACK): void
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
            linespace: self::LINE_SPACING,
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
