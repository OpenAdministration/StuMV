<?php

namespace App\Support;

use Com\Tecnick\Pdf\Tcpdf;

/** Tcpdf with link annotations hooked into the PDF/UA structure tree, as its HTML renderer does. */
class TaggedPdf extends Tcpdf
{
    /** Adds the link under the currently open structure element, which should be a Link. */
    public function addTaggedLink(float $posx, float $posy, float $width, float $height, string $url): void
    {
        $pid = $this->page->getPageID();
        $oid = $this->setLink($posx, $posy, $width, $height, $url);
        $this->page->addAnnotRef($oid, $pid);
        $this->registerPdfUaAnnotation($oid, $pid);
    }
}
