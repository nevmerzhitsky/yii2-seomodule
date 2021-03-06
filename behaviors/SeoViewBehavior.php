<?php
/**
 * @link Inspired by https://github.com/demisang/yii2-seo/
 */
namespace nevmerzhitsky\seomodule\behaviors;
use Yii;
use yii\base\Behavior;
use yii\web\View;
use yii\helpers\Html;
use nevmerzhitsky\seomodule\Meta;

/**
 * Add ability of view to setup page title and meta-tags for SEO.
 *
 * @package nevmerzhitsky\seomodule
 */
class SeoViewBehavior extends Behavior {

    /**
     *
     * @param string $str
     * @return string
     */
    static public function normalizeStr ($str) {
        // @TODO Move back to Model bahavior.
        $str = strip_tags($str);
        // Replace many various sequential space chars to one.
        $str = trim(preg_replace('/[\s]+/isu', ' ', $str));

        return $str;
    }

    private $_pageTitle = '';

    private $_metaDescription = '';

    private $_metaKeywords = '';

    private $_metaRobots = '';

    /**
     * Set meta-params for current page.
     *
     * @param array|SeoModelBehavior|string $title - array: ["title"=>"Page
     *        Title", "desc"=>"Page Descriptions", "keywords"=>"Page, Keywords"]
     *        - string: used as title of page
     * @param string $desc Meta description
     * @param string|string[] $keywords Meta keywords string or array of
     *        keywords.
     * @return static
     */
    private function setSeoData ($title, $desc = '', $keywords = '') {
        // @TODO DELETE -- REPLACED BY Meta class.
        // @TODO Get metaRobots from model also.
        if ($title instanceof SeoModelBehavior) {
            $meta = $title->getSeoData();
            $data = [
                'title' => $meta[Meta::KEY_TITLE],
                'desc' => $meta[Meta::KEY_DESCRIPTION],
                'keywords' => $meta[Meta::KEY_KEYWORDS]
            ];
        } elseif (is_string($title)) {
            $data = [
                'title' => $title,
                'desc' => $desc,
                'keywords' => !is_array($keywords) ? $keywords : implode(', ', $keywords)
            ];
        }

        if (isset($data['title'])) {
            $this->_pageTitle = static::normalizeStr($data['title']);
        }
        if (isset($data['desc'])) {
            $this->_metaDescription = static::normalizeStr($data['desc']);
        }
        if (isset($data['keywords'])) {
            $this->_metaKeywords = static::normalizeStr($data['keywords']);
        }

        return $this;
    }

    /**
     * Set robots meta-tag.
     *
     * @param string $content Content of the meta-tag.
     */
    private function setMetaRobots ($content = 'noindex, follow') {
        // @TODO Move to Meta class.
        $this->_metaRobots = trim($content);
    }

    /**
     * Render HTML with configured title and meta tags.
     *
     * Meta tags rendered by calling to standard $view->registerMetaTag().
     * And key of these tags is seo-description, seo-keywords and seo-robots.
     * You can manually override their values after calling this method.
     *
     * @return string
     */
    private function renderMetaTags () {
        // @TODO DELETE -- REPLACED BY Meta class.
        /* @var $view View */
        $view = $this->owner;

        $map = [
            'description' => $this->_metaDescription,
            'keywords' => $this->_metaKeywords,
            'robots' => $this->_metaRobots
        ];

        foreach ($map as $name => $value) {
            if (empty($value)) {
                continue;
            }

            $view->registerMetaTag(
                [
                    'name' => $name,
                    'content' => static::normalizeStr($value)
                ], "seo-{$name}");
        }

        // @TODO Add title template to params of the behavior.
        if (!empty($this->_pageTitle)) {
            $title = $this->_pageTitle . ' - ' . Yii::$app->name;
        } else {
            $title = Yii::$app->name;
        }

        $title = Html::encode(static::normalizeStr($title));

        return "<title>{$title}</title>" . PHP_EOL;
    }
}
