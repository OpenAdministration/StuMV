<?php

namespace app\widgets;

use yii\bootstrap4\Html;

class Card extends \yii\bootstrap4\Widget
{
    public array $containerOptions = [];

    /** @see https://getbootstrap.com/docs/4.0/utilities/colors/ for coloring options of the whole card and its text */
    public array $cardOptions = [];

    public array $headerOptions = [];
    public string $header = '';

    //TODO: add images

    public array $titleOptions = [];
    public string $title = '';

    public array $subTitleOptions = [];
    public string $subTitle = '';

    public array $bodyOptions = [];
    public string $body = '';

    // TODO: add call to action buttons and links
    // public array $links = [];

    public array $footerOptions = [];
    public string $footer = '';

    public function init() : void {

        parent::init();

        if (empty($this->containerOptions)) {
            Html::addCssClass($this->cardOptions, 'card-default');
        }
        Html::addCssClass($this->cardOptions, 'card');
        Html::addCssClass($this->headerOptions, 'card-header');
        Html::addCssClass($this->titleOptions, 'card-title');
        Html::addCssClass($this->subTitleOptions, ['card-subtitle', 'text-muted', 'mb-2']);
        Html::addCssClass($this->bodyOptions, 'card-body');
        Html::addCssClass($this->footerOptions, 'card-footer');

        ob_start();
    }

    public function run() : string
    {
        //wrap card in div 'container'
        $out = Html::beginTag('div', $this->containerOptions);

        // start card
        $out .= Html::beginTag('div', $this->cardOptions);

        //seperated header
        if (!empty($this->header)) {
            $out .= Html::tag('div', $this->header, $this->headerOptions);
        }

        //start body
        $out .= Html::beginTag('div', $this->bodyOptions);
        // card title in body
        if (!empty($this->title)) {
            $out .= Html::tag('h5', $this->title, $this->titleOptions);
        }

        // card subtitle in body
        if (!empty($this->subTitle)) {
            $out .= Html::tag('h6', $this->subTitle, $this->subTitleOptions);
        }


        $this->body = ob_get_clean() . $this->body;

        $out .= Html::tag('p', $this->body);

        // end body
        $out .= Html::endTag('div');

        if (!empty($this->footer)) {
            $out .= Html::tag('div', $this->footer, $this->footerOptions);
        }

        // end card
        $out .= Html::endTag('div');

        // end container
        $out .= Html::endTag('div');
        return $out;
    }
}
